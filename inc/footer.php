<?php
/**
 * Datele din subsolul paginii.
 *
 * Coloanele de linkuri vin din meniul 'footer-menu': elementele de nivel 1 dau
 * titlurile coloanelor (nu sunt clicabile), copiii lor dau linkurile. Datele de
 * contact si conturile de retele sociale vin de pe pagina de contact, ca sa
 * existe un singur loc unde se schimba. Restul - metodele de plata, copyright -
 * se suprascriu prin filtre, la fel ca slide-urile din hero.
 *
 * Nimic de aici nu are date de rezerva: cat timp o sursa e goala, blocul ei nu
 * se afiseaza. Continutul initial se scrie cu `php bin/seed.php`.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coloanele de linkuri: cele din meniul 'footer-menu', apoi coloana "Legal"
 * cu termenii si politica de confidentialitate (vezi ht_footer_legal()).
 *
 * Cat timp meniul nu e setat, raman doar coloana "Legal" (daca are pagini).
 * Se suprascrie complet cu: add_filter('ht_footer_columns', ...)
 *
 * @return array Fiecare coloana: 'title' + 'links' (label, url, target, rel, title).
 */
function ht_footer_columns()
{
    $columns = array();
    $locations = get_nav_menu_locations();

    if (!empty($locations['footer-menu'])) {
        $items = wp_get_nav_menu_items($locations['footer-menu']);

        if ($items) {
            /* grupam copiii pe parinte ca sa parcurgem meniul o singura data */
            $children = array();

            foreach ($items as $item) {
                if ($item->menu_item_parent) {
                    $children[(int)$item->menu_item_parent][] = $item;
                }
            }

            foreach ($items as $item) {
                /* doar nivelul 1 deschide o coloana */
                if ($item->menu_item_parent) {
                    continue;
                }

                if (empty($children[$item->ID])) {
                    /* titlu fara linkuri - nu are ce afisa */
                    continue;
                }

                $links = array();

                foreach ($children[$item->ID] as $child) {
                    $links[] = array(
                        'label'  => $child->title,
                        'url'    => $child->url,
                        'target' => $child->target,
                        'rel'    => $child->xfn,
                        'title'  => $child->attr_title,
                    );
                }

                $columns[] = array(
                    'title' => $item->title,
                    'links' => $links,
                );
            }
        }
    }

    /* dupa coloanele din meniu vine coloana "Legal", construita din setari */
    $legal = ht_footer_legal();

    if ($legal) {
        $links = array();

        foreach ($legal as $link) {
            $links[] = array(
                'label'  => $link['label'],
                'url'    => $link['url'],
                'target' => '',
                'rel'    => '',
                'title'  => '',
            );
        }

        $columns[] = array(
            'title' => __('Legal', 'herbal-therapy'),
            'links' => $links,
        );
    }

    return apply_filters('ht_footer_columns', $columns);
}

/**
 * Datele de contact afisate langa retelele sociale.
 *
 * Vin din cartonasul paginii de contact: randul cu telefon da textul si
 * legatura, randul cu program da randul gri de dedesubt. Asa numarul se schimba
 * o singura data, din Contact, si se vede in tot site-ul.
 *
 * Fiecare intrare are:
 *   'label'    - textul vizibil (de regula numarul de telefon)
 *   'href'     - link-ul ('tel:...', 'mailto:...'); '' => text simplu
 *   'schedule' - randul gri de dedesubt; '' => nu se afiseaza
 *
 * @return array Gol cat timp pagina de contact nu e completata.
 */
function ht_footer_contacts()
{
    $contacts = array();
    $methods = function_exists('ht_contact_methods') ? ht_contact_methods() : array();
    $schedule = '';

    /* programul insoteste numarul, deci il citim inainte */
    foreach ($methods as $method) {
        if ('clock' === $method['icon']) {
            $schedule = implode(', ', $method['lines']);

            break;
        }
    }

    foreach ($methods as $method) {
        if ('phone' !== $method['icon']) {
            continue;
        }

        $contacts[] = array(
            'label'    => $method['lines'][0],
            'href'     => $method['href'],
            'schedule' => $schedule,
        );
    }

    return apply_filters('ht_footer_contacts', $contacts);
}

