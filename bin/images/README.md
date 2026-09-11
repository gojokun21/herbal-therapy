# Imagini de prezentare pentru produse

Genereaza, pentru produsele care nu au inca, imaginile de prezentare in stilul
fisierului Figma "Toate pozele jpg" (`kcN6mD2YKtUttVTf3f26i2`): o persoana care
arata problema, produsul pe o masa alba, si trei randuri de text (RO si RU).

Fundalul (persoana + produs) il face OpenAI. Textul NU il face AI-ul de
imagini: il pune un plugin Figma, cu exact fontul, marimile, gradientul si
umbrele cadrelor existente. Asa textul iese identic de fiecare data si se
poate corecta cu mana in Figma, ca pana acum.

Fiecare rulare da alta persoana si alta poza: modelele de imagine nu sunt
deterministe. Ce e reproductibil e stilul, nu pixelii.

## Pregatire

```
pip install -r ../import/requirements.txt openai pillow
cp .env.example .env      # OPENAI_API_KEY (sau se ia din ../import/.env), FIGMA_TOKEN
```

Fontul **Figtree** trebuie sa fie disponibil in Figma (e deja folosit in fisier).

## Ordinea pasilor

```
HT_DB_HOST=127.0.0.1:10004 php export_products.php   # 0. produsele fara imagine Figma -> data/products.json
python texts.py                                       # 1. textele RO/RU, culorile, scena -> data/copy.json
python generate.py                                   # 2. fundalurile -> data/backgrounds/<slug>.png
python fit_texts.py                                  # 2b. scurteaza textele care nu incap
python manifest.py                                   # 3. -> data/figma_manifest.json
#                                                    # 4. pluginul Figma construieste cadrele (vezi mai jos)
HT_DB_HOST=127.0.0.1:10004 php upload_to_wp.php      # 5. export JPG din Figma -> imagine reprezentativa RO + RU
HT_DB_HOST=127.0.0.1:10004 php trim_gallery.php      # 6. in galerie ramane doar prima imagine (packshot-ul)
```

Pasii 1 si 2 sunt reluabili si se pot rula pe bucati:

```
python texts.py --limit 3                 # o proba
python texts.py --only slug-1,slug-2
python generate.py --dry-run             # arata prompturile, nu costa nimic
python generate.py --only slug-1 --force # regenereaza un fundal care nu a iesit bine
python generate.py --quality high        # low | medium | high
```

**Citeste `data/copy.json` inainte de pasul 2.** E text simplu: intrebarea,
verbul, substantivul, in ambele limbi, culorile gradientului si scena.
Corecteaza acolo ce nu suna bine; textul ajunge in Figma exact asa.

Cheile din `copy.json` pe produs:

| cheie | rand pe imagine | stil in Figma |
|---|---|---|
| `question_ro` / `question_ru` | 1 | Figtree Regular 43, #302f2f, glow alb |
| `verb_ro` / `verb_ru` | 2 | Figtree Black 102, gradient `color_start` -> `color_end` |
| `noun_ro` / `noun_ru` | 3 | Figtree ExtraBold 83, #302f2f |
| `text_y` (optional) | - | y-ul primului rand; implicit 140 |
| `text_max_w` (optional) | - | latimea maxima a textului; implicit masurata pe fundal |
| `person`, `symptom`, `scene`, `backdrop` | - | intra doar in promptul de imagine |

Asezarea textului se masoara pe fiecare fundal (`manifest.py`, functia `layout`):
intrebarea sta la nivelul fetei, in zona libera din stanga persoanei
(`text_max_w`); verbul si substantivul incep sub barbie (`text_y`), la marimea
originala (102 / 83 px), si pot trece peste umar sau piept ca in cadrele
originale, dar nu peste fata si nu peste produs. Ambele se pot forta in
`copy.json` (`text_y`, `text_max_w`). `fit_texts.py` (pasul 2b) inlocuieste
verbele lungi cu variante scurte fixe si cere AI-ului cuvinte mai scurte doar
pentru intrebarile care nu incap.

## Pasul 4: pluginul Figma

Pluginul e in `figma-plugin/` si se ruleaza local, fara publicare:

