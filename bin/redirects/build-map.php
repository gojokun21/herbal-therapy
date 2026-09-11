<?php
/**
 * Genereaza inc/redirect-map.php: harta adreselor vechi (Shopify) catre cele
 * noi (WooCommerce), in ambele limbi ale site-ului.
 *
 * Surse:
 *   bin/redirects/shopify.json  adresele vechi (scoase de extract_shopify.py)
 *   bin/redirects/manual.php    potrivirile facute de mana
 *   baza de date WordPress      slug-urile si traducerile Polylang
 *
 * Rulare, din radacina instalarii WordPress (app/public):
 *   HT_DB_HOST=127.0.0.1:10004 php wp-content/themes/herbal-therapy/bin/redirects/build-map.php [--csv=cale.csv]
 *
 * Scriptul e idempotent: se ruleaza din nou oricand se schimba un slug pe
 * site-ul nou; harta rezultata se comite in repo si o incarca inc/redirects.php.
 * Iese cu cod 1 cand ramane vreo adresa veche fara tinta.
 *
 * @package Herbal_Therapy
 */

if (PHP_SAPI !== 'cli') {
    exit("Doar din linia de comanda.\n");
}

$options = getopt('', array('csv::', 'wp-root::'));
$wp_root = isset($options['wp-root']) ? $options['wp-root'] : dirname(__DIR__, 5);

if (getenv('HT_DB_HOST')) {
    define('DB_HOST', getenv('HT_DB_HOST'));
}
error_reporting(E_ERROR | E_PARSE);
require $wp_root . '/wp-load.php';

if (!function_exists('pll_get_post')) {
    exit("Polylang nu e activ.\n");
}

$theme = dirname(__DIR__, 2);
$shopify = json_decode(file_get_contents(__DIR__ . '/shopify.json'), true);
$manual = require __DIR__ . '/manual.php';
$langs = array('ro', 'ru');

require_once $theme . '/inc/redirects.php';

/* ---------------------------------------------------------------------------
 * Rezolvarea tintelor
 * ------------------------------------------------------------------------ */

/**
 * Calea (plus query) dintr-o adresa absoluta.
 */
function ht_rm_path($url)
{
    $p = wp_parse_url($url);
    $path = isset($p['path']) && '' !== $p['path'] ? $p['path'] : '/';

    return $path . (isset($p['query']) ? '?' . $p['query'] : '');
}

/**
 * Caile in toate limbile pentru un articol/pagina/produs (dupa ID-ul RO).
 */
function ht_rm_post_paths($post_id, $langs)
{
    $out = array();
    foreach ($langs as $lang) {
        $id = $post_id ? pll_get_post($post_id, $lang) : 0;
        if ($id && 'publish' === get_post_status($id)) {
            $out[$lang] = ht_rm_path(get_permalink($id));
        }
    }

    return $out;
}

/**
 * Caile in toate limbile pentru un termen (dupa ID-ul RO).
 */
function ht_rm_term_paths($term_id, $langs)
{
    $out = array();
    foreach ($langs as $lang) {
        $id = pll_get_term($term_id, $lang);
        if ($id) {
            $link = get_term_link((int)$id);
            if (!is_wp_error($link)) {
                $out[$lang] = ht_rm_path($link);
            }
        }
    }

    return $out;
}

function ht_rm_post_by_slug($slug, $type)
{
    $posts = get_posts(array(
        'name'        => $slug,
        'post_type'   => $type,
        'post_status' => 'publish',
        'numberposts' => 1,
        'lang'        => 'ro',
    ));

    return $posts ? $posts[0]->ID : 0;
}

function ht_rm_term_by_slug($slug, $taxonomy)
{
    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'slug'       => $slug,
        'hide_empty' => false,
        'lang'       => 'ro',
    ));

    return ($terms && !is_wp_error($terms)) ? $terms[0]->term_id : 0;
}

function ht_rm_product_by_sku($sku)
{
    $posts = get_posts(array(
        'post_type'   => 'product',
        'post_status' => 'publish',
        'numberposts' => 1,
        'lang'        => 'ro',
        'meta_key'    => '_sku',
        'meta_value'  => $sku,
    ));

    return $posts ? $posts[0]->ID : 0;
}

function ht_rm_product_by_title($title)
{
    static $posts = null;
    if (null === $posts) {
        $posts = get_posts(array(
            'post_type'   => 'product',
            'post_status' => 'publish',
            'numberposts' => -1,
            'lang'        => 'ro',
        ));
    }
    foreach ($posts as $p) {
        if (trim($p->post_title) === trim($title)) {
            return $p->ID;
        }
    }

    return 0;
}

$shop_paths = ht_rm_post_paths(wc_get_page_id('shop'), $langs);
$home_paths = array();
foreach ($langs as $lang) {
    $home_paths[$lang] = ht_rm_path(pll_home_url($lang));
}

