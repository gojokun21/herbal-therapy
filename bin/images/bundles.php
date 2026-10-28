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
 * schimbat (meta `_ht_bundle_image` = md5 pe atasament).
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
if (!class_exists('WC_Product_Bundle')) {
    exit("WooCommerce Product Bundles nu e activ.\n");
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

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
 * Pachetul creat deja pentru slug-ul dat (dupa meta `_ht_bundle`), sau 0.
 */
function ht_bundle_find($slug)
{
    $ids = get_posts(array(
        'post_type'      => 'product',
        'post_status'    => 'any',
        'meta_key'       => '_ht_bundle',
        'meta_value'     => $slug,
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'lang'           => '',
    ));

    return $ids ? (int) $ids[0] : 0;
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
    $ht_product->set_layout('default');
    $ht_product->set_group_mode('parent');
    $ht_product->set_editable_in_cart(false);

    /* componentele existente se pastreaza dupa produs, ca sa nu se schimbe ID-urile */
    $ht_existing = array();
    foreach ($ht_product->get_bundled_data_items('edit') as $ht_item) {
        $ht_existing[(int) $ht_item->get_product_id()] = (int) $ht_item->get_id();
    }
    $ht_items = array();
    foreach (array_values($ht_b['items']) as $ht_i => $ht_pid) {
        $ht_items[] = array(
            'bundled_item_id' => isset($ht_existing[$ht_pid]) ? $ht_existing[$ht_pid] : 0,
            'product_id'      => (int) $ht_pid,
            'menu_order'      => $ht_i,
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
    $ht_product->set_bundled_data_items($ht_items);
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
        $ht_md5   = md5_file($ht_jpg);
        $ht_thumb = (int) get_post_thumbnail_id($ht_id);
        if (!$ht_thumb || get_post_meta($ht_thumb, '_ht_bundle_image', true) !== $ht_md5) {
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
                set_post_thumbnail($ht_id, $ht_att);
                $ht_note = ", poza #{$ht_att}";
            }
        }
    }

    wc_delete_product_transients($ht_id);
    echo "  + #{$ht_id} {$ht_slug}: {$ht_b['price']} lei (separat {$ht_regular}){$ht_note}\n";
}