1. Deschide fisierul "Toate pozele jpg" in aplicatia Figma (desktop).
2. Meniu > Plugins > Development > **Import plugin from manifest...** si alege
   `figma-plugin/manifest.json`. Se face o singura data.
3. Plugins > Development > "Herbal Therapy - Imagini produse".
4. Alege `data/figma_manifest.json` si toate PNG-urile din `data/backgrounds/`.
5. "Construieste cadrele". Cadrele RO apar pe randul de sus, cele RU pe randul
   de jos, in dreapta celor existente, cate o coloana pe produs.
6. Descarca `figma_nodes.json` si salveaza-l in `data/`. Fara el, pasul 5 nu
   stie ce cadre sa exporte.

Verifica vizual cadrele inainte de pasul 5: maini ciudate, produs deformat,
text peste persoana. Un fundal prost se reface cu `generate.py --only slug --force`,
apoi se sterg cadrele lui din Figma si se ruleaza din nou pluginul doar pentru
el (`manifest.py --only slug`).

## Pasul 4 fara plugin: prin Figma MCP

Pe 2026-09-04 cadrele pentru 23 de produse s-au construit direct din Claude Code,
cu serverul MCP Figma (`use_figma`), fara pluginul de mai sus:

1. `use_figma`: cate un frame 1000x1000 RO (y=358) si RU (y=1469) pe produs, la
   dreapta ultimei coloane (`x = maxRight + 200`, pas 1200), fiecare cu un
   dreptunghi "Fundal".
2. `upload_assets` cu `nodeIds` = dreptunghiurile "Fundal" (cate 2 pe produs),
   apoi POST multipart cu PNG-ul din `data/backgrounds/` pe fiecare `submitUrl`.
3. `use_figma`: cele trei texte, cu exact constantele din `figma-plugin/code.js`
   (font, marimi, gradient, umbre, `text_y` / `text_max_w` din manifest).
4. Verificare: export JPG la scara 0.4 prin API-ul Figma (`/v1/images`) si
   planse de control in `data/preview_new/`.
5. `figma_nodes.json` primeste `slug -> {ro, ru}` (id-urile frame-urilor), apoi
   `upload_to_wp.php` ca de obicei.

## Pasul 5: in WordPress

`upload_to_wp.php` exporta cadrele prin API-ul Figma (JPG, 1000x1000), le urca
in Media si le pune ca imagine reprezentativa pe produsul RO si pe perechea RU.
Atasamentul primeste meta `_ht_figma_node`, la fel ca exporturile de pana acum,
deci `export_products.php` nu-l va mai propune data viitoare. Vechea imagine
reprezentativa (pack-shot-ul) se muta in galeria produsului.

```
HT_DB_HOST=127.0.0.1:10004 php upload_to_wp.php --dry-run
HT_DB_HOST=127.0.0.1:10004 php upload_to_wp.php
```

## Pasul 6: galeria

Pagina de produs arata prezentarea + packshot-ul, atat. `trim_gallery.php`
lasa in galeria fiecarui produs (RO si RU) doar prima imagine si le scoate pe
celelalte (pozele ramase din importul Shopify). Imaginea reprezentativa nu e
atinsa. Valorile vechi ale galeriilor ajung in `data/gallery_backup.json`.
Atasamentele scoase raman in Media; `--delete-media` le sterge pe cele care nu
mai sunt folosite de niciun produs.

```
HT_DB_HOST=127.0.0.1:10004 php trim_gallery.php --dry-run
HT_DB_HOST=127.0.0.1:10004 php trim_gallery.php
HT_DB_HOST=127.0.0.1:10004 php trim_gallery.php --delete-media   # optional
```

## Costuri orientative

| pas | model | pe produs |
|---|---|---|
| texts.py | gpt-4o + poza | ~1 cent |
| generate.py | gpt-image-2, 1024x1024 | low ~1c, medium ~4c, high ~17c |

Pentru ~100 de produse, la calitate medium, tot setul iese sub 5 dolari. Din
experienta, 1 din 3-4 fundaluri se regenereaza (maini, produs deformat), deci
socoteste cam de 1.5 ori.

