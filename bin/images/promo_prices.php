<?php
/**
 * Preturi de reducere pe durata promotiei: pune _sale_price din
 * data/promo/sale_prices.csv (coloanele SKU, Regular price, Sale price) pe
 * produsul RO gasit dupa SKU si pe perechea lui RU (Polylang).
 *
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/promo_prices.php [--dry-run] [--only sku,sku]
 *   HT_DB_HOST=127.0.0.1:10004 php bin/images/promo_prices.php --restore
 *
 * Inainte sa schimbe ceva, scrie pretul de reducere vechi al fiecarui produs in
 * data/promo/prices_backup.json ({product_id: sale_price_vechi}); --restore il
 * pune la loc (gol = fara reducere). Pretul obisnuit nu se atinge; daca difera
 * de cel din CSV, produsul e sarit si raportat. Produsele care nu sunt
 * publicate (ciorne, cos) se sar.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/promo_prices.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv    = isset($argv) ? $argv : array();
$ht_dry     = in_array('--dry-run', $ht_argv, true);
$ht_restore = in_array('--restore', $ht_argv, true);
$ht_only    = array();
$ht_pos     = array_search('--only', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_only = array_filter(array_map('trim', explode(',', $ht_argv[$ht_pos + 1])));
}

$ht_csv_file    = __DIR__ . '/data/promo/sale_prices.csv';
$ht_backup_file = __DIR__ . '/data/promo/prices_backup.json';

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

$ht_backup = file_exists($ht_backup_file) ? json_decode(file_get_contents($ht_backup_file), true) : array();

function ht_prices_save_backup($file, $backup)
{
    file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/** Pune (sau scoate) pretul de reducere printr-un obiect WC, ca sa se refaca _price si tabelele de cautare. */
function ht_prices_apply($pid, $sale)
{
    $product = wc_get_product($pid);
    if (!$product) {
        return 'produs inexistent';
    }
    $product->set_sale_price('' === $sale ? '' : wc_format_decimal($sale));
    $product->save();
    return true;
}

/* ---------------------------------------------------------------------------
 * --restore: pretul vechi la loc
 * ------------------------------------------------------------------------ */
if ($ht_restore) {
    if (!$ht_backup) {
        exit("Nu exista data/promo/prices_backup.json - nimic de restaurat.\n");
    }
    $ht_done = 0;
    foreach ($ht_backup as $ht_pid => $ht_old) {
        $ht_pid = (int) $ht_pid;
        $ht_cur = get_post_meta($ht_pid, '_sale_price', true);
        if ($ht_dry) {
            echo "  [dry-run] #{$ht_pid}: '{$ht_cur}' -> '{$ht_old}'\n";
            continue;
        }
        $ht_res = ht_prices_apply($ht_pid, (string) $ht_old);
        if (true !== $ht_res) {
            echo "  ! #{$ht_pid}: {$ht_res}\n";
            continue;
        }
        $ht_done++;
        echo "  ~ #{$ht_pid}: reducere '{$ht_cur}' -> '{$ht_old}'\n";
    }
    if (!$ht_dry) {
        ht_prices_save_backup($ht_backup_file . '.restored-' . date('Ymd-His'), $ht_backup);
        @unlink($ht_backup_file);
    }
    echo "Restaurate: {$ht_done}.\n";
    exit;
}

/* ---------------------------------------------------------------------------
 * CSV
 * ------------------------------------------------------------------------ */
