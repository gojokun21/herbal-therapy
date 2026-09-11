<?php
/**
 * Adrese scurte pentru magazin: fara /product/ si /product-category/.
 *
 * WooCommerce publica produsele la /product/slug/ si categoriile la
 * /product-category/slug/. Aici scoatem cele doua baze din adrese, in ambele
 * limbi: /siropuri/ pe romana, /ru/siropy/ pe rusa.
 *
 * Cum functioneaza:
 *
 * - Legaturile (permalink si term_link) se rescriu fara baza.
 * - Pentru categorii se adauga cate un set de reguli de rescriere per termen,
 *   pe filtrul 'product_cat_rewrite_rules', INAINTE ca Polylang sa-l ruleze
 *   (prioritatea lui e 10): Polylang genereaza singur si varianta cu prefixul
 *   de limba - (ru)/siropy/?$ -> lang=ru&product_cat=siropy - si pastreaza
 *   regula fara prefix, ca redirectarea canonica intre limbi sa functioneze.
 * - Produsele nu au nevoie de reguli proprii: /slug/ cade pe regula generica
 *   de articole (name=slug), iar filtrul 'request' o muta pe post_type
 *   'product' cand exista un produs cu acel slug.
 * - Adresele vechi, cu baza, raman valide si fac 301 catre cele scurte.
 *
 * Coliziuni de slug: paginile castiga intotdeauna (regulile lor stau primele),
 * o categorie castiga in fata unui produs cu acelasi slug, iar un articol
 * castiga in fata unui produs. Practic slug-urile din magazin sunt unice.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Versiunea regulilor: se schimba cand se modifica forma lor, ca sa se faca
 * flush o singura data, la primul request dupa deploy.
 */
if (!defined('HT_PERMALINKS_VERSION')) {
    define('HT_PERMALINKS_VERSION', 'ht-permalinks-1');
}

/* ---------------------------------------------------------------------------
 * Bazele configurate in WooCommerce
 * ------------------------------------------------------------------------ */

/**
 * Bazele de permalink ale magazinului, fara slash-uri de capat.
 *
 * Se citesc direct din optiunea 'woocommerce_permalinks', ca functia sa
 * mearga si cand WooCommerce nu si-a incarcat inca helperele.
 *
 * @return array Cheile 'product' si 'category'.
 */
function ht_permalink_bases()
{
    $options = (array)get_option('woocommerce_permalinks', array());

    return array(
        'product'  => !empty($options['product_base']) ? trim($options['product_base'], '/') : 'product',
        'category' => !empty($options['category_base']) ? trim($options['category_base'], '/') : 'product-category',
    );
}

/**
 * O baza care contine %placeholder% (ex. shop/%product_cat%) nu se poate
 * scoate cu inlocuire de segment, deci o lasam in pace.
 *
 * @param string $base Baza.
 *
 * @return bool
 */
function ht_permalink_base_is_plain($base)
{
    return '' !== $base && false === strpos($base, '%');
}

/* ---------------------------------------------------------------------------
 * Inlocuirea segmentului de baza (functie pura, testata unitar)
 * ------------------------------------------------------------------------ */

/**
 * Scoate primul segment "/baza/" dintr-o adresa, pastrand ambele slash-uri
 * ca unul singur: /ru/product-category/siropy/ -> /ru/siropy/.
 *
 * Inlocuirea cere segmentul complet, intre slash-uri, deci un slug care doar
 * contine baza (/product-lovers/) ramane neatins. Exact aici gresea Rank
 * Math: stergea si slash-urile si lipea bucatile (/rusiropy/).
 *
 * @param string $url  Adresa sau calea.
 * @param string $base Baza de scos, fara slash-uri de capat.
 *
 * @return string
 */
function ht_strip_url_base($url, $base)
{
    $base = trim((string)$base, '/');

    if ('' === $base) {
        return $url;
    }

    return (string)preg_replace('#/' . preg_quote($base, '#') . '/#', '/', (string)$url, 1);
}

/* ---------------------------------------------------------------------------
 * Legaturile
 * ------------------------------------------------------------------------ */

/**
 * Permalink-ul produselor, fara baza.
 *
 * Prioritatea 99 lasa WooCommerce si Polylang sa construiasca intai adresa
 * completa (cu /ru/ cu tot), apoi scoatem doar segmentul de baza.
 *
 * @param string  $link Adresa.
 * @param WP_Post $post Postarea.
 *
 * @return string
 */
function ht_product_link_without_base($link, $post)
{
    if ('product' !== $post->post_type) {
        return $link;
    }

    $bases = ht_permalink_bases();

    if (!ht_permalink_base_is_plain($bases['product'])) {
        return $link;
    }

    return ht_strip_url_base($link, $bases['product']);
}

add_filter('post_type_link', 'ht_product_link_without_base', 99, 2);

