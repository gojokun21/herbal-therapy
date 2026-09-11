<?php
/**
 * Packshot-uri: pasul C - urca JPG-urile din data/packshots/ in Media si le
 * pune ca singura imagine din galeria produsului RO si a perechii RU.
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/upload_packshots.php [--dry-run] [--force] [--only slug,slug]
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/upload_packshots.php --restore [--only slug,slug]
 *
 * Citeste data/gallery_products.json (export_gallery.php) si urca doar
 * produsele care au data/packshots/<slug>.jpg (packshots.py). Atasamentul nou
 * primeste meta `_ht_packshot_src` = id-ul vechii poze din galerie, deci un
 * produs a carui galerie incepe deja cu un packshot regenerat se sare (fara
 * --force). Imaginea reprezentativa nu e atinsa.
 *
 * Galeriile vechi (RO si RU) se scriu in data/packshots_backup.json;
 * --restore le pune la loc. Pozele vechi raman in Media: se pot curata dupa
 * aceea cu bin/media-orphans.php.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/upload_packshots.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv    = isset($argv) ? $argv : array();
$ht_dry     = in_array('--dry-run', $ht_argv, true);
$ht_force   = in_array('--force', $ht_argv, true);
$ht_restore = in_array('--restore', $ht_argv, true);
$ht_only    = array();
$ht_pos     = array_search('--only', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_only = array_filter(array_map('trim', explode(',', $ht_argv[$ht_pos + 1])));
}

$ht_prod_file   = __DIR__ . '/data/gallery_products.json';
$ht_backup_file = __DIR__ . '/data/packshots_backup.json';
$ht_img_dir     = __DIR__ . '/data/packshots';
if (!file_exists($ht_prod_file)) {
    exit("Lipseste data/gallery_products.json - ruleaza intai export_gallery.php.\n");
}
$ht_products = json_decode(file_get_contents($ht_prod_file), true);
$ht_backup   = file_exists($ht_backup_file) ? json_decode(file_get_contents($ht_backup_file), true) : array();
if (!is_array($ht_backup)) {
    $ht_backup = array();
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

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

function ht_gallery_ids($pid)
{
    return array_values(array_filter(array_map('intval', explode(',', (string) get_post_meta($pid, '_product_image_gallery', true)))));
}

function ht_set_gallery($pid, array $ids)
{
    if ($ids) {
        update_post_meta($pid, '_product_image_gallery', implode(',', $ids));
    } else {
        delete_post_meta($pid, '_product_image_gallery');
    }
    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($pid);
    }
}

/* ---------------------------------------------------------------------------
 * --restore
 * ------------------------------------------------------------------------ */

if ($ht_restore) {
    $ht_n = 0;
    foreach ($ht_backup as $ht_slug => $ht_old) {
        if ($ht_only && !in_array($ht_slug, $ht_only, true)) {
            continue;
        }
        foreach (array('ro', 'ru') as $ht_lang) {
            if (empty($ht_old[$ht_lang]['id'])) {
                continue;
            }
            $ht_ids = array_filter(array_map('intval', explode(',', (string) $ht_old[$ht_lang]['gallery'])));
            if ($ht_dry) {
                echo "  ~ {$ht_slug} {$ht_lang}: produs {$ht_old[$ht_lang]['id']} <- galerie '" . implode(',', $ht_ids) . "'\n";
            } else {
                ht_set_gallery((int) $ht_old[$ht_lang]['id'], $ht_ids);
            }
        }
        $ht_n++;
        if (!$ht_dry) {
            unset($ht_backup[$ht_slug]);
        }
    }
    if (!$ht_dry) {
        file_put_contents($ht_backup_file, json_encode($ht_backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    echo "{$ht_n} produse " . ($ht_dry ? 'de readus' : 'readuse') . " la galeria veche.\n";
    exit;
}

/* ---------------------------------------------------------------------------
 * Urcare
 * ------------------------------------------------------------------------ */

$ht_done = 0;
$ht_skip = 0;

foreach ($ht_products as $ht_p) {
    $ht_slug = $ht_p['slug'];
    if ($ht_only && !in_array($ht_slug, $ht_only, true)) {
        continue;
    }
    $ht_jpg = $ht_img_dir . '/' . $ht_slug . '.jpg';
    if (!file_exists($ht_jpg)) {
        continue;
    }

    $ht_pid   = (int) $ht_p['id'];
    $ht_ru    = (int) $ht_p['ru_id'];
    $ht_cur   = ht_gallery_ids($ht_pid);
    $ht_first = $ht_cur ? $ht_cur[0] : 0;

    if (!$ht_force && $ht_first && get_post_meta($ht_first, '_ht_packshot_src', true)) {
        $ht_skip++;
        continue;
    }

    if ($ht_dry) {
        echo "  + {$ht_slug}: {$ht_jpg} -> produs {$ht_pid}" . ($ht_ru ? " + {$ht_ru}" : '') . " (inlocuieste " . implode(',', $ht_cur) . ")\n";
        $ht_done++;
        continue;
    }

    /* media_handle_sideload muta fisierul: lucram pe o copie temporara */
    $ht_tmp = wp_tempnam($ht_slug . '.jpg');
    copy($ht_jpg, $ht_tmp);
    $ht_att = media_handle_sideload(
        array('name' => $ht_slug . '.jpg', 'tmp_name' => $ht_tmp),
        $ht_pid,
        $ht_p['title_ro']
    );
    if (is_wp_error($ht_att)) {
        @unlink($ht_tmp);
        echo "  ! {$ht_slug}: " . $ht_att->get_error_message() . "\n";
        continue;
    }

    update_post_meta($ht_att, '_wp_attachment_image_alt', $ht_p['title_ro']);
    update_post_meta($ht_att, '_ht_packshot_src', (int) $ht_p['att_id']);
    if (function_exists('pll_set_post_language')) {
        pll_set_post_language($ht_att, 'ro');
    }

    if (!isset($ht_backup[$ht_slug])) {
        $ht_backup[$ht_slug] = array(
            'ro' => array('id' => $ht_pid, 'gallery' => implode(',', $ht_cur)),
            'ru' => array('id' => $ht_ru, 'gallery' => $ht_ru ? implode(',', ht_gallery_ids($ht_ru)) : ''),
        );
        file_put_contents($ht_backup_file, json_encode($ht_backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    ht_set_gallery($ht_pid, array($ht_att));
    if ($ht_ru) {
        ht_set_gallery($ht_ru, array($ht_att));
    }

    echo "  + {$ht_slug}: atasament {$ht_att} pe produsele {$ht_pid}" . ($ht_ru ? ", {$ht_ru}" : '') . "\n";
    $ht_done++;
}

echo "{$ht_done} produse " . ($ht_dry ? 'de urcat' : 'urcate') . ($ht_skip ? ", {$ht_skip} aveau deja packshot regenerat" : '') . ".\n";
