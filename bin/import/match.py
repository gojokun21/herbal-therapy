# -*- coding: utf-8 -*-
"""Pasul 1 - leaga lista oficiala din Excel de continutul de pe site.

Excel-ul e sursa de adevar: denumirea, SKU-ul si categoria de acolo raman.
Site-ul e doar magazie de continut - descriere, imagini, pret.

Potrivirea foloseste atribuire global optima (algoritmul ungar), nu alegere
lacoma: un produs de pe site ajunge la randul caruia i se potriveste cel mai
bine in ansamblu, nu la primul care il revendica. Fara asta, "Calciu 1000 mg
N30" fura corespondentul lui "N60" si al doilea ramanea orfan.

    python match.py
"""
import argparse
import json

import openpyxl
from rapidfuzz import fuzz
from scipy.optimize import linear_sum_assignment
import numpy as np

import config
from normalize import norm, tokens, size_conflict, size_bonus

FORBIDDEN = 1e6   # cost pentru perechile imposibile (marimi diferite)


def _pret(valoare):
    """Pretul din Excel ca text WooCommerce ("85.55"), sau "" daca lipseste."""
    if valoare in (None, ""):
        return ""
    try:
        numar = float(str(valoare).replace(",", "."))
    except ValueError:
        return ""
    return ("%.2f" % numar).rstrip("0").rstrip(".")


def read_xlsx(path):
    """Coloanele asteptate: Denumire | SKU | Categorie | Pret cu amanuntul | Cantitate.

    Primele trei sunt obligatorii. Pretul si cantitatea au aparut in lista din
    01.09.2026; cand lipsesc, raman goale si importul cade pe pretul de pe site.
    """
    wb = openpyxl.load_workbook(path, data_only=True)
    ws = wb.active
    rows = []
    for r in ws.iter_rows(min_row=2, values_only=True):
        r = tuple(r) + (None,) * 5
        name = str(r[0]).strip() if r[0] else ""
        if not name:
            continue
        rows.append({
            "denumire":   name,
            "sku":        str(r[1]).strip() if r[1] not in (None, "") else "",
            "categorie":  str(r[2]).strip() if r[2] else "",
            "pret_excel": _pret(r[3]),
            "cantitate":  str(r[4]).strip() if r[4] else "",
        })
    return rows


def build_candidates(products):
    """Un candidat per varianta: in Excel fiecare marime are SKU propriu, deci
    un produs variabil de pe Shopify hraneste mai multe randuri."""
    cands = []
    for p in products:
        multi = len(p["variants"]) > 1
        for v in p["variants"]:
            cands.append({
                "shopify_id": p["id"],
                "variant_id": v["id"],
                "handle":     p["handle"],
                "titlu":      f'{p["title"]} [{v["title"]}]' if multi else p["title"],
                "text":       p["title"] + (" " + v["title"] if multi else ""),
                "pret":       v.get("price"),
                "pret_vechi": v.get("compare_at_price"),
                "sku_shop":   v.get("sku") or "",
                "grams":      v.get("grams") or 0,
                "disponibil": bool(v.get("available")),
                "imagini":    [i["src"] for i in p.get("images", [])],
                "descriere":  p.get("body_html") or "",
            })
    return cands


def score(a, b):
    """0-100+; 0 inseamna imposibil."""
    if size_conflict(a, b):
        return 0.0
    ta, tb = tokens(a), tokens(b)
    if not ta or not tb:
        return 0.0
    acoperire = len(ta & tb) / len(ta)      # cat din denumirea oficiala se regaseste
    return 0.55 * fuzz.token_set_ratio(norm(a), norm(b)) + 45 * acoperire + size_bonus(a, b)


