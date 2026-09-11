<?php
/**
 * Pagina de cont (WooCommerce > Contul meu).
 *
 * Pagina de cont e o pagina obisnuita cu shortcode-ul [woocommerce_my_account],
 * deci WooCommerce nu o trece prin propriul incarcator de sabloane - fara
 * interventia de aici ar iesi prin page.php, in coloana de 820px a articolelor.
 * Aici o mutam pe templates/account.php si ii dam layout-ul propriu, iar
 * bucatile din interior sunt suprascrise in woocommerce/myaccount/.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Sablonul si stilurile
 * ------------------------------------------------------------------------ */

/**
 * Scoate pagina de cont din page.php si o duce pe sablonul temei.
 *
 * @param string $template Calea aleasa de WordPress.
 *
 * @return string
 */
function ht_account_template($template)
{
    if (!is_account_page()) {
        return $template;
    }

    $custom = HT_DIR . '/templates/account.php';

    return file_exists($custom) ? $custom : $template;
}

add_filter('template_include', 'ht_account_template', 99);

/**
 * Stilurile si scriptul paginii de cont.
 *
 * Foaia depinde de 'ht-shop': de acolo vin notificarile si paginarea WooCommerce.
 */
function ht_enqueue_account_assets()
{
    if (!is_account_page()) {
        return;
    }

    wp_enqueue_style(
        'ht-account',
        ht_asset_uri('/assets/css/account.css'),
        array('ht-shop'),
        ht_asset_version('/assets/css/account.css')
    );

    wp_enqueue_script(
        'ht-account',
        ht_asset_uri('/assets/js/account.js'),
        array(),
        ht_asset_version('/assets/js/account.js'),
        true
    );
}

add_action('wp_enqueue_scripts', 'ht_enqueue_account_assets', 21);

/* ---------------------------------------------------------------------------
 * Meniul contului
 * ------------------------------------------------------------------------ */

/**
 * Iconita din dreptul unei intrari de meniu.
 *
 * Cheile sunt endpoint-urile WooCommerce; ce nu e in lista ramane fara iconita.
 *
 * @param string $endpoint Endpoint-ul, ex. 'orders'.
 *
 * @return string Numele iconitei din ht_icons() sau sir gol.
 */
function ht_account_menu_icon($endpoint)
{
    $icons = apply_filters('ht_account_menu_icons', array(
        'dashboard'           => 'grid',
        'orders'              => 'box',
        'downloads'           => 'download',
        'edit-address'        => 'pin',
        'payment-methods'     => 'shield',
        'edit-account'        => 'user',
        'ht-favorites'        => 'heart',
        'customer-logout'     => 'logout',
    ));

    return isset($icons[$endpoint]) ? $icons[$endpoint] : '';
}

/**
 * Ascunde "Descarcari" cand clientul nu are niciun fisier de descarcat.
 *
 * Magazinul vinde produse fizice; intrarea ar duce mereu la o lista goala.
 *
 * @param array $items Intrarile de meniu.
 *
 * @return array
 */
function ht_account_menu_items($items)
{
    if (isset($items['downloads']) && !ht_account_has_downloads()) {
        unset($items['downloads']);
    }

    return $items;
}

add_filter('woocommerce_account_menu_items', 'ht_account_menu_items');

/**
 * Clientul curent are fisiere de descarcat? Se calculeaza o singura data pe cerere.
 *
 * @return bool
 */
function ht_account_has_downloads()
{
    static $has = null;

    if (null === $has) {
        $customer = function_exists('WC') && WC()->customer ? WC()->customer : null;
        $has = $customer ? (bool)$customer->get_downloadable_products() : false;
    }

    return $has;
}

/* ---------------------------------------------------------------------------
 * Datele afisate in interfata
 * ------------------------------------------------------------------------ */

/**
 * Initialele pentru avatarul din bara laterala.
 *
 * @param WP_User $user Utilizatorul.
 *
 * @return string Una sau doua litere.
 */
function ht_account_initials($user)
{
    $source = trim($user->first_name . ' ' . $user->last_name);

    if ('' === $source) {
        $source = $user->display_name;
    }

    $letters = '';

    foreach (array_slice(preg_split('~\s+~', trim($source)), 0, 2) as $word) {
        if ('' !== $word) {
            $letters .= function_exists('mb_substr') ? mb_substr($word, 0, 1) : substr($word, 0, 1);
        }
    }

    return function_exists('mb_strtoupper') ? mb_strtoupper($letters) : strtoupper($letters);
}

/**
 * Numele afisat in bara laterala - prenumele si numele, cu display_name ca rezerva.
 *
 * @param WP_User $user Utilizatorul.
 *
 * @return string
 */
function ht_account_display_name($user)
{
    $name = trim($user->first_name . ' ' . $user->last_name);

    return '' !== $name ? $name : $user->display_name;
}

/**
 * Titlul paginii curente.
 *
 * Pe endpoint-uri WooCommerce inlocuieste titlul paginii cu cel al endpoint-ului
 * (Comenzi, Adrese, ...), asa ca luam direct titlul din bucla.
 *
 * @return string
 */
function ht_account_title()
{
    $title = wp_strip_all_tags(get_the_title());

    return '' !== $title ? $title : __('Contul meu', 'herbal-therapy');
}

/**
 * Cardurile de acces rapid din panoul de control.
 *
 * @return array Fiecare intrare are 'url', 'icon', 'title', 'text'.
 */
function ht_account_dashboard_cards()
{
    $orders = wc_get_customer_order_count(get_current_user_id());

    $cards = array(
        'orders' => array(
            'url'   => wc_get_endpoint_url('orders'),
            'icon'  => 'box',
            'title' => __('Comenzile mele', 'herbal-therapy'),
            'text'  => $orders
                /* translators: %s: numarul de comenzi. */
                ? sprintf(_n('%s comandă plasată', '%s comenzi plasate', $orders, 'herbal-therapy'), number_format_i18n($orders))
                : __('Încă nu ai plasat nicio comandă', 'herbal-therapy'),
        ),
        'addresses' => array(
            'url'   => wc_get_endpoint_url('edit-address'),
            'icon'  => 'pin',
            'title' => __('Adrese', 'herbal-therapy'),
            'text'  => __('Adresele de livrare și de facturare', 'herbal-therapy'),
        ),
        'account' => array(
            'url'   => wc_get_endpoint_url('edit-account'),
            'icon'  => 'user',
            'title' => __('Detaliile contului', 'herbal-therapy'),
            'text'  => __('Datele personale și parola', 'herbal-therapy'),
        ),
        'favorites' => array(
            'url'   => ht_header_link('wishlist'),
            'icon'  => 'heart',
            'title' => __('Favorite', 'herbal-therapy'),
            'text'  => __('Produsele pe care le-ai salvat', 'herbal-therapy'),
        ),
    );

    return apply_filters('ht_account_dashboard_cards', $cards);
}
