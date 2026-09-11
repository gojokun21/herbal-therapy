# Traducerile temei

Domeniul de text al temei este **`herbal-therapy`**, declarat in `style.css`
(`Text Domain` + `Domain Path: /languages`) si incarcat din `inc/setup.php` cu
`load_theme_textdomain()`. Limba sursa a sirurilor din cod este romana.

## Sablonul

`herbal-therapy.pot` se genereaza din surse cu:

```
php bin/make-pot.php
```

Scriptul citeste toate fisierele `.php` ale temei (fara `bin/`, `vendor/`,
`node_modules/`, `acf-json/`), extrage apelurile gettext cu domeniul temei -
inclusiv contextele (`_x`) si formele de plural (`_n`) - impreuna cu
comentariile `translators:` si cu antetul din `style.css`. Nu are nevoie de
WP-CLI si nici de WordPress incarcat.

Ruleaza-l dupa fiecare adaugare de siruri noi si comite `.pot`-ul rezultat.
Daca un sir nu poate fi extras (text construit din variabile sau concatenari),
scriptul il raporteaza la final, cu fisier si linie.

## Traducerile propriu-zise

**Atentie la denumire - difera de conventia pluginurilor.** Pentru fisierele
care stau in directorul temei, WordPress cauta doar `<locale>.mo`, fara prefixul
domeniului:

- `ru_RU.po` / `ru_RU.mo`
- `en_US.po` / `en_US.mo`

Forma `herbal-therapy-<locale>.mo` este valabila **numai** in
`wp-content/languages/themes/` (acolo ajung traducerile descarcate din
wordpress.org). In `wp-content/themes/herbal-therapy/languages/` un fisier
numit asa **nu se incarca**.

Regula e in `_load_textdomain_just_in_time()` din `wp-includes/l10n.php`:

```php
// Themes with their language directory outside of WP_LANG_DIR have a different file name.
if ( str_starts_with( $path, $template_directory ) || str_starts_with( $path, $stylesheet_directory ) ) {
    $mofile = "{$path}{$locale}.mo";      // <- cazul nostru
} else {
    $mofile = "{$path}{$domain}-{$locale}.mo";
}
```

Fisierele se creeaza din `.pot` cu Poedit sau din administrare cu Loco Translate.
`.mo` este cel citit de WordPress; `.po` se pastreaza pentru editari ulterioare.
Incepand cu WP 6.8 se poate genera si un `<locale>.l10n.php` alaturi de `.mo` -
e doar o optimizare, `.mo` singur functioneaza.

## Polylang

Polylang **nu scaneaza codul temei** si nu citeste `.pot`-ul. Panoul
*Limbi -> Traduceri siruri* listeaza exclusiv ce s-a inregistrat cu
`pll_register_string()`, plus cateva implicite (titlul si descrierea site-ului,
formatele de data, titlurile de widget) - vezi `PLL_Admin_Strings::get_strings()`.

Ca sa apara si sirurile temei acolo, `inc/i18n.php` face doua lucruri:

1. inregistreaza inventarul din `inc/i18n-strings.php`, grupat pe sectiuni
   ("Herbal Therapy: Cos", "Herbal Therapy: Produs" etc.);
2. leaga cele doua domenii printr-un filtru `gettext` / `ngettext`.

Al doilea pas e obligatoriu si e cel mai usor de ratat. Traducerile din panou se
salveaza in domeniul `pll_string`, iar `pll__()` nu e altceva decat
`__( $string, 'pll_string' )` (`api.php:204`). Polylang **nu instaleaza niciun
filtru gettext**, deci fara punte panoul s-ar umple, clientul ar traduce, si
frontendul ar ramane neschimbat - o eroare tacuta, greu de observat cand limba
sursa e chiar romana.

Ordinea de precedenta:

```
panoul Polylang  >  languages/<locale>.mo  >  textul din cod
```

Inventarul se regenereaza din surse:

```
php bin/generate-i18n-strings.php
```

Ruleaza-l odata cu `bin/make-pot.php` dupa fiecare adaugare de siruri; amandoua
folosesc aceeasi extragere (`bin/lib-i18n-extract.php`), deci arata mereu
aceleasi texte.

### Seed-ul traducerilor

Traducerile rusesti ale intregului inventar sunt versionate in
`bin/seed-i18n-content.php` si se scriu in baza de date cu:

```
HT_DB_HOST=127.0.0.1:10004 php bin/seed-i18n.php
```

Scriptul foloseste acelasi depozit ca panoul (`PLL_MO`, term meta
`_pll_strings_translations`), deci rezultatul apare in *Limbi -> Traduceri
siruri* si poate fi corectat de acolo. Implicit completeaza doar sirurile
netraduse (ce a modificat clientul in panou ramane); `--force` suprascrie tot,
`--dry-run` doar arata. Dupa regenerarea inventarului, seed-ul raporteaza
sirurile noi fara traducere si traducerile ramase fara sir sursa (msgid
schimbat) - adauga perechile lipsa in `bin/seed-i18n-content.php` si ruleaza-l
din nou.

### Limita cunoscuta: pluralele

Panoul Polylang lucreaza cu siruri simple, nu cu perechi singular/plural.
Inventarul inregistreaza ambele forme separat, iar puntea traduce forma pe care
a ales-o WordPress - dar alegerea se face dupa regulile limbii **sursa** (romana,
3 forme). Pentru rusa, care imparte pluralul altfel, traducerea corecta vine din
`.po`/`.mo`, unde formele sunt tratate cum trebuie. De aceea puntea lasa `.mo`
sa castige atunci cand acesta a returnat deja ceva.

Pe scurt: textele simple - din panou; pluralele (`_n()`) - din `ru_RU.po`.

Ce nu trece prin gettext se traduce in alta parte:

- continutul paginilor si campurile ACF ale acestora (`acf-json/`) - se traduc
  pe fiecare traducere de pagina, in editor;
- meniurile, widget-urile si titlul/descrierea site-ului - din Polylang;
- daca pe viitor apar siruri salvate in optiuni (setari de tema), acelea se
  inregistreaza cu `pll_register_string()` ca sa apara in
  *Limbi -> Traduceri siruri*.

Textele afisate din JavaScript vin din atribute `data-*` scrise in sabloanele
PHP, deci sunt deja traduse cand ajung in pagina; nu e nevoie de
`wp_set_script_translations()` cat timp nu se apeleaza `wp.i18n` din scripturi.
