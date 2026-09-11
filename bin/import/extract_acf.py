# -*- coding: utf-8 -*-
"""Pasul 2 - scoate campurile ACF din descrierile de pe site, cu ajutorul AI.

Sursa difera de la camp la camp:

  - ingrediente, mod_de_utilizare, atentionari vin din taburile paginii de
    produs, aduse la pasul 0b (fetch_tabs.py). Acolo textul e deja structurat,
    deci se preia ca atare - fara AI, fara risc de reformulare.
  - beneficii nu exista nicaieri ca lista: niciun produs nu are o sectiune
    "Beneficii". Aici modelul reformuleaza in randuri scurte ce spune deja
    descrierea.

Cand un produs nu are taburi pe site, se cade pe extragerea completa din
descriere, ca inainte.

Rezultatul se scrie intr-un fisier intermediar, nu direct in baza de date: poate
fi citit, corectat cu mana si abia apoi aplicat (pasul 3).

Scriptul e reluabil - produsele deja procesate se sar - si se poate rula pe un
subset:

    python extract_acf.py                 # tot ce lipseste
    python extract_acf.py --limit 5       # doar 5, pentru o proba
    python extract_acf.py --force         # reface tot
"""
import argparse
import json
import re
import sys
import time

import requests

import config

API_URL = "https://api.openai.com/v1/chat/completions"

# Structura ceruta modelului. 'strict' garanteaza ca raspunsul respecta schema.
SCHEMA = {
    "name": "campuri_produs",
    "strict": True,
    "schema": {
        "type": "object",
        "additionalProperties": False,
        "required": ["beneficii", "ingrediente", "mod_de_utilizare", "atentionari"],
        "properties": {
            "beneficii": {
                "type": "array",
                "maxItems": 6,
                "items": {"type": "string"},
                "description": "Maximum 6 beneficii, cate un rand scurt fiecare, "
                               "fara bulina si fara punct final.",
            },
            "ingrediente": {
                "type": "array",
                "items": {
                    "type": "object",
                    "additionalProperties": False,
                    "required": ["text", "strong"],
                    "properties": {
                        "text": {"type": "string"},
                        "strong": {
                            "type": "boolean",
                            "description": "Adevarat doar pentru randuri de nota, ca "
                                           "'VNR - Valoarea nutritionala de referinta'.",
                        },
                    },
                },
            },
            "mod_de_utilizare": {"type": "string", "description": "HTML simplu (<p>), sau sir gol."},
            "atentionari": {"type": "string", "description": "HTML simplu (<p>), sau sir gol."},
        },
    },
}

SYSTEM = """Esti redactor de continut pentru un magazin de produse naturiste din Moldova.
Primesti descrierea unui produs, asa cum e scrisa pe site, si o reorganizezi in campurile
cerute de schema.

REGULA CEA MAI IMPORTANTA: nu inventa nimic. Foloseste exclusiv informatia din textul primit.
Daca textul nu spune nimic despre un camp, lasa-l gol (lista goala sau sir gol). Este perfect
acceptabil ca 'atentionari' si 'ingrediente' sa ramana goale - majoritatea produselor nu au
aceste informatii in descriere.

Sunt produse pentru sanatate: nu adauga afirmatii terapeutice care nu apar in text, nu promite
vindecarea vreunei boli, nu exagera efectele. Reformulezi ce scrie deja, nu scrii ceva nou.

beneficii - reformuleaza in maximum 6 randuri scurte ceea ce textul spune deja ca face produsul.
  Stil: "Sustine metabolismul energetic", "Reduce oboseala si stresul". Fara bulina, fara punct
  final, maximum 60 de caractere pe rand.

ingrediente - doar daca textul enumera efectiv o compozitie. Un ingredient pe rand, pastrand
  cantitatile daca sunt date: "Vitamina B1 (Mononitrat de tiamina) - 1,65 mg (150 %)".
  Nu transforma o enumerare de plante din proza in tabel de compozitie decat daca textul chiar
  o prezinta ca lista de ingrediente.

mod_de_utilizare - instructiunile de folosire, in <p>. Daca textul are "Mod de utilizare:" sau
  "Mod de administrare:", preia continutul de acolo, curatat.

atentionari - avertismente, contraindicatii, conditii de pastrare, in <p>. Doar daca apar in text.

Scrii in romana, cu diacritice, la fel ca textul primit."""

