# -*- coding: utf-8 -*-
"""Pasul 3 - creeaza produsele in WooCommerce.

Lista oficiala e Excel-ul: denumirea, SKU-ul si categoria de acolo raman asa cum
sunt. De pe site vin doar descrierea, imaginile si pretul.

Scriptul e idempotent: inainte sa scrie ceva, citeste ce exista deja in magazin
si sare peste produsele gasite - dupa SKU, iar pentru randurile fara SKU dupa
denumirea normalizata. Se poate rula de cate ori e nevoie fara sa dubleze nimic.

Campurile ACF nu se scriu prin meta_data, ci printr-o ruta a temei care le trece
prin update_field(). Repeaterele isi tin valorile desfacute pe randuri, fiecare
cu o cheie-oglinda; fabricate de mana, se strica tacut la prima salvare din
administrare.

Daca exista traduceri (data/ru_translations.json), fiecare produs primeste si
perechea in rusa, legata cu Polylang. Si aceea trece prin ruta temei, din acelasi
motiv: SKU-ul e comun celor doua traduceri, iar WooCommerce il accepta duplicat
doar dupa ce produsele sunt deja legate intre ele.

    python import_products.py --dry-run     # arata ce s-ar intampla
    python import_products.py --limit 3     # importa 3 produse, pentru proba
    python import_products.py               # importa tot ce lipseste
    python import_products.py --acf-only    # doar campurile ACF, pe ce exista deja
    python import_products.py --no-ru       # doar romana
    python import_products.py --ru-refresh  # rescrie doar perechile RU existente
    python import_products.py --scurte      # descrierile scurte pe ce exista deja
    python import_products.py --doar SKU1,SKU2            # doar produsele numite
    python import_products.py --continut --doar SKU1      # rescrie continutul unui
                                                          # produs potrivit gresit
"""
import argparse
import json
import sys

import config
from normalize import norm
from woo import Woo, WooError

# Consola Windows e pe cp1250: fara asta, prima denumire cu "s" sau "t" cu virgula
# opreste importul in mijlocul lui, cu UnicodeEncodeError.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# Produsele fara corespondent pe site intra ca ciorna: nu au descriere, imagini
# sau pret, deci nu au ce cauta publicate in magazin.
STARE_FARA_CONTINUT = "draft"
STARE_NORMALA = "publish"

# Lista oficiala e in romana; rusa se genereaza din ea.
LIMBA_SURSA = "ro"
LIMBA_TINTA = "ru"


def cheie_titlu(text):
    """Denumire normalizata, pentru randurile fara SKU."""
    return norm(text)


def indexeaza_existente(produse):
    """Doua cai de cautare: dupa SKU si dupa denumire."""
    dupa_sku, dupa_titlu = {}, {}
    for p in produse:
        if p.get("sku"):
            dupa_sku[p["sku"].strip()] = p
        dupa_titlu[cheie_titlu(p.get("name", ""))] = p
    return dupa_sku, dupa_titlu


def gaseste_existent(rand, dupa_sku, dupa_titlu):
    if rand.get("sku") and rand["sku"] in dupa_sku:
        return dupa_sku[rand["sku"]], "SKU"
    gasit = dupa_titlu.get(cheie_titlu(rand["denumire"]))
    if gasit:
        return gasit, "denumire"
    return None, None


def pregateste_categorii(woo, randuri, dry_run):
    """Se asigura ca cele 15 categorii din Excel exista; le creeaza pe cele lipsa."""
    existente = {c["name"].strip().lower(): c["id"]
                 for c in woo.toate_categoriile(lang=LIMBA_SURSA)}
    nevoite = sorted({r["categorie"].strip() for r in randuri if r.get("categorie")})

    harta = {}
    for nume in nevoite:
        gasit = existente.get(nume.lower())
        if gasit:
            harta[nume] = gasit
            continue
        if dry_run:
            print("  [dry-run] as crea categoria: " + nume)
            harta[nume] = 0
            continue
        creata = woo.creeaza_categorie(nume, LIMBA_SURSA)
        harta[nume] = creata["id"]
        print("  + categorie noua: " + nume + " (#" + str(creata["id"]) + ")")

    return harta


