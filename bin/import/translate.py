# -*- coding: utf-8 -*-
"""Pasul 2b - traduce in rusa continutul produselor.

Traduce ce se vede: denumirea, descrierea si campurile ACF. Nu traduce SKU-ul,
pretul sau imaginile - acelea se copiaza asa cum sunt pe perechea rusa, de catre
ruta ht-import/v1/twin.

Ca si extragerea ACF, scrie intr-un fisier intermediar care poate fi citit si
corectat inainte de a ajunge in baza de date. E reluabil: produsele deja traduse
se sar.

    python translate.py                 # tot ce lipseste
    python translate.py --limit 3       # proba
    python translate.py --categorii     # doar denumirile celor 15 categorii
    python translate.py --force         # reface tot
"""
import argparse
import json
import re
import sys
import time

import requests

import config

API_URL = "https://api.openai.com/v1/chat/completions"

SCHEMA = {
    "name": "produs_tradus",
    "strict": True,
    "schema": {
        "type": "object",
        "additionalProperties": False,
        "required": ["titlu", "descriere", "descriere_scurta", "beneficii",
                     "ingrediente", "mod_de_utilizare", "atentionari"],
        "properties": {
            "titlu": {"type": "string"},
            "descriere": {"type": "string", "description": "HTML, aceeasi structura ca originalul."},
            "descriere_scurta": {"type": "string", "description": "1-3 propozitii, text simplu."},
            "beneficii": {"type": "array", "maxItems": 6, "items": {"type": "string"}},
            "ingrediente": {
                "type": "array",
                "items": {
                    "type": "object",
                    "additionalProperties": False,
                    "required": ["text", "strong"],
                    "properties": {
                        "text": {"type": "string"},
                        "strong": {"type": "boolean"},
                    },
                },
            },
            "mod_de_utilizare": {"type": "string"},
            "atentionari": {"type": "string"},
        },
    },
}

SYSTEM = """Traduci din romana in rusa continutul unui magazin de produse naturiste din Moldova.
Publicul e vorbitor de rusa din Republica Moldova.

Reguli:
- Traduci fidel. Nu adaugi, nu scoti, nu infrumusetezi. Daca un camp vine gol, il lasi gol.
- Pastrezi structura HTML a descrierii exact cum e: aceleasi etichete, aceeasi ordine.
  Traduci doar textul dintre ele.
- Denumirile de plante, vitamine si substante se traduc cu termenul rusesc consacrat:
  galbenele = календула, musetel = ромашка, urzica = крапива, catina = облепиха,
  immortelle/siminoc = бессмертник, rostopasca = чистотел, tataneasa = окопник,
  brusture = лопух, spanz = морозник, salvie = шалфей, coada soricelului = тысячелистник.
- Unitatile si cifrele raman neschimbate: 500 ml, 1,65 mg, N30, 150 %. Exceptie:
  UI (unitati internationale) devine МЕ - "2000UI" -> "2000 МЕ".
- Marcile si denumirile proprii nu se traduc si nu se transliterează: Herbal Therapy,
  ARTIX, VARIX, REUMIX, MELKFETT, Bom-Benghe, Geucamen, Dexpanthen, Reliefix,
  PhytoCalm, IMUNO, Flusept, Hepatoliv, Tusin, Detox, Herbal-Lax, Hepatic.
  Raman in alfabet latin, cum sunt pe ambalaj.

Titlul, in plus:
- Majuscule ca in rusa, nu ca in romana: doar primul cuvant si numele proprii.
  "Мазь с календулой и прополисом", nu "Мазь с Календула и Прополис".
- Substantivele dupa "с" stau la instrumental: с календулой, с прополисом,
  с ромашкой, с бессмертником, с облепихой, с чистотелом, с шалфеем.
- Genul adjectivului se acorda cu substantivul: "лосьон" e masculin
  ("лосьон против акне", nu "противоугревая лосьон"), "вазелин" e masculin
  ("вазелин косметический", nu "косметическая вазелин").
- "aromă de X" in denumire -> "со вкусом X" (aceeasi forma peste tot).
- "ulei de cânepă" dupa "+" -> "+ конопляное масло"; legat cu "si" ->
  "и конопляным маслом". Nu amesteca cele doua forme.
- Nu adauga cuvinte care nu sunt in original: "Unguent cu Aloe Vera" e
  "Мазь с алоэ вера", nu "Мазь с экстрактом алоэ вера".
- Descrierea scurta e text simplu, fara HTML, si ramane intre 150 si 165 de
  caractere: e ce se vede in rezultatele Google. Pastreaza cifrele si cantitatea.
- Beneficiile raman scurte, fara punct final, ca in original.
- Sunt produse pentru sanatate: nu introduci afirmatii terapeutice care nu sunt in original."""

