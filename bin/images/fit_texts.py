# -*- coding: utf-8 -*-
"""Pasul 2b - scurteaza textele care nu incap, la marimea originala, in zona
libera din stanga persoanei.

Intrebarea (43 px) sta la nivelul fetei si trebuie sa incapa in zona libera
masurata pe fundal (manifest.free_width); verbul (102 px) si substantivul
(83 px) stau sub barbie si au toata latimea cadrului. Din astea ies bugete de
caractere, cu o toleranta: pluginul mai poate micsora textul pana la 85% fara
sa se vada. Cuvintele lungi frecvente au inlocuiri fixe (SHORT_*), fara AI. Pentru produsele care depasesc bugetul, modelul propune
cuvinte mai scurte cu acelasi sens; restul raman neatinse.

    python fit_texts.py            # scurteaza ce nu incape
    python fit_texts.py --dry-run  # arata doar ce ar trebui scurtat
    python fit_texts.py --only slug-1,slug-2

Scrie in data/copy.json (textul vechi ramane in `*_long`, ca sa se poata reveni).
"""
import argparse
import json
import sys

from openai import OpenAI

import config
from manifest import free_width

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

# latimi medii pe caracter, masurate pe cadrele existente (majuscule, Figtree)
PX = {
    "verb_ro": 63, "noun_ro": 56, "question_ro": 27,
    "verb_ru": 73, "noun_ru": 64, "question_ru": 27,
}
TOLERANCE = 0.85   # pluginul poate micsora pana aici fara sa se observe

SCHEMA = {
    "name": "texte_scurte",
    "strict": True,
    "schema": {
        "type": "object",
        "additionalProperties": False,
        "required": ["question_ro", "verb_ro", "noun_ro", "question_ru", "verb_ru", "noun_ru"],
        "properties": {k: {"type": "string"} for k in
                       ["question_ro", "verb_ro", "noun_ro", "question_ru", "verb_ru", "noun_ru"]},
    },
}

SYSTEM = """Esti copywriter pentru Herbal Therapy (produse naturiste, Moldova). Primesti textul de pe o imagine
de produs - o intrebare, un verb la imperativ si un substantiv, in romana si in rusa - si limitele de
caractere pentru fiecare. Rescrie DOAR randurile care depasesc limita, cu cuvinte mai scurte si acelasi
sens; pe celelalte le intorci neschimbate. Verbul + substantivul, citite impreuna, trebuie sa ramana o
propozitie naturala ("Redă-i confortul", "Alină pielea", "Верните блеск"). Fara promisiuni medicale.
Romana cu diacritice corecte; rusa la forma de politete. Fara cratime de despartire, fara rand nou.
Numara caracterele cu atentie: limita include spatiile si cratimele."""


def budgets(limit):
    """Intrebarea trebuie sa incapa in zona libera de langa fata; verbul si
    substantivul stau sub barbie si au toata latimea cadrului (920 px)."""
    q = limit / TOLERANCE
    return {k: int((q if k.startswith("question") else 920 / TOLERANCE) // px) for k, px in PX.items()}


# inlocuiri sigure pentru cuvintele lungi frecvente; se aplica inainte de AI
SHORT_VERB_RO = {
    "Îngrijește-ți": "Îngrijește", "Protejează-ți": "Protejează", "Protejează-i": "Protejează",
    "Relaxează-ți": "Relaxează", "Întărește-ți": "Întărește", "Întărește-i": "Întărește",
    "Hidratează-ți": "Hidratează", "Calmează-ți": "Calmează", "Revitalizează": "Revigorează",
    "Îmbunătățește": "Ușurează", "Regăsește-ți": "Regăsește", "Catifelează": "Alină",
    "Susține-ți": "Susține", "Susține-i": "Susține", "Îndepărtează": "Curăță",
    "Ameliorează": "Alină", "Controlează": "Reduce", "Reglează": "Reduce",
}
SHORT_RU = {  # (verb, substantiv sau None) -> (verb, substantiv sau None)
    ("Ухаживайте", "за кожей"): ("Питайте", "кожу"), ("Позаботьтесь", "о коже"): ("Питайте", "кожу"),
    ("Позаботьтесь", "о губах"): ("Питайте", "губы"), ("Верните им", None): ("Верните", None),
    ("Верните себе", None): ("Верните", None), ("Поддержите", "её"): ("Помогите", "печени"),
    ("Регулируйте", "себум"): ("Снизьте", "жирность"), ("Контролируйте", None): ("Снизьте", None),
}


def apply_short(c):
    c["verb_ro"] = SHORT_VERB_RO.get(c["verb_ro"], c["verb_ro"])
    for (v, n), (v2, n2) in SHORT_RU.items():
        if c["verb_ru"] == v and (n is None or c["noun_ru"] == n):
            c["verb_ru"] = v2
            if n2:
                c["noun_ru"] = n2


def too_long(c, b):
    return [k for k in PX if len(c[k]) > b[k]]


def shorten(client, c, b, product_title):
    lines = [f"Produs: {product_title}"]
    for k in PX:
        lines.append(f"{k}: {c[k]!r} (max {b[k]} caractere, acum {len(c[k])})")
    r = client.chat.completions.create(
        model=config.COPY_MODEL, temperature=0.4,
        messages=[{"role": "system", "content": SYSTEM}, {"role": "user", "content": "\n".join(lines)}],
        response_format={"type": "json_schema", "json_schema": SCHEMA},
    )
    d = json.loads(r.choices[0].message.content)
    return {k: " ".join(v.replace("­", "").split()) for k, v in d.items()}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--only", default="")
    args = ap.parse_args()

    copies = json.loads(config.COPY_JSON.read_text(encoding="utf-8"))
    only = {s.strip() for s in args.only.split(",") if s.strip()}
    client = None
    changed = 0

    for slug, c in copies.items():
        if only and slug not in only:
            continue
        png = config.BACKGROUNDS_DIR / f"{slug}.png"
        if not png.exists():
            continue
        limit = c.get("text_max_w") or free_width(png)
        c["text_max_w"] = limit
        apply_short(c)
        b = budgets(limit)
        over = too_long(c, b)
        if not over:
            continue
        print(f"{slug[:48]:48} liber {limit}px  depasesc: " +
              ", ".join(f"{k}={c[k]!r}>{b[k]}" for k in over))
        if args.dry_run:
            continue
        if client is None:
            config.need_openai()
            client = OpenAI(api_key=config.OPENAI_API_KEY)
        new = None
        for _ in range(2):
            cand = shorten(client, c, b, c.get("title_ro", slug))
            if not too_long(cand, b):
                new = cand
                break
            new = cand   # pastram ultima incercare chiar daca mai depaseste putin
        for k in PX:
            if new[k] != c[k]:
                c.setdefault(k + "_long", c[k])
                c[k] = new[k]
        still = too_long(c, b)
        print("   -> " + " | ".join(f"{c[k]}" for k in ["question_ro", "verb_ro", "noun_ro"]) +
              "   //   " + " | ".join(f"{c[k]}" for k in ["question_ru", "verb_ru", "noun_ru"]) +
              (f"   (inca peste: {still})" if still else ""))
        changed += 1
        config.COPY_JSON.write_text(json.dumps(copies, ensure_ascii=False, indent=2), encoding="utf-8")

    config.COPY_JSON.write_text(json.dumps(copies, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"{changed} produse rescrise")


if __name__ == "__main__":
    sys.exit(main())