def date_produs(rand, id_categorie, scurta=""):
    """Corpul cererii pentru WooCommerce.

    Imaginile se trimit ca adrese: WooCommerce le descarca singur in biblioteca
    media. Asa ocolim si protectia anti-bot a site-ului sursa, care ne-ar refuza
    daca le-am aduce noi.
    """
    date = {
        "name": rand["denumire"],          # denumirea oficiala din Excel
        "type": "simple",                  # fiecare marime are SKU propriu
        "catalog_visibility": "visible",
        "lang": LIMBA_SURSA,               # altfel produsul ramane fara limba
        "categories": [{"id": id_categorie}] if id_categorie else [],
    }

    if rand.get("sku"):
        date["sku"] = rand["sku"]

    # excerpt-ul: Rank Math cade pe el cand produsul n-are meta description
    if scurta:
        date["short_description"] = scurta

    # Lista de pret din Excel (coloana "Pret cu amanuntul") bate pretul de pe
    # site: e cea oficiala, iar pe produsele deja importate coincide cu magazinul.
    pret_excel = rand.get("pret_excel")

    if not rand.get("shopify_id"):
        # rand fara corespondent pe site: identitatea si pretul, restul completati voi
        date["status"] = STARE_FARA_CONTINUT
        if pret_excel:
            date["regular_price"] = pret_excel
        return date

    date["status"] = STARE_NORMALA
    date["description"] = rand.get("descriere", "")

    pret = rand.get("pret")
    pret_vechi = rand.get("pret_vechi")
    if pret_excel:
        date["regular_price"] = pret_excel
    elif pret_vechi:
        # pe Shopify compare_at_price e pretul intreg, iar price cel redus
        date["regular_price"] = str(pret_vechi)
        date["sale_price"] = str(pret)
    elif pret:
        date["regular_price"] = str(pret)

    date["stock_status"] = "instock" if rand.get("disponibil") else "outofstock"

    grame = rand.get("grams") or 0
    if grame > 1:
        date["weight"] = str(round(grame / 1000.0, 3))

    imagini = rand.get("imagini") or []
    if imagini:
        date["images"] = [{"src": sursa} for sursa in imagini]

    return date


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--dry-run", action="store_true", help="nu scrie nimic, doar raporteaza")
    parser.add_argument("--limit", type=int, help="importa doar primele N produse")
    parser.add_argument("--acf-only", action="store_true",
                        help="nu creeaza produse, doar scrie campurile ACF")
    parser.add_argument("--include-maybe", action="store_true",
                        help="importa si potrivirile nesigure (implicit se sar)")
    parser.add_argument("--no-ru", action="store_true",
                        help="nu creea perechea in rusa")
    parser.add_argument("--ru-lipsa", action="store_true",
                        help="doar produsele care nu au inca pereche in rusa; "
                             "nu creeaza produse si nu rescrie ACF-ul")
    parser.add_argument("--ru-refresh", action="store_true",
                        help="rescrie perechea rusa pe produsele care exista deja "
                             "(titlu, slug, descriere); nu creeaza produse noi")
    parser.add_argument("--scurte", action="store_true",
                        help="scrie descrierea scurta pe produsele care exista deja, "
                             "in romana si pe perechea rusa; nu creeaza produse noi")
    parser.add_argument("--continut", action="store_true",
                        help="rescrie descrierea, imaginile, ACF-ul si perechea RU pe "
                             "produsele care exista deja (pentru potriviri corectate); "
                             "de folosit cu --doar")
    parser.add_argument("--doar", default="",
                        help="proceseaza doar produsele cu aceste SKU-uri sau denumiri, "
                             "separate prin virgula")
    args = parser.parse_args()

    raport = json.loads(config.MATCH_JSON.read_text(encoding="utf-8"))
    produse_shopify = {p["id"]: p for p in
                       json.loads(config.SHOPIFY_JSON.read_text(encoding="utf-8"))["products"]}

    campuri_acf = {}
    if config.ACF_JSON.exists():
        campuri_acf = json.loads(config.ACF_JSON.read_text(encoding="utf-8"))

    traduceri = {"produse": {}, "categorii": {}}
    if config.RU_JSON.exists():
        traduceri = json.loads(config.RU_JSON.read_text(encoding="utf-8"))
        traduceri.setdefault("produse", {})
        traduceri.setdefault("categorii", {})

    scurte = {}
    if config.SCURTE_JSON.exists():
        scurte = json.loads(config.SCURTE_JSON.read_text(encoding="utf-8"))

    randuri = list(raport["sure"])
    if args.include_maybe:
        randuri += raport["maybe"]
    randuri += raport["none"]          # intra ca ciorne, fara continut

    # descrierea vine din catalogul brut, nu din raportul de potrivire
    for rand in randuri:
        if rand.get("shopify_id"):
            rand["descriere"] = produse_shopify[rand["shopify_id"]].get("body_html", "")

    woo = Woo()
    try:
        woo.verifica_conexiunea()
    except WooError as eroare:
        sys.exit("Conexiunea catre WooCommerce nu merge:\n  " + str(eroare))

    print("citesc ce exista deja in magazin ...")
    # doar produsele romanesti: perechea rusa are acelasi SKU si ar ascunde-o
    existente = woo.toate_produsele(lang=LIMBA_SURSA)
    dupa_sku, dupa_titlu = indexeaza_existente(existente)
    print("  " + str(len(existente)) + " produse deja in magazin\n")

    if args.doar:
        alese = {cheie_titlu(x) for x in args.doar.split(",") if x.strip()}
        randuri = [r for r in randuri
                   if cheie_titlu(r.get("sku", "")) in alese
                   or cheie_titlu(r["denumire"]) in alese]
        print("  --doar: " + str(len(randuri)) + " randuri alese\n")

    de_procesat = randuri[:args.limit] if args.limit else randuri

    # numai categoriile produselor chiar procesate: o proba cu --limit nu trebuie
    # sa umple magazinul cu toate cele 15
    harta_categorii = ({} if args.acf_only
                       else pregateste_categorii(woo, de_procesat, args.dry_run))

    crea, sarite, acf_scrise, ru_create, erori = 0, [], 0, 0, []
    scurte_scrise = 0

    for numar, rand in enumerate(de_procesat, 1):
        eticheta = "[" + str(numar) + "/" + str(len(de_procesat)) + "] "
        gasit, cum = gaseste_existent(rand, dupa_sku, dupa_titlu)

        # --- produsul exista deja: nu il atingem ---------------------------
        if gasit and not (args.acf_only or args.ru_lipsa or args.ru_refresh or args.scurte
                          or args.continut):
            sarite.append((rand["denumire"], cum, gasit["id"]))
            print(eticheta + "= exista (dupa " + cum + ", #" + str(gasit["id"]) + ") " +
                  rand["denumire"][:52])
            continue

        id_produs = gasit["id"] if gasit else None

        # in modul --ru-lipsa ne intereseaza doar cei fara traducere
        if args.ru_lipsa:
            if not gasit or gasit.get("translations", {}).get(LIMBA_TINTA):
                continue

        # --ru-refresh, --scurte si --continut nu creeaza nimic: lucreaza pe ce exista deja
        if (args.ru_refresh or args.scurte or args.continut) and not gasit:
            continue

        # --- creare --------------------------------------------------------
        if not gasit:
            if args.acf_only or args.ru_lipsa or args.ru_refresh or args.scurte:
                continue

            date = date_produs(rand, harta_categorii.get(rand.get("categorie", ""), 0),
                               (scurte.get(rand["denumire"]) or {}).get("ro", ""))

            if args.dry_run:
                print(eticheta + "[dry-run] as crea: " + rand["denumire"][:52] +
                      "  (" + str(len(date.get("images", []))) + " imagini, " +
                      date.get("regular_price", "fara pret") + ")")
                continue

            try:
                creat = woo.creeaza_produs(date)
            except WooError as eroare:
                erori.append((rand["denumire"], str(eroare)))
                print(eticheta + "! EROARE " + rand["denumire"][:40] + ": " + str(eroare)[:120])
                continue

            id_produs = creat["id"]
            crea += 1
            print(eticheta + "+ creat #" + str(id_produs) + " " + rand["denumire"][:52] +
                  "  (" + str(len(date.get("images", []))) + " img)")

        if not id_produs:
            continue

        # --- continutul de pe site, rescris pe un produs existent ------------
        # Pentru randurile potrivite gresit la primul import: descrierea si
        # imaginile vin de la corespondentul corect; ACF-ul si perechea RU se
        # rescriu mai jos, pe drumul obisnuit. Pretul si SKU-ul raman.
        if args.continut:
            if not rand.get("shopify_id"):
                continue
            date = date_produs(rand, 0)
            nou = {cheie: date[cheie] for cheie in ("description", "images") if cheie in date}
            if args.dry_run:
                print("        [dry-run] as rescrie descrierea si " +
                      str(len(nou.get("images", []))) + " imagini din " + rand.get("handle", ""))
            else:
                try:
                    woo.actualizeaza_produs(id_produs, nou)
                    print("        continut rescris din " + rand.get("handle", "")[:50])
                except WooError as eroare:
                    erori.append((rand["denumire"] + " (continut)", str(eroare)))
                    print("        ! continutul a esuat: " + str(eroare)[:110])
                    continue

        # --- descrierea scurta pe produsul romanesc existent ---------------
        if args.scurte:
            text = (scurte.get(rand["denumire"]) or {}).get("ro", "")
            if text and args.dry_run:
                print("        [dry-run] descriere scurta RO: " + text[:58] + "...")
            elif text:
                try:
                    woo.actualizeaza_produs(id_produs, {"short_description": text})
                    scurte_scrise += 1
                    print("        descriere scurta RO (" + str(len(text)) + " car.)")
                except WooError as eroare:
                    erori.append((rand["denumire"] + " (scurta)", str(eroare)))
                    print("        ! descrierea scurta a esuat: " + str(eroare)[:110])

        # --- campurile ACF -------------------------------------------------
        # Lipsa lor nu opreste restul: produsele fara continut pe site tot au
        # nevoie de perechea in rusa, altfel raman doar in romana.
        camp = campuri_acf.get(str(rand.get("shopify_id"))) or {}
        util = {cheie: camp[cheie] for cheie in
                ("beneficii", "ingrediente", "mod_de_utilizare", "atentionari")
                if camp.get(cheie)}

        if args.ru_lipsa or args.ru_refresh or args.scurte:
            util = {}

        if util and args.dry_run:
            print("        [dry-run] ACF: " + ", ".join(util.keys()))
        elif util:
            try:
                woo.scrie_acf(id_produs, util)
                acf_scrise += 1
                print("        ACF: " + ", ".join(util.keys()))
            except WooError as eroare:
                erori.append((rand["denumire"] + " (ACF)", str(eroare)))
                print("        ! ACF esuat: " + str(eroare)[:120])

        # --- perechea in rusa ----------------------------------------------
        if args.no_ru:
            continue

        tradus = traduceri["produse"].get(rand["denumire"])
        if not tradus:
            continue

        pereche = {
            "lang": LIMBA_TINTA,
            "titlu": tradus["titlu"],
            "slug": tradus.get("slug", ""),
            "descriere": tradus.get("descriere", ""),
            "descriere_scurta": tradus.get("descriere_scurta", ""),
            "categorie": traduceri["categorii"].get(rand.get("categorie", ""), ""),
        }
        for cheie in ("beneficii", "ingrediente", "mod_de_utilizare", "atentionari"):
            if tradus.get(cheie):
                pereche[cheie] = tradus[cheie]

        if args.dry_run:
            print("        [dry-run] pereche RU: " + tradus["titlu"][:50])
            continue

        try:
            rezultat = woo.creeaza_pereche(id_produs, pereche)
            ru_create += 1
            print("        RU #" + str(rezultat["id"]) +
                  (" (creat)" if rezultat.get("creat") else " (actualizat)") +
                  " " + tradus["titlu"][:44])
        except WooError as eroare:
            erori.append((rand["denumire"] + " (RU)", str(eroare)))
            print("        ! perechea RU a esuat: " + str(eroare)[:120])

    # --- raport final ------------------------------------------------------
    print("\n" + "=" * 70)
    print("  produse create      " + str(crea))
    print("  sarite (existau)    " + str(len(sarite)))
    print("  campuri ACF scrise  " + str(acf_scrise))
    print("  descrieri scurte   " + str(scurte_scrise))
    print("  perechi RU         " + str(ru_create))
    print("  erori               " + str(len(erori)))

    if sarite:
        print("\n  Sarite fiindca existau deja:")
        for denumire, cum, id_gasit in sarite:
            print("    #" + str(id_gasit) + " (" + cum + ") " + denumire)

    if erori:
        print("\n  Erori:")
        for denumire, mesaj in erori:
            print("    " + denumire + ": " + mesaj[:160])

    if not args.dry_run:
        config.IMPORT_LOG.write_text(json.dumps(
            {"create": crea, "sarite": [s[0] for s in sarite],
             "acf": acf_scrise, "ru": ru_create, "erori": erori},
            ensure_ascii=False, indent=1), encoding="utf-8")
        print("\n-> " + str(config.IMPORT_LOG))


if __name__ == "__main__":
    main()
