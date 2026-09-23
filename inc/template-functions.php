<?php
/**
 * Filtre care ajusteaza comportamentul implicit al WordPress.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase utile pe <body>.
 *
 * @param array $classes Clasele existente.
 *
 * @return array
 */
function ht_body_classes($classes)
{
    if (ht_has_hero()) {
        $classes[] = 'has-hero';
    }

    if (!is_active_sidebar('sidebar-1')) {
        $classes[] = 'no-sidebar';
    }

    if (is_singular() && !is_front_page()) {
        $classes[] = 'is-singular';
    }

    return $classes;
}

add_filter('body_class', 'ht_body_classes');

/**
 * Pagina curenta e o pagina de text pe sablonul implicit (termeni, politici,
 * livrare) - primeste firimituri, cartonasul de text si assets/css/page.css.
 *
 * @return bool
 */
function ht_is_text_page()
{
    if (!is_page() || is_front_page() || '' !== (string)get_page_template_slug()) {
        return false;
    }

    /* cosul, finalizarea si contul au sabloanele lor, puse prin template_include */
    if (function_exists('is_woocommerce') && (is_cart() || is_checkout() || is_account_page())) {
        return false;
    }

    return true;
}

/**
 * Terminatia extrasului.
 *
 * @return string
 */
function ht_excerpt_more()
{
    return '&hellip;';
}

add_filter('excerpt_more', 'ht_excerpt_more');

/**
 * Lungimea extrasului, in cuvinte.
 *
 * @return int
 */
function ht_excerpt_length()
{
    return (int)apply_filters('ht_excerpt_length', 28);
}

add_filter('excerpt_length', 'ht_excerpt_length', 999);

/**
 * Contact Form 7 nu trebuie sa adauge <p> si <br> automat.
 */
add_filter('wpcf7_autop_or_not', '__return_false');

/**
 * Scoate wrapper-ul <p> din jurul imaginilor din continut.
 *
 * @param string $content Continutul.
 *
 * @return string
 */
function ht_unwrap_images($content)
{
    return preg_replace('/<p>\s*(<a [^>]*>)?\s*(<img [^>]+>)\s*(<\/a>)?\s*<\/p>/i', '$1$2$3', $content);
}

add_filter('the_content', 'ht_unwrap_images', 20);

/**
 * Tipurile de continut pentru care comentariile sunt oprite.
 *
 * Recenziile la produse folosesc tot sistemul de comentarii, deci "product"
 * nu apare in lista.
 *
 * @return array
 */
function ht_closed_comment_types()
{
    return (array)apply_filters('ht_closed_comment_types', array('post', 'page'));
}

/**
 * Verifica daca un obiect apartine unui tip cu comentariile oprite.
 *
 * @param int|WP_Post $post_id ID-ul sau obiectul postarii.
 *
 * @return bool
 */
function ht_comments_disabled_for($post_id)
{
    $type = get_post_type($post_id);

    return $type && in_array($type, ht_closed_comment_types(), true);
}

/**
 * Inchide formularul de comentarii pe articole si pagini.
 *
 * @param bool $open    Starea curenta.
 * @param int  $post_id ID-ul postarii.
 *
 * @return bool
 */
function ht_close_comments($open, $post_id)
{
    return ht_comments_disabled_for($post_id) ? false : $open;
}

add_filter('comments_open', 'ht_close_comments', 20, 2);
add_filter('pings_open', 'ht_close_comments', 20, 2);

/**
 * Ascunde comentariile ramase in baza de date de la articolele vechi.
 *
 * @param array $comments Comentariile.
 * @param int   $post_id  ID-ul postarii.
 *
 * @return array
 */
function ht_hide_existing_comments($comments, $post_id)
{
    return ht_comments_disabled_for($post_id) ? array() : $comments;
}

add_filter('comments_array', 'ht_hide_existing_comments', 20, 2);

/**
 * Numarul de comentarii afisat devine zero pe tipurile inchise.
 *
 * @param string|int $count   Numarul curent.
 * @param int        $post_id ID-ul postarii.
 *
 * @return string|int
 */
function ht_hide_comments_number($count, $post_id)
{
    return ht_comments_disabled_for($post_id) ? 0 : $count;
}

add_filter('get_comments_number', 'ht_hide_comments_number', 20, 2);

/**
 * Scoate suportul pentru comentarii din editorul de articole si pagini.
 */
function ht_remove_comment_support()
{
    foreach (ht_closed_comment_types() as $type) {
        remove_post_type_support($type, 'comments');
        remove_post_type_support($type, 'trackbacks');
    }
}

add_action('init', 'ht_remove_comment_support', 20);
