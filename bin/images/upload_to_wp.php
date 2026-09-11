<?php
/**
 * Pasul 5 - exporta cadrele din Figma (JPG 1000x1000) si le pune ca imagine
 * reprezentativa pe produsul RO si pe perechea lui RU.
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/upload_to_wp.php [--dry-run] [--force] [--only slug,slug]
 *
 * Citeste data/figma_nodes.json (scris de pluginul Figma: slug -> {ro, ru}) si
 * data/products.json (id-urile produselor). Are nevoie de FIGMA_TOKEN in
 * bin/images/.env (token personal: Figma > Settings > Personal access tokens,
 * cu drept de citire pe fisiere).
 *
 * Fiecare atasament nou primeste meta `_ht_figma_node` cu id-ul cadrului, la
 * fel ca exporturile de pana acum, asa ca export_products.php nu-l va mai
 * propune. Vechea imagine reprezentativa se muta in galerie, sa nu se piarda.
 * Scriptul e idempotent: un produs deja legat de acelasi cadru se sare.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/upload_to_wp.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv  = isset($argv) ? $argv : array();
$ht_dry   = in_array('--dry-run', $ht_argv, true);
$ht_force = in_array('--force', $ht_argv, true);
$ht_only  = array();
$ht_pos   = array_search('--only', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_only = array_filter(array_map('trim', explode(',', $ht_argv[$ht_pos + 1])));
}

/* ---------------------------------------------------------------------------
 * Configuratie (.env)
 * ------------------------------------------------------------------------ */

