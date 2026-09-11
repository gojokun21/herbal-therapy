<?php
/**
 * Metodele de livrare din sumarul comenzii.
 *
 * Suprascrie cart/cart-shipping.php din WooCommerce 8.8.0.
 *
 * Sablonul pluginului scoate un rand de tabel (<tr>), iar sumarul temei nu mai e
 * tabel - un <tr> in afara unui tabel e aruncat de parser, deci randul se rescrie
 * cu acelasi rand de sumar ca restul totalurilor. Logica din interior si numele
 * campurilor raman neatinse: dupa 'input.shipping_method' isi cauta checkout.js
 * metoda aleasa.
 *
 * @package Herbal_Therapy
 * @version 8.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$formatted_destination = isset($formatted_destination)
    ? $formatted_destination
    : WC()->countries->get_formatted_address($package['destination'], ', ');

$has_calculated_shipping = !empty($has_calculated_shipping);
$show_shipping_calculator = !empty($show_shipping_calculator);
$calculator_text = '';
?>

<div class="ht-checkout-total__row ht-checkout-total__row--shipping woocommerce-shipping-totals shipping">

    <span class="ht-checkout-total__text"><?php echo wp_kses_post($package_name); ?></span>

    <div class="ht-checkout-total__value" data-title="<?php echo esc_attr($package_name); ?>">
        <?php if (!empty($available_methods) && is_array($available_methods)) : ?>

            <ul id="shipping_method" class="woocommerce-shipping-methods ht-checkout-ship">
                <?php foreach ($available_methods as $method) : ?>
                    <li class="ht-checkout-ship__item">
                        <?php
                        if (1 < count($available_methods)) {
                            printf(
                                '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />',
                                $index, // phpcs:ignore WordPress.Security.EscapeOutput -- intreg.
                                esc_attr(sanitize_title($method->id)),
                                esc_attr($method->id),
                                checked($method->id, $chosen_method, false) // phpcs:ignore WordPress.Security.EscapeOutput -- atribut fix.
                            );
                        } else {
                            printf(
                                '<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" />',
                                $index, // phpcs:ignore WordPress.Security.EscapeOutput -- intreg.
                                esc_attr(sanitize_title($method->id)),
                                esc_attr($method->id)
                            );
                        }

                        printf(
                            '<label for="shipping_method_%1$s_%2$s">%3$s</label>',
                            $index, // phpcs:ignore WordPress.Security.EscapeOutput -- intreg.
                            esc_attr(sanitize_title($method->id)),
                            wc_cart_totals_shipping_method_label($method) // phpcs:ignore WordPress.Security.EscapeOutput -- pret formatat de WooCommerce.
                        );

                        do_action('woocommerce_after_shipping_rate', $method, $index);
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (is_cart()) : ?>
                <p class="woocommerce-shipping-destination">
                    <?php
                    if ($formatted_destination) {
                        printf(
                            /* translators: %s: adresa de livrare. */
                            esc_html__('Livrare către %s.', 'herbal-therapy') . ' ',
                            '<strong>' . esc_html($formatted_destination) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput -- escapat mai sus.
                        );
                        $calculator_text = esc_html__('Schimbă adresa', 'herbal-therapy');
                    } else {
                        echo wp_kses_post(apply_filters(
                            'woocommerce_shipping_estimate_html',
                            __('Opțiunile de livrare se calculează la finalizarea comenzii.', 'herbal-therapy')
                        ));
                    }
                    ?>
                </p>
            <?php endif; ?>

        <?php
        elseif (!$has_calculated_shipping || !$formatted_destination) :
            if (is_cart() && 'no' === get_option('woocommerce_enable_shipping_calc')) {
                echo wp_kses_post(apply_filters(
                    'woocommerce_shipping_not_enabled_on_cart_html',
                    __('Costul livrării se calculează la finalizarea comenzii.', 'herbal-therapy')
                ));
            } else {
                echo wp_kses_post(apply_filters(
                    'woocommerce_shipping_may_be_available_html',
                    __('Completează adresa ca să vezi opțiunile de livrare.', 'herbal-therapy')
                ));
            }
        elseif (!is_cart()) :
            echo wp_kses_post(apply_filters(
                'woocommerce_no_shipping_available_html',
                __('Nu există opțiuni de livrare pentru adresa introdusă. Verifică datele sau scrie-ne.', 'herbal-therapy')
            ));
        else :
            echo wp_kses_post(apply_filters(
                'woocommerce_cart_no_shipping_available_html',
                sprintf(
                    /* translators: %s: adresa de livrare. */
                    esc_html__('Nu am găsit opțiuni de livrare pentru %s.', 'herbal-therapy') . ' ',
                    '<strong>' . esc_html($formatted_destination) . '</strong>'
                ),
                $formatted_destination
            ));

            $calculator_text = esc_html__('Încearcă altă adresă', 'herbal-therapy');
        endif;
        ?>

        <?php if ($show_package_details) : ?>
            <p class="woocommerce-shipping-contents"><small><?php echo esc_html($package_details); ?></small></p>
        <?php endif; ?>

        <?php if ($show_shipping_calculator) : ?>
            <?php woocommerce_shipping_calculator($calculator_text); ?>
        <?php endif; ?>
    </div>

</div>
