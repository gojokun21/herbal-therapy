# -*- coding: utf-8 -*-
"""Pasul 0 - aduce catalogul Shopify si il salveaza local.

Site-ul sursa are protectie anti-bot si incepe sa refuze conexiunile dupa
cateva cereri, asa ca descarcam o singura data, cu pauze intre pagini, si
lucram apoi pe fisierul local. Rularile urmatoare nu mai ating reteaua decat
daca ceri explicit --force.

    python fetch_shopify.py [--force]
"""
import json
import sys
import time

import requests

import config


def fetch_all():
    products, page = [], 1
    session = requests.Session()
    session.headers["User-Agent"] = config.USER_AGENT

    while True:
        url = f"{config.SHOPIFY_BASE}/products.json"
        print(f"  pagina {page} ...", end=" ", flush=True)
        try:
            r = session.get(url, params={"limit": 250, "page": page}, timeout=30)
            r.raise_for_status()
        except requests.RequestException as exc:
            print(f"esuat: {exc}")
            if products:
                print("  ! pastrez ce am apucat sa aduc")
                break
            raise SystemExit("nu am putut descarca nimic - incearca mai tarziu")

        batch = r.json().get("products", [])
        print(f"{len(batch)} produse")
        if not batch:
            break
        products.extend(batch)
        page += 1
        time.sleep(config.REQUEST_DELAY)

    return products


def main():
    force = "--force" in sys.argv
    if config.SHOPIFY_JSON.exists() and not force:
        cached = json.loads(config.SHOPIFY_JSON.read_text(encoding="utf-8"))
        print(f"deja descarcat: {len(cached['products'])} produse "
              f"({config.SHOPIFY_JSON}). Foloseste --force ca sa reiei.")
        return

    print(f"descarc catalogul de pe {config.SHOPIFY_BASE}")
    products = fetch_all()
    config.SHOPIFY_JSON.write_text(
        json.dumps({"products": products}, ensure_ascii=False, indent=1),
        encoding="utf-8")

    images = sum(len(p.get("images", [])) for p in products)
    print(f"\n{len(products)} produse, {images} imagini -> {config.SHOPIFY_JSON}")


if __name__ == "__main__":
    main()
