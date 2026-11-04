<?php
/**
 * Pachete (WooCommerce Product Bundles) din bundles.json.
 *
 *   HT_DB_HOST=127.0.0.1:10051 php bin/images/bundles.php --export
 *       packshot-urile produselor din fiecare pachet -> data/bundles/sources.json
 *       (pentru bundles.py, care face poza setului)
 *
 *   HT_DB_HOST=127.0.0.1:10051 php bin/images/bundles.php [--dry-run] [--draft] [--only slug,slug]
 *       creeaza sau actualizeaza pachetele (RO) si le pune poza din
 *       data/bundles/<slug>.jpg ca imagine reprezentativa, daca exista
 *
 * Pretul pachetului e cel din oferta (`price`) si intra ca pret redus; pretul
 * intreg e suma preturilor actuale ale produselor, adica ce ar plati clientul
 * cumparandu-le separat azi. Componentele nu au pret propriu in pachet
 * (priced_individually = no), deci in cos si in comanda pachetul are un singur
 * pret. Stocul pachetului urmeaza stocul componentelor (pluginul).
 *
 * Un pachet se recunoaste dupa meta `_ht_bundle` = slug, deci scriptul se
 * poate rula de mai multe ori. Poza se urca din nou doar cand JPG-ul s-a
 * schimbat (meta `_ht_bundle_image` = md5 pe atasament). Dupa ce pachetul
 * primeste bannerul din Figma (upload_to_wp.php), poza setului sta in galerie.
 *
 * Cand pachetul are cheia `ru` in bundles.json, se creeaza si perechea in
 * rusa, legata prin Polylang: componentele sunt perechile RU ale produselor,
 * pretul, SKU-ul si poza sunt ale pachetului RO, categoria e traducerea
 * categoriei RO.
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/images/bundles.php se ruleaza doar din linia de comanda.\n");
}

$ht_argv   = isset($argv) ? $argv : array();
$ht_dry    = in_array('--dry-run', $ht_argv, true);
$ht_export = in_array('--export', $ht_argv, true);
$ht_draft  = in_array('--draft', $ht_argv, true);
$ht_only   = array();
$ht_pos    = array_search('--only', $ht_argv, true);
if (false !== $ht_pos && isset($ht_argv[$ht_pos + 1])) {
    $ht_only = array_filter(array_map('trim', explode(',', $ht_argv[$ht_pos + 1])));
}

$ht_def_file = __DIR__ . '/bundles.json';
$ht_out_dir  = __DIR__ . '/data/bundles';
$ht_def      = json_decode(file_get_contents($ht_def_file), true);
if (!is_array($ht_def) || empty($ht_def['bundles'])) {
    exit("bundles.json lipseste sau nu e JSON valid.\n");
}
if (!is_dir($ht_out_dir)) {
    mkdir($ht_out_dir, 0777, true);
}

/* ---------------------------------------------------------------------------
 * WordPress
 * ------------------------------------------------------------------------ */

/* HT_WP_ROOT: radacina WordPress, cand scriptul ruleaza dintr-o copie din afara temei */
$ht_root = getenv('HT_WP_ROOT') ? rtrim(getenv('HT_WP_ROOT'), '/\\') : __DIR__;
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
if (!class_exists('WC_Product_Bundle')) {
    exit("WooCommerce Product Bundles nu e activ.\n");
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/*
 * La legarea traducerilor Polylang for WooCommerce copiaza pretul, stocul etc.
 * ca randuri noi de meta peste cele existente (vezi ht_import_skip_meta_copy()
 * din inc/import-endpoint.php). Scriptul pune singur fiecare valoare.
 */
add_filter('pllwc_copy_post_metas', '__return_empty_array', 999);

/*
 * Polylang Pro >= 3.9 trimite pe traduceri fiecare valoare ACF scrisa: un
 * repeater cu subcampuri text (beneficii) se sincronizeaza, deci beneficiile RU
 * ar ajunge si pe pachetul RO. Fiecare limba isi scrie singura campurile.
 */
remove_filter('acf/update_value', array('WP_Syntex\\Polylang_Pro\\Integrations\\ACF\\Dispatcher', 'update'), 5);

/**
 * Prima poza din galeria produsului (packshot-ul pe alb), cale absoluta.
 */
function ht_bundle_packshot($pid)
{
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) get_post_meta($pid, '_product_image_gallery', true)))));
    $att = $ids ? $ids[0] : (int) get_post_thumbnail_id($pid);

    return $att ? get_attached_file($att) : '';
}

