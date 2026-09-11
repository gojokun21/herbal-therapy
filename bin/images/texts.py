# -*- coding: utf-8 -*-
"""Pasul 1 - scrie textele de pe imagine (RO + RU), culorile titlului si
descrierea scenei pentru fiecare produs din data/products.json.

Modelul primeste denumirea, categoria, descrierea scurta, beneficiile si
poza de produs (ca sa aleaga culorile titlului dupa ambalaj) si intoarce, cu
schema stricta:

  - intrebarea scurta      ("Oboseala?")        - randul 1, Figtree Regular
  - verbul la imperativ    ("Recastiga-ti")     - randul 2, Figtree Black, gradient
  - substantivul           ("energia")          - randul 3, Figtree ExtraBold
  - aceleasi trei in rusa
  - doua culori hex pentru gradientul randului 2, luate de pe ambalaj
  - scena (in engleza) pentru generatorul de imagini: cine e persoana si
    ce simptom arata, plus culoarea fundalului

Rezultatul merge in data/copy.json, un produs pe cheie (slug). Fisierul e
text simplu: se citeste si se corecteaza cu mana inainte de pasul 2.

Scriptul e reluabil - produsele deja scrise se sar:

    python texts.py                  # tot ce lipseste
    python texts.py --limit 3        # o proba
    python texts.py --only slug-1,slug-2
    python texts.py --force          # rescrie si ce exista
"""
import argparse
import base64
import io
import json
import sys
import time

from openai import OpenAI
from PIL import Image

import config

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")  # consola Windows e cp1250

SCHEMA = {
    "name": "text_imagine_produs",
    "strict": True,
    "schema": {
        "type": "object",
        "additionalProperties": False,
        "required": [
            "question_ro", "verb_ro", "noun_ro",
            "question_ru", "verb_ru", "noun_ru",
            "color_start", "color_end", "backdrop",
            "person", "symptom", "scene",
        ],
        "properties": {
            "question_ro": {"type": "string", "description": "Intrebare scurta despre problema, max 24 caractere, cu '?' la final. Ex: 'Oboseală?', 'Gât iritat?', 'Piele uscată?'"},
            "verb_ro":     {"type": "string", "description": "Un singur verb la imperativ, persoana a II-a singular, eventual cu clitic legat prin cratima. Max 13 caractere. Ex: 'Recâștigă-ți', 'Redă-i', 'Calmează', 'Protejează'"},
            "noun_ro":     {"type": "string", "description": "Un singur cuvant care incheie indemnul, max 12 caractere. Ex: 'energia', 'confortul', 'pielea'"},
            "question_ru": {"type": "string", "description": "Aceeasi intrebare in rusa, max 24 caractere, cu '?'."},
            "verb_ru":     {"type": "string", "description": "Verbul in rusa, imperativ, forma de politete (Вы), max 13 caractere. Ex: 'Верните', 'Защитите', 'Успокойте'"},
            "noun_ru":     {"type": "string", "description": "Substantivul in rusa, max 14 caractere, cu pronume daca e nevoie de sens. Ex: 'энергию', 'себе комфорт', 'кожу'"},
            "color_start": {"type": "string", "description": "Hex fara #, culoarea dominanta de pe eticheta produsului (varianta mai deschisa) pentru inceputul gradientului titlului."},
            "color_end":   {"type": "string", "description": "Hex fara #, aceeasi nuanta, mai inchisa, pentru finalul gradientului. Trebuie sa fie lizibila pe fundal deschis."},
            "backdrop":    {"type": "string", "enum": ["warm beige", "light grey", "soft sage green", "pale blue", "cream"], "description": "Culoarea fundalului de studio, in armonie cu ambalajul."},
            "person":      {"type": "string", "description": "Persoana din poza, in engleza, o propozitie scurta: sex, varsta aproximativa, haine simple deschise la culoare. Copil doar pentru produsele de copii."},
            "symptom":     {"type": "string", "description": "In engleza: gestul sau expresia care arata problema pe care o rezolva produsul (ex. 'rubbing a sore knee', 'scratching dry, irritated skin on the forearm'). Fara sange, rani sau imagini neplacute."},
            "scene":       {"type": "string", "description": "In engleza, 1-2 propozitii: cum sta persoana in cadru, spre ce se uita, ce face cu mainile. Persoana e in jumatatea dreapta a cadrului."},
        },
    },
}

