<?php
/**
 * Integrarea cu WooCommerce. Fisierul se incarca doar daca pluginul e activ.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Actualizeaza contorul cosului prin fragmentele AJAX.
 *
 * @param array $fragments Fragmentele existente.
 *
 * @return array
 */
function ht_cart_count_fragment($fragments)
{
    $count = ht_cart_count();

    /* bulina arata numarul si cand cosul e gol, ca in Figma */
    $fragments['span[data-ht-cart-count]'] = sprintf(
        '<span class="ht-icons__count" data-ht-cart-count>%s</span>',
        esc_html($count)
    );

    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'ht_cart_count_fragment');

/**
 * Lasa fiecare limba cu pozele ei la produse.
 *
 * Polylang for WooCommerce sincronizeaza mereu _thumbnail_id si
 * _product_image_gallery intre traduceri, fara vreo setare in interfata
 * (vezi polylang-wc/src/product-language-cpt.php) - nici dezactivarea
 * traducerii media nu opreste asta. Le scoatem doar la sincronizare
 * ($sync true): la crearea unei traduceri noi pozele se copiaza in
 * continuare ca punct de plecare, dar editarile ulterioare nu se mai
 * suprascriu intre limbi.
 *
 * @param string[] $metas Meta-urile pe care pluginul le copiaza/sincronizeaza.
 * @param bool     $sync  True la sincronizare, false la copierea initiala.
 *
 * @return string[]
 */
function ht_keep_product_images_per_language($metas, $sync)
{
    if ($sync) {
        unset($metas['_thumbnail_id'], $metas['_product_image_gallery']);
    }

    return $metas;
}

add_filter('pllwc_copy_post_metas', 'ht_keep_product_images_per_language', 10, 2);

/**
 * Suportul de baza.
 *
 * Suporturile 'wc-product-gallery-*' lipsesc intentionat: pagina de produs isi
 * deseneaza galeria cu Swiper (inc/single-product.php), nu cu .woocommerce-product-gallery.
 * Activate, ar incarca degeaba flexslider, zoom si photoswipe pe fiecare produs.
 */
function ht_woocommerce_setup()
{
    add_theme_support('woocommerce');
}

add_action('after_setup_theme', 'ht_woocommerce_setup');

/* ---------------------------------------------------------------------------
 * Stilurile implicite ale pluginului
 * ------------------------------------------------------------------------ */

/**
 * Foile clasice - woocommerce-layout, woocommerce-smallscreen, woocommerce.css.
 *
 * Tema isi deseneaza singura componentele de magazin, iar foile pluginului doar
 * suprascriu designul. Ramane incarcat doar 'woocommerce-inline': nu e stil de
 * design, ci o singura regula care arata sau ascunde asteriscul de la campurile
 * obligatorii, dupa setarea din Woo.
 */
add_filter('woocommerce_enqueue_styles', '__return_empty_array');

/**
 * Pagina curenta randeaza un bloc WooCommerce?
 *
 * Blocurile (cos, finalizare comanda, grile de produse) nu au layout propriu in
 * markup - fara wc-blocks.css raman complet nestilizate. Pe paginile unde apar,
 * pastram foaia lor.
 *
 * @return bool
 */
function ht_has_woocommerce_block()
{
    if (!is_singular()) {
        return false;
    }

    $post = get_post();

    if (!$post || !function_exists('has_blocks') || !has_blocks($post)) {
        return false;
    }

    return (bool)preg_match('~<!--\s+wp:woocommerce/~', $post->post_content);
}

/**
 * Scoate foile blocurilor de magazin de pe paginile care nu contin astfel de blocuri.
 *
 * Ca sa le scoti peste tot, inclusiv de la finalizarea comenzii (atentie, atunci
 * layout-ul blocului ramane in seama temei):
 *
 *   add_filter('ht_keep_woocommerce_block_styles', '__return_false');
 */
function ht_dequeue_woocommerce_block_styles()
{
    if (apply_filters('ht_keep_woocommerce_block_styles', ht_has_woocommerce_block())) {
        return;
    }

    foreach ((array)wp_styles()->queue as $handle) {
        if (0 === strpos($handle, 'wc-blocks')) {
            wp_dequeue_style($handle);
        }
    }
}

/* o data pentru foile din <head>, o data pentru cele inregistrate la randarea continutului */
add_action('wp_enqueue_scripts', 'ht_dequeue_woocommerce_block_styles', 100);
add_action('wp_footer', 'ht_dequeue_woocommerce_block_styles', 0);