function ht_read_env($file)
{
    $out = array();
    if (!file_exists($file)) {
        return $out;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ('' === $line || '#' === $line[0] || false === strpos($line, '=')) {
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        $out[trim($k)] = trim(trim($v), "\"'");
    }
    return $out;
}

$ht_env  = array_merge(ht_read_env(dirname(__DIR__) . '/import/.env'), ht_read_env(__DIR__ . '/.env'));
$ht_tok  = getenv('FIGMA_TOKEN') ?: (isset($ht_env['FIGMA_TOKEN']) ? $ht_env['FIGMA_TOKEN'] : '');
$ht_file = getenv('FIGMA_FILE_KEY') ?: (isset($ht_env['FIGMA_FILE_KEY']) ? $ht_env['FIGMA_FILE_KEY'] : 'kcN6mD2YKtUttVTf3f26i2');

if (!$ht_tok) {
    exit("Lipseste FIGMA_TOKEN (bin/images/.env).\n");
}

$ht_nodes_file = __DIR__ . '/data/figma_nodes.json';
$ht_prod_file  = __DIR__ . '/data/products.json';
if (!file_exists($ht_nodes_file) || !file_exists($ht_prod_file)) {
    exit("Lipsesc data/figma_nodes.json sau data/products.json.\n");
}
$ht_nodes    = json_decode(file_get_contents($ht_nodes_file), true);
$ht_products = array();
foreach (json_decode(file_get_contents($ht_prod_file), true) as $ht_row) {
    $ht_products[$ht_row['slug']] = $ht_row;
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
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/* ---------------------------------------------------------------------------
 * Ce e de facut
 * ------------------------------------------------------------------------ */

$ht_jobs = array();   // node id => [product id, lang, slug, titlu]
foreach ($ht_nodes as $ht_slug => $ht_pair) {
    if ($ht_only && !in_array($ht_slug, $ht_only, true)) {
        continue;
    }
    if (!isset($ht_products[$ht_slug])) {
        echo "  ! {$ht_slug}: nu e in products.json, sar\n";
        continue;
    }
    $ht_p = $ht_products[$ht_slug];
    $ht_targets = array('ro' => (int) $ht_p['id'], 'ru' => (int) $ht_p['ru_id']);
    foreach ($ht_targets as $ht_lang => $ht_pid) {
        if (!$ht_pid || empty($ht_pair[$ht_lang])) {
            continue;
        }
        $ht_current = (int) get_post_thumbnail_id($ht_pid);
        if (!$ht_force && $ht_current && get_post_meta($ht_current, '_ht_figma_node', true) === $ht_pair[$ht_lang]) {
            echo "  = {$ht_slug} {$ht_lang}: deja legat de {$ht_pair[$ht_lang]}\n";
            continue;
        }
        $ht_jobs[$ht_pair[$ht_lang]] = array(
            'pid'   => $ht_pid,
            'lang'  => $ht_lang,
            'slug'  => $ht_slug,
            'title' => 'ru' === $ht_lang && $ht_p['title_ru'] ? $ht_p['title_ru'] : $ht_p['title_ro'],
            'file'  => sanitize_file_name($ht_p['title_ro']) . '-' . $ht_lang . '.jpg',
        );
    }
}

if (!$ht_jobs) {
    exit("Nimic de urcat.\n");
}
echo count($ht_jobs) . " imagini de exportat din Figma" . ($ht_dry ? ' (dry-run)' : '') . "\n";

/* ---------------------------------------------------------------------------
 * Export din Figma, in loturi de 40 de noduri
 * ------------------------------------------------------------------------ */

$ht_urls = array();
foreach (array_chunk(array_keys($ht_jobs), 40) as $ht_chunk) {
    $ht_api = 'https://api.figma.com/v1/images/' . rawurlencode($ht_file)
        . '?ids=' . rawurlencode(implode(',', $ht_chunk)) . '&format=jpg&scale=1';
    $ht_res = wp_remote_get($ht_api, array('headers' => array('X-Figma-Token' => $ht_tok), 'timeout' => 120));
    if (is_wp_error($ht_res)) {
        exit('Figma: ' . $ht_res->get_error_message() . "\n");
    }
    $ht_body = json_decode(wp_remote_retrieve_body($ht_res), true);
    if (empty($ht_body['images'])) {
        exit('Figma: raspuns neasteptat: ' . wp_remote_retrieve_body($ht_res) . "\n");
    }
    $ht_urls = array_merge($ht_urls, $ht_body['images']);
}

/* ---------------------------------------------------------------------------
 * Urcare in WordPress
 * ------------------------------------------------------------------------ */

foreach ($ht_jobs as $ht_node => $ht_job) {
    $ht_label = "{$ht_job['slug']} {$ht_job['lang']}";
    if (empty($ht_urls[$ht_node])) {
        echo "  ! {$ht_label}: Figma nu a exportat nodul {$ht_node}\n";
        continue;
    }
    if ($ht_dry) {
        echo "  + {$ht_label}: {$ht_node} -> produs {$ht_job['pid']} ({$ht_job['file']})\n";
        continue;
    }

    $ht_tmp = download_url($ht_urls[$ht_node], 120);
    if (is_wp_error($ht_tmp)) {
        echo "  ! {$ht_label}: " . $ht_tmp->get_error_message() . "\n";
        continue;
    }

    $ht_att = media_handle_sideload(
        array('name' => $ht_job['file'], 'tmp_name' => $ht_tmp),
        $ht_job['pid'],
        $ht_job['title']
    );
    if (is_wp_error($ht_att)) {
        @unlink($ht_tmp);
        echo "  ! {$ht_label}: " . $ht_att->get_error_message() . "\n";
        continue;
    }

    update_post_meta($ht_att, '_wp_attachment_image_alt', $ht_job['title']);
    update_post_meta($ht_att, '_ht_figma_node', $ht_node);
    if (function_exists('pll_set_post_language')) {
        pll_set_post_language($ht_att, $ht_job['lang']);
    }

    /* vechea imagine reprezentativa ramane in galerie */
    $ht_old = (int) get_post_thumbnail_id($ht_job['pid']);
    if ($ht_old && $ht_old !== $ht_att) {
        $ht_gallery = array_filter(array_map('intval', explode(',', (string) get_post_meta($ht_job['pid'], '_product_image_gallery', true))));
        if (!in_array($ht_old, $ht_gallery, true)) {
            array_unshift($ht_gallery, $ht_old);
            update_post_meta($ht_job['pid'], '_product_image_gallery', implode(',', $ht_gallery));
        }
    }
    set_post_thumbnail($ht_job['pid'], $ht_att);

    echo "  + {$ht_label}: atasament {$ht_att} pe produsul {$ht_job['pid']}\n";
}

echo "Gata.\n";
