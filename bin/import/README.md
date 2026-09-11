# Import catalog Herbal Therapy

Aduce produsele din lista oficiala (Excel) in WooCommerce, folosind site-ul
Shopify existent ca sursa de continut.

Rolurile sunt fixe:

- **Excel** = lista oficiala. Denumirea, SKU-ul, categoria si - din lista de
  pret 01.09.2026 - pretul cu amanuntul de acolo raman.
- **Shopify** = magazie de continut. De acolo vin doar descrierea si imaginile
  (pretul de pe site se foloseste doar daca Excel-ul nu are coloana de pret).

Nu se importa nimic ce nu e in Excel: pachetele promotionale, produsele
tehnice ("Asigurare comanda", "Pickup In store") si produsele de pe site care
lipsesc din lista raman pe dinafara.

Randurile fara SKU se importa si ele (clientul a confirmat pe 01.09.2026 ca
sunt produse existente, doar fara cod in inventar); `match.py --exclude-fara-sku`
le lasa deoparte daca e nevoie. Ele se recunosc in magazin dupa denumire.

## Pregatire

```
pip install -r requirements.txt
cp .env.example .env      # apoi completeaza cheile
```

In `.env` sunt nevoie de trei seturi de chei:

| Cheie | De unde |
|---|---|
| `OPENAI_API_KEY` | platform.openai.com |
| `WC_KEY` / `WC_SECRET` | WooCommerce > Setari > Avansat > API REST |
| `WP_USER` / `WP_APP_PASSWORD` | Utilizatori > Profil > Parole de aplicatie |

Parola de aplicatie e separata pentru ca rutele `ht-import/v1` (campurile ACF
si perechea in rusa) sunt rute WordPress obisnuite: cheile WooCommerce
autentifica doar rutele `wc/v3`.

## Ordinea pasilor

```
python fetch_shopify.py        # 0. catalogul sursa, o singura data
python match.py                # 1. Excel x Shopify -> match_report.json
python extract_acf.py          # 2. descrieri --AI--> acf_fields.json
python translate.py            # 2b. traduceri RO->RU -> ru_translations.json
python import_products.py      # 3. scrie in WooCommerce
```

`data/descrieri_scurte.json` tine descrierile scurte (excerpt-ul), scrise de mana
in romana si rusa, cate una pe produs. Site-ul sursa n-avea asa ceva, deci nu se
extrag de nicaieri - se scriu. Formatul e `{"denumire RO": {"ro": "...", "ru": "..."}}`.

Sunt tinute la 150-165 de caractere pentru ca Rank Math cade pe excerpt cand
produsul n-are meta description proprie, iar Google taie pe la 160. Fiecare
varianta de volum are text propriu: doua excerpt-uri identice pe doua produse
inseamna continut duplicat.

De acolo pleaca in doua directii: `import_products.py` il pune in
`short_description` pe produsul romanesc, iar `translate.py` il trimite la
tradus si ajunge in `post_excerpt` pe perechea rusa, prin ruta `twin`.

Fiecare pas scrie un fisier in `data/` si il citeste pe cel dinainte. Pasii 2
si 2b sunt reluabili: produsele deja procesate se sar, iar rezultatul se
salveaza dupa fiecare produs, ca o intrerupere sa nu piarda ce s-a platit.

**Citeste fisierele intermediare inainte de pasul 3.** `acf_fields.json` si
`ru_translations.json` sunt text simplu: se pot corecta cu mana, si abia apoi
aplicate. Odata ajunse in baza de date, corectura e mai scumpa.

## Verificari inainte de import

```
python match.py                          # vezi sectiunea "de confirmat"
python extract_acf.py --limit 5          # o proba, sa vezi calitatea
python translate.py --categorii          # doar cele 15 denumiri de categorii
python import_products.py --dry-run      # ce s-ar intampla, fara sa scrie
python import_products.py --scurte --dry-run   # doar descrierile scurte
```

## Actualizari pe ce e deja importat

Importul normal sare produsele existente. Pentru a modifica ceva pe ele:

```
python import_products.py --scurte       # descrierile scurte, RO + RU
python import_products.py --ru-refresh   # perechea RU: titlu, slug, descriere
python import_products.py --acf-only     # doar campurile ACF
```

`--ru-refresh` rescrie si slug-ul perechii ruse. WordPress nu lasa redirect de la
adresa veche, deci daca produsul a apucat sa fie indexat, vechea adresa devine 404.

Orice mod se poate restrange la cateva produse cu `--doar SKU1,SKU2` (merge si
cu denumiri). Cand o potrivire a fost gresita la primul import - produsul a
primit descrierea si pozele altui articol - se corecteaza in
`data/potriviri_manuale.json`, se reia `match.py`, se sterge intrarea veche din
`ru_translations.json` (`translate.py` o reface din descrierea corecta) si apoi:

```
python import_products.py --continut --doar 4840257007379
```

`--continut` rescrie descrierea, imaginile, campurile ACF si perechea RU pe un
produs existent; pretul, SKU-ul si slug-ul RO raman. Imaginea de prezentare
generata in Figma (`bin/images/`) trebuie refacuta separat, fiindca a plecat din
packshot-ul gresit.

In `potriviri_manuale.json`, valoarea `0` inseamna "nu exista pe site": randul
nu primeste niciun candidat si intra ca ciorna, oricat de bine ar semana cu
altceva ("Vitamina C 180 mg N20" seamana cu cea de 100 mg, dar nu e ea).

## Ce nu face importul

- **Recenziile** - nu sunt in catalogul Shopify public.
- **Meta SEO Rank Math** - nu vine din sursa.
- **Descrierea si pozele celor 10 produse** fara corespondent pe site (Vitamina
  C N10 cu arome, Vitamina C 180 mg, Calciu gluconat N10, Bom-Benghe crema,
  Alcool mentolat). Acelea intra ca ciorne, cu denumire, SKU, categorie, pret
  si perechea RU, si se completeaza din administrare inainte de publicare.

## Idempotenta

`import_products.py` citeste intai tot magazinul si sare peste produsele care
exista deja - dupa SKU, iar pentru randurile fara SKU dupa denumirea
normalizata. Se poate rula de cate ori e nevoie fara sa dubleze nimic.

## Dupa import

Rutele REST se folosesc doar in timpul importului. Cand s-a terminat, se poate
scoate din `functions.php` linia:

```php
require_once HT_DIR . '/inc/import-endpoint.php';
```
