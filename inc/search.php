<?php
/**
 * Cautarea in magazin.
 *
 * Trei bucati care folosesc aceleasi functii de interogare:
 *  - panoul din header, cu sugestii live (ht_search_panel);
 *  - ruta REST herbal-therapy/v1/search, care intoarce markup gata randat;
 *  - pagina de rezultate (search.php), cu produsele pe primul tab.
 *
 * Markup-ul se construieste in PHP si pentru cererile REST, ca escaparea si
 * formatarea pretului sa ramana intr-un singur loc, nu duplicate in JavaScript.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Reglaje
 * ------------------------------------------------------------------------ */

/**
 * De la cate caractere pornim cautarea live.
 *
 * @return int
 */
function ht_search_min_chars()
{
    return (int)apply_filters('ht_search_min_chars', 2);
}

/**
 * Cate produse incap in panoul de sugestii.
 *
 * @return int
 */
function ht_search_suggest_limit()
{
    return (int)apply_filters('ht_search_suggest_limit', 6);
}

/**
 * Cate produse pe o pagina de rezultate.
 *
 * @return int
 */
function ht_search_products_per_page()
{
    return (int)apply_filters('ht_search_products_per_page', 12);
}

/**
 * Magazinul e disponibil?
 *
 * @return bool
 */
function ht_search_has_shop()
{
    return class_exists('WooCommerce') && function_exists('wc_get_products');
}

/**
 * Limba curenta, ca slug Polylang ('ro', 'ru').
 *
 * Panoul de sugestii se aduce prin REST, iar acolo adresa nu mai are prefixul
 * de limba. Fara slug-ul trecut explicit in interogari, Polylang nu are dupa ce
 * filtra si raspunsul aduce si produsul romanesc, si traducerea lui rusa.
 *
 * @return string Slug-ul limbii, gol daca site-ul nu e multilingv.
 */
function ht_search_lang()
{
    $lang = '';

    if (function_exists('pll_current_language')) {
        $lang = (string)pll_current_language('slug');

        if ('' === $lang && function_exists('pll_default_language')) {
            $lang = (string)pll_default_language('slug');
        }
    }

    return (string)apply_filters('ht_search_lang', $lang);
}

/**
 * Adauga limba curenta la un set de argumente de interogare.
 *
 * 'lang' e query var-ul taxonomiei de limba, deci merge la fel in WP_Query,
 * get_terms si wc_get_products. Pe un site cu o singura limba nu adauga nimic.
 *
 * @param array $args Argumentele.
 *
 * @return array
 */
function ht_search_with_lang($args)
{
    $lang = ht_search_lang();

    if ('' !== $lang && !isset($args['lang'])) {
        $args['lang'] = $lang;
    }

    return $args;
}

/**
 * Varianta din limba curenta a unui produs.
 *
 * Codul SKU e acelasi pe toate traducerile, asa ca o cautare dupa cod poate
 * cadea pe alta limba decat cea in care se uita vizitatorul.
 *
 * @param int $post_id Produsul gasit.
 *
 * @return int Identificatorul din limba curenta, 0 daca nu are traducere.
 */
function ht_search_translated_id($post_id)
{
    $post_id = (int)$post_id;
    $lang = ht_search_lang();

    if ('' === $lang || !$post_id || !function_exists('pll_get_post')) {
        return $post_id;
    }

    $translated = pll_get_post($post_id, $lang);

    if ($translated) {
        return (int)$translated;
    }

    /* produs fara limba atribuita: nu are ce filtra, il lasam asa cum e */
    if (function_exists('pll_get_post_language') && !pll_get_post_language($post_id)) {
        return $post_id;
    }

    return 0;
}

/**
 * Tab-ul cerut pe pagina de rezultate.
 *
 * @return string 'products' sau 'posts'.
 */