## Lectii din prima rulare completa (2026-09-03, 104 produse)

- Scenele cu copii pot fi respinse de filtrul de siguranta OpenAI (`moderation_blocked`).
  Solutia: in `copy.json` pune `person` un parinte si `scene` fara copil (ex. mama
  obosita cu un ursulet de plus), apoi `generate.py --only slug --force`.
- Gradientele galbene/pastel ies necitibile pe fundal deschis. `texts.py` reincearca o
  data; ce ramane deschis se intuneca automat la construirea cadrelor (aceeasi regula in
  plugin si in `copy.json`: `color_end` cu luminanta peste 0.45 -> ambele culori inchise).
- Verifica `copy.json` inainte de a construi cadrele: cratime ramase la finalul verbului,
  verbe peste 13 caractere (se micsoreaza), perechi verb + substantiv fara sens.

## Ce nu face

- Nu sterge si nu rescrie cadre existente in Figma.
- Nu traduce singur denumirile: titlul RU vine din Polylang.
- Nu decide singur pozitia textului: e mereu in stanga, la `text_y`; fundalul
  e cerut cu persoana in dreapta. Daca modelul pune persoana in stanga, se
  regenereaza sau se muta textul cu mana in Figma.

## Packshot-urile din galerie (regenerate 2026-09-04)

Galeria fiecarui produs are o singura imagine: packshot-ul (produsul pe alb).
Pana pe 2026-09-04 erau in 11 formate (2000x2000, 1254x1254, poze de telefon
in portret 1536x2048, 471x530...), unele neclare. Clientul a cerut toate la
1000x1000, calitate buna, cu produsul. Trei scripturi, dupa acelasi tipar:

```
HT_DB_HOST=127.0.0.1:10004 php export_gallery.php     # A. galeria curenta -> data/gallery_products.json
python packshots.py --workers 5                       # B. gpt-image-2 -> data/packshots/<slug>.jpg (1000x1000)
python packshots.py --sheet                           #    plansa vechi/nou: data/packshots/sheet.png
HT_DB_HOST=127.0.0.1:10004 php upload_packshots.php --dry-run
HT_DB_HOST=127.0.0.1:10004 php upload_packshots.php   # C. in Media + galeria RO si RU
```

`packshots.py` trimite modelului poza curenta din galerie (`images.edit`) si
cere acelasi produs, cu acelasi ambalaj, ca packshot de studio: centrat, eticheta
spre camera, fundal alb, umbra moale. Cost real masurat (edit cu poza de
referinta, 1024x1024): **high ~22 centi**, **medium ~6 centi** pe imagine, mai
mult decat tabelul de la generate.py. Etichetele ies lizibile si la medium, de
aceea medium e implicit. Setul din 2026-09-04 e mixt: 95 de produse la high
(inclusiv proba), restul la medium, dupa ce clientul a oprit high la 22 EUR. PNG-ul brut 1024x1024 ramane in `data/packshots/raw/`; JPG-ul
final e adus la 1000x1000 (Lanczos, alb, calitate 92). O imagine dureaza ~2
minute, de aceea `--workers`.

Borcanasele mici (unguente 20 ml, balsam de buze, vaselina) ies implicit cu
capacul vazut de sus; clientul a vrut borcanul deschis cu capacul rezemat, ca in
pozele vechi. Pentru ele: `python packshots.py --variant open-jar --only slug --force`
(lista folosita pe 2026-09-04: `data/packshots/open_jar.txt`, 18 produse).

Verifica plansa inainte de pasul C: produs taiat de margine (blisterele inalte),
eticheta rescrisa, forma schimbata. Un packshot prost se reface cu
`python packshots.py --only slug --force`.

`upload_packshots.php` urca JPG-ul o singura data (atasament RO, meta
`_ht_packshot_src` = id-ul pozei vechi) si il pune ca singura imagine din
galeria produsului RO si a perechii RU. Imaginea reprezentativa (coperta /
bannerul promo) nu e atinsa. Galeriile vechi ajung in `data/packshots_backup.json`;
`--restore` le pune la loc. Pozele vechi raman in Media si se pot sterge apoi cu
`bin/media-orphans.php`.

