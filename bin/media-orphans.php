<?php
/**
 * Pozele de produs ramase in Media fara nicio folosinta.
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/media-orphans.php            # doar lista
 *   HT_DB_HOST=127.0.0.1:10004 php bin/media-orphans.php --delete   # le sterge (fisiere + subdimensiuni)
 *   HT_DB_HOST=127.0.0.1:10004 php bin/media-orphans.php --csv out.csv
 *
 * Se uita doar la imaginile atasate unui produs sau fara parinte (pozele
 * paginilor si ale articolelor nu sunt atinse). O imagine e "orfana" daca nu e:
 *   - imagine reprezentativa sau in galeria vreunui produs / vreunei postari;
 *   - imaginea unei categorii (termmeta thumbnail_id);
 *   - intr-un camp ACF / de tema (postmeta cu id-ul sau cu numele fisierului);
 *   - in continutul vreunei postari (numele fisierului sau wp-image-ID);
 *   - logo, favicon, placeholder WooCommerce sau in vreo optiune.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/media-orphans.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv   = isset($argv) ? $argv : array();
$ht_delete = in_array('--delete', $ht_argv, true);
$ht_csv    = '';
$ht_pos    = array_search('--csv', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_csv = $ht_argv[$ht_pos + 1];
}

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

global $wpdb;

/* ---------------------------------------------------------------------------
 * Tot ce inseamna "folosit"
 * ------------------------------------------------------------------------ */

$ht_used = array();
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'") as $ht_v) {
    $ht_used[(int) $ht_v] = 'thumbnail';
}
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value <> ''") as $ht_v) {
    foreach (explode(',', $ht_v) as $ht_id) {
        $ht_used[(int) $ht_id] = 'galerie';
    }
}
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'thumbnail_id'") as $ht_v) {
    $ht_used[(int) $ht_v] = 'categorie';
}
foreach (array('site_logo', 'custom_logo', 'site_icon', 'woocommerce_placeholder_image') as $ht_k) {
    if ((int) get_option($ht_k)) {
        $ht_used[(int) get_option($ht_k)] = 'optiune ' . $ht_k;
    }
}

/* metadate (ACF etc.), continut si optiuni - cautate dupa id si dupa numele fisierului */
$ht_meta = $wpdb->get_results(
    "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
      WHERE meta_key NOT IN ('_thumbnail_id', '_product_image_gallery', '_wp_attached_file', '_wp_attachment_metadata',
                             '_wp_attachment_image_alt', '_wp_attachment_backup_sizes', '_edit_lock', '_edit_last',
                             '_ht_figma_node', '_wp_old_slug', '_wp_trash_meta_time', '_price', '_regular_price',
                             '_sale_price', '_product_version', '_sku', '_stock')
        AND meta_key NOT LIKE 'rank_math_%'
        AND meta_value <> ''"
);
$ht_content = $wpdb->get_results(
    "SELECT ID, post_type, post_content FROM {$wpdb->posts}
      WHERE post_type NOT IN ('attachment', 'revision') AND post_content <> ''"
);
$ht_options = $wpdb->get_results(
    "SELECT option_name, option_value FROM {$wpdb->options}
      WHERE option_name NOT LIKE '_transient%' AND option_name NOT LIKE '_site_transient%'
        AND option_name NOT IN ('rank_math_sitemap_cache_files', 'ai1wm_status')"
);

/**
 * Valoare simpla ("123"), element serializat (i:123; / "123") sau JSON.
 */
function ht_meta_has_id($value, $id)
{
    return (string) $id === trim((string) $value)
        || false !== strpos($value, ':' . $id . ';')
        || false !== strpos($value, '"' . $id . '"');
}

/* ---------------------------------------------------------------------------
 * Candidatii: imagini atasate unui produs sau fara parinte
 * ------------------------------------------------------------------------ */