function ht_search_tab()
{
    $tab = isset($_GET['tip']) ? sanitize_key(wp_unslash($_GET['tip'])) : '';

    if ('articole' === $tab || 'posts' === $tab) {
        return 'posts';
    }

    return ht_search_has_shop() ? 'products' : 'posts';
}

/**
 * Adresa paginii de rezultate pentru un termen.
 *
 * @param string $query Termenul cautat.
 * @param string $tab   'products' sau 'posts'.
 *
 * @return string
 */
function ht_search_url($query, $tab = 'products')
{
    $args = array('s' => $query);

    if ('posts' === $tab) {
        $args['tip'] = 'articole';
    }

    return add_query_arg(array_map('rawurlencode', $args), ht_search_home_url());
}

/**
 * Adresa de baza a cautarii, cu prefixul de limba.
 *
 * home_url() nu e filtrat de Polylang in contextul REST, unde adresa cererii nu
 * are prefix; fara pll_home_url(), "Vezi toate rezultatele" ar duce vizitatorul
 * rus pe pagina de rezultate romaneasca.
 *
 * @return string
 */
function ht_search_home_url()
{
    $lang = ht_search_lang();

    if ('' !== $lang && function_exists('pll_home_url')) {
        return pll_home_url($lang);
    }

    return home_url('/');
}

/* ---------------------------------------------------------------------------
 * Interogari
 * ------------------------------------------------------------------------ */

/**
 * Termenii de vizibilitate care scot produse din cautare.
 *
 * Respecta si setarea "Ascunde produsele epuizate" din WooCommerce.
 *
 * @return array Fragment de tax_query, gol daca WooCommerce lipseste.
 */
function ht_search_visibility_tax_query()
{
    if (!function_exists('wc_get_product_visibility_term_ids')) {
        return array();
    }

    $terms = wc_get_product_visibility_term_ids();
    $hidden = array();

    if (!empty($terms['exclude-from-search'])) {
        $hidden[] = (int)$terms['exclude-from-search'];
    }

    if ('yes' === get_option('woocommerce_hide_out_of_stock_items') && !empty($terms['outofstock'])) {
        $hidden[] = (int)$terms['outofstock'];
    }

    if (!$hidden) {
        return array();
    }

    return array(
        array(
            'taxonomy' => 'product_visibility',
            'field'    => 'term_taxonomy_id',
            'terms'    => $hidden,
            'operator' => 'NOT IN',
        ),
    );
}

/**
 * Argumentele unei cautari de produse.
 *
 * @param string $query Termenul.
 * @param array  $args  Suprascrieri.
 *
 * @return array
 */
function ht_search_product_args($query, $args = array())
{
    $args = ht_search_with_lang(wp_parse_args($args, array(
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'posts_per_page'      => ht_search_suggest_limit(),
        's'                   => $query,
        'ignore_sticky_posts' => true,
    )));

    $visibility = ht_search_visibility_tax_query();

    if ($visibility) {
        $existing = isset($args['tax_query']) ? (array)$args['tax_query'] : array();
        $args['tax_query'] = array_merge($existing, $visibility); // phpcs:ignore WordPress.DB.SlowDBQuery
    }

    return apply_filters('ht_search_product_args', $args, $query);
}

/**
 * Produsele care raspund unei cautari.
 *
 * @param string $query Termenul.
 * @param int    $limit Cate produse.
 *
 * @return WC_Product[]
 */
function ht_search_products($query, $limit = 0)
{
    if (!ht_search_has_shop() || '' === trim($query)) {
        return array();
    }

    $limit = $limit ? (int)$limit : ht_search_suggest_limit();
    $ids = ht_search_product_ids($query, $limit);

    return array_values(array_filter(array_map('wc_get_product', $ids)));
}

/**
 * Identificatorii produselor gasite, codul SKU inclus.
 *
 * @param string $query Termenul.
 * @param int    $limit Cate rezultate.
 *
 * @return int[]
 */
