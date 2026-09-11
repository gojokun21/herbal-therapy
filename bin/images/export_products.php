<?php
/**
 * Pasul 0 - scoate din WooCommerce produsele care nu au inca imagine de
 * prezentare din Figma si scrie data/products.json.
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/export_products.php [--all] [--only slug,slug]
 *
 * Un produs e considerat "gata" cand imaginea lui reprezentativa are meta
 * `_ht_figma_node` (asa sunt marcate exporturile din fisierul Figma). Cu
 * --all se exporta toate produsele, indiferent de asta.
 *
 * Pentru fiecare produs se scriu: slug, titlurile RO si RU, categoria,
 * descrierea scurta, beneficiile ACF si calea poza de produs (pack-shot-ul
 * curent, cel de pe fundal alb). Textele de mai departe se scriu pe baza lor.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/export_products.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv = isset($argv) ? $argv : array();
$ht_all  = in_array('--all', $ht_argv, true);
$ht_only = array();
$ht_pos  = array_search('--only', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_only = array_filter(array_map('trim', explode(',', $ht_argv[$ht_pos + 1])));
}

/* ---------------------------------------------------------------------------
 * WordPress
 * ------------------------------------------------------------------------ */

$ht_root = __DIR__;
while (!file_exists($ht_root . '/wp-load.php')) {
    $ht_parent = dirname($ht_root);
    if ($ht_parent === $ht_root) {
        exit('Nu am gasit wp-load.php pornind de la ' . __DIR__ . ".\n");
    }
    $ht_root = $ht_parent;
}

$_SERVER['HTTP_HOST']      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_SERVER['SERVER_NAME']    = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/index.php';

if (!empty($_SERVER['HT_DB_HOST']) && !defined('DB_HOST')) {
    define('DB_HOST', (string) $_SERVER['HT_DB_HOST']);
    set_error_handler(function ($no, $str) {
        return false !== strpos($str, 'DB_HOST already defined');
    }, E_WARNING);
    require_once $ht_root . '/wp-load.php';
    restore_error_handler();
} else {
    require_once $ht_root . '/wp-load.php';
}

/* ---------------------------------------------------------------------------
 * Export
 * ------------------------------------------------------------------------ */

$ht_products = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'lang'           => 'ro',
));

$ht_rows = array();
$ht_skipped = 0;

foreach ($ht_products as $ht_p) {
    if ($ht_only && !in_array($ht_p->post_name, $ht_only, true)) {
        continue;
    }

    $ht_thumb = (int) get_post_thumbnail_id($ht_p->ID);
    $ht_node  = $ht_thumb ? get_post_meta($ht_thumb, '_ht_figma_node', true) : '';

    if ($ht_node && !$ht_all) {
        $ht_skipped++;
        continue;
    }

    $ht_file = $ht_thumb ? get_attached_file($ht_thumb) : '';
    if (!$ht_file || !file_exists($ht_file)) {
        fwrite(STDERR, "  ! {$ht_p->post_name}: nu are poza de produs, sar\n");
        continue;
    }

    $ht_ru_id = function_exists('pll_get_post') ? (int) pll_get_post($ht_p->ID, 'ru') : 0;
    $ht_cats  = wp_get_post_terms($ht_p->ID, 'product_cat', array('fields' => 'names'));
    $ht_benef = function_exists('get_field') ? get_field('beneficii', $ht_p->ID) : array();
    $ht_benef = is_array($ht_benef) ? array_values(array_filter(array_map(function ($b) {
        return is_array($b) && isset($b['text']) ? trim($b['text']) : '';
    }, $ht_benef))) : array();

    $ht_rows[] = array(
        'id'        => $ht_p->ID,
        'ru_id'     => $ht_ru_id,
        'slug'      => $ht_p->post_name,
        'title_ro'  => $ht_p->post_title,
        'title_ru'  => $ht_ru_id ? get_the_title($ht_ru_id) : '',
        'category'  => $ht_cats ? $ht_cats[0] : '',
        'excerpt'   => trim(wp_strip_all_tags($ht_p->post_excerpt)),
        'benefits'  => array_slice($ht_benef, 0, 6),
        'packshot'  => $ht_file,
        'thumb_id'  => $ht_thumb,
        'figma_node'=> $ht_node ? $ht_node : null,
    );
}

$ht_out = __DIR__ . '/data/products.json';
if (!is_dir(dirname($ht_out))) {
    mkdir(dirname($ht_out), 0777, true);
}
file_put_contents($ht_out, json_encode($ht_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo count($ht_rows) . " produse scrise in data/products.json";
echo $ht_skipped ? " ({$ht_skipped} au deja imagine Figma, sarite)\n" : "\n";
