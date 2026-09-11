# -*- coding: utf-8 -*-
"""Packshot-uri: pasul B - regenereaza poza de produs din galerie cu OpenAI.

Porneste de la imaginea curenta din galerie (data/gallery_products.json,
scris de export_gallery.php) si cere modelului acelasi produs, cu acelasi
ambalaj, ca packshot de studio pe alb: centrat, eticheta spre camera, umbra
moale. Rezultatul se aduce la 1000x1000 (JPG, fundal alb) in
data/packshots/<slug>.jpg; PNG-ul brut 1024x1024 ramane in data/packshots/raw/.

    python packshots.py                    # tot ce nu are inca packshot nou
    python packshots.py --limit 5          # o proba
    python packshots.py --only slug-1,slug-2
    python packshots.py --only slug --force  # refa unul care nu a iesit bine
    python packshots.py --quality high     # low | medium | high (implicit: medium)
    python packshots.py --dry-run          # arata ce ar face, nu costa nimic
    python packshots.py --sheet            # doar plansa de control data/packshots/sheet.png
    python packshots.py --workers 4        # cereri in paralel (o imagine dureaza ~2 minute)
    python packshots.py --variant open-jar --only slug --force
                                           # borcanase mici: borcanul deschis, capacul rezemat, ca in original

Cost real masurat pe 2026-09-04 (gpt-image-2, edit cu poza de referinta):
high ~ $0.22 pe imagine, medium ~ $0.06. Implicit e medium: textul de pe
eticheta iese lizibil si la medium, iar high costa de aproape 4 ori mai mult.
Setul din 2026-09-04 e mixt: 95 la high, restul la medium (clientul a oprit
high la 22 EUR cheltuiti).
"""
import argparse
import base64
import json
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed

from PIL import Image, ImageDraw, ImageOps

import config

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")  # consola Windows e cp1250

OUT_DIR = config.DATA / "packshots"
RAW_DIR = OUT_DIR / "raw"
OUT_SIZE = 1000
JPEG_QUALITY = 92

PROMPT = """Professional e-commerce packshot of exactly the product shown in the reference image.
Pure white seamless background (#FFFFFF), no props, no other objects, no added text, no watermark.
The product stands upright, centered, front label facing the camera, filling about 80% of the frame height,
with a clear even margin on all sides: the whole product is inside the frame, nothing touches or crosses the edges.
Soft diffused studio lighting, a subtle soft shadow under the product, gentle reflection on the base only.
Reproduce the packaging exactly: same shape, proportions, colors, logo, label layout and label text as in the
reference. Do not redesign, translate, rotate, add or remove anything from the label. Sharp, high resolution,
photorealistic, clean edges."""


PROMPT_OPEN_JAR = """Professional e-commerce packshot of exactly the product shown in the reference image: a small round cosmetic jar.
Show it exactly like the reference composition: the jar is OPEN, the lid is taken off and leans against the jar
(or stands beside it) so that the round printed label on the lid faces the camera and the cream inside the open
jar is visible. Three-quarter view from slightly above, both parts close together, centered, filling about 70% of
the frame width, with a clear even margin on all sides: nothing touches or crosses the edges.
Pure white seamless background (#FFFFFF), no props, no other objects, no added text, no watermark.
Soft diffused studio lighting, a subtle soft shadow under the jar and lid, gentle reflection on the base only.
Reproduce the packaging exactly: same shape, proportions, colors, logo, label layout and label text as in the
reference. Do not redesign, translate, add or remove anything from the label. Sharp, high resolution,
photorealistic, clean edges."""

PROMPTS = {"default": PROMPT, "open-jar": PROMPT_OPEN_JAR}


def generate(client, product, quality, variant="default"):
    with open(product["file"], "rb") as src:
        kwargs = dict(
            model=config.IMAGE_MODEL,
            image=src,
            prompt=PROMPTS[variant],
            size=config.IMAGE_SIZE,
            quality=quality,
            n=1,
        )
        if config.IMAGE_MODEL.startswith("gpt-image-1"):
            kwargs["input_fidelity"] = "high"
        r = client.images.edit(**kwargs)
    return base64.b64decode(r.data[0].b64_json)