# Exemplu real, completat de om, de pe produsul B Complex + Vitamina C (ID 69).
# Ii arata modelului nivelul de detaliu si stilul asteptat.
EXEMPLU = {
    "beneficii": [
        "Susține metabolismul energetic",
        "Îmbunătățește funcția sistemului nervos",
        "Crește imunitatea",
        "Piele, păr și unghii sănătoase",
        "Reduce oboseala și stresul",
    ],
    "ingrediente": [
        {"text": "Vitamina B1 (Mononitrat de tiamină) - 1,65 mg (150 %)", "strong": False},
        {"text": "Vitamina B2 (Riboflavină) - 2,1 mg (150 %)", "strong": False},
        {"text": "Acid ascorbic/Vitamina C - 80 mg (100 %)", "strong": False},
    ],
    "mod_de_utilizare": "<p>Mod de administrare: Adulți: 1 capsulă pe zi.<br>"
                        "Durata medie de utilizare: 1 lună.</p>",
    "atentionari": "<p>Suplimentele alimentare nu înlocuiesc o dietă variată și echilibrată. "
                   "A nu se lăsa la îndemâna și vederea copiilor mici.</p>",
}


def html_to_text(html):
    """Descrierea Shopify e HTML; modelul lucreaza mai bine pe text curat."""
    text = re.sub(r"<br\s*/?>", "\n", html or "")
    text = re.sub(r"</(p|div|li|h[1-6])>", "\n", text)
    text = re.sub(r"<[^>]+>", " ", text)
    for entitate, semn in (("&nbsp;", " "), ("&amp;", "&"), ("&lt;", "<"),
                           ("&gt;", ">"), ("&quot;", '"'), ("&#39;", "'")):
        text = text.replace(entitate, semn)
    text = re.sub(r"[ \t]+", " ", text)
    return re.sub(r"\n\s*\n+", "\n\n", text).strip()


def ask(session, denumire, descriere):
    """O cerere catre model, cu reincercari la limitare de rata."""
    mesaje = [
        {"role": "system", "content": SYSTEM},
        {"role": "user", "content": "Produs: Vitamina B Complex + C, 30 capsule\n\n"
                                    "(exemplu de raspuns bine format)"},
        {"role": "assistant", "content": json.dumps(EXEMPLU, ensure_ascii=False)},
        {"role": "user", "content": "Produs: " + denumire + "\n\nDescriere:\n" + descriere},
    ]
    payload = {
        "model": config.OPENAI_MODEL,
        "messages": mesaje,
        "temperature": 0.2,
        "response_format": {"type": "json_schema", "json_schema": SCHEMA},
    }

    for incercare in range(1, 6):
        raspuns = session.post(API_URL, json=payload, timeout=120)

        if raspuns.status_code == 200:
            return json.loads(raspuns.json()["choices"][0]["message"]["content"])

        if raspuns.status_code in (429, 500, 502, 503, 529):
            pauza = min(2 ** incercare, 30)
            print("    " + str(raspuns.status_code) + ", reiau peste " + str(pauza) + "s", flush=True)
            time.sleep(pauza)
            continue

        raise RuntimeError("OpenAI " + str(raspuns.status_code) + ": " + raspuns.text[:300])

    raise RuntimeError("prea multe reincercari")


NOTA = re.compile(r"^\s*(VNR|DZR|\*|\()", re.I)


def din_taburi(taburi):
    """Transforma randurile aduse din pagina in valorile campurilor ACF."""
    camp = {}

    randuri = taburi.get("ingrediente") or []
    camp["ingrediente"] = [
        {"text": r.rstrip(" ,;"), "strong": bool(NOTA.match(r))}
        for r in randuri if r.strip()
    ]

    for cheie in ("mod_de_utilizare", "atentionari"):
        randuri = [r for r in (taburi.get(cheie) or []) if r.strip()]
        camp[cheie] = "".join("<p>" + r + "</p>" for r in randuri)

    return camp