/**
 * Traduce o tinta din notatia din manual.php in cai per limba.
 *
 * @return array|null Null daca tinta nu exista pe site.
 */
function ht_rm_resolve($target, $langs, $shop_paths, $home_paths)
{
    if ('home' === $target) {
        return $home_paths;
    }

    if ('shop' === $target || 0 === strpos($target, 'shop?')) {
        $query = 'shop' === $target ? '' : substr($target, 5);
        $out = array();
        foreach ($shop_paths as $lang => $path) {
            $q = $query;
            if (preg_match('/(^|&)cat=([^&]+)/', $query, $m)) {
                /* slug-urile de categorie se traduc pentru magazinul rusesc */
                $slugs = array();
                foreach (explode(',', $m[2]) as $slug) {
                    $tid = ht_rm_term_by_slug($slug, 'product_cat');
                    $tr = $tid ? pll_get_term($tid, $lang) : 0;
                    $term = $tr ? get_term($tr, 'product_cat') : null;
                    $slugs[] = ($term && !is_wp_error($term)) ? $term->slug : $slug;
                }
                $q = str_replace($m[0], $m[1] . 'cat=' . implode(',', $slugs), $query);
            }
            $out[$lang] = $path . ('' === $q ? '' : '?' . $q);
        }

        return $out;
    }

    if (false === strpos($target, ':')) {
        return null;
    }

    list($kind, $slug) = explode(':', $target, 2);

    switch ($kind) {
        case 'product':
        case 'page':
        case 'post':
            $id = ht_rm_post_by_slug($slug, $kind);
            return $id ? ht_rm_post_paths($id, $langs) : null;
        case 'product_cat':
        case 'category':
            $id = ht_rm_term_by_slug($slug, $kind);
            return $id ? ht_rm_term_paths($id, $langs) : null;
    }

    return null;
}

/* ---------------------------------------------------------------------------
 * Construirea hartii
 * ------------------------------------------------------------------------ */

$paths = array();
$problems = array();
$stats = array('sku' => 0, 'title' => 0, 'manual' => 0, 'auto-blog' => 0);

/* produsele: dupa SKU, dupa denumire, apoi din manual.php */
foreach ($shopify['products'] as $p) {
    $key = '/products/' . $p['handle'];
    if (isset($manual[$key])) {
        continue;
    }
    $id = 0;
    $how = '';
    if (!empty($p['wp_sku'])) {
        $id = ht_rm_product_by_sku($p['wp_sku']);
        $how = 'sku';
    }
    if (!$id && !empty($p['wp_title'])) {
        $id = ht_rm_product_by_title($p['wp_title']);
        $how = 'title';
    }
    if ($id) {
        $paths[$key] = ht_rm_post_paths($id, $langs);
        $stats[$how]++;
    } else {
        $problems[] = "produs fara tinta: {$key} ({$p['title']})";
    }
}

/* colectii, pagini, politici, bloguri: toate trebuie sa fie in manual.php */
foreach ($shopify['collections'] as $c) {
    if (!isset($manual['/collections/' . $c['handle']])) {
        $problems[] = "colectie fara tinta: /collections/{$c['handle']}";
    }
}
foreach ($shopify['pages'] as $h) {
    if (!isset($manual['/pages/' . $h])) {
        $problems[] = "pagina fara tinta: /pages/{$h}";
    }
}
foreach ($shopify['policies'] as $h) {
    if (!isset($manual['/policies/' . $h])) {
        $problems[] = "politica fara tinta: /policies/{$h}";
    }
}
foreach ($shopify['blogs'] as $blog => $articles) {
    if (!isset($manual['/blogs/' . $blog])) {
        $problems[] = "blog fara tinta: /blogs/{$blog}";
    }
    foreach ($articles as $a) {
        $key = '/blogs/' . $blog . '/' . $a;
        if (isset($manual[$key])) {
            continue;
        }
        /* articolele au acelasi titlu pe ambele site-uri: slug-ul WP e handle-ul fara diacritice */
        $id = ht_rm_post_by_slug(sanitize_title(remove_accents($a)), 'post');
        if ($id) {
            $paths[$key] = ht_rm_post_paths($id, $langs);
            $stats['auto-blog']++;
        } else {
            $problems[] = "articol fara tinta: {$key}";
        }
    }
}

/* potrivirile de mana */
foreach ($manual as $key => $target) {
    $resolved = ht_rm_resolve($target, $langs, $shop_paths, $home_paths);
    if (!$resolved || empty($resolved['ro'])) {
        $problems[] = "tinta inexistenta pe site: {$key} -> {$target}";
        continue;
    }
    $paths[$key] = $resolved;
    $stats['manual']++;
}