TRANSLIT = {
    "а": "a", "б": "b", "в": "v", "г": "g", "д": "d", "е": "e", "ё": "e", "ж": "zh",
    "з": "z", "и": "i", "й": "y", "к": "k", "л": "l", "м": "m", "н": "n", "о": "o",
    "п": "p", "р": "r", "с": "s", "т": "t", "у": "u", "ф": "f", "х": "h", "ц": "c",
    "ч": "ch", "ш": "sh", "щ": "sch", "ъ": "", "ы": "y", "ь": "", "э": "e",
    "ю": "yu", "я": "ya",
}


LUNGIME_SLUG = 80

# Cantitatea de la finalul denumirii: "500 ml", "100 г", "3 мг", "N30", "12+".
# E singurul lucru care deosebeste variantele aceleiasi game, deci nu se taie.
CANTITATE = re.compile(
    r"(?:\d+\s*(?:мл|л|г|мг|мкг|ме)|n\s*\d+)\s*$", re.IGNORECASE)


def slug_din_rusa(titlu):
    """Slug latin dintr-un titlu chirilic.

    sanitize_title() din WordPress goleste textul chirilic si cade pe ID-ul
    postului, deci slug-ul il facem noi, ca in 'vitamin-b-kompleks-c'.

    Peste LUNGIME_SLUG taiem la ultima cratima, nu in mijlocul cuvantului, si
    lipim inapoi cantitatea: fara ea "...-500-ml" si "...-1000-ml" ar da acelasi
    slug, iar WordPress ar pune tacut un "-2" pe al doilea.
    """
    def latin(text):
        text = "".join(TRANSLIT.get(litera, litera) for litera in text.lower())
        text = re.sub(r"[^a-z0-9]+", "-", text)
        return re.sub(r"-{2,}", "-", text).strip("-")

    intreg = latin(titlu)

    if len(intreg) <= LUNGIME_SLUG:
        return intreg

    potrivire = CANTITATE.search(titlu)
    coada = "-" + latin(potrivire.group()) if potrivire else ""

    scurt = intreg[:LUNGIME_SLUG - len(coada)]
    if "-" in scurt:
        scurt = scurt[:scurt.rindex("-")]

    return (scurt.strip("-") + coada).strip("-")


def cere(session, payload):
    for incercare in range(1, 6):
        raspuns = session.post(API_URL, json=payload, timeout=180)

        if raspuns.status_code == 200:
            return json.loads(raspuns.json()["choices"][0]["message"]["content"])

        if raspuns.status_code in (429, 500, 502, 503, 529):
            pauza = min(2 ** incercare, 30)
            print("    " + str(raspuns.status_code) + ", reiau peste " + str(pauza) + "s", flush=True)
            time.sleep(pauza)
            continue

        raise RuntimeError("OpenAI " + str(raspuns.status_code) + ": " + raspuns.text[:300])

    raise RuntimeError("prea multe reincercari")


def traduce_produs(session, rand, descriere, acf, scurta=""):
    sursa = {
        "titlu": rand["denumire"],
        "descriere": descriere,
        "descriere_scurta": scurta,
        "beneficii": (acf or {}).get("beneficii", []),
        "ingrediente": (acf or {}).get("ingrediente", []),
        "mod_de_utilizare": (acf or {}).get("mod_de_utilizare", ""),
        "atentionari": (acf or {}).get("atentionari", ""),
    }

    payload = {
        "model": config.OPENAI_MODEL,
        "messages": [
            {"role": "system", "content": SYSTEM},
            {"role": "user", "content": "Tradu in rusa:\n\n" +
                                        json.dumps(sursa, ensure_ascii=False, indent=1)},
        ],
        "temperature": 0.2,
        "response_format": {"type": "json_schema", "json_schema": SCHEMA},
    }

    tradus = cere(session, payload)
    tradus["slug"] = slug_din_rusa(tradus["titlu"])
    return tradus


