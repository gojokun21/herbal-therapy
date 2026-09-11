# -*- coding: utf-8 -*-
"""Pasul 0b - aduce continutul taburilor de pe paginile de produs.

products.json contine doar descrierea (body_html). Taburile "Ingrediente",
"Mod de Utilizare" si "Atentionari si Mentiuni" stau in metafields Shopify si se
vad doar in pagina randata, nu in catalogul JSON. Fara pasul asta, cele trei
campuri ACF raman goale la aproape toate produsele, iar tema cade pe textele ei
de rezerva - care sunt in romana si apar asa si pe varianta rusa.

Continutul de aici e deja structurat si curat, deci nu mai trece prin AI:
se foloseste ca atare. Modelul ramane necesar doar pentru 'beneficii', care
nicaieri nu exista ca lista.

Site-ul are protectie anti-bot si raspunde intermitent, asa ca cererile sunt
rare, cu reincercari, iar rezultatul se salveaza dupa fiecare pagina.

    python fetch_tabs.py              # tot ce lipseste
    python fetch_tabs.py --limit 5    # proba
    python fetch_tabs.py --force      # reia tot
"""
import argparse
import json
import time

import requests
from bs4 import BeautifulSoup

import config

ANTET = {
    "User-Agent": config.USER_AGENT,
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "ro-RO,ro;q=0.9,en;q=0.8",
    "Upgrade-Insecure-Requests": "1",
}

# eticheta tabului de pe site -> numele campului ACF
CAMPURI = {
    "ingrediente": "ingrediente",
    "mod de utilizare": "mod_de_utilizare",
    "mod de administrare": "mod_de_utilizare",
    "atentionari si mentiuni": "atentionari",
    "atenționări și mențiuni": "atentionari",
}


def cheie(eticheta):
    """Normalizeaza eticheta tabului ca sa o putem cauta in CAMPURI."""
    text = eticheta.strip().lower()
    for a, b in (("ș", "s"), ("ş", "s"), ("ț", "t"), ("ţ", "t"),
                 ("ă", "a"), ("â", "a"), ("î", "i")):
        text = text.replace(a, b)
    return " ".join(text.split())


def taburi_din_pagina(html):
    """Continutul celor patru taburi, dupa eticheta lor."""
    supa = BeautifulSoup(html, "lxml")
    butoane = [b.get_text(strip=True) for b in supa.select("button.f-tabs__nav")]
    panouri = supa.select(".f-tabs__content")

    rezultat = {}

    for eticheta, panou in zip(butoane, panouri):
        camp = CAMPURI.get(cheie(eticheta))
        if not camp:
            continue                      # "Descriere" o avem deja din products.json

        interior = panou.select_one(".f-tabs__content-inner") or panou
        # cu \n pastram randurile: ingredientele sunt cate unul pe rand
        text = interior.get_text("\n", strip=True)
        randuri = [r.strip(" -–—•\t") for r in text.split("\n")]
        randuri = [r for r in randuri if r]

        if randuri:
            rezultat[camp] = randuri

    return rezultat


def adu(sesiune, handle):
    url = config.SHOPIFY_BASE + "/products/" + handle

    for incercare in range(1, 5):
        try:
            raspuns = sesiune.get(url, timeout=45)
        except requests.RequestException as eroare:
            print("    retea: " + type(eroare).__name__ + ", reiau", flush=True)
            time.sleep(3 * incercare)
            continue

        # raspunsurile scurte sunt pagini de verificare, nu produsul
        if raspuns.status_code == 200 and len(raspuns.content) > 150000:
            return taburi_din_pagina(raspuns.text)

        print("    HTTP " + str(raspuns.status_code) + " / " +
              str(len(raspuns.content)) + " octeti, reiau", flush=True)
        time.sleep(3 * incercare)

    return None


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, help="doar primele N produse")
    parser.add_argument("--force", action="store_true", help="reia si ce e deja adus")
    args = parser.parse_args()

    raport = json.loads(config.MATCH_JSON.read_text(encoding="utf-8"))

    gata = {}
    if config.TABS_JSON.exists() and not args.force:
        gata = json.loads(config.TABS_JSON.read_text(encoding="utf-8"))

    randuri = [r for r in raport["sure"] + raport["maybe"] if r.get("handle")]
    # un produs de pe site poate hrani mai multe randuri din Excel: aducem pagina o data
    handles = {}
    for r in randuri:
        handles.setdefault(str(r["shopify_id"]), r["handle"])

    ramase = [(sid, h) for sid, h in handles.items() if sid not in gata]
    if args.limit:
        ramase = ramase[:args.limit]

    print(str(len(handles)) + " pagini de produs | " + str(len(gata)) +
          " deja aduse | " + str(len(ramase)) + " de adus acum")

    if not ramase:
        return

    sesiune = requests.Session()
    sesiune.headers.update(ANTET)

    goale = 0

    for numar, (sid, handle) in enumerate(ramase, 1):
        print("[" + str(numar) + "/" + str(len(ramase)) + "] " + handle[:58], flush=True)
        taburi = adu(sesiune, handle)

        if taburi is None:
            print("    ! nu am putut aduce pagina")
            continue

        gata[sid] = taburi
        if not taburi:
            goale += 1

        print("    " + (", ".join(k + "=" + str(len(v)) for k, v in taburi.items())
                        if taburi else "(niciun tab)"))

        config.TABS_JSON.write_text(json.dumps(gata, ensure_ascii=False, indent=1),
                                    encoding="utf-8")
        time.sleep(config.REQUEST_DELAY)

    print("\n-> " + str(config.TABS_JSON))
    print("   " + str(len(gata)) + " pagini, din care " + str(goale) + " fara taburi")


if __name__ == "__main__":
    main()