/* ---------------------------------------------------------------------------
 * Listarile de produse
 * ------------------------------------------------------------------------ */

/* calculul pur al contoarelor din bara de filtre */
require_once HT_DIR . '/inc/shop-facets.php';

/* bara de filtre, etichetele active si conditiile pe care le pun in interogare */
require_once HT_DIR . '/inc/shop-filters.php';

/* listarea filtrata, adusa fara reincarcarea paginii */
require_once HT_DIR . '/inc/shop-ajax.php';

/*
 * Cardul temei (woocommerce/content-product.php) deseneaza singur imaginea,
 * titlul, pretul si butonul de cos, asa ca scoatem callback-urile pluginului
 * care le-ar desena a doua oara. Hook-urile in sine raman si se declanseaza din
 * sablon: pe ele se aseaza extensiile - etichete, quick view, comparare,
 * liste de dorinte - iar fara ele nu ar avea unde sa scrie nimic.
 */
remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10);
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
remove_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10);
remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5);
remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);
remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);

/**
 * Titlul listarii, plus descrierea categoriei cand exista.
 *
 * In design pagina incepe direct cu titlul, deci il scoatem din antetul
 * implicit al WooCommerce si il scriem aici, deasupra celor doua coloane.
 */
function ht_shop_archive_header()
{
    $title = apply_filters('ht_shop_title', wp_strip_all_tags(woocommerce_page_title(false)));

    if ('' !== $title) {
        printf('<h1 class="ht-shop__title">%s</h1>', esc_html($title));
    }

    if (!is_product_taxonomy()) {
        return;
    }

    $term = get_queried_object();
    $description = ($term && !is_wp_error($term)) ? wc_format_content($term->description) : '';

    if ('' !== $description) {
        printf('<div class="ht-shop__intro">%s</div>', wp_kses_post($description));
    }
}

/**
 * Containerul paginilor de magazin - acelasi <main> + .ht-wrapper ca in restul temei.
 *
 * Pe listari se deschid aici si cele doua coloane din design: bara de filtre in
 * stanga, grila in dreapta. Restul paginilor de magazin (cos, cont, finalizare)
 * primesc doar wrapper-ul.
 */
function ht_woocommerce_wrapper_start()
{
    echo '<main id="primary" class="site-main ht-shop">';
    echo '<div class="ht-wrapper">';

    if (!ht_is_shop_archive()) {
        return;
    }

    ht_shop_archive_header();

    echo '<div class="ht-shop__layout">';
    ht_shop_filters_markup();
    /* data-ht-shop-main: bucata pe care o inlocuieste filtrarea prin AJAX */
    echo '<div class="ht-shop__main" data-ht-shop-main>';
}

/**
 * Inchide containerul deschis de ht_woocommerce_wrapper_start().
 */
function ht_woocommerce_wrapper_end()
{
    if (ht_is_shop_archive()) {
        echo '</div></div>';
    }

    echo '</div></main>';
}

remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
add_action('woocommerce_before_main_content', 'ht_woocommerce_wrapper_start', 10);
add_action('woocommerce_after_main_content', 'ht_woocommerce_wrapper_end', 10);

/* tema nu are bara laterala de magazin */
remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);

/**
 * Numarul de coloane din grila.
 *
 * Grila reala e in shop.css; WooCommerce foloseste valoarea doar pentru clasele
 * 'first' / 'last' pe care le pune pe fiecare card.
 *
 * @return int
 */
function ht_loop_shop_columns()
{
    return (int)apply_filters('ht_shop_columns', 4);
}

add_filter('loop_shop_columns', 'ht_loop_shop_columns');

/**
 * Deschide bara de deasupra grilei: etichetele filtrelor active in stanga,
 * sortarea in dreapta.
 *
 * In design nu apare numarul de rezultate, deci callback-ul WooCommerce care il
 * scria e scos mai jos; hook-ul ramane pe pozitie pentru extensii.
 */
function ht_shop_toolbar_start()
{
    echo '<div class="ht-shop__bar">';

    ht_shop_chips_markup();
}

/**
 * Inchide bara deschisa de ht_shop_toolbar_start().
 */
function ht_shop_toolbar_end()
{
    echo '</div>';
}

/* numarul de rezultate era pe 20, sortarea e pe 30 */
add_action('woocommerce_before_shop_loop', 'ht_shop_toolbar_start', 19);
add_action('woocommerce_before_shop_loop', 'ht_shop_toolbar_end', 31);
remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);