Produsul "Vitamina C 500 mg N60" (#366) nu are nicio imagine in galerie, deci
nu are de la ce porni: ii trebuie o poza de produs de la client.

## Bannere promotionale (fisierul Figma "Herbal Promo", `Evz4dnqHOEcCAt7Sue94UT`)

Pe durata unei promotii, coperta obisnuita e inlocuita cu un banner = aceeasi
coperta + un abtibild cu procentul reducerii, dupa modelul dat de client
(`data/promo/reference/`): doua panglici rosii cu margini zimtate (vector
generat, gradient #F02E29 -> #B80A0D, umbra 0/10/24 la 28%), rotite 4°,
cu "REDUCERE" / "СКИДКА" (Figtree Black 46, spatiere 3) sus si "-50%" /
"-33%" (Figtree Black 136) jos, alb cu umbra. Latime 340, inaltime 250;
sta in coltul cel mai liber al copertii (`data/promo/badge_pos.json`:
BL = 40/700, TL = 40/40, BR = 620/700). Constantele sunt in scriptul
`use_figma` folosit pe 2026-09-04 (vezi mai jos); textul se schimba acolo.

Pana pe 2026-09-04 abtibildul era un cerc "PROMOTIE 1+1 GRATIS"; a fost
inlocuit pe toate cele 110 cadre cand clientul a cerut procentul.

Cum s-a facut (55 produse; lista de preturi in `data/promo/sale_prices.csv`,
export din `HTL_reduceri_procentuale.xlsx`):

1. Randurile din CSV se potrivesc cu produsele DUPA SKU (`data/promo/sku_pid.json`),
   nu dupa nume: la prima runda potrivirea dupa nume a pus bannerul pe
   "Vitamina C Propolis si Echinacea N60" (#1759, fara zinc) in loc de
   "Propolis, Echinacea si Zinc N60" (#340, SKU 4840257007362). Produsele din
   cos (Calciu-Farmaco N10, Alcool mentolat) se sar.
2. Coperta RO si RU se exporta PNG 1000x1000 din "Toate pozele jpg"
   (`/v1/images`, dupa `_ht_figma_node` al thumbnail-ului) in `data/promo/covers/`.
3. In "Herbal Promo": un frame pe produs si limba (RO y=200, RU y=1400, coloane
   de 1200) cu un dreptunghi "Coperta"; PNG-urile intra prin `upload_assets` cu
   `nodeIds`; abtibildul "Promo -50%" se construieste cu `use_figma` in coltul
   din `badge_pos.json`. Numele frame-ului se termina in ` -50% ro` / ` -50% ru`.
4. `data/promo/promo_nodes.json` (slug -> {ro, ru, id, ru_id, promo, pct}), apoi:

```
HT_DB_HOST=127.0.0.1:10004 php promo_prices.php --dry-run
HT_DB_HOST=127.0.0.1:10004 php promo_prices.php           # pune _sale_price din CSV (RO + RU)
HT_DB_HOST=127.0.0.1:10004 php promo_to_wp.php --dry-run
HT_DB_HOST=127.0.0.1:10004 php promo_to_wp.php            # pune bannerele
HT_DB_HOST=127.0.0.1:10004 php promo_to_wp.php --force    # dupa modificari in Figma
```

Dupa promotie:

```
HT_DB_HOST=127.0.0.1:10004 php promo_prices.php --restore
HT_DB_HOST=127.0.0.1:10004 php promo_to_wp.php --restore [--delete-promo]
```

`promo_prices.php` pune pretul de reducere prin `WC_Product::set_sale_price()`
(se refac `_price` si tabelele de cautare); Polylang copiaza pretul si pe
perechea RU, deci in `data/promo/prices_backup.json` apar doar produsele RO.
Daca pretul obisnuit din site difera de cel din CSV, produsul e sarit si
raportat, nu suprascris.

Coperta veche a fiecarui produs ramane in `data/promo/promo_backup.json`
(NU se sterge, nu se muta in galerie); `--restore` o pune la loc. Bannerele au
meta `_ht_promo_node`, deci `export_products.php` nu le confunda cu copertile.