/**
 * Adresa categoriilor de produse, fara baza.
 *
 * @param string  $link     Adresa.
 * @param WP_Term $term     Termenul.
 * @param string  $taxonomy Taxonomia.
 *
 * @return string
 */
function ht_category_link_without_base($link, $term, $taxonomy)
{
    if ('product_cat' !== $taxonomy) {
        return $link;
    }

    $bases = ht_permalink_bases();

    if (!ht_permalink_base_is_plain($bases['category'])) {
        return $link;
    }

    return ht_strip_url_base($link, $bases['category']);
}

add_filter('term_link', 'ht_category_link_without_base', 99, 3);

/* ---------------------------------------------------------------------------
 * Regulile de rescriere pentru categorii
 * ------------------------------------------------------------------------ */

/**
 * Regulile scurte pentru o lista de cai de categorie (functie pura).
 *
 * Caile mai adanci intra primele, ca "parinte/copil" sa nu fie umbrit de
 * regula parintelui. Interogarea foloseste slug-ul frunzei: acolo ii ajunge
 * lui WordPress ca sa gaseasca termenul.
 *
 * @param array $paths Perechi cale => slug (ex. 'ingrijire/creme' => 'creme').
 *
 * @return array Reguli de rescriere, in formatul WP_Rewrite.
 */
function ht_category_base_free_rules($paths)
{
    $ordered = array_keys((array)$paths);

    usort($ordered, function ($a, $b) {
        $depth = substr_count($b, '/') - substr_count($a, '/');

        return 0 !== $depth ? $depth : strcmp($a, $b);
    });

    $rules = array();

    foreach ($ordered as $path) {
        $slug = $paths[$path];
        $query = 'index.php?product_cat=' . $slug;

        $rules[$path . '/?$'] = $query;
        $rules[$path . '/page/([0-9]{1,})/?$'] = $query . '&paged=$matches[1]';
        $rules[$path . '/feed/(feed|rdf|rss|rss2|atom)/?$'] = $query . '&feed=$matches[1]';
        $rules[$path . '/(feed|rdf|rss|rss2|atom)/?$'] = $query . '&feed=$matches[1]';
    }

    return $rules;
}

/**
 * Caile tuturor categoriilor de produse, din toate limbile.
 *
 * 'lang' => '' opreste filtrarea Polylang: la flush ne trebuie si termenii
 * rusesti, altfel regulile lor n-ar exista si /ru/siropy/ ar da 404 - exact
 * defectul cu care am plecat la drum.
 *
 * @return array Perechi cale => slug.
 */
function ht_category_paths()
{
    if (!taxonomy_exists('product_cat')) {
        return array();
    }

    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'lang'       => '',
    ));

    if (is_wp_error($terms) || empty($terms)) {
        return array();
    }

    $by_id = array();

    foreach ($terms as $term) {
        $by_id[(int)$term->term_id] = $term;
    }

    $paths = array();

    foreach ($terms as $term) {
        $segments = array($term->slug);
        $parent = (int)$term->parent;

        while ($parent && isset($by_id[$parent])) {
            array_unshift($segments, $by_id[$parent]->slug);
            $parent = (int)$by_id[$parent]->parent;
        }

        $paths[implode('/', $segments)] = $term->slug;
    }

    return $paths;
}

/**
 * Adauga regulile scurte inaintea celor cu baza, la flush.
 *
 * Prioritatea 5 e sub cea a Polylang (10): el primeste si regulile noastre si
 * le dubleaza cu prefixul de limba. Regulile taxonomiilor stau oricum
 * inaintea regulii generice de articole, deci o categorie nu poate fi
 * confundata cu un articol.
 *
 * @param array $rules Regulile taxonomiei product_cat.
 *
 * @return array
 */
function ht_category_rewrite_rules($rules)
{
    $bases = ht_permalink_bases();

    if (!ht_permalink_base_is_plain($bases['category'])) {
        return $rules;
    }

    return array_merge(ht_category_base_free_rules(ht_category_paths()), (array)$rules);
}

add_filter('product_cat_rewrite_rules', 'ht_category_rewrite_rules', 5);

/* ---------------------------------------------------------------------------
 * Produsele pe adresa scurta
 * ------------------------------------------------------------------------ */

/**
 * Muta cererile /slug/ pe produs cand slug-ul e al unui produs.
 *
 * Adresa scurta a unui produs cade pe regula generica de articole, care seteaza
 * doar 'name'. Daca exista un produs publicat cu acel slug si niciun articol
 * la fel, cererea devine una de produs. Merge identic si sub /ru/: regula
 * prefixata a Polylang pastreaza 'name' si adauga 'lang'.
 *
 * Slug-urile vechi ale produselor (meta '_wp_old_slug', scrisa de WP la
 * schimbarea slug-ului) primesc doar 'post_type' = 'product': cererea ramane
 * 404, dar wp_old_slug_redirect() cauta atunci printre produse si face 301
 * catre adresa noua. Fara asta ar cauta doar printre articole si adresa veche
 * ar da 404.
 *
 * @param array $vars Variabilele cererii.
 *
 * @return array
 */
