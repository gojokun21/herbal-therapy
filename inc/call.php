<?php
/**
 * Butonul plutitor de apel telefonic.
 *
 * O legatura tel: in stiva din coltul din dreapta-jos (inc/floating.php),
 * deasupra celei de WhatsApp: o bulina gri cu receptor, care deschide direct
 * apelul; numarul ramane in aria-label, pentru cititoarele de ecran.
 *
 * Numarul vine din Setari generale (campul "Numar de telefon pentru apel")
 * sau, cand acesta e gol, din randul cu telefon al paginii de contact. Fara
 * niciun numar butonul nu apare.
 *
 * Normalizarea numarului (prefix de tara, "00", spatii) e aceeasi ca la
 * WhatsApp - ht_whatsapp_digits() - ca ambele butoane sa accepte numarul scris
 * in oricare forma.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Functii pure - testate in tests/CallTest.php
 * ------------------------------------------------------------------------ */

/**
 * Adresa de apel, in forma E.164 ceruta de schema tel: ("tel:+37378884061").
 *
 * @param string $raw Numarul, in orice forma; se curata cu ht_whatsapp_digits().
 *
 * @return string Sir gol cand nu ramane niciun numar plauzibil.
 */
function ht_call_href($raw)
{
    $digits = ht_whatsapp_digits($raw);

    return '' === $digits ? '' : 'tel:+' . $digits;
}

/**
 * Textul afisat pentru un numar: ce a scris administratorul, fara "tel:"
 * si fara spatii de prisos; cand e gol, forma internationala din cifre.
 *
 * @param string $raw    Numarul asa cum a fost scris.
 * @param string $digits Cifrele deja curatate, pentru varianta de rezerva.
 *
 * @return string
 */
function ht_call_display($raw, $digits = '')
{
    $text = trim(preg_replace('/^\s*tel:/i', '', (string)$raw));

    if ('' !== $text) {
        return preg_replace('/\s+/', ' ', $text);
    }

    return '' === $digits ? '' : '+' . $digits;
}

/* ---------------------------------------------------------------------------
 * Datele
 * ------------------------------------------------------------------------ */

/**
 * Numarul de apel si textul lui, asa cum sunt scrise in administrare.
 *
 * Ordinea: campul "Numar de telefon pentru apel" din Setari generale (pagina
 * de optiuni ACF, comuna tuturor limbilor), apoi randul cu telefon din
 * cartonasul de contact - legatura tel: pentru apel, textul afisat pentru
 * eticheta.
 *
 * @return array{number: string, text: string} Ambele goale cand nu exista
 *                                              niciun numar.
 */
function ht_call_source()
{
    $number = function_exists('get_field')
        ? trim((string)get_field('ht_call_number', 'option'))
        : '';
    $text = $number;

    if ('' === $number) {
        $phone = ht_floating_contact_phone();
        $number = '' !== $phone['href'] ? $phone['href'] : $phone['text'];
        $text = $phone['text'];
    }

    return array(
        'number' => (string)apply_filters('ht_call_number', $number),
        'text'   => (string)apply_filters('ht_call_text', $text),
    );
}

/**
 * Se afiseaza butonul pe pagina curenta?
 *
 * Lipseste in administrare si acolo unde nu exista numar valid. Filtrul
 * 'ht_call_enabled' il poate scoate de pe anumite pagini.
 *
 * @return bool
 */
function ht_call_enabled()
{
    if (is_admin()) {
        return false;
    }

    $source = ht_call_source();

    return (bool)apply_filters('ht_call_enabled', '' !== ht_call_href($source['number']));
}

/* ---------------------------------------------------------------------------
 * Afisarea
 * ------------------------------------------------------------------------ */

/**
 * Markup-ul butonului.
 *
 * @return string Sir gol cand butonul nu se afiseaza.
 */
function ht_call_button()
{
    if (!ht_call_enabled()) {
        return '';
    }

    $source = ht_call_source();
    $href = ht_call_href($source['number']);

    if ('' === $href) {
        return '';
    }

    $display = ht_call_display($source['text'], substr($href, 5));

    return ht_floating_button(
        'call',
        $href,
        'phone-fill',
        $display,
        sprintf(
            /* translators: %s: numarul de telefon afisat. */
            __('Sună-ne la %s', 'herbal-therapy'),
            $display
        )
    );
}

/**
 * Pune butonul in stiva plutitoare, deasupra celui de WhatsApp.
 *
 * @param array<string, string> $buttons Butoanele adunate pana acum.
 *
 * @return array<string, string>
 */
function ht_call_floating_button(array $buttons)
{
    $buttons['call'] = ht_call_button();

    return $buttons;
}

add_filter('ht_floating_buttons', 'ht_call_floating_button', 10);