def traduce_categorii(session, nume):
    payload = {
        "model": config.OPENAI_MODEL,
        "messages": [
            {"role": "system", "content": SYSTEM},
            {"role": "user", "content":
                "Tradu in rusa aceste denumiri de categorii de magazin. Raspunde cu un obiect "
                "JSON in care cheia e denumirea romaneasca, iar valoarea traducerea:\n\n" +
                json.dumps(nume, ensure_ascii=False, indent=1)},
        ],
        "temperature": 0.1,
        "response_format": {"type": "json_object"},
    }
    return cere(session, payload)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, help="traduce doar primele N produse")
    parser.add_argument("--force", action="store_true", help="reface si ce e deja tradus")
    parser.add_argument("--categorii", action="store_true", help="doar denumirile categoriilor")
    args = parser.parse_args()

    if not config.OPENAI_API_KEY:
        sys.exit("Lipseste OPENAI_API_KEY (pune-l in bin/import/.env).")

    raport = json.loads(config.MATCH_JSON.read_text(encoding="utf-8"))
    produse = {p["id"]: p for p in
               json.loads(config.SHOPIFY_JSON.read_text(encoding="utf-8"))["products"]}

    acf_toate = {}
    if config.ACF_JSON.exists():
        acf_toate = json.loads(config.ACF_JSON.read_text(encoding="utf-8"))

    gata = {"produse": {}, "categorii": {}}
    if config.RU_JSON.exists() and not args.force:
        gata = json.loads(config.RU_JSON.read_text(encoding="utf-8"))
        gata.setdefault("produse", {})
        gata.setdefault("categorii", {})

    session = requests.Session()
    session.headers.update({"Authorization": "Bearer " + config.OPENAI_API_KEY})

    toate_randurile = raport["sure"] + raport["maybe"] + raport["none"]

    scurte = {}
    if config.SCURTE_JSON.exists():
        scurte = json.loads(config.SCURTE_JSON.read_text(encoding="utf-8"))

    # --- categoriile ------------------------------------------------------
    categorii = sorted({r["categorie"] for r in toate_randurile if r.get("categorie")})
    lipsa = [c for c in categorii if c not in gata["categorii"]]

    if lipsa:
        print("traduc " + str(len(lipsa)) + " categorii ...")
        gata["categorii"].update(traduce_categorii(session, lipsa))
        config.RU_JSON.write_text(json.dumps(gata, ensure_ascii=False, indent=1), encoding="utf-8")
        for ro, ru in sorted(gata["categorii"].items()):
            print("  " + ro + "  ->  " + ru)

    if args.categorii:
        print("\n-> " + str(config.RU_JSON))
        return

    # --- produsele --------------------------------------------------------
    ramase = [r for r in toate_randurile if r["denumire"] not in gata["produse"]]
    if args.limit:
        ramase = ramase[:args.limit]

    print("\n" + str(len(toate_randurile)) + " produse | " + str(len(gata["produse"])) +
          " deja traduse | " + str(len(ramase)) + " de tradus acum")

    for numar, rand in enumerate(ramase, 1):
        eticheta = "[" + str(numar) + "/" + str(len(ramase)) + "] "
        shopify_id = rand.get("shopify_id")
        descriere = (produse[shopify_id].get("body_html", "") if shopify_id else "")
        acf = acf_toate.get(str(shopify_id)) if shopify_id else None

        print(eticheta + rand["denumire"][:60], flush=True)
        tradus = traduce_produs(session, rand, descriere, acf,
                                (scurte.get(rand["denumire"]) or {}).get("ro", ""))
        tradus["_denumire_ro"] = rand["denumire"]
        gata["produse"][rand["denumire"]] = tradus

        print("    " + tradus["titlu"][:60] + "  /" + tradus["slug"][:40])

        config.RU_JSON.write_text(json.dumps(gata, ensure_ascii=False, indent=1), encoding="utf-8")

    print("\n-> " + str(config.RU_JSON))
    print("   Citeste traducerile inainte de a le publica.")


if __name__ == "__main__":
    main()
