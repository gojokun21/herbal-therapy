# Redirecturi 301 Shopify -> WooCommerce

Domeniul `herbal-therapy.md` ramane acelasi, deci dupa mutarea DNS-ului toate
adresele vechi (Shopify) ajung pe WordPress. Le preia `inc/redirects.php`,
folosind harta din `inc/redirect-map.php`.

## Fisiere

| Fisier | Rol |
|---|---|
| `extract_shopify.py` | scoate din datele brute ale site-ului vechi lista de adrese (`shopify.json`) |
| `shopify.json` | adresele vechi: 183 produse, 13 colectii, 19 pagini, 4 politici, 4 bloguri + articole; comis in repo |
| `manual.php` | potrivirile facute de mana (colectii, pagini, bloguri, produse fara corespondent) |
| `build-map.php` | rezolva totul pe baza WP (slug-uri, traduceri Polylang) si scrie `inc/redirect-map.php` |
| `../../inc/redirects.php` | codul de runtime + tintele de rezerva; teste in `tests/RedirectsTest.php` |

## Regenerare

Harta se regenereaza oricand se schimba un slug pe site-ul nou (produs,
categorie, pagina). Din radacina instalarii WordPress (`app/public`):

```
HT_DB_HOST=127.0.0.1:10004 php wp-content/themes/herbal-therapy/bin/redirects/build-map.php --csv=redirecturi.csv
```

Scriptul iese cu cod 1 si listeaza problemele daca ramane vreo adresa veche
fara tinta sau daca o tinta din `manual.php` nu mai exista. Harta rezultata se
comite.

`extract_shopify.py` se ruleaza doar daca apar date noi despre site-ul vechi
(are nevoie de folderul "SEO Herbal" cu `_date-brute_scripturi/`).

## Reguli

- `/ru/...` ramane pe rusa; `/en/...` (limba disparuta) merge pe romana.
- Adresele necunoscute dintr-un spatiu Shopify primesc o tinta de rezerva:
  `/products/*` si `/collections/*` -> magazin, `/pages/*` -> prima pagina,
  `/blogs/*` -> blog, `/policies/*` -> termeni, `/account/*` -> contul meu,
  `/cart`, `/checkouts/*` -> cos, `/search?q=` -> cautare.
- Se pastreaza doar parametrii de urmarire (`utm_*`, `gclid`, `fbclid`).
- Adresele noi ale site-ului nu sunt atinse: se intra in joc doar pentru
  primul segment din `ht_legacy_namespaces()`, care nu exista in WordPress.

## Potriviri de compromis (de revizuit cand apar paginile)

- politica de livrare si retur, formular de retur, modalitati de plata,
  `/policies/refund-policy`, `/policies/shipping-policy` -> Termenii si conditiile
- intrebari frecvente, program livrare sarbatori -> Contact
- program de fidelizare -> Despre noi; reviews -> prima pagina
- pachetele promotionale si colectiile de oferte -> pagina Reduceri
- produse Shopify care nu mai exista (crom, zinc, vitamina A, lemn dulce, ulei
  camforat, parafina, cadouri) -> categoria lor
