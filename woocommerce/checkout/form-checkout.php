<?php
/**
 * Formularul de finalizare a comenzii.
 *
 * Suprascrie checkout/form-checkout.php din WooCommerce 9.4.0.
 *
 * Doua coloane: in stanga pasii de completat, in dreapta sumarul lipit la scroll.
 * Fata de sablonul pluginului s-au mutat doua lucruri, amandoua din inc/checkout.php:
 * blocul de plata (din sumar in coloana din stanga, ca pasul 3) si butonul de
 * comanda (din blocul de plata in sumar, sub total).
 *
 * @package Herbal_Therapy
 * @version 9.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_before_checkout_form', $checkout);

/* daca inregistrarea e obligatorie si oprita, vizitatorul nu poate comanda */
if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
    echo '<p class="ht-checkout__notice">' . esc_html(apply_filters(
        'woocommerce_checkout_must_be_logged_in_message',
        __('Trebuie să fii autentificat ca să finalizezi comanda.', 'herbal-therapy')
    )) . '</p>';

    return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout ht-checkout__form"
      action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data"
      aria-label="<?php esc_attr_e('Finalizare comandă', 'herbal-therapy'); ?>">

    <div class="ht-checkout__grid">

        <div class="ht-checkout__main">

            <?php if ($checkout->get_checkout_fields()) : ?>

                <?php do_action('woocommerce_checkout_before_customer_details'); ?>

                <?php /* structura 'col2-set' ramane: de ea depind scripturi si extensii */ ?>
                <div class="col2-set" id="customer_details">
                    <div class="col-1">
                        <?php do_action('woocommerce_checkout_billing'); ?>
                    </div>

                    <div class="col-2">
                        <?php do_action('woocommerce_checkout_shipping'); ?>
                    </div>
                </div>

                <?php do_action('woocommerce_checkout_after_customer_details'); ?>

            <?php endif; ?>

            <?php
            /*
             * Pasul de plata. Functia e aceeasi pe care WooCommerce o tine implicit
             * in sumar - a fost desprinsa de acolo in inc/checkout.php.
             */
            ?>
            <section class="ht-checkout-step ht-checkout-step--payment">
                <h2 class="ht-checkout-step__title">
                    <span class="ht-checkout-step__num">3</span>
                    <?php esc_html_e('Modalitate de plată', 'herbal-therapy'); ?>
                </h2>

                <div class="ht-checkout-step__body">
                    <?php woocommerce_checkout_payment(); ?>
                </div>
            </section>

        </div>

        <aside class="ht-checkout__aside">
            <div class="ht-checkout__summary" data-ht-checkout-summary>

                <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>

                <h2 class="ht-checkout__summary-title" id="order_review_heading">
                    <?php esc_html_e('Comanda ta', 'herbal-therapy'); ?>
                    <?php /* bulina verde cu numarul de produse, ca in Figma; se reface din checkout.js */ ?>
                    <span class="ht-checkout__summary-count"><?php echo esc_html(ht_checkout_count()); ?></span>
                </h2>

                <?php do_action('woocommerce_checkout_before_order_review'); ?>

                <div id="order_review" class="woocommerce-checkout-review-order">
                    <?php do_action('woocommerce_checkout_order_review'); ?>
                </div>

                <?php do_action('woocommerce_checkout_after_order_review'); ?>

                <?php ht_checkout_coupon_field(); ?>

                <?php ht_checkout_place_order(); ?>

            </div>
        </aside>

    </div>

</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>