function ht_search_product_ids($query, $limit)
{
    $ids = array();

    /* potrivirea exacta de SKU trece inaintea potrivirilor din text */
    if (function_exists('wc_get_product_id_by_sku')) {
        $sku_id = ht_search_translated_id(wc_get_product_id_by_sku(trim($query)));

        if ($sku_id) {
            $ids[] = $sku_id;
        }
    }

    $found = get_posts(ht_search_product_args($query, array(
        'posts_per_page' => $limit,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'exclude'        => $ids,
    )));

    return array_slice(array_merge($ids, array_map('intval', $found)), 0, $limit);
}

/**
 * Articolele care raspund unei cautari.
 *
 * @param string $query Termenul.
 * @param int    $limit Cate articole.
 *
 * @return WP_Post[]
 */
function ht_search_posts($query, $limit = 3)
{
    if ('' === trim($query)) {
        return array();
    }

    return get_posts(ht_search_with_lang(array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => (int)$limit,
        's'                   => $query,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    )));
}

/**
 * Categoriile de produse care se potrivesc cu termenul.
 *
 * @param string $query Termenul.
 * @param int    $limit Cate categorii.
 *
 * @return WP_Term[]
 */
function ht_search_categories($query, $limit = 5)
{
    if (!taxonomy_exists('product_cat')) {
        return array();
    }

    $terms = get_terms(ht_search_with_lang(array(
        'taxonomy'   => 'product_cat',
        'search'     => $query,
        'number'     => (int)$limit,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
    )));

    return is_wp_error($terms) ? array() : $terms;
}

/**
 * Categoriile aratate cat timp campul e gol.
 *
 * @param int $limit Cate categorii.
 *
 * @return WP_Term[]
 */
function ht_search_top_categories($limit = 6)
{
    if (!taxonomy_exists('product_cat')) {
        return array();
    }

    $terms = get_terms(ht_search_with_lang(array(
        'taxonomy'   => 'product_cat',
        'number'     => (int)$limit,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'exclude'    => array((int)get_option('default_product_cat')),
    )));

    return is_wp_error($terms) ? array() : apply_filters('ht_search_top_categories', $terms);
}

/**
 * Cate rezultate are un tip de continut pentru termenul dat.
 *
 * @param string $post_type Tipul de continut.
 * @param string $query     Termenul.
 *
 * @return int
 */
function ht_search_count($post_type, $query)
{
    if ('' === trim($query)) {
        return 0;
    }

    $args = ht_search_with_lang(array(
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        's'              => $query,
    ));

    if ('product' === $post_type) {
        $args = ht_search_product_args($query, $args);
    }

    $args['no_found_rows'] = false;

    $found = new WP_Query($args);

    return (int)$found->found_posts;
}

/* ---------------------------------------------------------------------------
 * Cautare si dupa codul SKU
 * ------------------------------------------------------------------------ */

/**
 * Adauga produsele al caror SKU contine termenul la rezultatele cautarii.
 *
 * WordPress cauta doar in titlu, continut si extras; intr-un magazin, oamenii
 * scriu si codul produsului.
 *
 * @param string   $search Clauza WHERE construita de WordPress.
 * @param WP_Query $query  Interogarea.
 *
 * @return string
 */
function ht_search_include_sku($search, $query)
{
    global $wpdb;

    if (is_admin() || '' === $search || !$query->is_search()) {
        return $search;
    }

    if (!in_array('product', (array)$query->get('post_type'), true)) {
        return $search;
    }

    $term = trim((string)$query->get('s'));

    if (strlen($term) < ht_search_min_chars()) {
        return $search;
    }

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s LIMIT 30",
        '%' . $wpdb->esc_like($term) . '%'
    ));

    if (!$ids) {
        return $search;
    }

    $list = implode(',', array_map('absint', $ids));

    /* clauza vine mereu ca " AND (...)"; adaugam alternativa in aceeasi paranteza */
    return (string)preg_replace(
        '/^(\s*AND\s*\()(.*)(\)\s*)$/s',
        '$1($2) OR ' . $wpdb->posts . '.ID IN (' . $list . ')$3',
        $search
    );
}

