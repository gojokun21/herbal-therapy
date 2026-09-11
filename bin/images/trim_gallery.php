<?php
/**
 * Pasul 6 - lasa in galeria fiecarui produs doar prima imagine si le scoate pe
 * restul (imaginea reprezentativa nu e atinsa).
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/trim_gallery.php [--dry-run] [--delete-media] [--only id,id]
 *
 * Dupa upload_to_wp.php galeria incepe cu vechiul packshot, urmat de pozele
 * ramase din importul Shopify; designul paginii de produs vrea doar
 * prezentarea + packshot-ul, deci restul pleaca.
 *
 * Implicit doar meta `_product_image_gallery` e rescrisa; atasamentele raman
 * in Media. Cu --delete-media se sterg si atasamentele scoase, dar numai cele
 * care nu mai sunt folosite de niciun alt produs (thumbnail sau galerie).
 * Valorile vechi se scriu in data/gallery_backup.json, ca sa se poata reveni.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/trim_gallery.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv   = isset($argv) ? $argv : array();
$ht_dry    = in_array('--dry-run', $ht_argv, true);
$ht_delete = in_array('--delete-media', $ht_argv, true);
$ht_only   = array();
$ht_pos    = array_search('--only', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_only = array_filter(array_map('intval', explode(',', $ht_argv[$ht_pos + 1])));
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
 * Produsele cu mai mult de o imagine in galerie
 * ------------------------------------------------------------------------ */

global $wpdb;

$ht_rows = $wpdb->get_results(
    "SELECT p.ID, p.post_title, pm.meta_value AS gallery
       FROM {$wpdb->posts} p
       JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_product_image_gallery'
      WHERE p.post_type IN ('product', 'product_variation')
        AND p.post_status <> 'trash'
        AND pm.meta_value LIKE '%,%'
      ORDER BY p.ID"
);

$ht_backup_file = __DIR__ . '/data/gallery_backup.json';
$ht_backup      = file_exists($ht_backup_file) ? (array) json_decode(file_get_contents($ht_backup_file), true) : array();
$ht_dropped     = array();   // attachment id => [product id, ...]
$ht_changed     = 0;

foreach ($ht_rows as $ht_row) {
    $ht_pid = (int) $ht_row->ID;
    if ($ht_only && !in_array($ht_pid, $ht_only, true)) {
        continue;
    }

    $ht_ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $ht_row->gallery)))));
    if (count($ht_ids) < 2) {
        continue;
    }

    $ht_first = $ht_ids[0];
    $ht_rest  = array_slice($ht_ids, 1);
    foreach ($ht_rest as $ht_att) {
        $ht_dropped[$ht_att][] = $ht_pid;
    }

    echo ($ht_dry ? '  ~ ' : '  + ') . "{$ht_pid} {$ht_row->post_title}: pastrez {$ht_first}, scot " . implode(',', $ht_rest) . "\n";
    $ht_changed++;

    if ($ht_dry) {
        continue;
    }

    if (!isset($ht_backup[$ht_pid])) {
        $ht_backup[$ht_pid] = (string) $ht_row->gallery;
    }

    $ht_product = wc_get_product($ht_pid);
    if ($ht_product) {
        $ht_product->set_gallery_image_ids(array($ht_first));
        $ht_product->save();
    } else {
        update_post_meta($ht_pid, '_product_image_gallery', (string) $ht_first);
    }
}

if (!$ht_dry && $ht_changed) {
    if (!is_dir(dirname($ht_backup_file))) {
        mkdir(dirname($ht_backup_file), 0777, true);
    }
    file_put_contents($ht_backup_file, json_encode($ht_backup, JSON_PRETTY_PRINT));
}

echo "\nProduse " . ($ht_dry ? 'de modificat' : 'modificate') . ": {$ht_changed}; atasamente scoase din galerii: " . count($ht_dropped) . "\n";

/* ---------------------------------------------------------------------------
 * Optional: stergerea atasamentelor ramase fara nicio folosinta
 * ------------------------------------------------------------------------ */

if ($ht_delete && !$ht_dry) {
    /* si atasamentele scoase la o rulare anterioara, dupa data/gallery_backup.json */
    foreach ($ht_backup as $ht_pid => $ht_old) {
        $ht_ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $ht_old)))));
        foreach (array_slice($ht_ids, 1) as $ht_att) {
            $ht_dropped[$ht_att][] = (int) $ht_pid;
        }
    }
}

if (!$ht_delete || !$ht_dropped) {
    if ($ht_dropped) {
        echo "Atasamentele raman in Media (ruleaza cu --delete-media ca sa le stergi pe cele nefolosite).\n";
    }
    exit(0);
}

$ht_in_use = array();
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'") as $ht_v) {
    $ht_in_use[(int) $ht_v] = true;
}
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value <> ''") as $ht_v) {
    foreach (explode(',', $ht_v) as $ht_id) {
        $ht_in_use[(int) $ht_id] = true;
    }
}

$ht_deleted = 0;
$ht_kept    = 0;
foreach (array_keys($ht_dropped) as $ht_att) {
    if (isset($ht_in_use[$ht_att]) && !$ht_dry) {
        $ht_kept++;
        continue;
    }
    if ($ht_dry) {
        /* in dry-run galeria nu e inca rescrisa, deci "in folosinta" nu spune nimic */
        continue;
    }
    if (wp_delete_attachment($ht_att, true)) {
        $ht_deleted++;
    } else {
        echo "  ! nu am putut sterge atasamentul {$ht_att}\n";
    }
}

if ($ht_dry) {
    echo "Dry-run: " . count($ht_dropped) . " atasamente ar fi candidate la stergere.\n";
} else {
    echo "Atasamente sterse: {$ht_deleted}; pastrate (inca folosite): {$ht_kept}\n";
}
