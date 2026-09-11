<?php
/**
 * Butonul plutitor de WhatsApp.
 *
 * O legatura in stiva din coltul din dreapta-jos (inc/floating.php), sub cea
 * de apel telefonic, care deschide o conversatie pe WhatsApp cu un mesaj
 * pregatit dinainte. Pe pagina unui produs mesajul numeste produsul, ca
 * vanzatorul sa stie din prima despre ce e vorba.
 *
 * Numarul vine din Setari generale (campul "Numar WhatsApp") sau, cand acesta
 * e gol, din randul cu telefon al paginii de contact. Asa numarul se schimba
 * dintr-un singur loc si butonul dispare singur cat timp nu exista niciun numar.
 *
 * Butonul nu are JavaScript: e un <a> stilat in assets/css/floating.css.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Functii pure - testate in tests/WhatsappTest.php
 * ------------------------------------------------------------------------ */

/**
 * Prefixul de tara adaugat numerelor scrise local ('078 88 40 61').
 *
 * @return string Doar cifre; '' opreste completarea.
 */
function ht_whatsapp_country_code()
{
    return (string)apply_filters('ht_whatsapp_country_code', '373');
}

/**
 * Numarul in forma ceruta de wa.me: doar cifre, cu prefixul de tara, fara
 * "+", spatii, paranteze sau "00" in fata.
 *
 * Un numar scris local, cu 0 in fata ('078884061'), primeste prefixul din
 * ht_whatsapp_country_code() in locul zeroului.
 *
 * @param string $raw Numarul asa cum a fost scris ('+373 78 88 40 61', 'tel:+373...').
 *
 * @return string Sir gol cand nu ramane niciun numar plauzibil.
 */
function ht_whatsapp_digits($raw)
{
    $digits = preg_replace('/\D+/', '', (string)$raw);

    if (0 === strpos($digits, '00')) {
        /* "00" e prefixul international scris la telefonul fix; wa.me nu il vrea */
        $digits = substr($digits, 2);
    } elseif (0 === strpos($digits, '0') && '' !== ht_whatsapp_country_code()) {
        /* numar local: zeroul din fata tine locul prefixului de tara */
        $digits = ht_whatsapp_country_code() . substr($digits, 1);
    }

    /* sub 8 cifre nu e un numar international; peste 15 e limita E.164 */
    $length = strlen($digits);

    if ($length < 8 || $length > 15) {
        return '';
    }

    return $digits;
}

/**
 * Adresa conversatiei.
 *
 * @param string $number Numarul, in orice forma; se curata cu ht_whatsapp_digits().
 * @param string $text   Mesajul pregatit dinainte; '' => fara mesaj.
 *
 * @return string Sir gol cand numarul nu e valid.
 */
function ht_whatsapp_url($number, $text = '')
{
    $digits = ht_whatsapp_digits($number);

    if ('' === $digits) {
        return '';
    }

    $url = 'https://wa.me/' . $digits;
    $text = trim((string)$text);

    if ('' !== $text) {
        $url .= '?text=' . rawurlencode($text);
    }

    return $url;
}

/* ---------------------------------------------------------------------------
 * Datele
 * ------------------------------------------------------------------------ */

/**
 * Numarul de WhatsApp, asa cum e scris in administrare.
 *
 * Ordinea: campul "Numar WhatsApp" din Setari generale (pagina de optiuni
 * ACF, comuna tuturor limbilor), apoi randul cu telefon din cartonasul de
 * contact (legatura tel: sau textul afisat).
 *
 * @return string Sir gol cand nu exista niciun numar.
 */
function ht_whatsapp_number()
{
    $number = function_exists('get_field')
        ? (string)get_field('ht_whatsapp_number', 'option')
        : '';

    if ('' === trim($number)) {
        $phone = ht_floating_contact_phone();
        $number = '' !== $phone['href'] ? $phone['href'] : $phone['text'];
    }

    return (string)apply_filters('ht_whatsapp_number', trim($number));
}

/**
 * Mesajul cu care se deschide conversatia.
 *
 * Pe pagina unui produs numeste produsul si pune si adresa lui, ca sa se poata
 * deschide dintr-un clic de pe telefon.
 *
 * @return string
 */
function ht_whatsapp_text()
{
    $text = __('Bună ziua! Aș dori mai multe informații despre produsele Herbal Therapy.', 'herbal-therapy');

    if (function_exists('is_product') && is_product()) {
        $text = sprintf(
            /* translators: 1: numele produsului, 2: adresa paginii produsului. */
            __('Bună ziua! Aș dori mai multe informații despre produsul „%1$s”: %2$s', 'herbal-therapy'),
            wp_strip_all_tags(get_the_title()),
            get_permalink()
        );
    }

    return (string)apply_filters('ht_whatsapp_text', $text);
}

/**
 * Se afiseaza butonul pe pagina curenta?
 *
 * Lipseste in administrare si acolo unde nu exista numar. Filtrul
 * 'ht_whatsapp_enabled' il poate scoate de pe anumite pagini (ex. checkout).
 *
 * @return bool
 */
function ht_whatsapp_enabled()
{
    if (is_admin()) {
        return false;
    }

    return (bool)apply_filters('ht_whatsapp_enabled', '' !== ht_whatsapp_number());
}

/* ---------------------------------------------------------------------------
 * Afisarea
 * ------------------------------------------------------------------------ */

/**
 * Markup-ul butonului.
 *
 * @return string Sir gol cand butonul nu se afiseaza.
 */
function ht_whatsapp_button()
{
    if (!ht_whatsapp_enabled()) {
        return '';
    }

    $url = ht_whatsapp_url(ht_whatsapp_number(), ht_whatsapp_text());

    if ('' === $url) {
        return '';
    }

    $label = __('Scrie-ne pe WhatsApp', 'herbal-therapy');

    return ht_floating_button('whatsapp', $url, 'whatsapp', $label, $label, array(
        'target' => '_blank',
        'rel'    => 'noopener',
    ));
}

/**
 * Pune butonul in stiva plutitoare, sub cel de apel telefonic.
 *
 * @param array<string, string> $buttons Butoanele adunate pana acum.
 *
 * @return array<string, string>
 */
function ht_whatsapp_floating_button(array $buttons)
{
    $buttons['whatsapp'] = ht_whatsapp_button();

    return $buttons;
}

add_filter('ht_floating_buttons', 'ht_whatsapp_floating_button', 20);