/**
 * Retelele sociale.
 *
 * Conturile se tin pe pagina de contact, in campul "Conturile de retele
 * sociale". Intrarile fara adresa nu se afiseaza, asa scoti o retea fara sa
 * strici lista. 'icon' este numele iconitei din ht_icons().
 *
 * @return array Gol cat timp niciun cont nu e completat.
 */
function ht_footer_socials()
{
    $socials = array();
    $rows = function_exists('ht_contact_socials') ? ht_contact_socials() : array();

    foreach ($rows as $row) {
        $socials[] = array(
            'icon'  => $row['icon'],
            'label' => $row['label'],
            'url'   => $row['url'],
        );
    }

    return apply_filters('ht_footer_socials', $socials);
}

/**
 * Logourile metodelor de plata.
 *
 * Implicit: o singura imagine cu Visa, Mastercard si American Express
 * (cards.png, cerut pe 2026-09-11). Fisierele stau in /assets/img/payments/;
 * tot acolo raman si logourile separate (visa.svg, mastercard.svg,
 * maestro.svg), pentru cine vrea cate o intrare de fiecare:
 *
 *   add_filter('ht_footer_payment_files', function () {
 *       return array('Visa' => 'visa.svg', 'Mastercard' => 'mastercard.svg');
 *   });
 *
 * Cheia e eticheta (alt-ul imaginii), valoarea - numele fisierului. Intrarile
 * fara fisier pe disc sunt ignorate.
 *
 * @return array Fiecare intrare: 'label' + 'src'.
 */
function ht_footer_payments()
{
    $files = apply_filters('ht_footer_payment_files', array(
        'Visa, Mastercard, American Express' => 'cards.png',
    ));

    $payments = array();

    foreach ($files as $label => $file) {
        if (!file_exists(HT_DIR . '/assets/img/payments/' . $file)) {
            continue;
        }

        $payments[] = array(
            'label' => $label,
            'src'   => HT_URI . '/assets/img/payments/' . $file,
        );
    }

    return apply_filters('ht_footer_payments', $payments);
}

/**
 * ID-ul paginii cu termenii si conditiile.
 *
 * Se alege din Setari generale; cand nu e aleasa, cade pe pagina de termeni
 * din WooCommerce (Setari -> Avansat), daca e setata acolo.
 *
 * @return int 0 cand nu exista nicio pagina.
 */
function ht_footer_terms_page_id()
{
    $id = function_exists('get_field') ? (int)get_field('ht_terms_page', 'option') : 0;

    if (!$id && function_exists('wc_terms_and_conditions_page_id')) {
        $id = (int)wc_terms_and_conditions_page_id();
    }

    return (int)apply_filters('ht_footer_terms_page_id', $id);
}

/**
 * Legaturile din coloana "Legal": termenii si conditiile si politica de
 * confidentialitate.
 *
 * Politica vine din Setari -> Confidentialitate (aceeasi pagina o foloseste si
 * bifa din formularul de contact). Fiecare pagina se ia in limba curenta cand
 * are traducere; altfel ramane originalul, ca legatura sa nu dispara.
 *
 * @return array Fiecare intrare are 'label' (titlul paginii) si 'url'.
 */
function ht_footer_legal()
{
    $ids = array(
        ht_footer_terms_page_id(),
        (int)get_option('wp_page_for_privacy_policy'),
    );

    $links = array();

    foreach ($ids as $id) {
        if (function_exists('pll_get_post')) {
            $translated = (int)pll_get_post($id);
            $id = $translated ? $translated : $id;
        }

        if (!$id || 'publish' !== get_post_status($id)) {
            continue;
        }

        $links[] = array(
            'label' => get_the_title($id),
            'url'   => get_permalink($id),
        );
    }

    return apply_filters('ht_footer_legal', $links);
}

/**
 * Textul din bara de jos.
 *
 * @return string Poate contine markup simplu (se trece prin wp_kses_post).
 */
function ht_footer_copyright()
{
    $text = sprintf(
        /* translators: 1: anul curent, 2: numele site-ului. */
        __('&copy; %1$s %2$s &mdash; Toate drepturile rezervate', 'herbal-therapy'),
        gmdate('Y'),
        get_bloginfo('name')
    );

    return apply_filters('ht_footer_copyright', $text);
}
