<?php
/**
 * Seed pentru traducerile de siruri din Polylang (Limbi -> Traduceri siruri).
 *
 * Scrie in baza de date, pentru fiecare limba din bin/seed-i18n-content.php,
 * traducerile sirurilor UI ale temei - exact ceea ce ar introduce clientul
 * de mana in panoul Polylang. Foloseste acelasi depozit ca panoul (PLL_MO,
 * term meta '_pll_strings_translations'), deci traducerile apar in panou si
 * ajung in frontend prin puntea gettext din inc/i18n.php.
 *
 * Rulare, din radacina temei:
 *   php bin/seed-i18n.php            - completeaza doar sirurile netraduse
 *   php bin/seed-i18n.php --force    - suprascrie si traducerile existente
 *   php bin/seed-i18n.php --dry-run  - arata ce ar scrie, fara sa scrie
 *
 * Cand PHP-ul din linia de comanda nu vede MySQL-ul (ex. Local pe Windows),
 * gazda se da prin mediu: HT_DB_HOST=127.0.0.1:10004 php bin/seed-i18n.php
 *
 * Scriptul e idempotent si compara continutul cu inventarul generat
 * inc/i18n-strings.php: sirurile noi fara traducere si traducerile ramase
 * fara sir sursa (msgid schimbat) sunt raportate la final.
 *
 * Formele de plural (_n) primesc aici o singura forma; traducerea completa,
 * cu toate formele rusesti, vine din languages/ru_RU.po/.mo - vezi
 * languages/README.md.
 *
 * @package Herbal_Therapy
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/seed-i18n.php se ruleaza doar din linia de comanda.\n");
}

/* ---------------------------------------------------------------------------
 * Argumente
 * ------------------------------------------------------------------------ */

$ht_argv  = isset($argv) ? $argv : array();
$ht_force = in_array('--force', $ht_argv, true);
$ht_dry   = in_array('--dry-run', $ht_argv, true);

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

/* WordPress se asteapta la un context de cerere chiar si in linia de comanda */
$_SERVER['HTTP_HOST']      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_SERVER['SERVER_NAME']    = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/index.php';

/* Local expune MySQL pe TCP; din CLI se poate indica gazda: HT_DB_HOST=127.0.0.1:10004 */
if (!empty($_SERVER['HT_DB_HOST']) && !defined('DB_HOST')) {
    define('DB_HOST', (string)$_SERVER['HT_DB_HOST']);

    /* wp-config.php defineste DB_HOST din nou; avertismentul e asteptat */
    set_error_handler(function ($no, $str) {
        return false !== strpos($str, 'DB_HOST already defined');
    }, E_WARNING);
    require_once $ht_root . '/wp-load.php';
    restore_error_handler();
} else {
    require_once $ht_root . '/wp-load.php';
}

/* ---------------------------------------------------------------------------
 * Unelte
 * ------------------------------------------------------------------------ */

/**
 * Scrie un rand in consola.
 *
 * @param string $message Textul.
 * @param string $level   'ok', 'skip', 'warn' sau '' pentru randuri simple.
 */
function ht_i18n_seed_log($message, $level = '')
{
    $prefix = array(
        'ok'   => '  + ',
        'skip' => '  = ',
        'warn' => '  ! ',
    );

    echo (isset($prefix[$level]) ? $prefix[$level] : '') . $message . "\n";
}

/* ---------------------------------------------------------------------------
 * Verificari
 * ------------------------------------------------------------------------ */

if (!function_exists('PLL') || !class_exists('PLL_MO')) {
    exit("Polylang nu este activ - nu am unde sa scriu traducerile.\n");
}

$ht_content = require __DIR__ . '/seed-i18n-content.php';

$ht_inventory_file = dirname(__DIR__) . '/inc/i18n-strings.php';
$ht_inventory      = is_readable($ht_inventory_file) ? (array)require $ht_inventory_file : array();