def curata(camp):
    """Aduce raspunsul in limitele campurilor ACF si scoate bulinele ramase."""
    beneficii = []
    for text in camp.get("beneficii") or []:
        text = re.sub(r"^[\s\-•\*]+", "", str(text)).strip().rstrip(".")
        if text:
            beneficii.append(text)
    camp["beneficii"] = beneficii[:6]   # repeaterul are max 6 randuri

    ingrediente = []
    for rand in camp.get("ingrediente") or []:
        text = str(rand.get("text", "")).strip()
        if text:
            ingrediente.append({"text": text, "strong": bool(rand.get("strong"))})
    camp["ingrediente"] = ingrediente

    for cheie in ("mod_de_utilizare", "atentionari"):
        camp[cheie] = (camp.get(cheie) or "").strip()

    return camp


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, help="proceseaza doar primele N produse")
    parser.add_argument("--force", action="store_true", help="reface si ce e deja extras")
    args = parser.parse_args()

    if not config.OPENAI_API_KEY:
        sys.exit("Lipseste OPENAI_API_KEY (pune-l in bin/import/.env).")

    raport = json.loads(config.MATCH_JSON.read_text(encoding="utf-8"))
    produse = {p["id"]: p for p in
               json.loads(config.SHOPIFY_JSON.read_text(encoding="utf-8"))["products"]}

    taburi_toate = {}
    if config.TABS_JSON.exists():
        taburi_toate = json.loads(config.TABS_JSON.read_text(encoding="utf-8"))
    else:
        print("ATENTIE: data/product_tabs.json lipseste - ruleaza intai fetch_tabs.py,")
        print("         altfel ingredientele si atentionarile raman goale.")

    gata = {}
    if config.ACF_JSON.exists() and not args.force:
        gata = json.loads(config.ACF_JSON.read_text(encoding="utf-8"))

    # doar randurile cu corespondent pe site au din ce sa fie extrase
    de_facut = [r for r in raport["sure"] + raport["maybe"] if r.get("shopify_id")]
    ramase = [r for r in de_facut if str(r["shopify_id"]) not in gata]

    if args.limit:
        ramase = ramase[:args.limit]

    print(str(len(de_facut)) + " produse cu continut | " + str(len(gata)) +
          " deja extrase | " + str(len(ramase)) + " de procesat acum")

    if not ramase:
        return

    session = requests.Session()
    session.headers.update({"Authorization": "Bearer " + config.OPENAI_API_KEY})

    for numar, rand in enumerate(ramase, 1):
        eticheta = "[" + str(numar) + "/" + str(len(ramase)) + "] "
        descriere = html_to_text(produse[rand["shopify_id"]].get("body_html", ""))

        if len(descriere) < 40:
            print(eticheta + "(sar, descriere goala) " + rand["denumire"][:50])
            continue

        print(eticheta + rand["denumire"][:60], flush=True)
        camp = curata(ask(session, rand["denumire"], descriere))

        # taburile de pe site bat ce a dedus modelul din proza
        taburi = taburi_toate.get(str(rand["shopify_id"]))
        if taburi:
            for cheie, valoare in din_taburi(taburi).items():
                if valoare:
                    camp[cheie] = valoare
            camp["_sursa"] = "taburi + AI pentru beneficii"
        else:
            camp["_sursa"] = "doar AI (produsul nu are taburi pe site)"

        camp["_denumire"] = rand["denumire"]
        gata[str(rand["shopify_id"])] = camp

        print("    beneficii=" + str(len(camp["beneficii"])) +
              " ingrediente=" + str(len(camp["ingrediente"])) +
              " utilizare=" + ("da" if camp["mod_de_utilizare"] else "nu") +
              " atentionari=" + ("da" if camp["atentionari"] else "nu"))

        # salvam dupa fiecare produs: o intrerupere nu pierde ce s-a platit deja
        config.ACF_JSON.write_text(json.dumps(gata, ensure_ascii=False, indent=1),
                                   encoding="utf-8")

    print("\n-> " + str(config.ACF_JSON))
    print("   Citeste fisierul si corecteaza ce nu suna bine INAINTE de pasul 3.")


if __name__ == "__main__":
    main()
