<?php
/**
 * Bara de progres spre livrarea gratuita.
 *
 * "Mai adauga produse de 250,00 MDL si ai livrare gratuita!" cu o bara umpluta
 * proportional, in cosul rapid (deasupra listei) si in sumarul paginii de cos.
 * Blocul intra in fragmentele reincarcate prin AJAX, deci se actualizeaza la
 * fiecare schimbare a cosului.
 *
 * Pragul nu e scris in tema: vine din metoda "Livrare gratuita" a zonei de
 * livrare in care cade clientul (WooCommerce > Setari > Livrare), cu conditia
 * "suma minima a comenzii". Cand zona nu are o astfel de metoda - alta tara,
 * sau livrarea gratuita se da doar cu cupon - bara nu apare.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Regula de livrare gratuita din zona clientului.
 *
 * @return array|null array('min' => float, 'ignore_discounts' => bool), null cand nu exista.
 */
function ht_free_shipping_rule()
{
    if (!function_exists('WC') || !WC()->cart || !WC()->cart->needs_shipping()) {
        return null;
    }

    /* pe paginile obisnuite pachetele nu sunt calculate; fara ele nu stim zona */
    $packages = WC()->shipping()->get_packages();

    if (!$packages) {
        WC()->cart->calculate_shipping();
        $packages = WC()->shipping()->get_packages();
    }

    if (!$packages) {
        return null;
    }

    $zone = WC_Shipping_Zones::get_zone_matching_package(reset($packages));

    foreach ($zone->get_shipping_methods(true) as $method) {
        if ('free_shipping' !== $method->id) {
            continue;
        }

        /* doar pragul de suma; "cupon" sau "ambele" nu se pot arata ca progres */
        if (!in_array($method->requires, array('min_amount', 'either'), true)) {
            continue;
        }

        $min = (float)$method->min_amount;

        if ($min <= 0) {
            continue;
        }

        return apply_filters('ht_free_shipping_rule', array(
            'min'              => $min,
            'ignore_discounts' => 'yes' === $method->ignore_discounts,
        ), $method);
    }

    return null;
}

/**
 * Suma din cos care conteaza pentru prag - exact cum o numara WooCommerce in
 * WC_Shipping_Free_Shipping::is_available(): subtotalul afisat, minus
 * reducerile cand metoda nu le ignora.
 *
 * @param bool $ignore_discounts Setarea "aplica pragul inainte de reduceri".
 *
 * @return float
 */
function ht_free_shipping_amount($ignore_discounts)
{
    $cart = WC()->cart;
    $total = (float)$cart->get_displayed_subtotal();

    if (!$ignore_discounts) {
        $total -= (float)$cart->get_discount_total();

        if ($cart->display_prices_including_tax()) {
            $total -= (float)$cart->get_discount_tax();
        }
    }

    return round($total, wc_get_price_decimals());
}

/**
 * Cat mai lipseste si cat e umpluta bara.
 *
 * Functie pura, testata in tests/ShippingProgressTest.php.
 *
 * @param float $threshold Pragul de livrare gratuita.
 * @param float $amount    Suma din cos care conteaza.
 *
 * @return array array('remaining' => float, 'percent' => int, 'reached' => bool).
 */
function ht_shipping_progress_data($threshold, $amount)
{
    $threshold = (float)$threshold;
    $amount = max(0.0, (float)$amount);

    if ($threshold <= 0 || $amount >= $threshold) {
        return array('remaining' => 0.0, 'percent' => 100, 'reached' => true);
    }

    return array(
        'remaining' => $threshold - $amount,
        'percent'   => (int)floor($amount / $threshold * 100),
        'reached'   => false,
    );
}

/**
 * Randeaza bara. Nu scoate nimic cand zona clientului nu are livrare gratuita
 * pe prag de suma.
 */
function ht_shipping_progress()
{
    $rule = ht_free_shipping_rule();

    if (!$rule) {
        return;
    }

    $data = ht_shipping_progress_data($rule['min'], ht_free_shipping_amount($rule['ignore_discounts']));
    ?>
    <div class="ht-ship-progress<?php echo $data['reached'] ? ' is-reached' : ''; ?>" role="status">
        <p class="ht-ship-progress__text">
            <?php if ($data['reached']) : ?>
                <?php esc_html_e('Ai livrare gratuită!', 'herbal-therapy'); ?>
            <?php else : ?>
                <?php
                printf(
                    /* translators: %s: suma care mai lipseste pana la livrarea gratuita. */
                    esc_html__('Mai adaugă produse de %s și ai livrare gratuită!', 'herbal-therapy'),
                    '<strong>' . wp_kses_post(wc_price($data['remaining'])) . '</strong>'
                );
                ?>
            <?php endif; ?>
        </p>
        <div class="ht-ship-progress__bar" aria-hidden="true">
            <span class="ht-ship-progress__fill" style="width: <?php echo (int)$data['percent']; ?>%"></span>
        </div>
    </div>
    <?php
}