/**
 * Cand filtrele nu lasa niciun produs, hook-urile de deasupra grilei nu se mai
 * declanseaza. Etichetele active raman totusi afisate, ca vizitatorul sa aiba
 * de unde le scoate.
 */
function ht_shop_empty_toolbar()
{
    if (!ht_shop_has_filters()) {
        return;
    }

    echo '<div class="ht-shop__bar">';
    ht_shop_chips_markup();
    echo '</div>';
}

add_action('woocommerce_no_products_found', 'ht_shop_empty_toolbar', 5);

/* titlul si descrierea categoriei le scrie ht_shop_archive_header() */
remove_action('woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10);

/**
 * Prima optiune de sortare se numeste "Recomandat", ca in design.
 *
 * @param array $options Optiunile de sortare.
 *
 * @return array
 */
function ht_shop_catalog_orderby($options)
{
    if (isset($options['menu_order'])) {
        $options['menu_order'] = __('Recomandat', 'herbal-therapy');
    }

    return $options;
}

add_filter('woocommerce_catalog_orderby', 'ht_shop_catalog_orderby');

/**
 * Firul Ariadnei nu apare in designul listarilor; pe pagina de produs ramane.
 *
 * @param array $crumbs Elementele firului.
 *
 * @return array
 */
function ht_shop_hide_breadcrumb($crumbs)
{
    return ht_is_shop_archive() ? array() : $crumbs;
}

add_filter('woocommerce_get_breadcrumb', 'ht_shop_hide_breadcrumb');

/**
 * Stilurile paginilor de magazin.
 */
function ht_enqueue_shop_styles()
{
    /* pagina de rezultate foloseste aceeasi grila de produse ca listarile */
    if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page() && !is_search()) {
        return;
    }

    wp_enqueue_style(
        'ht-shop',
        ht_asset_uri('/assets/css/shop.css'),
        array('ht-style', 'ht-products'),
        ht_asset_version('/assets/css/shop.css')
    );

    /* bara de filtre exista doar pe listari */
    if (ht_is_shop_archive()) {
        wp_enqueue_script(
            'ht-shop-filters',
            ht_asset_uri('/assets/js/shop-filters.js'),
            array(),
            ht_asset_version('/assets/js/shop-filters.js'),
            true
        );
    }
}

add_action('wp_enqueue_scripts', 'ht_enqueue_shop_styles', 20);

/* ---------------------------------------------------------------------------
 * Pagina de produs
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/single-product.php';

/* ---------------------------------------------------------------------------
 * Recenziile de produs
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/reviews.php';

/* ---------------------------------------------------------------------------
 * Pagina de cont
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/account.php';

/* ---------------------------------------------------------------------------
 * Cosul rapid
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/minicart.php';

/* ---------------------------------------------------------------------------
 * Bara de progres spre livrarea gratuita (cos rapid + pagina de cos)
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/shipping-progress.php';

/* ---------------------------------------------------------------------------
 * Notificarile de cos
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/toast.php';

/* ---------------------------------------------------------------------------
 * Pagina de cos
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/cart.php';

/* ---------------------------------------------------------------------------
 * Finalizarea comenzii
 * ------------------------------------------------------------------------ */

require_once HT_DIR . '/inc/checkout.php';

/* ---------------------------------------------------------------------------
 * Metodele de livrare
 * ------------------------------------------------------------------------ */

/**
 * Cand livrarea gratuita e disponibila (cosul a trecut de pragul setat in
 * WooCommerce > Livrare, ex. 500 MDL), curierul cu plata nu se mai afiseaza.
 * Ridicarea locala ramane ca alternativa.
 *
 * Se opreste cu add_filter('ht_hide_paid_shipping_when_free', '__return_false').
 *
 * @param array $rates   Metodele disponibile pentru pachet.
 * @param array $package Pachetul de livrare.
 *
 * @return array
 */
function ht_hide_paid_shipping_when_free($rates, $package)
{
    if (!apply_filters('ht_hide_paid_shipping_when_free', true, $rates, $package)) {
        return $rates;
    }

    $has_free = false;

    foreach ($rates as $rate) {
        if ('free_shipping' === $rate->get_method_id()) {
            $has_free = true;
            break;
        }
    }

    if (!$has_free) {
        return $rates;
    }

    foreach ($rates as $key => $rate) {
        if ('flat_rate' === $rate->get_method_id()) {
            unset($rates[$key]);
        }
    }

    return $rates;
}

add_filter('woocommerce_package_rates', 'ht_hide_paid_shipping_when_free', 10, 2);
