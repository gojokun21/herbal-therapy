<?php
/**
 * Bannere promotionale: exporta cadrele din fisierul Figma "Herbal Promo" si le
 * pune ca imagine reprezentativa pe produsul RO si pe perechea RU, IN LOCUL
 * copertii obisnuite, pe durata promotiei.
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/promo_to_wp.php [--dry-run] [--only slug,slug]
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/promo_to_wp.php --restore [--delete-promo]
 *
 * Citeste data/promo/promo_nodes.json (slug -> {ro, ru, id, ru_id, promo}).
 * Inainte sa schimbe ceva, scrie coperta veche a fiecarui produs in
 * data/promo/promo_backup.json ({product_id: attachment_id}); --restore o pune
 * la loc (si, cu --delete-promo, sterge bannerele din Media). Galeria nu se
 * atinge. Bannerul primeste meta `_ht_promo_node`, ca sa nu fie luat drept
 * coperta Figma de export_products.php (acela cauta `_ht_figma_node`).
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/promo_to_wp.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv    = isset($argv) ? $argv : array();
$ht_dry     = in_array('--dry-run', $ht_argv, true);
$ht_restore = in_array('--restore', $ht_argv, true);
$ht_delete  = in_array('--delete-promo', $ht_argv, true);
$ht_force   = in_array('--force', $ht_argv, true);   // reia si bannerele deja puse (cadrul s-a schimbat in Figma)
$ht_only    = array();
$ht_pos     = array_search('--only', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_only = array_filter(array_map('trim', explode(',', $ht_argv[$ht_pos + 1])));
}

$ht_file = getenv('HT_PROMO_FIGMA_FILE') ?: 'Evz4dnqHOEcCAt7Sue94UT';

/* ---------------------------------------------------------------------------
 * FIGMA_TOKEN din mediu sau din bin/images/.env
 * ------------------------------------------------------------------------ */
$ht_tok = getenv('FIGMA_TOKEN');
if (!$ht_tok && file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ht_line) {
        if (0 === strpos(trim($ht_line), 'FIGMA_TOKEN=')) {
            $ht_tok = trim(substr(trim($ht_line), strlen('FIGMA_TOKEN=')), " \"'");
        }
    }
}
if (!$ht_tok && !$ht_restore) {
    exit("Lipseste FIGMA_TOKEN (bin/images/.env).\n");
}

$ht_nodes_file  = __DIR__ . '/data/promo/promo_nodes.json';
$ht_backup_file = __DIR__ . '/data/promo/promo_backup.json';
if (!file_exists($ht_nodes_file)) {
    exit("Lipseste data/promo/promo_nodes.json.\n");
}
$ht_nodes  = json_decode(file_get_contents($ht_nodes_file), true);
$ht_backup = file_exists($ht_backup_file) ? json_decode(file_get_contents($ht_backup_file), true) : array();

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

