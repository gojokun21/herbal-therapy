# -*- coding: utf-8 -*-
"""Pasul 2 - genereaza fundalul fiecarei imagini cu OpenAI (gpt-image-2).

Modelul primeste doua imagini de referinta:
  1. poza de produs (pack-shot-ul pe alb) - ambalajul trebuie reprodus intocmai;
  2. un fundal existent din Figma (reference/style-ref.png) - stilul foto,
     lumina si compozitia trebuie sa semene.
si scena scrisa la pasul 1. Textul NU se genereaza aici: il pune pluginul
Figma, ca sa fie identic cu cadrele existente si usor de corectat.

Rezultatul: data/backgrounds/<slug>.png, 1024x1024. Scriptul e reluabil;
un fundal existent nu se regenereaza fara --force.

    python generate.py                     # tot ce are text si nu are fundal
    python generate.py --limit 3           # o proba
    python generate.py --only slug-1       # un singur produs (si cu --force, ca sa-l refaci)
    python generate.py --quality high      # low | medium | high (implicit: HT_IMAGE_QUALITY sau medium)

Cost orientativ (1024x1024): low ~ $0.01, medium ~ $0.04, high ~ $0.17 pe imagine.
"""
import argparse
import base64
import json
import sys
import time

from openai import OpenAI

import config

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")  # consola Windows e cp1250

PROMPT = """Advertising product photo for a natural pharmacy brand, square format.
Recreate the photographic style, lighting and composition of the SECOND reference image: bright studio,
soft diffused light, plain smooth {backdrop} seamless backdrop, glossy white tabletop in the foreground
with a soft reflection of the product.

Scene: {person} {scene} The person shows the problem naturally: {symptom}.
The person occupies the RIGHT half of the frame only. The LEFT 45% of the image is clean, empty backdrop
with nothing in it - it is reserved for text that will be added later.

The product from the FIRST reference image stands on the tabletop in the lower center-right area, in front of
the person, facing the camera, large and sharp - about 40% of the frame height, clearly the hero of the shot. Reproduce its packaging exactly: same shape, label, colors and
label text as in the reference. Do not redesign, translate, rotate or alter the label.

Photorealistic, natural skin, anatomically correct hands, no added text, no extra logos, no watermark."""


def build_prompt(c):
    return PROMPT.format(
        backdrop=c.get("backdrop") or "warm beige",
        person=c["person"].rstrip("."),
        scene=c["scene"].strip(),
        symptom=c["symptom"].rstrip("."),
    )


def generate(client, product, copy, quality):
    with open(product["packshot"], "rb") as packshot, open(config.STYLE_REF, "rb") as style:
        kwargs = dict(
            model=config.IMAGE_MODEL,
            image=[packshot, style],
            prompt=build_prompt(copy),
            size=config.IMAGE_SIZE,
            quality=quality,
            n=1,
        )
        # gpt-image-1 are parametrul input_fidelity (pastreaza detaliile ambalajului);
        # gpt-image-2 il refuza, acolo fidelitatea e implicita
        if config.IMAGE_MODEL.startswith("gpt-image-1"):
            kwargs["input_fidelity"] = "high"
        r = client.images.edit(**kwargs)
    return base64.b64decode(r.data[0].b64_json)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--only", default="")
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--quality", default=config.IMAGE_QUALITY, choices=["low", "medium", "high"])
    ap.add_argument("--dry-run", action="store_true", help="arata promptul, nu genereaza")
    args = ap.parse_args()

    if not config.COPY_JSON.exists():
        raise SystemExit("Lipseste data/copy.json - ruleaza intai texts.py")
    if not config.STYLE_REF.exists():
        raise SystemExit(f"Lipseste referinta de stil {config.STYLE_REF}")

    products = {p["slug"]: p for p in json.loads(config.PRODUCTS_JSON.read_text(encoding="utf-8"))}
    copies = json.loads(config.COPY_JSON.read_text(encoding="utf-8"))
    only = {s.strip() for s in args.only.split(",") if s.strip()}

    todo = []
    for slug, c in copies.items():
        if only and slug not in only:
            continue
        if slug not in products:
            continue
        out = config.BACKGROUNDS_DIR / f"{slug}.png"
        if out.exists() and not args.force:
            continue
        todo.append((slug, c, out))
    if args.limit:
        todo = todo[:args.limit]
    if not todo:
        print("nimic de facut")
        return

    if args.dry_run:
        for slug, c, _ in todo:
            print("==", slug)
            print(build_prompt(c))
            print()
        return

    config.need_openai()
    client = OpenAI(api_key=config.OPENAI_API_KEY)
    print(f"{len(todo)} fundaluri de generat, calitate {args.quality}")

    for i, (slug, c, out) in enumerate(todo, 1):
        print(f"[{i}/{len(todo)}] {c['title_ro']}", end=" ... ", flush=True)
        t0 = time.time()
        try:
            png = generate(client, products[slug], c, args.quality)
        except Exception as exc:  # noqa: BLE001
            print(f"esuat: {exc}")
            time.sleep(3)
            continue
        out.write_bytes(png)
        print(f"ok ({time.time() - t0:.0f}s) -> {out.name}")

    print("fundalurile sunt in", config.BACKGROUNDS_DIR)


if __name__ == "__main__":
    sys.exit(main())
