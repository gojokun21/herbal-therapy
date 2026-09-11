<?php
/**
 * Packshot-uri: pasul A - scoate imaginea din galeria fiecarui produs RO si
 * scrie data/gallery_products.json.
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/export_gallery.php [--only slug,slug]
 *
 * Dupa upload_to_wp.php + trim_gallery.php galeria are o singura imagine:
 * pack-shot-ul (produsul pe alb). Formatele sunt amestecate (2000x2000,
 * 1254x1254, poze de telefon in portret, 471x530...). Scriptul aduna, pentru
 * fiecare produs RO: id-ul lui si al perechii RU, slug, titlu, atasamentul din
 * galerie si calea fisierului. Pe baza lui, packshots.py regenereaza pozele.
 *
 * Produsele fara imagine in galerie sunt raportate si sarite: nu avem de la
 * ce porni.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/export_gallery.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv = isset($argv) ? $argv : array();
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

if (!function_exists('wc_get_product')) {
    exit("WooCommerce nu e activ.\n");
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

foreach ($ht_products as $ht_p) {
    if ($ht_only && !in_array($ht_p->post_name, $ht_only, true)) {
        continue;
    }

    $ht_gallery = array_values(array_filter(array_map('intval', explode(',', (string) get_post_meta($ht_p->ID, '_product_image_gallery', true)))));
    $ht_att     = $ht_gallery ? $ht_gallery[0] : 0;
    $ht_file    = $ht_att ? get_attached_file($ht_att) : '';

    if (!$ht_file || !file_exists($ht_file)) {
        fwrite(STDERR, "  ! {$ht_p->post_name} (#{$ht_p->ID}): nu are imagine in galerie, sar\n");
        continue;
    }

    $ht_meta = wp_get_attachment_metadata($ht_att);

    $ht_rows[] = array(
        'id'       => $ht_p->ID,
        'ru_id'    => function_exists('pll_get_post') ? (int) pll_get_post($ht_p->ID, 'ru') : 0,
        'slug'     => $ht_p->post_name,
        'title_ro' => $ht_p->post_title,
        'att_id'   => $ht_att,
        'file'     => $ht_file,
        'width'    => isset($ht_meta['width']) ? (int) $ht_meta['width'] : 0,
        'height'   => isset($ht_meta['height']) ? (int) $ht_meta['height'] : 0,
        'extra'    => array_slice($ht_gallery, 1),
    );
}

$ht_out = __DIR__ . '/data/gallery_products.json';
if (!is_dir(dirname($ht_out))) {
    mkdir(dirname($ht_out), 0777, true);
}
file_put_contents($ht_out, json_encode($ht_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo count($ht_rows) . " produse scrise in data/gallery_products.json\n";
