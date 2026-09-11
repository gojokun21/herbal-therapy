<?php
/**
 * Traduceri: inventarul de siruri in Polylang, peste gettext-ul obisnuit.
 *
 * Tema isi scrie textele normal, cu __() / _e() / esc_html_e() si domeniul
 * 'herbal-therapy'. Modulul acesta adauga o a doua cale de traducere, pentru
 * clientii care prefera sa lucreze din administrare:
 *
 *  1. inregistreaza sirurile din inc/i18n-strings.php cu pll_register_string(),
 *     ca sa apara in Limbi -> Traduceri siruri, grupate pe sectiuni;
 *  2. leaga cele doua domenii: ce e tradus in panoul Polylang ajunge sa fie
 *     returnat si de __( ..., 'herbal-therapy' ), fara sa schimbam apelurile.
 *
 * Puntea e necesara pentru ca Polylang nu filtreaza gettext-ul. Traducerile din
 * panou se salveaza in domeniul 'pll_string' si se citesc doar prin pll__() -
 * vezi pll__() din api.php, care e mai exact __( $string, 'pll_string' ). Fara
 * puntea de mai jos, panoul s-ar umple frumos, iar frontendul ar ramane neschimbat.
 *
 * Ordinea de precedenta:
 *
 *   panoul Polylang  >  languages/<locale>.mo  >  textul din cod
 *
 * Asa clientul poate corecta din administrare orice text, iar traducerile
 * livrate cu tema (.po/.mo) raman ca baza si ca rezerva daca Polylang lipseste.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Domeniul de text al temei.
 */
const HT_TEXT_DOMAIN = 'herbal-therapy';

/**
 * Inventarul de siruri, pe sectiuni.
 *
 * @return array Sectiune => lista de siruri.
 */
function ht_i18n_strings()
{
    static $strings = null;

    if (null === $strings) {
        $file    = HT_DIR . '/inc/i18n-strings.php';
        $strings = is_readable($file) ? (array)require $file : array();
    }

    return $strings;
}

/* ---------------------------------------------------------------------------
 * Inregistrarea in Polylang
 * ------------------------------------------------------------------------ */

/**
 * Trece inventarul prin pll_register_string().
 *
 * Se face doar in administrare: pll_register_string() oricum nu are efect in
 * frontend (verifica PLL() instanceof PLL_Admin_Base), iar asa nu incarcam
 * inventarul degeaba la fiecare cerere publica.
 *
 * @return void
 */
function ht_i18n_register_strings()
{
    if (!is_admin() || !function_exists('pll_register_string')) {
        return;
    }

    foreach (ht_i18n_strings() as $section => $list) {
        $context = 'Herbal Therapy: ' . $section;

        foreach ((array)$list as $string) {
            if (!is_string($string) || '' === $string) {
                continue;
            }

            /* textele lungi sau pe mai multe randuri primesc textarea in panou */
            $multiline = (false !== strpos($string, "\n")) || (mb_strlen($string) > 80);

            pll_register_string(ht_i18n_string_name($string), $string, $context, $multiline);
        }
    }
}

add_action('init', 'ht_i18n_register_strings');

/**
 * Eticheta din coloana "Nume" a panoului.
 *
 * Polylang indexeaza sirurile dupa md5(text), deci numele e doar pentru ochi;
 * il scurtam ca tabelul sa ramana citibil.
 *
 * @param string $string Sirul.
 *
 * @return string
 */
function ht_i18n_string_name($string)
{
    $name = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($string)));

    if (mb_strlen($name) > 48) {
        $name = mb_substr($name, 0, 47) . '…';
    }

    return $name;
}

/* ---------------------------------------------------------------------------
 * Puntea intre domeniul temei si traducerile din panou
 * ------------------------------------------------------------------------ */

/**
 * Traducerea din panoul Polylang pentru un text, daca exista.
 *
 * @param string $text Textul din cod.
 *
 * @return string|null Traducerea, sau null daca nu e tradus in panou.
 */