/**
 * Pachetul RO creat deja pentru slug-ul dat (dupa meta `_ht_bundle`), sau 0.
 *
 * Doar cel in romana: daca Polylang ar copia meta pe traducere, perechea RU
 * ar avea acelasi `_ht_bundle`.
 */
function ht_bundle_find($slug)
{
    $ids = get_posts(array(
        'post_type'      => 'product',
        'post_status'    => 'any',
        'meta_key'       => '_ht_bundle',
        'meta_value'     => $slug,
        'fields'         => 'ids',
        'posts_per_page' => -1,
        'lang'           => '',
    ));

    foreach ($ids as $id) {
        if (!function_exists('pll_get_post_language') || 'ro' === pll_get_post_language($id)) {
            return (int) $id;
        }
    }

    return 0;
}

/**
 * Traducerea RU a categoriei RO, creata si legata daca lipseste.
 *
 * @return int ID-ul categoriei RU, 0 in --dry-run cand inca nu exista.
 */
function ht_bundle_category_ru($ro_cat_id, array $def, $dry)
{
    $ru_id = $ro_cat_id ? (int) pll_get_term($ro_cat_id, 'ru') : 0;
    if ($ru_id || $dry) {
        if (!$ru_id) {
            echo "  + categoria RU '{$def['name']}'\n";
        }
        return $ru_id;
    }

    $term = wp_insert_term($def['name'], 'product_cat', array('slug' => $def['slug']));
    if (is_wp_error($term)) {
        exit('Categoria RU nu s-a creat: ' . $term->get_error_message() . "\n");
    }
    $ru_id = (int) $term['term_id'];
    pll_set_term_language($ru_id, 'ru');
    $tr       = pll_get_term_translations($ro_cat_id);
    $tr['ro'] = $ro_cat_id;
    $tr['ru'] = $ru_id;
    pll_save_term_translations($tr);
    echo "  + categoria RU '{$def['name']}' (#{$ru_id})\n";

    return $ru_id;
}

/**
 * Datele de pachet comune RO / RU: componentele si setarile pluginului.
 *
 * Componentele existente se pastreaza dupa produs, ca sa nu se schimbe ID-urile.
 */
function ht_bundle_set_items(WC_Product_Bundle $product, array $pids)
{
    $product->set_layout('default');
    $product->set_group_mode('parent');
    $product->set_editable_in_cart(false);

    $existing = array();
    foreach ($product->get_bundled_data_items('edit') as $item) {
        $existing[(int) $item->get_product_id()] = (int) $item->get_id();
    }
    $items = array();
    foreach (array_values($pids) as $i => $pid) {
        $items[] = array(
            'bundled_item_id' => isset($existing[$pid]) ? $existing[$pid] : 0,
            'product_id'      => (int) $pid,
            'menu_order'      => $i,
            'meta_data'       => array(
                'quantity_min'              => 1,
                'quantity_max'              => 1,
                'quantity_default'          => 1,
                'priced_individually'       => 'no',
                'shipped_individually'      => 'no',
                'optional'                  => 'no',
                'single_product_visibility' => 'visible',
                'cart_visibility'           => 'visible',
                'order_visibility'          => 'visible',
            ),
        );
    }
    $product->set_bundled_data_items($items);
}

/**
 * Poza setului (atasamentul cu meta `_ht_bundle_image`): imaginea
 * reprezentativa sau, dupa ce bannerul i-a luat locul, prima din galerie.
 */
function ht_bundle_set_image($pid)
{
    $ids = array_merge(
        array((int) get_post_thumbnail_id($pid)),
        array_map('intval', explode(',', (string) get_post_meta($pid, '_product_image_gallery', true)))
    );
    foreach (array_filter($ids) as $id) {
        if (get_post_meta($id, '_ht_bundle_image', true)) {
            return $id;
        }
    }

    return 0;
}

