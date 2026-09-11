<?php
/**
 * Livrarea si comentariul la comanda - pasul 2.
 *
 * Suprascrie checkout/form-shipping.php din WooCommerce 3.6.0.
 *
 * Structura din interior ramane cea a pluginului: bifa cu id-ul
 * 'ship-to-different-address-checkbox' si containerul '.shipping_address' sunt
 * legate in checkout.js, care desface adresa a doua la bifare.
 *
 * @package Herbal_Therapy
 * @version 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$ht_needs_address = (true === WC()->cart->needs_shipping_address());

$ht_notes_on = apply_filters(
    'woocommerce_enable_order_notes_field',
    'yes' === get_option('woocommerce_enable_order_comments', 'yes')
);
?>

<section class="ht-checkout-step ht-checkout-step--shipping woocommerce-shipping-fields">

    <h2 class="ht-checkout-step__title">
        <span class="ht-checkout-step__num">2</span>
        <?php
        echo $ht_needs_address
            ? esc_html__('Livrare', 'herbal-therapy')
            : esc_html__('Informații suplimentare', 'herbal-therapy');
        ?>
    </h2>

    <div class="ht-checkout-step__body">

        <?php if ($ht_needs_address) : ?>

            <div class="ht-checkout__toggle" id="ship-to-different-address">
                <label class="ht-checkout__check woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input id="ship-to-different-address-checkbox" type="checkbox" value="1"
                           name="ship_to_different_address"
                           class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                        <?php checked(apply_filters(
                            'woocommerce_ship_to_different_address_checked',
                            'shipping' === get_option('woocommerce_ship_to_destination') ? 1 : 0
                        ), 1); ?>>
                    <span><?php esc_html_e('Livrează la o altă adresă', 'herbal-therapy'); ?></span>
                </label>
            </div>

            <div class="shipping_address">

                <?php do_action('woocommerce_before_checkout_shipping_form', $checkout); ?>

                <div class="ht-checkout-fields woocommerce-shipping-fields__field-wrapper">
                    <?php
                    foreach ($checkout->get_checkout_fields('shipping') as $ht_key => $ht_field) {
                        woocommerce_form_field($ht_key, $ht_field, $checkout->get_value($ht_key));
                    }
                    ?>
                </div>

                <?php do_action('woocommerce_after_checkout_shipping_form', $checkout); ?>

            </div>

        <?php endif; ?>

        <div class="ht-checkout-notes woocommerce-additional-fields">

            <?php do_action('woocommerce_before_order_notes', $checkout); ?>

            <?php if ($ht_notes_on) : ?>
                <div class="ht-checkout-fields woocommerce-additional-fields__field-wrapper">
                    <?php foreach ($checkout->get_checkout_fields('order') as $ht_key => $ht_field) : ?>
                        <?php woocommerce_form_field($ht_key, $ht_field, $checkout->get_value($ht_key)); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php do_action('woocommerce_after_order_notes', $checkout); ?>

        </div>

    </div>

</section>
