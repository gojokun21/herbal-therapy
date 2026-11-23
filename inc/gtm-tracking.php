<?php
/**
 * Evenimentul add_to_cart (GA4 / Meta prin GTM) pentru butoanele temei.
 *
 * GTM4WP trimite add_to_cart la click doar cand gaseste datele produsului:
 * span-ul .gtm4wp_productdata din bucla WooCommerce (li.product) sau atributul
 * data-gtm4wp_product_data pus pe butoanele din afara buclei. Cardurile temei
 * (prima pagina, carusele, cautare, cos, minicos), butonul principal si cel de
 * "pachet" de pe pagina produsului nu au niciuna, deci adaugarea in cos nu
 * ajungea in data layer. Pagina /produse/ (bucla) era singura care mergea.
 *
 * Aici:
 *  - datele vin tot de la GTM4WP, prin filtrul lui public
 *    'woocommerce_loop_add_to_cart_link' (acelasi item_id/sku/pret/categorie si
 *    aceeasi setare "SKU in loc de ID" ca in restul evenimentelor);
 *  - le punem pe buton in data-ht-gtm-item, NU in data-gtm4wp_product_data:
 *    GTM4WP ar trimite atunci evenimentul la click, cu cantitatea fixata la 1;
 *  - assets/js/gtm-cart.js trimite add_to_cart dupa raspunsul AJAX reusit, cu
 *    cantitatea reala, si sare peste butoanele pe care GTM4WP le acopera deja.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Este activ GTM4WP?
 *
 * @return bool
 */
function ht_gtm4wp_active()
{
    return defined('GTM4WP_OPTIONS');
}

/**
 * Scoate JSON-ul produsului din atributul data-gtm4wp_product_data al unui link.
 *
 * @param string $html Link-ul trecut prin filtrul GTM4WP.
 *
 * @return string JSON-ul (decodat din atribut), sau '' daca lipseste/e invalid.
 */
function ht_gtm_item_from_link($html)
{
    if (!is_string($html) || !preg_match('/\sdata-gtm4wp_product_data="([^"]*)"/', $html, $match)) {
        return '';
    }

    $json = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    $item = json_decode($json, true);

    if (!is_array($item) || !isset($item['item_id']) || '' === (string)$item['item_id']) {
        return '';
    }

    return $json;
}

/**
 * Datele GA4 ale produsului, exact cum le formeaza GTM4WP.
 *
 * @param WC_Product $product Produsul.
 *
 * @return string JSON pentru data-ht-gtm-item, sau '' (GTM4WP inactiv, produs
 *                care nu se adauga direct in cos).
 */
function ht_gtm_item_json($product)
{
    static $cache = array();

    if (!ht_gtm4wp_active() || !is_object($product) || !method_exists($product, 'get_id')) {
        return '';
    }

    $id = (int)$product->get_id();

    if (!isset($cache[$id])) {
        /* GTM4WP adauga atributul pe primul '<a ' - un link-proba e de ajuns */
        $probe = apply_filters('woocommerce_loop_add_to_cart_link', '<a href="#">', $product, array());
        $json = ht_gtm_item_from_link($probe);

        /* filtrul de mai sus serveste doar produsele simple; pachetele trec pe aici */
        if ('' === $json) {
            $json = ht_gtm_item_from_link(ht_gtm4wp_single_markup($product));
        }

        $cache[$id] = $json;
    }

    return $cache[$id];
}

/**
 * Span-ul .gtm4wp_single_productdata pe care GTM4WP il scrie langa butonul de
 * pe pagina produsului (merge pentru orice tip de produs, inclusiv pachete).
 *
 * Ruleaza doar callback-ul GTM4WP de pe 'woocommerce_after_add_to_cart_button',
 * nu tot hook-ul: alte pluginuri agatate acolo nu trebuie declansate din card.
 *
 * @param WC_Product $product Produsul.
 *
 * @return string HTML-ul scris de GTM4WP, sau ''.
 */
function ht_gtm4wp_single_markup($product)
{
    global $wp_filter;

    $hook = 'woocommerce_after_add_to_cart_button';

    if (empty($wp_filter[$hook]) || !isset($wp_filter[$hook]->callbacks)) {
        return '';
    }

    foreach ((array)$wp_filter[$hook]->callbacks as $callbacks) {
        foreach ((array)$callbacks as $callback) {
            $fn = isset($callback['function']) ? $callback['function'] : null;

            if (!is_array($fn) || !is_object($fn[0]) || 'single_add_to_cart_tracking' !== $fn[1]
                || false === stripos(get_class($fn[0]), 'gtm4wp')) {
                continue;
            }

            /* callback-ul citeste global $product */
            $had = array_key_exists('product', $GLOBALS);
            $previous = $had ? $GLOBALS['product'] : null;
            $GLOBALS['product'] = $product;

            ob_start();

            try {
                call_user_func($fn);
            } finally {
                $html = (string)ob_get_clean();

                if ($had) {
                    $GLOBALS['product'] = $previous;
                } else {
                    unset($GLOBALS['product']);
                }
            }

            return $html;
        }
    }

    return '';
}

/**
 * Atributul data-ht-gtm-item, gata de pus pe buton (cu spatiu in fata).
 *
 * @param WC_Product|string $product Produsul sau JSON-ul deja calculat.
 *
 * @return string
 */
function ht_gtm_item_attr($product)
{
    $json = is_string($product) ? $product : ht_gtm_item_json($product);

    return '' === $json ? '' : ' data-ht-gtm-item="' . esc_attr($json) . '"';
}

/**
 * Scriptul care trimite add_to_cart pentru butoanele temei.
 */
function ht_gtm_cart_assets()
{
    if (!ht_gtm4wp_active()) {
        return;
    }

    /* 'added_to_cart' e un eveniment jQuery al WooCommerce */
    wp_enqueue_script(
        'ht-gtm-cart',
        ht_asset_uri('/assets/js/gtm-cart.js'),
        array('jquery'),
        ht_asset_version('/assets/js/gtm-cart.js'),
        true
    );
}

add_action('wp_enqueue_scripts', 'ht_gtm_cart_assets', 20);