/**
 * Pune poza setului pe pachet: in galerie cand imaginea reprezentativa e deja
 * bannerul din Figma (meta `_ht_figma_node`, vezi upload_to_wp.php), altfel ca
 * imagine reprezentativa, cu galeria goala.
 */
function ht_bundle_place_image(WC_Product $product, $att)
{
    $thumb = (int) $product->get_image_id('edit');
    if ($thumb && $thumb !== $att && get_post_meta($thumb, '_ht_figma_node', true)) {
        $product->set_gallery_image_ids(array($att));
    } else {
        $product->set_image_id($att);
        $product->set_gallery_image_ids(array());
    }
}

/**
 * Perechea RU a pachetului RO, dupa `ru` din bundles.json.
 *
 * Aceeasi ordine ca in ht_import_twin_callback(): produsul se salveaza intai
 * fara SKU, primeste limba si legatura, si abia apoi SKU-ul RO - WooCommerce
 * accepta acelasi SKU pe doua produse doar daca sunt deja traduceri legate.
 *
 * @return string Randul de raport.
 */
function ht_bundle_ru($ro_id, array $b, $cat_ru, $dry)
{
    $def   = $b['ru'];
    $ru_id = $ro_id ? (int) pll_get_post($ro_id, 'ru') : 0;

    $pids = array();
    foreach ($b['items'] as $pid) {
        $tr = (int) pll_get_post($pid, 'ru');
        if (!$tr || 'publish' !== get_post_status($tr)) {
            return "    ! RU: produsul #{$pid} nu are pereche RU publicata, sar pachetul RU\n";
        }
        $pids[] = $tr;
    }

    if ($dry) {
        return '    ' . ($ru_id ? "~ RU #{$ru_id}" : '+ RU') . " {$def['slug']}: " . implode(',', $pids) . "\n";
    }

    $ro      = wc_get_product($ro_id);
    $product = $ru_id ? wc_get_product($ru_id) : new WC_Product_Bundle();
    if (!$product instanceof WC_Product_Bundle) {
        return "    ! RU: #{$ru_id} exista, dar nu e pachet\n";
    }

    $product->set_name($def['title']);
    $product->set_slug($def['slug']);
    $product->set_status($ro->get_status('edit'));
    $product->set_catalog_visibility('visible');
    $product->set_short_description($def['excerpt']);
    $product->set_description('<p>' . esc_html($def['excerpt']) . '</p>');
    $product->set_regular_price($ro->get_regular_price('edit'));
    $product->set_sale_price($ro->get_sale_price('edit'));
    $set = ht_bundle_set_image($ro_id);
    if ($set) {
        ht_bundle_place_image($product, $set);
    }
    if ($cat_ru) {
        $product->set_category_ids(array($cat_ru));
    }
    ht_bundle_set_items($product, $pids);
    $ru_id = $product->save();

    pll_set_post_language($ru_id, 'ru');
    $tr       = pll_get_post_translations($ro_id);
    $tr['ro'] = $ro_id;
    $tr['ru'] = $ru_id;
    pll_save_post_translations($tr);

    /*
     * La prima legare, sincronizarea Polylang copiaza pe traducere categoria RO
     * (netradusa, cand categoria RU e creata in aceeasi rulare), deci categoria
     * RU se pune din nou dupa legare, odata cu SKU-ul.
     */
    $product = wc_get_product($ru_id);
    if ($cat_ru) {
        $product->set_category_ids(array($cat_ru));
    }
    $sku = $ro->get_sku('edit');
    if ($sku && $sku !== $product->get_sku('edit')) {
        try {
            $product->set_sku($sku);
        } catch (WC_Data_Exception $e) {
            echo "    ~ RU: SKU {$sku} refuzat: " . $e->getMessage() . "\n";
        }
    }
    $product->save();

    if (function_exists('update_field')) {
        update_field('field_ht_product_benefits', array_map(function ($text) {
            return array('field_ht_product_benefit_text' => $text);
        }, $def['benefits']), $ru_id);
    }
    wc_delete_product_transients($ru_id);

    return "    + RU #{$ru_id} {$def['slug']}\n";
}