function ht_product_request_without_base($vars)
{
    if (!empty($vars['post_type']) || empty($vars['name'])) {
        return $vars;
    }

    if (!post_type_exists('product')) {
        return $vars;
    }

    /* un articol cu acelasi slug isi pastreaza adresa */
    $post = get_page_by_path($vars['name'], OBJECT, 'post');

    if ($post instanceof WP_Post && 'publish' === $post->post_status) {
        return $vars;
    }

    $product = get_page_by_path($vars['name'], OBJECT, 'product');

    if (!$product instanceof WP_Post || 'publish' !== $product->post_status) {
        if (ht_product_has_old_slug($vars['name'])) {
            $vars['post_type'] = 'product';
        }

        return $vars;
    }

    $vars['post_type'] = 'product';
    $vars['product'] = $vars['name'];

    return $vars;
}

/**
 * Exista un produs publicat care a avut candva acest slug?
 *
 * Aceeasi cautare ca _find_post_by_old_slug() din WP, restransa la produse.
 *
 * @param string $slug Slug-ul cerut.
 *
 * @return bool
 */
function ht_product_has_old_slug($slug)
{
    global $wpdb;

    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_wp_old_slug' AND pm.meta_value = %s
           AND p.post_type = 'product' AND p.post_status = 'publish'
         LIMIT 1",
        $slug
    ));

    return (int)$id > 0;
}

add_filter('request', 'ht_product_request_without_base');

/* ---------------------------------------------------------------------------
 * Adresele vechi fac 301 catre cele scurte
 * ------------------------------------------------------------------------ */

/**
 * Tinta redirectarii pentru o cerere cu baza in cale (functie pura).
 *
 * @param string $uri  Calea ceruta, cu query string cu tot.
 * @param string $base Baza de scos.
 *
 * @return string Calea scurta sau sir gol daca nu e nimic de redirectat.
 */
function ht_base_redirect_target($uri, $base)
{
    $base = trim((string)$base, '/');

    if ('' === $base || false === strpos((string)$uri, '/' . $base . '/')) {
        return '';
    }

    /* baza se cauta doar in cale, nu si in query string */
    $parts = explode('?', (string)$uri, 2);
    $path = ht_strip_url_base($parts[0], $base);

    if ($path === $parts[0]) {
        return '';
    }

    return isset($parts[1]) ? $path . '?' . $parts[1] : $path;
}

/**
 * Redirectarea 301 de pe adresele cu baza.
 *
 * Regulile WooCommerce pentru /product/ si /product-category/ raman
 * inregistrate, deci adresele vechi inca se rezolva; de aici le trimitem,
 * pastrand limba si restul caii: /ru/product-category/siropy/page/2/ ajunge
 * la /ru/siropy/page/2/.
 */
function ht_redirect_base_urls()
{
    if (!function_exists('is_product') || is_admin()) {
        return;
    }

    $is_product = is_product();

    if (!$is_product && !is_product_category()) {
        return;
    }

    $bases = ht_permalink_bases();
    $base = $is_product ? $bases['product'] : $bases['category'];

    if (!ht_permalink_base_is_plain($base)) {
        return;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string)wp_unslash($_SERVER['REQUEST_URI']) : '';
    $target = ht_base_redirect_target($uri, $base);

    if ('' === $target) {
        return;
    }

    wp_safe_redirect(home_url($target), 301);
    exit;
}

add_action('template_redirect', 'ht_redirect_base_urls', 1);

/* ---------------------------------------------------------------------------
 * Flush-ul regulilor
 * ------------------------------------------------------------------------ */

/**
 * Regulile per termen se invechesc cand categoriile se schimba.
 */
function ht_permalinks_flush()
{
    flush_rewrite_rules(false);
}

add_action('created_product_cat', 'ht_permalinks_flush');
add_action('edited_product_cat', 'ht_permalinks_flush');
add_action('delete_product_cat', 'ht_permalinks_flush');

/**
 * Un singur flush dupa deploy, cand versiunea regulilor s-a schimbat.
 *
 * Pe 'wp_loaded' taxonomiile si filtrele Polylang sunt la locul lor, deci
 * regulile se regenereaza complet, cu prefixele de limba cu tot.
 */
function ht_permalinks_maybe_flush()
{
    if (HT_PERMALINKS_VERSION === get_option('ht_permalinks_version')) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('ht_permalinks_version', HT_PERMALINKS_VERSION);
}

add_action('wp_loaded', 'ht_permalinks_maybe_flush', 20);
