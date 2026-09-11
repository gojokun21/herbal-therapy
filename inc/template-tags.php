<?php
/**
 * Functii folosite direct din template-uri.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pagina curenta afiseaza caruselul hero?
 *
 * Fara slide-uri configurate in ACF nu exista carusel, deci header-ul nu mai
 * trece in modul transparent.
 *
 * @return bool
 */
function ht_has_hero()
{
    $has_hero = is_front_page() && !is_paged() && ht_hero_slides();

    return (bool)apply_filters('ht_has_hero', $has_hero);
}

/**
 * Orasele in care livreaza magazinul.
 *
 * Cu un singur oras, header-ul si drawer-ul il arata ca text simplu; de la doua
 * in sus apare comutatorul. Se completeaza cu:
 *
 *   add_filter('ht_header_cities', function () { return array('Chisinau', 'Balti'); });
 *
 * @return array
 */
function ht_header_cities()
{
    return apply_filters('ht_header_cities', array(
        'Chisinau',
    ));
}

/**
 * Logo alternativ pentru header-ul transparent peste hero (de regula varianta alba).
 *
 * @return string URL sau sir gol.
 */
function ht_header_overlay_logo()
{
    $url = '';
    $id = get_theme_mod('ht_overlay_logo');

    if ($id) {
        $src = wp_get_attachment_image_src($id, 'full');

        if ($src) {
            $url = $src[0];
        }
    }

    return apply_filters('ht_header_overlay_logo', $url);
}

/**
 * Numarul de produse din cos (WooCommerce, daca e activ).
 *
 * @return int
 */
function ht_cart_count()
{
    if (function_exists('WC') && WC()->cart) {
        return (int)WC()->cart->get_cart_contents_count();
    }

    return 0;
}

/**
 * Link-uri din header, cu fallback daca WooCommerce nu e activ.
 *
 * @param string $what 'cart' | 'account' | 'wishlist'.
 *
 * @return string
 */
function ht_header_link($what)
{
    switch ($what) {
        case 'cart':
            return function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cos/');

        case 'account':
            return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();

        case 'wishlist':
            return apply_filters('ht_wishlist_url', home_url('/favorite/'));
    }

    return home_url('/');
}

/**
 * Data articolului, ca <time> semantic.
 */
function ht_posted_on()
{
    printf(
        '<time class="entry-date" datetime="%1$s">%2$s</time>',
        esc_attr(get_the_date(DATE_W3C)),
        esc_html(get_the_date())
    );
}

/**
 * Autorul articolului.
 */
function ht_posted_by()
{
    printf(
        '<span class="entry-author">%1$s <a href="%2$s">%3$s</a></span>',
        esc_html__('de', 'herbal-therapy'),
        esc_url(get_author_posts_url(get_the_author_meta('ID'))),
        esc_html(get_the_author())
    );
}

/**
 * Imaginea reprezentativa a articolului, ca link catre articol in listari.
 *
 * @param string $size Dimensiunea imaginii.
 */
function ht_post_thumbnail($size = 'ht-card')
{
    if (post_password_required() || is_attachment() || !has_post_thumbnail()) {
        return;
    }

    if (is_singular()) {
        printf('<figure class="entry-thumbnail">%s</figure>', get_the_post_thumbnail(null, 'full'));

        return;
    }

    printf(
        '<a class="entry-thumbnail" href="%1$s" aria-hidden="true" tabindex="-1">%2$s</a>',
        esc_url(get_permalink()),
        get_the_post_thumbnail(null, $size, array('loading' => 'lazy', 'decoding' => 'async'))
    );
}

/**
 * Titlul arhivei curente, fara prefixele implicite ale WordPress.
 *
 * @return string
 */
function ht_archive_title()
{
    if (is_home() && !is_front_page()) {
        return get_the_title(get_option('page_for_posts'));
    }

    if (is_search()) {
        /* translators: %s: termenul cautat. */
        return sprintf(esc_html__('Rezultate pentru: %s', 'herbal-therapy'), get_search_query());
    }

    if (is_404()) {
        return esc_html__('Pagina nu a fost găsită', 'herbal-therapy');
    }

    return wp_strip_all_tags(get_the_archive_title());
}