$ht_bundles = array_filter($ht_def['bundles'], function ($b) use ($ht_only) {
    return !$ht_only || in_array($b['slug'], $ht_only, true);
});

/* ---------------------------------------------------------------------------
 * Verificarea componentelor (comuna)
 * ------------------------------------------------------------------------ */

$ht_bad = 0;
foreach ($ht_bundles as $ht_b) {
    foreach ($ht_b['items'] as $ht_pid) {
        $ht_child = wc_get_product($ht_pid);
        $ht_status = get_post_status($ht_pid);
        if (!$ht_child || 'publish' !== $ht_status) {
            echo "  ! {$ht_b['slug']}: produsul #{$ht_pid} " . ($ht_child ? "e '{$ht_status}'" : 'nu exista') . "\n";
            $ht_bad++;
        } elseif (!$ht_child->is_in_stock()) {
            echo "  ~ {$ht_b['slug']}: #{$ht_pid} {$ht_child->get_name()} nu e in stoc (pachetul va fi indisponibil)\n";
        }
    }
}
if ($ht_bad) {
    exit("{$ht_bad} componente lipsesc sau nu sunt publicate; nu fac nimic.\n");
}

/* ---------------------------------------------------------------------------
 * --export
 * ------------------------------------------------------------------------ */

if ($ht_export) {
    $ht_rows = array();
    foreach ($ht_bundles as $ht_b) {
        $ht_items = array();
        foreach ($ht_b['items'] as $ht_pid) {
            $ht_file = ht_bundle_packshot($ht_pid);
            if (!$ht_file || !file_exists($ht_file)) {
                echo "  ! {$ht_b['slug']}: #{$ht_pid} nu are poza in galerie\n";
            }
            $ht_items[] = array(
                'id'    => (int) $ht_pid,
                'title' => get_the_title($ht_pid),
                'file'  => $ht_file,
            );
        }
        $ht_rows[] = array('slug' => $ht_b['slug'], 'title' => $ht_b['title'], 'items' => $ht_items);
    }
    file_put_contents($ht_out_dir . '/sources.json', json_encode($ht_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo count($ht_rows) . " pachete -> data/bundles/sources.json\n";
    exit;
}

/* ---------------------------------------------------------------------------
 * Categoria
 * ------------------------------------------------------------------------ */

$ht_cat_id = 0;
$ht_cat    = get_term_by('slug', $ht_def['category']['slug'], 'product_cat');
if ($ht_cat) {
    $ht_cat_id = (int) $ht_cat->term_id;
} elseif ($ht_dry) {
    echo "  + categoria '{$ht_def['category']['name']}'\n";
} else {
    $ht_term = wp_insert_term($ht_def['category']['name'], 'product_cat', array('slug' => $ht_def['category']['slug']));
    if (is_wp_error($ht_term)) {
        exit('Categoria nu s-a creat: ' . $ht_term->get_error_message() . "\n");
    }
    $ht_cat_id = (int) $ht_term['term_id'];
    if (function_exists('pll_set_term_language')) {
        pll_set_term_language($ht_cat_id, 'ro');
    }
    echo "  + categoria '{$ht_def['category']['name']}' (#{$ht_cat_id})\n";
}

$ht_ru     = function_exists('pll_get_post') && in_array('ru', pll_languages_list(), true);
$ht_cat_ru = ($ht_ru && !empty($ht_def['category']['ru']))
    ? ht_bundle_category_ru($ht_cat_id, $ht_def['category']['ru'], $ht_dry)
    : 0;

/* ---------------------------------------------------------------------------
 * Pachetele
 * ------------------------------------------------------------------------ */

foreach ($ht_bundles as $ht_b) {
    $ht_slug    = $ht_b['slug'];
    $ht_id      = ht_bundle_find($ht_slug);
    $ht_regular = 0.0;
    foreach ($ht_b['items'] as $ht_pid) {
        $ht_regular += (float) wc_get_product($ht_pid)->get_price('edit');
    }
    $ht_regular = wc_format_decimal($ht_regular, 2);
    $ht_jpg     = $ht_out_dir . '/' . $ht_slug . '.jpg';

    if ($ht_dry) {
        echo ($ht_id ? "  ~ #{$ht_id}" : '  +') . " {$ht_slug}: {$ht_b['price']} lei (separat {$ht_regular}), "
            . count($ht_b['items']) . ' produse' . (file_exists($ht_jpg) ? ', cu poza' : ', fara poza') . "\n";
        if ($ht_ru && !empty($ht_b['ru'])) {
            echo ht_bundle_ru($ht_id, $ht_b, $ht_cat_ru, true);
        }
        continue;
    }

    $ht_product = $ht_id ? wc_get_product($ht_id) : new WC_Product_Bundle();
    if (!$ht_product instanceof WC_Product_Bundle) {
        echo "  ! {$ht_slug}: #{$ht_id} exista, dar nu e pachet\n";
        continue;
    }

    $ht_product->set_name($ht_b['title']);
    $ht_product->set_slug($ht_slug);
    $ht_product->set_status($ht_draft ? 'draft' : 'publish');
    $ht_product->set_catalog_visibility('visible');
    $ht_product->set_short_description($ht_b['excerpt']);
    $ht_product->set_description('<p>' . esc_html($ht_b['excerpt']) . '</p>');
    $ht_product->set_regular_price($ht_regular);
    $ht_product->set_sale_price(wc_format_decimal($ht_b['price'], 2));
    if ($ht_cat_id) {
        $ht_product->set_category_ids(array($ht_cat_id));
    }
    if (!$ht_id || !$ht_product->get_sku('edit')) {
        try {
            $ht_product->set_sku($ht_b['sku']);
        } catch (WC_Data_Exception $ht_e) {
            echo "  ~ {$ht_slug}: SKU {$ht_b['sku']} e luat, raman fara\n";
        }
    }
    ht_bundle_set_items($ht_product, $ht_b['items']);
    $ht_id = $ht_product->save();

    update_post_meta($ht_id, '_ht_bundle', $ht_slug);
    if (function_exists('pll_set_post_language')) {
        pll_set_post_language($ht_id, 'ro');
    }
    if (function_exists('update_field')) {
        update_field('field_ht_product_benefits', array_map(function ($text) {
            return array('field_ht_product_benefit_text' => $text);
        }, $ht_b['benefits']), $ht_id);
    }

    $ht_note = '';
    if (file_exists($ht_jpg)) {
        $ht_md5 = md5_file($ht_jpg);
        $ht_set = ht_bundle_set_image($ht_id);
        if (!$ht_set || get_post_meta($ht_set, '_ht_bundle_image', true) !== $ht_md5) {
            /* media_handle_sideload muta fisierul: lucram pe o copie temporara */
            $ht_tmp = wp_tempnam($ht_slug . '.jpg');
            copy($ht_jpg, $ht_tmp);
            $ht_att = media_handle_sideload(array('name' => $ht_slug . '.jpg', 'tmp_name' => $ht_tmp), $ht_id, $ht_b['title']);
            if (is_wp_error($ht_att)) {
                @unlink($ht_tmp);
                $ht_note = ', poza nu s-a urcat: ' . $ht_att->get_error_message();
            } else {
                update_post_meta($ht_att, '_wp_attachment_image_alt', $ht_b['title']);
                update_post_meta($ht_att, '_ht_bundle_image', $ht_md5);
                if (function_exists('pll_set_post_language')) {
                    pll_set_post_language($ht_att, 'ro');
                }
                $ht_product = wc_get_product($ht_id);
                ht_bundle_place_image($ht_product, $ht_att);
                $ht_product->save();
                $ht_note = ", poza #{$ht_att}";
            }
        }
    }

    wc_delete_product_transients($ht_id);
    echo "  + #{$ht_id} {$ht_slug}: {$ht_b['price']} lei (separat {$ht_regular}){$ht_note}\n";

    if ($ht_ru && !empty($ht_b['ru'])) {
        echo ht_bundle_ru($ht_id, $ht_b, $ht_cat_ru, false);
    }
}
