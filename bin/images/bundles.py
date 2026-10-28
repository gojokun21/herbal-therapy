# -*- coding: utf-8 -*-
"""Pachete: poza setului din packshot-urile produselor, cu OpenAI.

Citeste data/bundles/sources.json (scris de `bundles.php --export`): pentru
fiecare pachet, packshot-ul pe alb din galeria fiecarui produs. Toate pozele
pleaca la model intr-un singur images.edit, cu cererea de a le aseza impreuna,
ca set, pe fundal alb. Rezultatul se aduce la 1000x1000 (JPG, fundal alb) in
data/bundles/<slug>.jpg; PNG-ul brut ramane in data/bundles/raw/.

    python bundles.py                     # tot ce nu are inca poza
    python bundles.py --only slug-1,slug-2
    python bundles.py --only slug --force # refa unul care nu a iesit bine
    python bundles.py --quality medium    # low | medium | high (implicit: high)
    python bundles.py --dry-run           # arata promptul, nu costa nimic
    python bundles.py --sheet             # doar plansa de control data/bundles/sheet.png
    python bundles.py --workers 5         # cereri in paralel (o imagine dureaza ~2-3 minute)

Implicit e high: cu 4-6 produse in cadru etichetele sunt mici si la medium
ies mai des rescrise.
"""
import argparse
import base64
import json
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from contextlib import ExitStack

from PIL import Image, ImageDraw

import config
from packshots import finish

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")  # consola Windows e cp1250

OUT_DIR = config.DATA / "bundles"
RAW_DIR = OUT_DIR / "raw"

PROMPT = """Professional e-commerce photo of a product bundle made of exactly the {n} products shown in the {n} reference images, one of each and nothing else:
{items}
Arrange all {n} products together as one tidy group on a pure white seamless background (#FFFFFF): taller bottles at the back,
smaller jars and tubes in front, only slightly overlapping, every front label facing the camera and readable.
Keep realistic relative sizes (the volume is in each product name). The whole group is centered and fills about 85% of the frame width,
with a clear even margin on all sides: nothing touches or crosses the edges.
Soft diffused studio lighting, subtle soft shadows under the products, gentle reflection on the base only.
No props, no gift box, no ribbon, no added text, no price tag, no watermark.
Reproduce every packaging exactly as in its reference image: same shape, proportions, colors, cap, logo, label layout and label text.
Do not redesign, translate, merge, duplicate or omit any product. Sharp, high resolution, photorealistic, clean edges."""


def prompt_for(bundle):
    items = "\n".join(f"- image {i}: {it['title']}" for i, it in enumerate(bundle["items"], 1))
    return PROMPT.format(n=len(bundle["items"]), items=items)


def generate(client, bundle, quality):
    with ExitStack() as stack:
        images = [stack.enter_context(open(it["file"], "rb")) for it in bundle["items"]]
        r = client.images.edit(
            model=config.IMAGE_MODEL,
            image=images,
            prompt=prompt_for(bundle),
            size=config.IMAGE_SIZE,
            quality=quality,
            n=1,
        )
    return base64.b64decode(r.data[0].b64_json)


def sheet(bundles, path, cell=200):
    """Plansa de control: pe fiecare rand packshot-urile produselor si poza setului."""
    done = [b for b in bundles if (OUT_DIR / f"{b['slug']}.jpg").exists()]
    if not done:
        print("nu e nicio poza de set de aratat")
        return
    cols = max(len(b["items"]) for b in done) + 2
    im = Image.new("RGB", (cols * cell, len(done) * (cell * 2 + 24)), "white")
    dr = ImageDraw.Draw(im)
    for r, b in enumerate(done):
        y = r * (cell * 2 + 24)
        for i, it in enumerate(b["items"]):
            t = Image.open(it["file"]).convert("RGB")
            t.thumbnail((cell - 8, cell - 8))
            im.paste(t, (i * cell + 4, y + 4))
        t = Image.open(OUT_DIR / f"{b['slug']}.jpg").convert("RGB")
        t.thumbnail((cell * 2 - 8, cell * 2 - 8))
        im.paste(t, ((cols - 2) * cell + 4, y + 4))
        dr.text((4, y + cell * 2 + 4), b["title"], fill="black")
        dr.line((0, y + cell * 2 + 22, im.width, y + cell * 2 + 22), fill="#ddd")
    im.save(path)
    print(f"plansa de control: {path} ({len(done)} pachete)")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--only", default="")
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--quality", default="high", choices=["low", "medium", "high"])
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--sheet", action="store_true", help="doar plansa de control")
    ap.add_argument("--workers", type=int, default=1, help="cereri OpenAI in paralel")
    args = ap.parse_args()

    src_json = OUT_DIR / "sources.json"
    if not src_json.exists():
        raise SystemExit("Lipseste data/bundles/sources.json - ruleaza intai bundles.php --export")
    bundles = json.loads(src_json.read_text(encoding="utf-8"))
    only = {s.strip() for s in args.only.split(",") if s.strip()}
    RAW_DIR.mkdir(parents=True, exist_ok=True)

    if args.sheet:
        sheet([b for b in bundles if not only or b["slug"] in only], OUT_DIR / "sheet.png")
        return

    todo = []
    for b in bundles:
        if only and b["slug"] not in only:
            continue
        out = OUT_DIR / f"{b['slug']}.jpg"
        if out.exists() and not args.force:
            continue
        todo.append((b, out))
    if not todo:
        print("nimic de facut")
        return

    if args.dry_run:
        for b, out in todo:
            print(f"== {b['slug']} -> {out.name}\n{prompt_for(b)}\n")
        print(f"{len(todo)} poze de set, calitate {args.quality}")
        return

    from openai import OpenAI  # noqa: E402  (importat tarziu ca --dry-run sa mearga fara pachet)

    config.need_openai()
    client = OpenAI(api_key=config.OPENAI_API_KEY)
    print(f"{len(todo)} poze de set de generat, calitate {args.quality}")

    failed = []

    def one(b, out):
        t0 = time.time()
        png = generate(client, b, args.quality)
        raw = RAW_DIR / f"{b['slug']}.png"
        raw.write_bytes(png)
        finish(raw, out)
        return time.time() - t0

    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as pool:
        futures = {pool.submit(one, b, out): b for b, out in todo}
        for i, fut in enumerate(as_completed(futures), 1):
            b = futures[fut]
            try:
                dt = fut.result()
            except Exception as exc:  # noqa: BLE001
                print(f"[{i}/{len(todo)}] {b['title']} ... esuat: {exc}", flush=True)
                failed.append(b["slug"])
                continue
            print(f"[{i}/{len(todo)}] {b['title']} ... ok ({dt:.0f}s)", flush=True)

    if failed:
        print("esuate:", ", ".join(failed))
    print("pozele sunt in", OUT_DIR)


if __name__ == "__main__":
    sys.exit(main())