$ht_atts = $wpdb->get_results(
    "SELECT a.ID, a.post_title, a.post_parent, a.post_mime_type, a.post_date, p.post_type AS parent_type, p.post_title AS parent_title
       FROM {$wpdb->posts} a
       LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_parent
      WHERE a.post_type = 'attachment' AND a.post_mime_type LIKE 'image/%'
        AND (a.post_parent = 0 OR p.post_type IN ('product', 'product_variation'))
      ORDER BY a.ID"
);

$ht_orphans = array();
$ht_kept    = array();

foreach ($ht_atts as $ht_a) {
    $ht_id = (int) $ht_a->ID;
    if (isset($ht_used[$ht_id])) {
        $ht_kept[$ht_id] = $ht_used[$ht_id];
        continue;
    }

    $ht_file = basename((string) get_attached_file($ht_id));
    $ht_stem = preg_replace('/\.[a-z0-9]+$/i', '', $ht_file);
    $ht_ref  = '';

    foreach ($ht_meta as $ht_m) {
        if ((int) $ht_m->post_id === $ht_id) {
            continue;
        }
        if (ht_meta_has_id($ht_m->meta_value, $ht_id) || ('' !== $ht_stem && false !== strpos($ht_m->meta_value, $ht_stem))) {
            $ht_ref = "postmeta {$ht_m->meta_key} #{$ht_m->post_id}";
            break;
        }
    }
    if ('' === $ht_ref) {
        foreach ($ht_content as $ht_c) {
            if (('' !== $ht_stem && false !== strpos($ht_c->post_content, $ht_stem)) || false !== strpos($ht_c->post_content, 'wp-image-' . $ht_id)) {
                $ht_ref = "continut {$ht_c->post_type} #{$ht_c->ID}";
                break;
            }
        }
    }
    if ('' === $ht_ref) {
        foreach ($ht_options as $ht_o) {
            if ('' !== $ht_stem && false !== strpos($ht_o->option_value, $ht_stem)) {
                $ht_ref = "optiune {$ht_o->option_name}";
                break;
            }
        }
    }

    if ('' !== $ht_ref) {
        $ht_kept[$ht_id] = $ht_ref;
        continue;
    }

    $ht_orphans[] = array(
        'id'     => $ht_id,
        'file'   => $ht_file,
        'parent' => (int) $ht_a->post_parent,
        'ptitle' => (string) $ht_a->parent_title,
        'mime'   => $ht_a->post_mime_type,
        'date'   => $ht_a->post_date,
    );
}

/* ---------------------------------------------------------------------------
 * Raport
 * ------------------------------------------------------------------------ */

foreach ($ht_orphans as $ht_o) {
    $ht_where = $ht_o['parent'] ? "produs {$ht_o['parent']} {$ht_o['ptitle']}" : 'fara parinte';
    echo "  {$ht_o['id']} {$ht_o['file']} | {$ht_where} | " . substr($ht_o['date'], 0, 10) . "\n";
}
echo "\nImagini de produs / fara parinte: " . count($ht_atts) . "; folosite: " . count($ht_kept) . "; orfane: " . count($ht_orphans) . "\n";

if ('' !== $ht_csv) {
    $ht_fh = fopen($ht_csv, 'w');
    fputcsv($ht_fh, array('id', 'fisier', 'produs_id', 'produs', 'mime', 'data'));
    foreach ($ht_orphans as $ht_o) {
        fputcsv($ht_fh, array($ht_o['id'], $ht_o['file'], $ht_o['parent'], $ht_o['ptitle'], $ht_o['mime'], $ht_o['date']));
    }
    fclose($ht_fh);
    echo "Lista scrisa in {$ht_csv}\n";
}

if (!$ht_delete) {
    if ($ht_orphans) {
        echo "Nimic sters (ruleaza cu --delete).\n";
    }
    exit(0);
}

$ht_done = 0;
foreach ($ht_orphans as $ht_o) {
    if (wp_delete_attachment($ht_o['id'], true)) {
        $ht_done++;
    } else {
        echo "  ! nu am putut sterge atasamentul {$ht_o['id']}\n";
    }
}
echo "Atasamente sterse: {$ht_done}\n";
