<?php
/**
 * Inregistrarea si incarcarea fisierelor CSS / JS.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Versiune de cache-busting bazata pe data fisierului.
 *
 * @param string $relative Cale relativa la radacina temei, ex. '/assets/css/style.css'.
 *
 * @return string
 */
function ht_asset_version($relative)
{
    $path = HT_DIR . $relative;

    return file_exists($path) ? (string)filemtime($path) : HT_VERSION;
}

/**
 * URL complet catre un fisier din tema.
 *
 * @param string $relative Cale relativa la radacina temei.
 *
 * @return string
 */
function ht_asset_uri($relative)
{
    return HT_URI . $relative;
}

/**
 * CSS si JS pentru front-end.
 */
function ht_enqueue_assets()
{
    $has_hero = ht_has_hero();

    /* ---------- Librarii ---------- */

    wp_enqueue_style(
        'swiper',
        ht_asset_uri('/assets/css/swiper-bundle.min.css'),
        array(),
        '11.1.14'
    );

    wp_enqueue_script(
        'swiper',
        ht_asset_uri('/assets/js/swiper-bundle.min.js'),
        array(),
        '11.1.14',
        true
    );

    /* ---------- Fonturi ---------- */

    wp_enqueue_style(
        'ht-fonts',
        'https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap',
        array(),
        null
    );

    /* ---------- Stilurile temei ---------- */

    wp_enqueue_style(
        'ht-style',
        get_stylesheet_uri(),
        array('ht-fonts'),
        ht_asset_version('/style.css')
    );

    wp_enqueue_style(
        'ht-header',
        ht_asset_uri('/assets/css/header.css'),
        array('ht-style'),
        ht_asset_version('/assets/css/header.css')
    );

    wp_enqueue_style(
        'ht-products',
        ht_asset_uri('/assets/css/products.css'),
        array('swiper', 'ht-style'),
        ht_asset_version('/assets/css/products.css')
    );

    wp_enqueue_style(
        'ht-categories',
        ht_asset_uri('/assets/css/categories.css'),
        array('swiper', 'ht-style'),
        ht_asset_version('/assets/css/categories.css')
    );

    wp_enqueue_style(
        'ht-top-sales',
        ht_asset_uri('/assets/css/top-sales.css'),
        array('ht-products'),
        ht_asset_version('/assets/css/top-sales.css')
    );

    wp_enqueue_style(
        'ht-home-about',
        ht_asset_uri('/assets/css/home-about.css'),
        array('ht-style'),
        ht_asset_version('/assets/css/home-about.css')
    );

    /* panoul de cautare sta in header, deci merge pe toate paginile */
    wp_enqueue_style(
        'ht-search',
        ht_asset_uri('/assets/css/search.css'),
        array('ht-header'),
        ht_asset_version('/assets/css/search.css')
    );

    /* butonul de catalog si panoul lui stau tot in header */
    wp_enqueue_style(
        'ht-catalog',
        ht_asset_uri('/assets/css/catalog.css'),
        array('ht-header'),
        ht_asset_version('/assets/css/catalog.css')
    );

    /* comutatorul de limba: randul de meniu si drawer-ul */
    wp_enqueue_style(
        'ht-language',
        ht_asset_uri('/assets/css/language.css'),
        array('ht-header'),
        ht_asset_version('/assets/css/language.css')
    );

    wp_enqueue_style(
        'ht-blog',
        ht_asset_uri('/assets/css/blog.css'),
        array('swiper', 'ht-style'),
        ht_asset_version('/assets/css/blog.css')
    );

    wp_enqueue_style(
        'ht-home-reviews',
        ht_asset_uri('/assets/css/home-reviews.css'),
        array('swiper', 'ht-products'),
        ht_asset_version('/assets/css/home-reviews.css')
    );

    wp_enqueue_style(
        'ht-home-benefits',
        ht_asset_uri('/assets/css/home-benefits.css'),
        array('swiper', 'ht-style'),
        ht_asset_version('/assets/css/home-benefits.css')
    );

    wp_enqueue_style(
        'ht-footer',
        ht_asset_uri('/assets/css/footer.css'),
        array('ht-style'),
        ht_asset_version('/assets/css/footer.css')
    );

    /* listarea de articole are propria grila si bara de teme */
    if (is_page_template('templates/blog.php')) {
        wp_enqueue_style(
            'ht-blog-archive',
            ht_asset_uri('/assets/css/blog-archive.css'),
            array('ht-blog'),
            ht_asset_version('/assets/css/blog-archive.css')
        );

        wp_enqueue_script(
            'ht-blog-archive',
            ht_asset_uri('/assets/js/blog-archive.js'),
            array(),
            ht_asset_version('/assets/js/blog-archive.js'),
            true
        );
    }

    if (is_page_template('templates/contact.php')) {
        wp_enqueue_style(
            'ht-contact',
            ht_asset_uri('/assets/css/contact.css'),
            array('ht-style'),
            ht_asset_version('/assets/css/contact.css')
        );
    }

    if (is_page_template('templates/b2b.php')) {
        wp_enqueue_style(
            'ht-b2b',
            ht_asset_uri('/assets/css/b2b.css'),
            array('ht-style'),
            ht_asset_version('/assets/css/b2b.css')
        );
    }

    if (is_page_template('templates/about.php')) {
        wp_enqueue_style(
            'ht-about',
            ht_asset_uri('/assets/css/about.css'),
            array('ht-style'),
            ht_asset_version('/assets/css/about.css')
        );
    }

    if (is_page_template('templates/favorites.php')) {
        wp_enqueue_style(
            'ht-favorites',
            ht_asset_uri('/assets/css/favorites.css'),
            array('ht-products'),
            ht_asset_version('/assets/css/favorites.css')
        );
    }

    if (is_page_template('templates/reduceri.php')) {
        wp_enqueue_style(
            'ht-reduceri',
            ht_asset_uri('/assets/css/reduceri.css'),
            array('ht-products'),
            ht_asset_version('/assets/css/reduceri.css')
        );
    }

    wp_enqueue_style(
        'ht-responsive',
        ht_asset_uri('/assets/css/responsive.css'),
        array('ht-style', 'ht-header', 'ht-footer'),
        ht_asset_version('/assets/css/responsive.css')
    );

    /* ---------- Scripturile temei ---------- */

    wp_enqueue_script(
        'ht-header',
        ht_asset_uri('/assets/js/header.js'),
        array(),
        ht_asset_version('/assets/js/header.js'),
        true
    );

    wp_enqueue_script(
        'ht-search',
        ht_asset_uri('/assets/js/search.js'),
        array('ht-header'),
        ht_asset_version('/assets/js/search.js'),
        true
    );

    wp_localize_script('ht-search', 'htSearchData', array(
        'rest' => esc_url_raw(rest_url('herbal-therapy/v1/search')),
        'min'  => ht_search_min_chars(),
        /* adresa REST nu are prefix de limba; o trimitem noi, vezi ht_search_lang() */
        'lang' => ht_search_lang(),
    ));

    wp_enqueue_script(
        'ht-products',
        ht_asset_uri('/assets/js/products.js'),
        array('swiper'),
        ht_asset_version('/assets/js/products.js'),
        true
    );

    wp_enqueue_script(
        'ht-categories',
        ht_asset_uri('/assets/js/categories.js'),
        array('swiper'),
        ht_asset_version('/assets/js/categories.js'),
        true
    );

    wp_enqueue_script(
        'ht-home-benefits',
        ht_asset_uri('/assets/js/home-benefits.js'),
        array('swiper'),
        ht_asset_version('/assets/js/home-benefits.js'),
        true
    );

    wp_enqueue_script(
        'ht-top-sales',
        ht_asset_uri('/assets/js/top-sales.js'),
        array('swiper'),
        ht_asset_version('/assets/js/top-sales.js'),
        true
    );

    wp_localize_script('ht-top-sales', 'htTopSalesData', array(
        'rest' => esc_url_raw(rest_url('herbal-therapy/v1/top-sales')),
        /* adresa REST nu are prefix de limba; o trimitem noi, vezi ht_search_lang() */
        'lang' => ht_search_lang(),
    ));

    wp_enqueue_script(
        'ht-blog',
        ht_asset_uri('/assets/js/blog.js'),
        array('swiper'),
        ht_asset_version('/assets/js/blog.js'),
        true
    );

    wp_enqueue_script(
        'ht-home-reviews',
        ht_asset_uri('/assets/js/home-reviews.js'),
        array('swiper'),
        ht_asset_version('/assets/js/home-reviews.js'),
        true
    );

    /*
     * Favoritele merg pe orice pagina: butonul e in card, iar contorul in header.
     * Scriptul nu depinde de Swiper, ca sa functioneze si acolo unde nu exista
     * carusel (pagina de cont, cos, articole).
     */
    wp_enqueue_script(
        'ht-favorites',
        ht_asset_uri('/assets/js/favorites.js'),
        array(),
        ht_asset_version('/assets/js/favorites.js'),
        true
    );

    /*
     * Datele sunt legate de vizitator (lista + nonce), deci paginile care ies
     * de aici nu se pot cache-ui integral pentru toata lumea.
     */
    wp_localize_script('ht-favorites', 'htFavoritesData', array(
        'rest'  => esc_url_raw(rest_url('herbal-therapy/v1/favorites')),
        'nonce' => wp_create_nonce('wp_rest'),
        'ids'   => ht_favorites_get(),
        'lang'  => function_exists('pll_current_language') ? (string)pll_current_language() : '',
    ));

    /* ---------- Caruselul hero: doar unde este randat ---------- */

    if ($has_hero) {
        wp_enqueue_style(
            'ht-hero',
            ht_asset_uri('/assets/css/hero-slider.css'),
            array('swiper', 'ht-style'),
            ht_asset_version('/assets/css/hero-slider.css')
        );

        wp_enqueue_script(
            'ht-hero',
            ht_asset_uri('/assets/js/hero-slider.js'),
            array('swiper'),
            ht_asset_version('/assets/js/hero-slider.js'),
            true
        );
    }

    /* raspunsul la comentarii, doar unde e cazul */
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}

add_action('wp_enqueue_scripts', 'ht_enqueue_assets');

/**
 * Preconnect catre Google Fonts - scurteaza timpul pana la primul render.
 *
 * @param array  $urls          URL-urile deja inregistrate.
 * @param string $relation_type Tipul de hint.
 *
 * @return array
 */
function ht_resource_hints($urls, $relation_type)
{
    if ('preconnect' === $relation_type) {
        $urls[] = array('href' => 'https://fonts.googleapis.com');
        $urls[] = array('href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous');
    }

    return $urls;
}

add_filter('wp_resource_hints', 'ht_resource_hints', 10, 2);
