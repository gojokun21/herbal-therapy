<?php
/**
 * Ajustari pentru zona de administrare.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ascunde meniul de comentarii - site-ul nu le foloseste.
 */
function ht_remove_admin_menus()
{
    remove_menu_page('edit-comments.php');
}

add_action('admin_menu', 'ht_remove_admin_menus');

/**
 * Curata bara de admin de intrarile nefolosite.
 *
 * @param WP_Admin_Bar $wp_admin_bar Bara de admin.
 */
function ht_remove_admin_bar_nodes($wp_admin_bar)
{
    foreach (array('comments', 'wpseo-menu', 'wp-logo') as $node) {
        $wp_admin_bar->remove_node($node);
    }
}

add_action('admin_bar_menu', 'ht_remove_admin_bar_nodes', 999);

/**
 * Permite incarcarea fisierelor SVG.
 *
 * Atentie: un SVG poate contine JavaScript. Daca vrei sa restrangi incarcarea
 * doar la administratori, adauga in conditie: current_user_can('manage_options').
 *
 * @param array $mimes Tipurile permise.
 *
 * @return array
 */
function ht_allow_svg_uploads($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';

    return $mimes;
}

add_filter('upload_mimes', 'ht_allow_svg_uploads');

/**
 * WordPress verifica continutul fisierului si respinge SVG-urile; trecem peste verificare
 * doar pentru extensia svg, restul tipurilor raman validate normal.
 *
 * @param array  $data     Datele fisierului.
 * @param string $file     Calea fisierului.
 * @param string $filename Numele fisierului.
 * @param array  $mimes    Tipurile permise.
 *
 * @return array
 */
function ht_fix_svg_mime($data, $file, $filename, $mimes)
{
    if (!empty($data['ext']) && !empty($data['type'])) {
        return $data;
    }

    $check = wp_check_filetype($filename, $mimes);

    if ('svg' !== $check['ext']) {
        return $data;
    }

    return array(
        'ext'             => 'svg',
        'type'            => 'image/svg+xml',
        'proper_filename' => isset($data['proper_filename']) ? $data['proper_filename'] : false,
    );
}

add_filter('wp_check_filetype_and_ext', 'ht_fix_svg_mime', 10, 4);

/**
 * SVG-urile nu au dimensiuni intrinseci; le limitam in lista media.
 */
function ht_admin_svg_styles()
{
    echo '<style>.attachment-266x266, .thumbnail img { width: 100% !important; height: auto !important; }</style>';
}

add_action('admin_head', 'ht_admin_svg_styles');

/**
 * Pagina de setari comune (ACF), daca pluginul e activ.
 */
function ht_acf_options_page()
{
    if (!function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page(array(
        'page_title' => __('Setări generale', 'herbal-therapy'),
        'menu_title' => __('Setări generale', 'herbal-therapy'),
        'menu_slug'  => 'common-settings',
        'capability' => 'edit_posts',
        'redirect'   => false,
        'icon_url'   => 'dashicons-admin-settings',
    ));
}

add_action('acf/init', 'ht_acf_options_page');