if (!file_exists($ht_csv_file)) {
    exit("Lipseste data/promo/sale_prices.csv.\n");
}
$ht_fh   = fopen($ht_csv_file, 'r');
$ht_head = fgetcsv($ht_fh);
$ht_head = array_map(function ($h) {
    return trim($h, "\xEF\xBB\xBF \t");
}, $ht_head);
$ht_idx = array_flip($ht_head);
foreach (array('SKU', 'Regular price', 'Sale price') as $ht_col) {
    if (!isset($ht_idx[$ht_col])) {
        exit("CSV: lipseste coloana '{$ht_col}'.\n");
    }
}
$ht_rows = array();
while (false !== ($ht_r = fgetcsv($ht_fh))) {
    if (count($ht_r) < 3) {
        continue;
    }
    $ht_sku = trim($ht_r[$ht_idx['SKU']]);
    if ('' === $ht_sku) {
        continue;
    }
    $ht_rows[$ht_sku] = array(
        'name' => isset($ht_idx['Name']) ? $ht_r[$ht_idx['Name']] : '',
        'reg'  => wc_format_decimal($ht_r[$ht_idx['Regular price']]),
        'sale' => wc_format_decimal($ht_r[$ht_idx['Sale price']]),
    );
}
fclose($ht_fh);
echo count($ht_rows) . " randuri in CSV" . ($ht_dry ? ' (dry-run)' : '') . "\n";

/* ---------------------------------------------------------------------------
 * Aplicare
 * ------------------------------------------------------------------------ */
global $wpdb;
$ht_ok = 0;
$ht_skipped = array();
foreach ($ht_rows as $ht_sku => $ht_row) {
    if ($ht_only && !in_array($ht_sku, $ht_only, true)) {
        continue;
    }
    $ht_pid = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE m.meta_key = '_sku' AND m.meta_value = %s AND p.post_type = 'product' ORDER BY p.ID ASC LIMIT 1",
        $ht_sku
    ));
    if (!$ht_pid) {
        $ht_skipped[] = "{$ht_sku} {$ht_row['name']}: SKU negasit";
        continue;
    }
    $ht_targets = array($ht_pid);
    if (function_exists('pll_get_post_translations')) {
        foreach (pll_get_post_translations($ht_pid) as $ht_tr) {
            if ((int) $ht_tr && !in_array((int) $ht_tr, $ht_targets, true)) {
                $ht_targets[] = (int) $ht_tr;
            }
        }
    }
    foreach ($ht_targets as $ht_tid) {
        $ht_label = "{$ht_sku} #{$ht_tid} " . get_the_title($ht_tid);
        if ('publish' !== get_post_status($ht_tid)) {
            $ht_skipped[] = "{$ht_label}: nu e publicat (" . get_post_status($ht_tid) . ')';
            continue;
        }
        $ht_reg_cur = wc_format_decimal(get_post_meta($ht_tid, '_regular_price', true));
        if ((float) $ht_reg_cur !== (float) $ht_row['reg']) {
            $ht_skipped[] = "{$ht_label}: pret obisnuit {$ht_reg_cur} in site, {$ht_row['reg']} in CSV";
            continue;
        }
        if ((float) $ht_row['sale'] <= 0 || (float) $ht_row['sale'] >= (float) $ht_row['reg']) {
            $ht_skipped[] = "{$ht_label}: pret de reducere invalid ({$ht_row['sale']})";
            continue;
        }
        $ht_sale_cur = (string) get_post_meta($ht_tid, '_sale_price', true);
        if ($ht_sale_cur === $ht_row['sale']) {
            echo "  = {$ht_label}: reducerea {$ht_row['sale']} e deja pusa\n";
            continue;
        }
        if ($ht_dry) {
            echo "  + {$ht_label}: {$ht_row['reg']} -> {$ht_row['sale']} (era '{$ht_sale_cur}')\n";
            continue;
        }
        if (!array_key_exists((string) $ht_tid, $ht_backup)) {
            $ht_backup[(string) $ht_tid] = $ht_sale_cur;
            ht_prices_save_backup($ht_backup_file, $ht_backup);
        }
        $ht_res = ht_prices_apply($ht_tid, $ht_row['sale']);
        if (true !== $ht_res) {
            $ht_skipped[] = "{$ht_label}: {$ht_res}";
            continue;
        }
        $ht_ok++;
        echo "  + {$ht_label}: {$ht_row['reg']} -> {$ht_row['sale']}\n";
    }
}
if ($ht_skipped) {
    echo "Sarite:\n  - " . implode("\n  - ", $ht_skipped) . "\n";
}
echo "Gata: {$ht_ok} preturi puse. Dupa promotie: php bin/images/promo_prices.php --restore\n";
