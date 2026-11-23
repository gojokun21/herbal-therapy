/**
 * add_to_cart in data layer pentru butoanele temei (carduri, pagina produsului).
 *
 * Datele produsului vin de la GTM4WP, in data-ht-gtm-item (vezi
 * inc/gtm-tracking.php). Evenimentul pleaca dupa raspunsul AJAX reusit, cu
 * cantitatea din data-quantity (pe pagina produsului o tine la zi
 * single-product.js). Tag-urile din GTM (Meta AddToCart) il asculta.
 *
 * Butoanele din bucla WooCommerce (li.product cu .gtm4wp_productdata) sunt deja
 * acoperite de GTM4WP la click - le sarim, ca sa nu numaram de doua ori.
 */
(function ($) {
    'use strict';

    if (!$) {
        return;
    }

    function coveredByGtm4wp(button) {
        var box = button.closest('.product,.wc-block-grid__product,.wc-block-product');

        return !!(box && box.querySelector('.gtm4wp_productdata')) || button.hasAttribute('data-gtm4wp_product_data');
    }

    function readItem(button) {
        try {
            var item = JSON.parse(button.getAttribute('data-ht-gtm-item'));

            return item && typeof item === 'object' ? item : null;
        } catch (e) {
            return null;
        }
    }

    $(document.body).on('added_to_cart', function (event, fragments, cartHash, $button) {
        var button = $button && $button[0];

        if (!button || !button.hasAttribute('data-ht-gtm-item') || coveredByGtm4wp(button)) {
            return;
        }

        var item = readItem(button);

        if (!item) {
            return;
        }

        var quantity = parseInt(button.getAttribute('data-quantity'), 10);

        delete item.internal_id;
        delete item.productlink;
        delete item.product_type;
        item.quantity = quantity > 0 ? quantity : 1;
        item.price = parseFloat(item.price) || 0;

        var extra = {
            /* const din <head>-ul GTM4WP: nu sta pe window, dar e vizibila global */
            currency: typeof gtm4wp_currency !== 'undefined' ? gtm4wp_currency : undefined,
            value: Math.round(item.price * item.quantity * 100) / 100
        };

        if (typeof window.gtm4wp_push_ecommerce === 'function') {
            window.gtm4wp_push_ecommerce('add_to_cart', [item], extra);

            return;
        }

        extra.items = [item];
        (window.dataLayer = window.dataLayer || []).push({event: 'add_to_cart', ecommerce: extra});
    });
})(window.jQuery);