def finish(raw_png, out_jpg):
    """PNG-ul modelului -> JPG 1000x1000, fundal alb, fara deformare."""
    im = Image.open(raw_png)
    if im.mode in ("RGBA", "LA", "P"):
        bg = Image.new("RGB", im.size, "white")
        bg.paste(im.convert("RGBA"), mask=im.convert("RGBA").split()[-1])
        im = bg
    else:
        im = im.convert("RGB")
    im = ImageOps.pad(im, (OUT_SIZE, OUT_SIZE), method=Image.LANCZOS, color="white")
    im.save(out_jpg, "JPEG", quality=JPEG_QUALITY, optimize=True, subsampling=0)


def sheet(products, path, cell=250, cols=8):
    """Plansa de control: vechi (stanga) / nou (dreapta) pentru fiecare produs."""
    done = [p for p in products if (OUT_DIR / f"{p['slug']}.jpg").exists()]
    if not done:
        print("nu e niciun packshot nou de aratat")
        return
    rows = (len(done) + cols - 1) // cols
    im = Image.new("RGB", (cols * cell * 2, rows * (cell + 24)), "white")
    dr = ImageDraw.Draw(im)
    for i, p in enumerate(done):
        x, y = (i % cols) * cell * 2, (i // cols) * (cell + 24)
        for j, src in enumerate((p["file"], OUT_DIR / f"{p['slug']}.jpg")):
            t = Image.open(src).convert("RGB")
            t.thumbnail((cell - 8, cell - 8))
            im.paste(t, (x + j * cell + 4, y + 4))
        dr.rectangle((x + cell, y, x + cell + 1, y + cell), fill="#ddd")
        dr.text((x + 4, y + cell + 4), f"#{p['id']} {p['width']}x{p['height']} -> 1000x1000  {p['title_ro'][:40]}", fill="black")
    im.save(path)
    print(f"plansa de control: {path} ({len(done)} produse)")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--only", default="")
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--quality", default="medium", choices=["low", "medium", "high"])
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--sheet", action="store_true", help="doar plansa de control")
    ap.add_argument("--workers", type=int, default=1, help="cereri OpenAI in paralel")
    ap.add_argument("--variant", default="default", choices=sorted(PROMPTS), help="promptul folosit")
    args = ap.parse_args()

    src_json = config.DATA / "gallery_products.json"
    if not src_json.exists():
        raise SystemExit("Lipseste data/gallery_products.json - ruleaza intai export_gallery.php")
    products = json.loads(src_json.read_text(encoding="utf-8"))
    only = {s.strip() for s in args.only.split(",") if s.strip()}
    OUT_DIR.mkdir(exist_ok=True)
    RAW_DIR.mkdir(exist_ok=True)

    if args.sheet:
        sheet([p for p in products if not only or p["slug"] in only], OUT_DIR / "sheet.png")
        return

    todo = []
    for p in products:
        if only and p["slug"] not in only:
            continue
        out = OUT_DIR / f"{p['slug']}.jpg"
        if out.exists() and not args.force:
            continue
        todo.append((p, out))
    if args.limit:
        todo = todo[:args.limit]
    if not todo:
        print("nimic de facut")
        return

    if args.dry_run:
        for p, out in todo:
            print(f"#{p['id']} {p['slug']}: {p['width']}x{p['height']} {p['file']} -> {out.name}")
        print(f"{len(todo)} packshot-uri, calitate {args.quality}")
        return

    from openai import OpenAI  # noqa: E402  (importat tarziu ca --dry-run sa mearga fara pachet)

    config.need_openai()
    client = OpenAI(api_key=config.OPENAI_API_KEY)
    print(f"{len(todo)} packshot-uri de generat, calitate {args.quality}")

    failed = []

    def one(p, out):
        t0 = time.time()
        png = generate(client, p, args.quality, args.variant)
        raw = RAW_DIR / f"{p['slug']}.png"
        raw.write_bytes(png)
        finish(raw, out)
        return time.time() - t0

    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as pool:
        futures = {pool.submit(one, p, out): p for p, out in todo}
        for i, fut in enumerate(as_completed(futures), 1):
            p = futures[fut]
            try:
                dt = fut.result()
            except Exception as exc:  # noqa: BLE001
                print(f"[{i}/{len(todo)}] {p['title_ro']} ... esuat: {exc}", flush=True)
                failed.append(p["slug"])
                continue
            print(f"[{i}/{len(todo)}] {p['title_ro']} ... ok ({dt:.0f}s)", flush=True)

    if failed:
        print("esuate:", ", ".join(failed))
    print("packshot-urile sunt in", OUT_DIR)


if __name__ == "__main__":
    sys.exit(main())