add_filter('posts_search', 'ht_search_include_sku', 10, 2);

/* ---------------------------------------------------------------------------
 * Pagina de rezultate
 * ------------------------------------------------------------------------ */

/**
 * Interogarea principala a cautarii tine un singur tip de continut: produsele
 * pe tab-ul implicit, articolele pe celalalt. Numaratorile din taburi se fac
 * separat, cu interogari care aduc doar identificatori.
 *
 * Prioritatea 20 lasa WooCommerce sa isi puna filtrele inainte.
 *
 * @param WP_Query $query Interogarea.
 */
function ht_search_pre_get_posts($query)
{
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    /*
     * Cautare fara termen: pagina exista (vezi ht_search_keep_empty_query), dar
     * nu are ce lista. Fara asta, un "s" gol ar scoate tot catalogul.
     */
    if ('' === trim((string)$query->get('s'))) {
        $query->set('post__in', array(0));

        return;
    }

    if ('posts' === ht_search_tab()) {
        $query->set('post_type', array('post', 'page'));
        $query->set('posts_per_page', 9);

        return;
    }

    $query->set('post_type', 'product');
    $query->set('posts_per_page', ht_search_products_per_page());

    $visibility = ht_search_visibility_tax_query();

    if ($visibility) {
        $existing = (array)$query->get('tax_query');
        $query->set('tax_query', array_merge($existing, $visibility));
    }
}

add_action('pre_get_posts', 'ht_search_pre_get_posts', 20);

/**
 * O cautare goala ("Caută" apasat fara text) ramane pe pagina de rezultate, in
 * loc sa cada pe prima pagina.
 *
 * @param WP_Query $query Interogarea.
 */
function ht_search_keep_empty_query($query)
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!isset($_GET['s']) || '' !== trim((string)wp_unslash($_GET['s']))) {
        return;
    }

    $query->set('s', '');
    $query->is_search = true;
    $query->is_home = false;
}

add_action('parse_query', 'ht_search_keep_empty_query');

/**
 * Orice cautare foloseste search.php.
 *
 * O adresa de forma ?s=ceva&post_type=product (asa o construiesc widget-urile
 * si blocurile de cautare din WooCommerce) este si cautare, si arhiva de
 * produse, iar pluginul o trimite spre sablonul de magazin. Rezultatul ar fi
 * doua pagini de cautare cu aspect diferit.
 *
 * Prioritatea 99 trece peste incarcatorul WooCommerce, care lucreaza pe 10.
 *
 * @param string $template Sablonul ales pana acum.
 *
 * @return string
 */
function ht_search_template($template)
{
    if (!is_search()) {
        return $template;
    }

    $found = locate_template('search.php');

    return $found ? $found : $template;
}

add_filter('template_include', 'ht_search_template', 99);

/* ---------------------------------------------------------------------------
 * Markup
 * ------------------------------------------------------------------------ */

/**
 * Un produs in lista de sugestii.
 *
 * @param WC_Product $product Produsul.
 */
function ht_search_product_item($product)
{
    $image_id = ht_product_image_id($product);
    $image = $image_id
        ? wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, array(
            'class'    => 'ht-suggest__img',
            'loading'  => 'lazy',
            'decoding' => 'async',
            'alt'      => $product->get_name(),
        ))
        : '';

    if ('' === $image && function_exists('wc_placeholder_img')) {
        $image = wc_placeholder_img('woocommerce_thumbnail', array('class' => 'ht-suggest__img'));
    }
    ?>
    <a class="ht-suggest" href="<?php echo esc_url($product->get_permalink()); ?>">
        <span class="ht-suggest__media"><?php echo wp_kses_post($image); ?></span>
        <span class="ht-suggest__body">
            <span class="ht-suggest__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
            <span class="ht-suggest__name"><?php echo esc_html($product->get_name()); ?></span>
        </span>
    </a>
    <?php
}

