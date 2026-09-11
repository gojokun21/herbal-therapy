# -*- coding: utf-8 -*-
"""Pasul 3 - impacheteaza textele si fundalurile intr-un manifest pentru
pluginul Figma (figma-plugin/), care construieste cadrele RO si RU.

Intra doar produsele care au si text (copy.json) si fundal (backgrounds/).

    python manifest.py                 # data/figma_manifest.json
Pozitia textului se masoara pe fundal (layout): intrebarea la nivelul fetei, in
stanga persoanei; verbul si substantivul sub barbie, la marimea originala.

In plugin se aleg fisierul de manifest si PNG-urile din data/backgrounds/.
"""
import argparse
import json
import sys

import config

try:
    import numpy as np
    from PIL import Image
except ImportError:  # fara numpy/PIL textul se limiteaza la 920px
    np = None

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")  # consola Windows e cp1250


def free_width(png, margin=30, text_x=41):
    """Cat loc liber e in stanga persoanei, in zona fetei (y 60-560), in px de cadru.

    Fundalul se ia pe fiecare rand din primii 40 px din stanga; prima coloana din
    care 12 coloane la rand sunt "ocupate" pe peste 12% din randuri e marginea
    persoanei. Textul se scaleaza in plugin ca sa nu treaca de ea.
    """
    if np is None:
        return 920
    im = np.asarray(Image.open(png).convert("RGB").resize((1000, 1000))).astype(int)
    band = im[60:560]
    bg = np.median(band[:, :40, :], axis=1, keepdims=True)
    occ = (np.abs(band - bg).sum(axis=2) > 75).mean(axis=0)
    hit = occ > 0.12
    first = 1000
    for x in range(0, 1000 - 12):
        if hit[x:x + 12].all():
            first = x
            break
    return max(360, min(920, first - margin - text_x))


def layout(png):
    """Unde incepe textul pe verticala: sub barbia persoanei si deasupra produsului.

    Intoarce (text_y, text_max_w). text_max_w limiteaza doar intrebarea (randul de
    sus, la nivelul fetei); verbul si substantivul stau sub barbie si pot trece
    peste umar sau piept, ca in cadrele originale, dar nu peste produs.
    """
    width = free_width(png)
    if np is None:
        return 140, width
    im = np.asarray(Image.open(png).convert("RGB").resize((1000, 1000))).astype(int)
    bg = np.median(im[:, :40, :], axis=1, keepdims=True)
    diff = np.abs(im - bg).sum(axis=2) > 75
    person_x = 1000 - width - 30 - 41
    rows = diff[:, max(person_x, 450):].mean(axis=1)
    head = next((y for y in range(0, 600) if rows[y:y + 8].min() > 0.04), 60)
    left = diff[300:1000, 300:max(person_x, 450)].mean(axis=1)
    product = next((300 + y for y in range(0, 700) if left[y:y + 8].min() > 0.08), 1000)
    verb_top = head + 310                       # ~ sub barbie
    if verb_top + 113 + 92 > product - 15:      # verb 113 px + substantiv 92 px
        verb_top = max(head + 250, product - 15 - 205)
    return int(verb_top - 52), width


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--text-y", type=int, default=140, help="(nefolosit; y-ul se masoara pe fundal, se poate forta cu text_y in copy.json)")
    ap.add_argument("--only", default="")
    args = ap.parse_args()

    copies = json.loads(config.COPY_JSON.read_text(encoding="utf-8"))
    only = {s.strip() for s in args.only.split(",") if s.strip()}

    items, missing = [], []
    for slug, c in copies.items():
        if only and slug not in only:
            continue
        png = config.BACKGROUNDS_DIR / f"{slug}.png"
        if not png.exists():
            missing.append(slug)
            continue
        text_y, text_max_w = layout(png)
        items.append({
            "slug": slug,
            "image": png.name,
            "name_ro": c["title_ro"] + " ro",
            "name_ru": c["title_ro"] + " ru",
            "ro": [c["question_ro"], c["verb_ro"], c["noun_ro"]],
            "ru": [c["question_ru"], c["verb_ru"], c["noun_ru"]],
            "gradient": [c["color_start"], c["color_end"]],
            "text_y": c.get("text_y", text_y),
            "text_max_w": c.get("text_max_w", text_max_w),
        })

    config.MANIFEST_JSON.write_text(json.dumps({"items": items}, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"{len(items)} produse in {config.MANIFEST_JSON.name}")
    if missing:
        print(f"{len(missing)} fara fundal inca (ruleaza generate.py): " + ", ".join(missing[:10])
              + (" ..." if len(missing) > 10 else ""))


if __name__ == "__main__":
    sys.exit(main())