/* inventarul, aplatizat: sir => sectiune */
$ht_known = array();

foreach ($ht_inventory as $ht_section => $ht_list) {
    foreach ((array)$ht_list as $ht_string) {
        if (is_string($ht_string) && '' !== $ht_string) {
            $ht_known[$ht_string] = $ht_section;
        }
    }
}

echo 'Seed traduceri de siruri Polylang' . ($ht_dry ? ' (dry-run)' : '') . ($ht_force ? ' (force)' : '') . "\n";
echo str_repeat('-', 52) . "\n";

/* ---------------------------------------------------------------------------
 * Scrierea, limba cu limba
 * ------------------------------------------------------------------------ */

foreach ($ht_content as $ht_slug => $ht_sections) {
    $ht_language = PLL()->model->get_language($ht_slug);

    if (empty($ht_language)) {
        ht_i18n_seed_log('limba "' . $ht_slug . '" nu exista in Polylang - sarita', 'warn');

        continue;
    }

    echo "\nLimba: {$ht_slug}\n";

    /* perechile, aplatizate; traducerile fara sir sursa in inventar se aduna deoparte */
    $ht_pairs = array();
    $ht_stale = array();

    foreach ($ht_sections as $ht_section => $ht_map) {
        foreach ((array)$ht_map as $ht_source => $ht_translation) {
            if (!is_string($ht_source) || '' === $ht_source || !is_string($ht_translation) || '' === $ht_translation) {
                continue;
            }

            $ht_pairs[$ht_source] = $ht_translation;

            if (!isset($ht_known[$ht_source])) {
                $ht_stale[] = $ht_source;
            }
        }
    }

    $ht_mo = new PLL_MO();
    $ht_mo->import_from_db($ht_language);

    $ht_added   = 0;
    $ht_updated = 0;
    $ht_kept    = 0;

    foreach ($ht_pairs as $ht_source => $ht_translation) {
        $ht_existing = isset($ht_mo->entries[$ht_source]) && !empty($ht_mo->entries[$ht_source]->translations[0])
            ? (string)$ht_mo->entries[$ht_source]->translations[0]
            : '';

        if ('' !== $ht_existing) {
            if ($ht_existing === $ht_translation || !$ht_force) {
                $ht_kept++;

                continue;
            }

            $ht_updated++;
        } else {
            $ht_added++;
        }

        if (!$ht_dry) {
            $ht_mo->add_entry($ht_mo->make_entry($ht_source, $ht_translation));
        }
    }

    if (!$ht_dry && ($ht_added || $ht_updated)) {
        $ht_mo->export_to_db($ht_language);
    }

    ht_i18n_seed_log(($ht_dry ? 'ar adauga ' : 'adaugate ') . $ht_added . ', ' . ($ht_dry ? 'ar actualiza ' : 'actualizate ') . $ht_updated, 'ok');
    ht_i18n_seed_log('pastrate neatinse ' . $ht_kept . ($ht_force ? '' : ' (suprascrie cu --force)'), 'skip');

    /* siruri din inventar fara traducere in continutul seed-ului */
    $ht_missing = array_diff_key($ht_known, $ht_pairs);

    if ($ht_missing) {
        ht_i18n_seed_log(count($ht_missing) . ' siruri din inventar fara traducere in bin/seed-i18n-content.php:', 'warn');

        foreach ($ht_missing as $ht_string => $ht_section) {
            ht_i18n_seed_log('    [' . $ht_section . '] ' . $ht_string, '');
        }
    }

    if ($ht_stale) {
        ht_i18n_seed_log(count($ht_stale) . ' traduceri fara sir sursa in inventar (msgid schimbat?):', 'warn');

        foreach ($ht_stale as $ht_string) {
            ht_i18n_seed_log('    ' . $ht_string, '');
        }
    }
}

echo "\n" . str_repeat('-', 52) . "\n";
echo "Gata.\n\n";