/**
 * Un bloc din coloana din stanga: titlu + lista de link-uri.
 *
 * @param string $title Titlul blocului.
 * @param array  $links Fiecare intrare: 'url', 'label', 'count'.
 */
function ht_search_links_block($title, $links)
{
    if (!$links) {
        return;
    }
    ?>
    <div class="ht-search__block">
        <h3 class="ht-search__block-title"><?php echo esc_html($title); ?></h3>
        <ul class="ht-search__links">
            <?php foreach ($links as $link) : ?>
                <li>
                    <a href="<?php echo esc_url($link['url']); ?>">
                        <span><?php echo esc_html($link['label']); ?></span>
                        <?php if (!empty($link['count'])) : ?>
                            <em class="ht-search__links-count"><?php echo esc_html($link['count']); ?></em>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Continutul panoului: coloana cu categorii si articole, plus produsele gasite.
 *
 * Cu campul gol arata categoriile mari si produsele recomandate, ca panoul sa
 * nu se deschida gol.
 *
 * @param string $query Termenul cautat.
 *
 * @return array 'html' - markup-ul, 'total' - cate produse s-au gasit.
 */
function ht_search_results($query)
{
    $query = trim((string)$query);
    $is_query = strlen($query) >= ht_search_min_chars();

    /* campul gol nu are continut: panoul e doar bara de cautare */
    if (!$is_query) {
        return array(
            'html'  => '',
            'total' => 0,
        );
    }

    $products = ht_search_products($query);
    $total = ht_search_count('product', $query);
    $categories = ht_search_categories($query);
    $posts = ht_search_posts($query);

    $category_links = array();

    foreach ($categories as $term) {
        $link = get_term_link($term);

        if (is_wp_error($link)) {
            continue;
        }

        $category_links[] = array(
            'url'   => $link,
            'label' => $term->name,
            'count' => $term->count,
        );
    }

    $post_links = array();

    foreach ($posts as $post) {
        $post_links[] = array(
            'url'   => get_permalink($post),
            'label' => get_the_title($post),
        );
    }

    ob_start();
    ?>
    <div class="ht-search__cols">

        <div class="ht-search__side">
            <div class="ht-search__block ht-search__block--recent" data-ht-recent hidden>
                <h3 class="ht-search__block-title"><?php esc_html_e('Căutări recente', 'herbal-therapy'); ?></h3>
                <ul class="ht-search__chips" data-ht-recent-list></ul>
                <button class="ht-search__chips-clear" type="button" data-ht-recent-clear>
                    <?php esc_html_e('Șterge istoricul', 'herbal-therapy'); ?>
                </button>
            </div>

            <?php
            ht_search_links_block(__('Categorii potrivite', 'herbal-therapy'), $category_links);

            ht_search_links_block(__('Din blog', 'herbal-therapy'), $post_links);
            ?>
        </div>

        <div class="ht-search__main">
            <?php if ($products) : ?>

                <div class="ht-search__main-head">
                    <h3 class="ht-search__block-title"><?php esc_html_e('Produse găsite', 'herbal-therapy'); ?></h3>
                    <?php if ($total > count($products)) : ?>
                        <span class="ht-search__main-count">
                            <?php
                            /* translators: %d: numarul total de produse gasite. */
                            printf(esc_html(_n('%d produs', '%d produse', $total, 'herbal-therapy')), (int)$total);
                            ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="ht-search__grid">
                    <?php foreach ($products as $product) : ?>
                        <?php ht_search_product_item($product); ?>
                    <?php endforeach; ?>
                </div>

                    <a class="ht-search__all" href="<?php echo esc_url(ht_search_url($query)); ?>">
                        <?php esc_html_e('Vezi toate rezultatele', 'herbal-therapy'); ?>
                        <?php ht_icon('arrow', 'ht-icon ht-search__all-icon'); ?>
                    </a>

            <?php else : ?>

                <div class="ht-search__empty">
                    <p class="ht-search__empty-title"><?php esc_html_e('Niciun produs pentru această căutare', 'herbal-therapy'); ?></p>
                    <p class="ht-search__empty-text"><?php esc_html_e('Încearcă un cuvânt mai scurt sau alege o categorie.', 'herbal-therapy'); ?></p>
                </div>

            <?php endif; ?>
        </div>

    </div>
    <?php

    return array(
        'html'  => (string)ob_get_clean(),
        'total' => (int)$total,
    );
}

/**
 * Panoul de cautare din header.
 *
 * Corpul ramane gol in HTML-ul paginii: continutul implicit (categorii mari si
 * produse recomandate) se aduce prin REST la prima deschidere. Altfel fiecare
 * pagina a site-ului ar plati doua interogari de produse pentru un panou pe
 * care majoritatea vizitatorilor nu il deschid.
 */
function ht_search_panel()
{
    ?>
    <div class="ht-search" id="htSearch">
        <div class="ht-search__panel">
            <div class="ht-wrapper">

                <form class="ht-search__form" role="search" method="get"
                      action="<?php echo esc_url(home_url('/')); ?>" data-ht-search-form>
                    <?php ht_icon('search', 'ht-icon ht-search__icon'); ?>

                    <label class="ht-visually-hidden" for="htSearchField">
                        <?php esc_html_e('Caută produse', 'herbal-therapy'); ?>
                    </label>
                    <input class="ht-search__field" id="htSearchField" type="search" name="s"
                           value="<?php echo esc_attr(get_search_query()); ?>"
                           placeholder="<?php esc_attr_e('Caută un produs, o categorie sau un cod', 'herbal-therapy'); ?>"
                           autocomplete="off" data-ht-search-input/>

                    <button class="ht-search__clear" type="button" data-ht-search-clear hidden>
                        <?php ht_icon('close'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Golește câmpul', 'herbal-therapy'); ?></span>
                    </button>

                    <button class="ht-search__submit" type="submit">
                        <?php esc_html_e('Caută', 'herbal-therapy'); ?>
                    </button>

                    <button class="ht-search__close" type="button" data-ht-search-toggle>
                        <?php ht_icon('close'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Închide căutarea', 'herbal-therapy'); ?></span>
                    </button>
                </form>

                <div class="ht-search__body" data-ht-search-body></div>

            </div>
        </div>

        <div class="ht-search__backdrop" data-ht-search-close></div>
    </div>
    <?php
}

/* ---------------------------------------------------------------------------
 * REST
 * ------------------------------------------------------------------------ */

/**
 * Ruta de sugestii. Publica: nu atinge date de sesiune si intoarce doar
 * continut deja publicat.
 */
function ht_search_register_rest()
{
    register_rest_route('herbal-therapy/v1', '/search', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ht_search_rest',
        'permission_callback' => '__return_true',
        'args'                => array(
            'q' => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            /*
             * Polylang citeste 'lang' pe 'rest_pre_dispatch' si isi seteaza
             * limba curenta; de acolo o iau ht_search_lang() si link-urile.
             */
            'lang' => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
            ),
        ),
    ));
}

add_action('rest_api_init', 'ht_search_register_rest');

/**
 * GET - sugestiile pentru un termen.
 *
 * @param WP_REST_Request $request Cererea.
 *
 * @return WP_REST_Response
 */
function ht_search_rest($request)
{
    $query = trim((string)$request->get_param('q'));
    $results = ht_search_results($query);

    return rest_ensure_response(array(
        'query' => $query,
        'total' => $results['total'],
        'url'   => ht_search_url($query),
        'html'  => $results['html'],
    ));
}