def citeste_manuale():
    """Potrivirile decise de om, care bat algoritmul.

    Fisierul e un obiect {denumire din Excel: shopify_id}. Foloseste-l pentru
    perechile pe care titlurile nu le apropie destul - de exemplu "Reliefix,
    Solutie Calmanta de Uz Extern" pe site se numeste "Reliefix Spray", fara
    niciun cuvant comun in afara marcii. Pune 0 ca sa fortezi 'fara continut'.
    """
    if not config.MANUAL_JSON.exists():
        return {}

    return json.loads(config.MANUAL_JSON.read_text(encoding="utf-8"))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--exclude-fara-sku", action="store_true",
                        help="lasa deoparte randurile fara SKU (implicit se importa si ele)")
    args = parser.parse_args()

    rows = read_xlsx(config.XLSX_PATH)

    # Din 01.09.2026 clientul vrea si randurile fara SKU pe site (sunt produse
    # existente, doar ca nu au cod nici in lista de pret, nici in inventar).
    # Cu --exclude-fara-sku raman in raport, la 'fara_sku', fara sa se importe.
    fara_sku = []
    if args.exclude_fara_sku:
        fara_sku = [r for r in rows if not r["sku"]]
        rows = [r for r in rows if r["sku"]]
    products = json.loads(config.SHOPIFY_JSON.read_text(encoding="utf-8"))["products"]
    cands = build_candidates(products)
    config.XLSX_JSON.write_text(json.dumps(rows, ensure_ascii=False, indent=1), encoding="utf-8")

    scores = np.zeros((len(rows), len(cands)))
    cost = np.full((len(rows), len(cands)), FORBIDDEN)
    for i, r in enumerate(rows):
        for j, c in enumerate(cands):
            s = score(r["denumire"], c["text"])
            scores[i, j] = s
            if s > 0:
                cost[i, j] = -s

    # potrivirile manuale se aseaza inaintea algoritmului, iar candidatii lor
    # ies din concurs ca sa nu fie luati de altcineva
    manuale = citeste_manuale()
    fixate = {}
    fara_continut = set()
    for i, r in enumerate(rows):
        if r["denumire"] not in manuale:
            continue
        tinta = manuale[r["denumire"]]
        if not tinta:
            # 0 = "nu exista pe site": randul nu primeste niciun candidat, altfel
            # "Vitamina C 180 mg N20" ar lua descrierea celei de 100 mg
            fara_continut.add(i)
            cost[i, :] = FORBIDDEN
            scores[i, :] = 0.0
            continue
        for j, c in enumerate(cands):
            if c["shopify_id"] == tinta:
                fixate[i] = j
                cost[i, :] = FORBIDDEN
                cost[:, j] = FORBIDDEN
                cost[i, j] = -200.0
                scores[i, j] = max(scores[i, j], 100.0)
                break

    ri, ci = linear_sum_assignment(cost)

    sure, maybe, none = [], [], []
    luate = set()
    for i, j in zip(ri, ci):
        s = scores[i, j]
        r = rows[i]
        rec = dict(r)
        if s >= config.SCORE_MAYBE:
            c = cands[j]
            luate.add(j)
            rec.update({k: c[k] for k in ("shopify_id", "variant_id", "handle", "titlu",
                                          "pret", "pret_vechi", "grams", "disponibil", "imagini")})
            rec["scor"] = round(float(s), 1)
            (sure if s >= config.SCORE_SURE else maybe).append(rec)
        else:
            rec["scor"] = round(float(s), 1)
            none.append(rec)

    # pentru randurile nesigure, arata si urmatoarele optiuni: alegerea o face omul
    for rec in maybe + none:
        i = next(k for k, r in enumerate(rows) if r["denumire"] == rec["denumire"])
        top = sorted(range(len(cands)), key=lambda j: -scores[i, j])[:3]
        rec["alternative"] = [{"titlu": cands[j]["titlu"],
                               "shopify_id": cands[j]["shopify_id"],
                               "variant_id": cands[j]["variant_id"],
                               "scor": round(float(scores[i, j]), 1)}
                              for j in top if scores[i, j] > 0]

    extra = [c["titlu"] for j, c in enumerate(cands) if j not in luate]

    print("=" * 78)
    print(f" Excel: {len(rows)} produse  |  Site: {len(products)} produse / {len(cands)} variante")
    print("=" * 78)
    print(f"  [OK] potrivite sigur        {len(sure):3d}")
    print(f"  [?]  de confirmat manual    {len(maybe):3d}")
    print(f"  [X]  fara continut pe site  {len(none):3d}")
    print(f"  [-]  pe site, neimportate   {len(extra):3d}")
    if fixate:
        print(f"  [M]  potriviri manuale     {len(fixate):3d}")
    if fara_continut:
        print(f"  [0]  fortate fara continut {len(fara_continut):3d}   (potriviri_manuale.json = 0)")
    if fara_sku:
        print(f"  [!]  excluse, fara SKU      {len(fara_sku):3d}   (produse iesite din stoc)")

    if maybe:
        print("\n" + "=" * 78 + f"\n  [?] DE CONFIRMAT ({len(maybe)})\n" + "=" * 78)
        for m in sorted(maybe, key=lambda x: x["scor"]):
            print(f'  Excel  {m["denumire"]}')
            for k, alt in enumerate(m["alternative"]):
                marcaj = "->" if alt["variant_id"] == m["variant_id"] else "  "
                print(f'    {marcaj} {alt["scor"]:5.1f}  {alt["titlu"]}')

    if none:
        print("\n" + "=" * 78 + f"\n  [X] FARA CONTINUT - fara descriere, imagini sau pret ({len(none)})\n" + "=" * 78)
        for m in none:
            print(f'  [{m["categorie"]}] {m["denumire"]}')

    if fara_sku:
        print()
        print("=" * 78)
        print(f"  [!] EXCLUSE - fara SKU in Excel ({len(fara_sku)})")
        print("=" * 78)
        for r in fara_sku:
            print(f'  [{r["categorie"]}] {r["denumire"]}')

    config.MATCH_JSON.write_text(
        json.dumps({"sure": sure, "maybe": maybe, "none": none, "extra": extra,
                    "fara_sku": fara_sku},
                   ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"\n-> {config.MATCH_JSON}")
    print("   Verifica sectiunea 'maybe', apoi muta manual perechile bune in 'sure'.")


if __name__ == "__main__":
    main()