function ht_i18n_from_polylang($text)
{
    if (!function_exists('pll__') || !is_string($text) || '' === $text) {
        return null;
    }

    $translated = pll__($text);

    return ($translated !== $text && '' !== $translated) ? $translated : null;
}

/**
 * __() si _e().
 *
 * @param string $translation Traducerea gasita in .mo (sau textul original).
 * @param string $text        Textul din cod.
 * @param string $domain      Domeniul.
 *
 * @return string
 */
function ht_i18n_filter_gettext($translation, $text, $domain)
{
    if (HT_TEXT_DOMAIN !== $domain) {
        return $translation;
    }

    $from_panel = ht_i18n_from_polylang($text);

    return (null !== $from_panel) ? $from_panel : $translation;
}

add_filter('gettext', 'ht_i18n_filter_gettext', 10, 3);

/**
 * _x() si _ex().
 *
 * Panoul Polylang nu cunoaste contexte gettext, deci doua texte identice cu
 * contexte diferite ar primi aceeasi traducere. Tema nu foloseste _x() acum;
 * filtrul e pus ca sa nu ramana o gaura daca apare mai tarziu.
 *
 * @param string $translation Traducerea gasita in .mo.
 * @param string $text        Textul din cod.
 * @param string $context     Contextul.
 * @param string $domain      Domeniul.
 *
 * @return string
 */
function ht_i18n_filter_gettext_with_context($translation, $text, $context, $domain)
{
    return ht_i18n_filter_gettext($translation, $text, $domain);
}

add_filter('gettext_with_context', 'ht_i18n_filter_gettext_with_context', 10, 4);

/**
 * _n() si _nx().
 *
 * Panoul Polylang lucreaza cu siruri simple, nu cu perechi singular/plural, deci
 * inventarul are ambele forme inregistrate separat. Aici traducem forma pe care
 * WordPress a ales-o.
 *
 * Atentie: alegerea formei se face dupa regulile limbii sursa (romana, 3 forme).
 * Pentru limbi cu alta impartire a pluralului - rusa, de exemplu - traducerea
 * corecta se obtine din .po/.mo, unde formele sunt tratate cum trebuie. De aceea
 * lasam .mo sa castige atunci cand a avut deja ceva de spus.
 *
 * @param string $translation Traducerea gasita in .mo (sau forma din cod).
 * @param string $single      Forma de singular din cod.
 * @param string $plural      Forma de plural din cod.
 * @param int    $number      Numarul.
 * @param string $domain      Domeniul.
 *
 * @return string
 */
function ht_i18n_filter_ngettext($translation, $single, $plural, $number, $domain)
{
    if (HT_TEXT_DOMAIN !== $domain) {
        return $translation;
    }

    /* daca traducerea difera de ambele forme din cod, vine din .mo: o pastram,
     * fiindca acolo pluralul e rezolvat dupa regulile limbii tinta */
    if ($translation !== $single && $translation !== $plural) {
        return $translation;
    }

    $from_panel = ht_i18n_from_polylang($translation);

    return (null !== $from_panel) ? $from_panel : $translation;
}

add_filter('ngettext', 'ht_i18n_filter_ngettext', 10, 5);

/**
 * _nx().
 *
 * @param string $translation Traducerea gasita in .mo.
 * @param string $single      Forma de singular.
 * @param string $plural      Forma de plural.
 * @param int    $number      Numarul.
 * @param string $context     Contextul.
 * @param string $domain      Domeniul.
 *
 * @return string
 */
function ht_i18n_filter_ngettext_with_context($translation, $single, $plural, $number, $context, $domain)
{
    return ht_i18n_filter_ngettext($translation, $single, $plural, $number, $domain);
}

add_filter('ngettext_with_context', 'ht_i18n_filter_ngettext_with_context', 10, 6);
