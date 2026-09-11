<?php
/**
 * Pagina de multumire, dupa plasarea comenzii.
 *
 * Suprascrie checkout/thankyou.php din WooCommerce 8.1.0.
 *
 * Tabelele cu produsele si adresele de dedesubt vin din sabloanele pluginului
 * (order/order-details.php), iar stilul lor e cel de la pagina de cont - vezi
 * blocul de tabele din assets/css/account.css.
 *
 * @package Herbal_Therapy
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="woocommerce-order ht-checkout-done">

    <?php if ($order) : ?>

        <?php do_action('woocommerce_before_thankyou', $order->get_id()); ?>

        <?php if ($order->has_status('failed')) : ?>

            <div class="ht-checkout-done__card ht-checkout-done__card--failed">
                <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed ht-checkout-done__title">
                    <?php esc_html_e('Plata nu a putut fi procesată. Încearcă din nou sau alege altă modalitate de plată.', 'herbal-therapy'); ?>
                </p>

                <p class="woocommerce-notice--error woocommerce-thankyou-order-failed-actions ht-checkout-done__actions">
                    <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button pay ht-checkout-done__button">
                        <?php esc_html_e('Reia plata', 'herbal-therapy'); ?>
                    </a>

                    <?php if (is_user_logged_in()) : ?>
                        <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button pay ht-checkout-done__button ht-checkout-done__button--ghost">
                            <?php esc_html_e('Contul meu', 'herbal-therapy'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>

        <?php else : ?>

            <div class="ht-checkout-done__card">
                <span class="ht-checkout-done__mark" aria-hidden="true"></span>

                <p class="ht-checkout-done__title woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
                    <?php
                    echo esc_html(apply_filters(
                        'woocommerce_thankyou_order_received_text',
                        __('Comanda ta a fost înregistrată. Te sunăm pentru confirmare.', 'herbal-therapy'),
                        $order
                    ));
                    ?>
                </p>

                <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

                    <li class="woocommerce-order-overview__order order">
                        <?php esc_html_e('Numărul comenzii:', 'herbal-therapy'); ?>
                        <strong><?php echo esc_html($order->get_order_number()); ?></strong>
                    </li>

                    <li class="woocommerce-order-overview__date date">
                        <?php esc_html_e('Data:', 'herbal-therapy'); ?>
                        <strong><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></strong>
                    </li>

                    <?php if (is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email()) : ?>
                        <li class="woocommerce-order-overview__email email">
                            <?php esc_html_e('Email:', 'herbal-therapy'); ?>
                            <strong><?php echo esc_html($order->get_billing_email()); ?></strong>
                        </li>
                    <?php endif; ?>

                    <li class="woocommerce-order-overview__total total">
                        <?php esc_html_e('Total:', 'herbal-therapy'); ?>
                        <strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
                    </li>

                    <?php if ($order->get_payment_method_title()) : ?>
                        <li class="woocommerce-order-overview__payment-method method">
                            <?php esc_html_e('Plata:', 'herbal-therapy'); ?>
                            <strong><?php echo wp_kses_post($order->get_payment_method_title()); ?></strong>
                        </li>
                    <?php endif; ?>

                </ul>

                <p class="ht-checkout-done__actions">
                    <a class="ht-checkout-done__button" href="<?php echo esc_url(ht_minicart_shop_url()); ?>">
                        <?php esc_html_e('Înapoi la catalog', 'herbal-therapy'); ?>
                    </a>
                </p>
            </div>

        <?php endif; ?>

        <?php do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()); ?>
        <?php do_action('woocommerce_thankyou', $order->get_id()); ?>

    <?php else : ?>

        <div class="ht-checkout-done__card">
            <p class="ht-checkout-done__title">
                <?php
                echo esc_html(apply_filters(
                    'woocommerce_thankyou_order_received_text',
                    __('Comanda ta a fost înregistrată.', 'herbal-therapy'),
                    null
                ));
                ?>
            </p>
        </div>

    <?php endif; ?>

</div>
