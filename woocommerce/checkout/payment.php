<?php
/**
 * Metodele de plata - pasul 3.
 *
 * Suprascrie checkout/payment.php din WooCommerce 10.9.0.
 *
 * Fata de sablonul pluginului, blocul de trimitere (conditii, buton, nonce) nu
 * mai e aici: il randeaza ht_checkout_place_order() in sumar, ca sa stea langa
 * total. Se pune la loc cu add_filter('ht_checkout_detach_submit', '__return_false').
 *
 * Clasa '.woocommerce-checkout-payment' ramane pe invelis: dupa ea isi cauta
 * checkout.js fragmentul pe care il inlocuieste la fiecare recalculare.
 *
 * @package Herbal_Therapy
 * @version 10.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!wp_doing_ajax()) {
    do_action('woocommerce_review_order_before_payment');
}
?>

<div id="payment" class="woocommerce-checkout-payment ht-checkout-payment">

    <?php if (WC()->cart && WC()->cart->needs_payment()) : ?>

        <ul class="wc_payment_methods payment_methods methods ht-checkout-payment__list"
            aria-label="<?php esc_attr_e('Modalități de plată', 'herbal-therapy'); ?>">
            <?php
            if (!empty($available_gateways)) {
                foreach ($available_gateways as $gateway) {
                    wc_get_template('checkout/payment-method.php', array('gateway' => $gateway));
                }
            } else {
                echo '<li class="ht-checkout-payment__empty">';
                wc_print_notice(apply_filters(
                    'woocommerce_no_available_payment_methods_message',
                    WC()->customer->get_billing_country()
                        ? esc_html__('Momentan nu există nicio modalitate de plată disponibilă. Scrie-ne și găsim o soluție.', 'herbal-therapy')
                        : esc_html__('Completează datele de mai sus ca să vezi modalitățile de plată.', 'herbal-therapy')
                ), 'notice');
                echo '</li>';
            }
            ?>
        </ul>

    <?php endif; ?>

    <noscript>
        <p class="ht-checkout-payment__noscript">
            <?php
            printf(
                /* translators: 1: eticheta butonului. */
                esc_html__('Browserul tău nu rulează JavaScript. Apasă %s înainte de a plasa comanda, altfel totalul afișat poate fi greșit.', 'herbal-therapy'),
                '<em>' . esc_html__('Actualizează totalurile', 'herbal-therapy') . '</em>' // phpcs:ignore WordPress.Security.EscapeOutput -- escapat aici.
            );
            ?>
            <button type="submit" class="button alt ht-checkout__submit ht-checkout__submit--totals"
                    name="woocommerce_checkout_update_totals"
                    value="<?php esc_attr_e('Actualizează totalurile', 'herbal-therapy'); ?>">
                <?php esc_html_e('Actualizează totalurile', 'herbal-therapy'); ?>
            </button>
        </p>
    </noscript>

    <?php if (!ht_checkout_detach_submit()) : ?>

        <div class="form-row place-order ht-checkout__place">

            <?php wc_get_template('checkout/terms.php'); ?>

            <?php do_action('woocommerce_review_order_before_submit'); ?>

            <?php
            echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit de WooCommerce.
                'woocommerce_order_button_html',
                '<button type="submit" class="button alt ht-checkout__submit" name="woocommerce_checkout_place_order" id="place_order" value="'
                . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '">'
                . esc_html($order_button_text) . '</button>'
            );
            ?>

            <?php do_action('woocommerce_review_order_after_submit'); ?>

            <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>

        </div>

    <?php endif; ?>

</div>

<?php
if (!wp_doing_ajax()) {
    do_action('woocommerce_review_order_after_payment');
}