/* tintele de rezerva, pentru adresele vechi care nu sunt in harta */
$fallback = array(
    'shop'    => $shop_paths,
    'home'    => $home_paths,
    'blog'    => ht_rm_resolve('page:blog', $langs, $shop_paths, $home_paths),
    'cart'    => ht_rm_post_paths(wc_get_page_id('cart'), $langs),
    'account' => ht_rm_post_paths(wc_get_page_id('myaccount'), $langs),
    'terms'   => ht_rm_resolve('page:termenii-si-conditiile', $langs, $shop_paths, $home_paths),
);
foreach ($fallback as $name => $value) {
    if (empty($value['ro'])) {
        $problems[] = "tinta de rezerva lipsa: {$name}";
    }
}

/* compactare: cheile se normalizeaza ca la runtime, ruseste identica cu romana se omite */
$compact = array();
foreach ($paths as $key => $value) {
    $nkey = ht_legacy_normalize_path($key);
    if (isset($compact[$nkey])) {
        $problems[] = "cheie duplicata dupa normalizare: {$key}";
    }
    if (isset($value['ru']) && $value['ru'] === $value['ro']) {
        unset($value['ru']);
    }
    $compact[$nkey] = $value;
}
ksort($compact, SORT_STRING);

foreach ($fallback as $name => $value) {
    if (isset($value['ru']) && $value['ru'] === $value['ro']) {
        unset($fallback[$name]['ru']);
    }
}

/* ---------------------------------------------------------------------------
 * Scrierea
 * ------------------------------------------------------------------------ */

function ht_rm_export_entry($value)
{
    $parts = array();
    foreach ($value as $lang => $path) {
        $parts[] = var_export($lang, true) . ' => ' . var_export($path, true);
    }

    return 'array(' . implode(', ', $parts) . ')';
}

$lines = array();
$lines[] = '<?php';
$lines[] = '/**';
$lines[] = ' * Harta redirecturilor 301 de pe site-ul vechi (Shopify) catre cel nou.';
$lines[] = ' *';
$lines[] = ' * FISIER GENERAT de bin/redirects/build-map.php pe ' . wp_date('Y-m-d') . ' - nu se editeaza de mana.';
$lines[] = ' * Cheile sunt caile vechi normalizate (fara domeniu, fara prefixul de limba,';
$lines[] = ' * litere mici, fara slash final); valorile sunt caile noi pe limbi. Cand';
$lines[] = ' * lipseste varianta ruseasca, se foloseste cea romaneasca.';
$lines[] = ' *';
$lines[] = ' * @package Herbal_Therapy';
$lines[] = ' */';
$lines[] = '';
$lines[] = 'return array(';
$lines[] = "    'generated' => " . var_export(wp_date('Y-m-d'), true) . ',';
$lines[] = "    'fallback' => array(";
foreach ($fallback as $name => $value) {
    $lines[] = '        ' . var_export($name, true) . ' => ' . ht_rm_export_entry($value) . ',';
}
$lines[] = '    ),';
$lines[] = "    'paths' => array(";
foreach ($compact as $key => $value) {
    $lines[] = '        ' . var_export($key, true) . ' => ' . ht_rm_export_entry($value) . ',';
}
$lines[] = '    ),';
$lines[] = ');';
$lines[] = '';

file_put_contents($theme . '/inc/redirect-map.php', implode("\n", $lines));

/* CSV optional: cate un rand pentru fiecare limba a adresei vechi */
if (isset($options['csv'])) {
    $fh = fopen($options['csv'], 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('sursa', 'destinatie', 'cod', 'limba veche'));
    $prefixes = array('ro' => '', 'ru' => '/ru', 'en' => '/en');
    $site = rtrim($shopify['site'], '/');
    foreach ($prefixes as $lang => $prefix) {
        $home = ('ru' === $lang && isset($fallback['home']['ru'])) ? $fallback['home']['ru'] : $fallback['home']['ro'];
        fputcsv($fh, array($site . $prefix . '/', $home, 301, $lang));
        foreach ($compact as $key => $value) {
            $dest = ('ru' === $lang && isset($value['ru'])) ? $value['ru'] : $value['ro'];
            fputcsv($fh, array($site . $prefix . $key, $dest, 301, $lang));
        }
    }
    fclose($fh);
}

printf(
    "Harta: %d adrese (%d dupa SKU, %d dupa denumire, %d articole dupa slug, %d de mana), %d tinte de rezerva.\n",
    count($compact),
    $stats['sku'],
    $stats['title'],
    $stats['auto-blog'],
    $stats['manual'],
    count($fallback)
);
if ($problems) {
    echo "PROBLEME:\n - " . implode("\n - ", $problems) . "\n";
    exit(1);
}
echo 'Scris inc/redirect-map.php' . (isset($options['csv']) ? " si {$options['csv']}" : '') . "\n";