SYSTEM = """Esti copywriter pentru Herbal Therapy, un brand de produse naturiste din Moldova.
Scrii textul de pe imaginile de prezentare ale produselor. Fiecare imagine are trei randuri,
in ordinea asta: o intrebare scurta despre problema, un verb la imperativ si un substantiv.
Impreuna, randul 2 + randul 3 formeaza un indemn: "Recâștigă-ți energia", "Redă-i confortul",
"Respiră mai ușor", "Protejează-ți organismul".

Exemple din setul existent (RO / RU):
  Oboseală? / Recâștigă-ți / energia        -> Усталость? / Верните себе / энергию
  Gât iritat? / Redă-i / confortul          -> Раздражённое горло? / Верните / комфорт
  Nas înfundat? / Respiră / mai ușor        -> Заложен нос? / Дышите / легче
  Ficat solicitat? / Oferă-i / susținere    -> Нагрузка на печень? / Поддержите / её
  Adormi greu? / Regăsește-ți / odihna      -> Трудно уснуть? / Верните / себе отдых
  Piele sensibilă? / Susține-i / sănătatea  -> Чувствительная кожа? / Поддержите / её здоровье

Reguli:
- Textele sunt scurte: se afiseaza cu majuscule, pe un singur rand fiecare. Respecta limitele de caractere.
- Ton pozitiv, fara promisiuni medicale (nu "vindecă", nu "tratează"). Vorbim de confort, sustinere, ingrijire.
- Nu inventa beneficii: pleaca de la categoria, descrierea si beneficiile primite.
- Pentru cosmetice (sampon, gel de dus, creme, sapun) problema e de ingrijire (par tern, piele uscata, maini aspre), nu medicala.
- Pentru produse de copii, persoana e un copil sau un parinte cu copilul.
- Diacritice corecte in romana (ă â î ș ț), rusa cu ё unde e cazul.
- Randul 2 + randul 3, citite impreuna, trebuie sa formeze o propozitie naturala si corecta gramatical
  ("Calmează durerea", "Redă-i strălucirea", "Îngrijește-ți pielea"). Nu "Calmează-ți confortul".
- Fara cratima de despartire in silabe si fara rand nou in niciun text.
- Culorile titlului: cea mai saturata culoare de pe ETICHETA (numele produsului, logo-ul, ilustratia),
  NU culoarea recipientului sau a fundalului etichetei. Alb, gri, bej si pastelurile sunt interzise:
  textul sta pe un fundal deschis. color_start e o nuanta medie, saturata; color_end e aceeasi nuanta,
  clar mai inchisa. Exemple bune: ff7137 -> e52e02 (portocaliu), 4a9c15 -> 277000 (verde), a2024d -> 64034a (mov).
- Scena e pentru un generator de imagini foto-realist: persoana in jumatatea dreapta, jumatatea stanga ramane libera
  pentru text. Gestul arata problema, natural, fara dramatism."""


def packshot_data_url(path, size=512):
    im = Image.open(path).convert("RGB")
    im.thumbnail((size, size))
    buf = io.BytesIO()
    im.save(buf, format="JPEG", quality=85)
    return "data:image/jpeg;base64," + base64.b64encode(buf.getvalue()).decode("ascii")


def write_copy(client, product):
    lines = [
        "Produs: " + product["title_ro"],
        "Denumire in rusa: " + (product.get("title_ru") or "-"),
        "Categorie: " + (product.get("category") or "-"),
        "Descriere scurta: " + (product.get("excerpt") or "-"),
        "Beneficii: " + ("; ".join(product.get("benefits") or []) or "-"),
    ]
    content = [
        {"type": "text", "text": "\n".join(lines)},
        {"type": "image_url", "image_url": {"url": packshot_data_url(product["packshot"]), "detail": "low"}},
    ]
    r = client.chat.completions.create(
        model=config.COPY_MODEL,
        temperature=0.6,
        messages=[
            {"role": "system", "content": SYSTEM},
            {"role": "user", "content": content},
        ],
        response_format={"type": "json_schema", "json_schema": SCHEMA},
    )
    data = json.loads(r.choices[0].message.content)
    for k in ("question_ro", "verb_ro", "noun_ro", "question_ru", "verb_ru", "noun_ru"):
        data[k] = " ".join(data[k].replace("­", "").split())
    for k in ("color_start", "color_end"):
        data[k] = data[k].lstrip("#").lower()
        if len(data[k]) != 6:
            raise ValueError("culoare invalida: " + data[k])
    return data


def luminance(hex6):
    r, g, b = (int(hex6[i:i + 2], 16) / 255 for i in (0, 2, 4))
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def colors_ok(data):
    """Gradientul trebuie sa se citeasca pe fundal deschis: capatul inchis sub 0.45."""
    return luminance(data["color_end"]) < 0.45 and luminance(data["color_start"]) < 0.7


def write_copy_checked(client, product):
    data = write_copy(client, product)
    if colors_ok(data):
        return data
    print("(culori prea deschise, reincerc)", end=" ", flush=True)
    data2 = write_copy(client, product)
    return data2 if colors_ok(data2) else data


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--only", default="")
    ap.add_argument("--force", action="store_true")
    args = ap.parse_args()

    config.need_openai()
    if not config.PRODUCTS_JSON.exists():
        raise SystemExit("Lipseste data/products.json - ruleaza intai export_products.php")

    products = json.loads(config.PRODUCTS_JSON.read_text(encoding="utf-8"))
    done = json.loads(config.COPY_JSON.read_text(encoding="utf-8")) if config.COPY_JSON.exists() else {}
    only = {s.strip() for s in args.only.split(",") if s.strip()}

    todo = [p for p in products
            if (not only or p["slug"] in only) and (args.force or p["slug"] not in done)]
    if args.limit:
        todo = todo[:args.limit]
    if not todo:
        print("nimic de facut")
        return

    client = OpenAI(api_key=config.OPENAI_API_KEY)
    print(f"{len(todo)} produse de scris ({len(done)} deja in copy.json)")

    for i, p in enumerate(todo, 1):
        print(f"[{i}/{len(todo)}] {p['title_ro']}", end=" ... ", flush=True)
        try:
            data = write_copy_checked(client, p)
        except Exception as exc:  # noqa: BLE001 - raportam si mergem mai departe
            print(f"esuat: {exc}")
            time.sleep(2)
            continue
        data["title_ro"] = p["title_ro"]
        data["title_ru"] = p.get("title_ru") or ""
        done[p["slug"]] = data
        config.COPY_JSON.write_text(json.dumps(done, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"{data['question_ro']} | {data['verb_ro']} {data['noun_ro']}  #{data['color_start']}")

    print("scris in", config.COPY_JSON)


if __name__ == "__main__":
    sys.exit(main())