function ht_promo_save_backup($file, $backup)
{
    file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/* ---------------------------------------------------------------------------
 * --restore: coperta veche la loc
 * ------------------------------------------------------------------------ */
if ($ht_restore) {
    if (!$ht_backup) {
        exit("Nu exista data/promo/promo_backup.json - nimic de restaurat.\n");
    }
    $ht_done = 0;
    foreach ($ht_backup as $ht_pid => $ht_old) {
        $ht_pid = (int) $ht_pid;
        $ht_cur = (int) get_post_thumbnail_id($ht_pid);
        if ($ht_dry) {
            echo "  [dry-run] #{$ht_pid}: {$ht_cur} -> {$ht_old}\n";
            continue;
        }
        if ($ht_old) {
            set_post_thumbnail($ht_pid, (int) $ht_old);
        } else {
            delete_post_thumbnail($ht_pid);
        }
        if ($ht_delete && $ht_cur && get_post_meta($ht_cur, '_ht_promo_node', true)) {
            wp_delete_attachment($ht_cur, true);
        }
        $ht_done++;
        echo "  ~ #{$ht_pid}: coperta {$ht_old} pusa la loc" . ($ht_delete ? ", banner {$ht_cur} sters" : '') . "\n";
    }
    if (!$ht_dry) {
        ht_promo_save_backup($ht_backup_file . '.restored-' . date('Ymd-His'), $ht_backup);
        @unlink($ht_backup_file);
    }
    echo "Restaurate: {$ht_done}.\n";
    exit;
}

/* ---------------------------------------------------------------------------
 * Ce e de facut
 * ------------------------------------------------------------------------ */
$ht_jobs = array();   // node id => [pid, lang, slug, titlu, fisier]
foreach ($ht_nodes as $ht_slug => $ht_row) {
    if ($ht_only && !in_array($ht_slug, $ht_only, true)) {
        continue;
    }
    $ht_targets = array('ro' => (int) $ht_row['id'], 'ru' => (int) $ht_row['ru_id']);
    foreach ($ht_targets as $ht_lang => $ht_pid) {
        if (!$ht_pid || empty($ht_row[$ht_lang])) {
            continue;
        }
        $ht_current = (int) get_post_thumbnail_id($ht_pid);
        if (!$ht_force && $ht_current && get_post_meta($ht_current, '_ht_promo_node', true) === $ht_row[$ht_lang]) {
            echo "  = {$ht_slug} {$ht_lang}: bannerul e deja pus\n";
            continue;
        }
        $ht_title = 'ru' === $ht_lang && !empty($ht_row['ru_title']) ? $ht_row['ru_title'] : $ht_row['title'];
        $ht_jobs[$ht_row[$ht_lang]] = array(
            'pid'   => $ht_pid,
            'lang'  => $ht_lang,
            'slug'  => $ht_slug,
            'title' => $ht_title . ' - Promoție ' . $ht_row['promo'],
            'file'  => sanitize_file_name($ht_row['title'] . ' promo ' . $ht_row['promo']) . '-' . $ht_lang . '.jpg',
        );
    }
}
if (!$ht_jobs) {
    exit("Nimic de urcat.\n");
}
echo count($ht_jobs) . " bannere de exportat din Figma" . ($ht_dry ? ' (dry-run)' : '') . "\n";

/* ---------------------------------------------------------------------------
 * Export din Figma, in loturi de 40
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
 * Urcare
 * ------------------------------------------------------------------------ */
$ht_ok = 0;
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
    $ht_att = media_handle_sideload(array('name' => $ht_job['file'], 'tmp_name' => $ht_tmp), $ht_job['pid'], $ht_job['title']);
    if (is_wp_error($ht_att)) {
        @unlink($ht_tmp);
        echo "  ! {$ht_label}: " . $ht_att->get_error_message() . "\n";
        continue;
    }
    update_post_meta($ht_att, '_wp_attachment_image_alt', $ht_job['title']);
    update_post_meta($ht_att, '_ht_promo_node', $ht_node);
    if (function_exists('pll_set_post_language')) {
        pll_set_post_language($ht_att, $ht_job['lang']);
    }

    /* coperta veche, o singura data per produs (a doua rulare nu o suprascrie cu bannerul) */
    $ht_old = (int) get_post_thumbnail_id($ht_job['pid']);
    if (!array_key_exists((string) $ht_job['pid'], $ht_backup)) {
        $ht_backup[(string) $ht_job['pid']] = $ht_old;
        ht_promo_save_backup($ht_backup_file, $ht_backup);
    }
    set_post_thumbnail($ht_job['pid'], $ht_att);
    /* la --force, bannerul anterior (nu coperta!) nu mai e folosit de nimeni */
    if ($ht_old && $ht_old !== $ht_att && get_post_meta($ht_old, '_ht_promo_node', true)) {
        wp_delete_attachment($ht_old, true);
    }
    $ht_ok++;
    echo "  + {$ht_label}: banner #{$ht_att} pe produs {$ht_job['pid']} (coperta veche #{$ht_old} pastrata in backup)\n";
}
echo "Gata: {$ht_ok} bannere puse. Dupa promotie: php bin/images/promo_to_wp.php --restore [--delete-promo]\n";
