<?php
/**
 * Datele de facturare - pasul 1.
 *
 * Suprascrie checkout/form-billing.php din WooCommerce 3.6.0. Fata de sablonul
 * pluginului se schimba doar invelisul: titlul devine pas numerotat, restul
 * campurilor sunt cele din WooCommerce, asezate pe doua coloane din
 * ht_checkout_fields() (inc/checkout.php).
 *
 * @package Herbal_Therapy
 * @version 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$ht_billing_only = wc_ship_to_billing_address_only() && WC()->cart->needs_shipping();
?>

<section class="ht-checkout-step ht-checkout-step--billing woocommerce-billing-fields">

    <h2 class="ht-checkout-step__title">
        <span class="ht-checkout-step__num">1</span>
        <?php
        echo $ht_billing_only
            ? esc_html__('Date de facturare și livrare', 'herbal-therapy')
            : esc_html__('Date de facturare', 'herbal-therapy');
        ?>
    </h2>

    <div class="ht-checkout-step__body">

        <?php do_action('woocommerce_before_checkout_billing_form', $checkout); ?>

        <div class="ht-checkout-fields woocommerce-billing-fields__field-wrapper">
            <?php
            foreach ($checkout->get_checkout_fields('billing') as $ht_key => $ht_field) {
                woocommerce_form_field($ht_key, $ht_field, $checkout->get_value($ht_key));
            }
            ?>
        </div>

        <?php do_action('woocommerce_after_checkout_billing_form', $checkout); ?>

        <?php if (!is_user_logged_in() && $checkout->is_registration_enabled()) : ?>

            <div class="ht-checkout-account woocommerce-account-fields">

                <?php if (!$checkout->is_registration_required()) : ?>
                    <p class="form-row form-row-wide create-account">
                        <label class="ht-checkout__check woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                            <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                                   id="createaccount" type="checkbox" name="createaccount" value="1"
                                <?php checked(
                                    (true === $checkout->get_value('createaccount')
                                        || true === apply_filters('woocommerce_create_account_default_checked', false)),
                                    true
                                ); ?>>
                            <span><?php esc_html_e('Vreau să îmi creez un cont', 'herbal-therapy'); ?></span>
                        </label>
                    </p>
                <?php endif; ?>

                <?php do_action('woocommerce_before_checkout_registration_form', $checkout); ?>

                <?php if ($checkout->get_checkout_fields('account')) : ?>
                    <div class="ht-checkout-fields create-account">
                        <?php foreach ($checkout->get_checkout_fields('account') as $ht_key => $ht_field) : ?>
                            <?php woocommerce_form_field($ht_key, $ht_field, $checkout->get_value($ht_key)); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php do_action('woocommerce_after_checkout_registration_form', $checkout); ?>

            </div>

        <?php endif; ?>

    </div>

</section>
