<?php
/**
 * Sumarul comenzii - produsele si totalurile din coloana din dreapta.
 *
 * Suprascrie checkout/review-order.php din WooCommerce 11.0.0.
 *
 * Sablonul pluginului deseneaza un tabel; aici e o lista, ca in cosul rapid.
 * Clasa 'woocommerce-checkout-review-order-table' ramane pe elementul de la
 * radacina chiar daca nu mai e tabel: dupa ea isi cauta checkout.js fragmentul
 * pe care il inlocuieste la fiecare recalculare.
 *
 * @package Herbal_Therapy
 * @version 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$ht_items = ht_checkout_items();
$ht_coupons = WC()->cart->get_coupons();
?>

<div class="ht-checkout-review woocommerce-checkout-review-order-table">

    <?php do_action('woocommerce_review_order_before_cart_contents'); ?>

    <ul class="ht-checkout-review__list">
        <?php foreach ($ht_items as $ht_item) : ?>
            <li class="ht-checkout-review__item <?php echo esc_attr($ht_item['class']); ?>">

                <span class="ht-checkout-review__img"
                      style="background-image:url('<?php echo esc_url($ht_item['image']); ?>')">
                    <span class="ht-checkout-review__qty"><?php echo wp_kses_post($ht_item['quantity']); ?></span>
                </span>

                <div class="ht-checkout-review__info">
                    <?php if ($ht_item['url']) : ?>
                        <a class="ht-checkout-review__title" href="<?php echo esc_url($ht_item['url']); ?>">
                            <?php echo wp_kses_post($ht_item['title']); ?>
                        </a>
                    <?php else : ?>
                        <span class="ht-checkout-review__title"><?php echo wp_kses_post($ht_item['title']); ?></span>
                    <?php endif; ?>

                    <?php if ($ht_item['meta']) : ?>
                        <div class="ht-checkout-review__meta"><?php echo wp_kses_post($ht_item['meta']); ?></div>
                    <?php endif; ?>
                </div>

                <span class="ht-checkout-review__price"><?php echo wp_kses_post($ht_item['total']); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php do_action('woocommerce_review_order_after_cart_contents'); ?>

    <div class="ht-checkout-total">

        <div class="ht-checkout-total__row cart-subtotal">
            <span class="ht-checkout-total__text"><?php esc_html_e('Valoarea produselor', 'herbal-therapy'); ?></span>
            <span class="ht-checkout-total__value"><?php wc_cart_totals_subtotal_html(); ?></span>
        </div>

        <?php foreach ($ht_coupons as $ht_code => $ht_coupon) : ?>
            <div class="ht-checkout-total__row ht-checkout-total__row--discount cart-discount coupon-<?php echo esc_attr(sanitize_title($ht_code)); ?>">
                <span class="ht-checkout-total__text">
                    <?php wc_cart_totals_coupon_label($ht_coupon); ?>
                    <button class="ht-checkout-total__drop" type="button"
                            data-ht-checkout-coupon-remove="<?php echo esc_attr($ht_code); ?>">
                        <?php esc_html_e('anulează', 'herbal-therapy'); ?>
                    </button>
                </span>
                <span class="ht-checkout-total__value"><?php wc_cart_totals_coupon_html($ht_coupon); ?></span>
            </div>
        <?php endforeach; ?>

        <?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>

            <?php do_action('woocommerce_review_order_before_shipping'); ?>

            <?php wc_cart_totals_shipping_html(); ?>

            <?php do_action('woocommerce_review_order_after_shipping'); ?>

        <?php endif; ?>

        <?php foreach (WC()->cart->get_fees() as $ht_fee) : ?>
            <div class="ht-checkout-total__row fee">
                <span class="ht-checkout-total__text"><?php echo esc_html($ht_fee->name); ?></span>
                <span class="ht-checkout-total__value"><?php wc_cart_totals_fee_html($ht_fee); ?></span>
            </div>
        <?php endforeach; ?>

        <?php if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) : ?>
            <?php if ('itemized' === get_option('woocommerce_tax_total_display')) : ?>
                <?php foreach (WC()->cart->get_tax_totals() as $ht_code => $ht_tax) : ?>
                    <div class="ht-checkout-total__row tax-rate tax-rate-<?php echo esc_attr(sanitize_title($ht_code)); ?>">
                        <span class="ht-checkout-total__text"><?php echo esc_html($ht_tax->label); ?></span>
                        <span class="ht-checkout-total__value"><?php echo wp_kses_post($ht_tax->formatted_amount); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="ht-checkout-total__row tax-total">
                    <span class="ht-checkout-total__text"><?php echo esc_html(WC()->countries->tax_or_vat()); ?></span>
                    <span class="ht-checkout-total__value"><?php wc_cart_totals_taxes_total_html(); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php do_action('woocommerce_review_order_before_order_total'); ?>

        <div class="ht-checkout-total__row ht-checkout-total__row--sum order-total">
            <span class="ht-checkout-total__text"><?php esc_html_e('De plată', 'herbal-therapy'); ?></span>
            <span class="ht-checkout-total__value"><?php wc_cart_totals_order_total_html(); ?></span>
        </div>

        <?php do_action('woocommerce_review_order_after_order_total'); ?>

    </div>

</div>
