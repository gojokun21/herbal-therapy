<?php
/**
 * Pagina de cos (WooCommerce > Cos) - "Produse in cosul tau".
 *
 * Ca si finalizarea comenzii, cosul e o pagina obisnuita cu shortcode sau bloc,
 * deci WooCommerce nu o trece prin propriul incarcator de sabloane. O mutam pe
 * templates/cart.php si desenam totul de aici, cu datele din cosul rapid
 * (inc/minicart.php): aceleasi linii, acelasi endpoint AJAX, aceleasi fragmente.
 *
 * Layout-ul e cel din Figma (node 122:2273): lista de produse in stanga,
 * sumarul lipit la scroll in dreapta, cu cod promotional si butonul de
 * finalizare.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Sablonul si fisierele paginii
 * ------------------------------------------------------------------------ */

/**
 * Scoate cosul din page.php si il duce pe sablonul temei.
 *
 * @param string $template Calea aleasa de WordPress.
 *
 * @return string
 */
function ht_cart_template($template)
{
    if (!is_cart()) {
        return $template;
    }

    $custom = HT_DIR . '/templates/cart.php';

    return file_exists($custom) ? $custom : $template;
}

add_filter('template_include', 'ht_cart_template', 99);

/**
 * CSS si JS. Scriptul refoloseste endpoint-ul si nonce-ul cosului rapid
 * (htMinicartData), inregistrate in ht_minicart_assets().
 */
function ht_enqueue_cart_assets()
{
    if (!is_cart()) {
        return;
    }

    wp_enqueue_style(
        'ht-cart',
        ht_asset_uri('/assets/css/cart.css'),
        array('ht-shop', 'ht-minicart'),
        ht_asset_version('/assets/css/cart.css')
    );

    wp_enqueue_script(
        'ht-cart',
        ht_asset_uri('/assets/js/cart.js'),
        array('ht-minicart'),
        ht_asset_version('/assets/js/cart.js'),
        true
    );
}

add_action('wp_enqueue_scripts', 'ht_enqueue_cart_assets', 21);

/* ---------------------------------------------------------------------------
 * Date
 * ------------------------------------------------------------------------ */

/**
 * Pretul unei linii, cu pretul vechi cand produsul e la reducere.
 *
 * Datele de baza vin din ht_minicart_item_data(); aici doar trecem produsul
 * prin filtrele standard ale paginii de cos, ca extensiile sa poata interveni.
 *
 * @param string $key  Cheia liniei din cos.
 * @param array  $item Linia din WC()->cart->get_cart().
 *
 * @return array|null
 */
function ht_cart_item_data($key, $item)
{
    $data = ht_minicart_item_data($key, $item);

    if (!$data) {
        return null;
    }

    if (!apply_filters('woocommerce_cart_item_visible', true, $item, $key)) {
        return null;
    }

    $data['title'] = apply_filters('woocommerce_cart_item_name', $data['title'], $item, $key);

    return $data;
}

/**
 * Liniile din cos, gata de randat.
 *
 * @return array
 */
function ht_cart_items()
{
    $items = array();

    if (!function_exists('WC') || !WC()->cart) {
        return $items;
    }

    foreach (WC()->cart->get_cart() as $key => $item) {
        $data = ht_cart_item_data($key, $item);

        if ($data) {
            $items[] = $data;
        }
    }

    return $items;
}

/**
 * Suma reducerilor din cupoane.
 *
 * Doar cupoanele: preturile promotionale ale produselor sunt deja cele din
 * subtotal, iar un rand "Reducere -47,10" sub un subtotal de 47,15 dadea
 * impresia ca totalul ar trebui sa fie zero (cerut pe 2026-09-11). Randul
 * apare in sumar doar cand exista un cod aplicat.
 *
 * @return float
 */
function ht_cart_discount_total()
{
    $cart = WC()->cart;
    $saved = (float)$cart->get_discount_total();

    if ($cart->display_prices_including_tax()) {
        $saved += (float)$cart->get_discount_tax();
    }

    return (float)apply_filters('ht_cart_discount_total', $saved);
}

/**
 * Textul "N produse" din sumar.
 *
 * @return string
 */
function ht_cart_count_label()
{
    $count = ht_cart_count();

    /* translators: %s: numarul de produse din cos. */
    return sprintf(_n('%s produs', '%s produse', $count, 'herbal-therapy'), number_format_i18n($count));
}

/* ---------------------------------------------------------------------------
 * Randare
 * ------------------------------------------------------------------------ */

/**
 * Firul Ariadnei de deasupra titlului, ca in Figma: Acasa / Contul meu / Produse in cosul tau.
 */
function ht_cart_breadcrumb()
{
    $crumbs = apply_filters('ht_cart_breadcrumb', array(
        array(__('Acasă', 'herbal-therapy'), home_url('/')),
        array(__('Contul meu', 'herbal-therapy'), ht_header_link('account')),
        array(__('Produse în coșul tău', 'herbal-therapy'), ''),
    ));

    $last = count($crumbs) - 1;
    ?>
    <nav class="ht-cart__crumbs" aria-label="<?php esc_attr_e('Navigare', 'herbal-therapy'); ?>">
        <?php foreach ($crumbs as $index => $crumb) : ?>
            <?php if ($index > 0) : ?>
                <span class="ht-cart__crumbs-sep" aria-hidden="true">/</span>
            <?php endif; ?>

            <?php if ($index < $last && !empty($crumb[1])) : ?>
                <a href="<?php echo esc_url($crumb[1]); ?>"><?php echo esc_html($crumb[0]); ?></a>
            <?php else : ?>
                <span class="ht-cart__crumbs-current" aria-current="page"><?php echo esc_html($crumb[0]); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * Un rand din lista de produse.
 *
 * @param array $item Datele din ht_cart_item_data().
 */
function ht_cart_row($item)
{
    $minus_off = ($item['quantity'] <= 1);
    $plus_off = ($item['max'] > 0 && $item['quantity'] >= $item['max']);
    ?>
    <li class="ht-cart-item" data-ht-cart-item="<?php echo esc_attr($item['key']); ?>">

        <div class="ht-cart-item__product">
            <?php if ($item['url']) : ?>
                <a class="ht-cart-item__img" href="<?php echo esc_url($item['url']); ?>" tabindex="-1" aria-hidden="true">
                    <img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy" decoding="async"/>
                </a>
            <?php else : ?>
                <span class="ht-cart-item__img">
                    <img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy" decoding="async"/>
                </span>
            <?php endif; ?>

            <div class="ht-cart-item__info">
                <?php if ($item['url']) : ?>
                    <a class="ht-cart-item__title" href="<?php echo esc_url($item['url']); ?>">
                        <?php echo esc_html($item['title']); ?>
                    </a>
                <?php else : ?>
                    <span class="ht-cart-item__title"><?php echo esc_html($item['title']); ?></span>
                <?php endif; ?>

                <?php if ($item['meta']) : ?>
                    <div class="ht-cart-item__meta"><?php echo wp_kses_post($item['meta']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ht-cart-item__actions">

            <?php if ($item['fixed']) : ?>
                <span class="ht-cart-item__single"><?php esc_html_e('1 buc.', 'herbal-therapy'); ?></span>
            <?php else : ?>
                <div class="ht-cart-item__qty">
                    <button class="ht-cart-item__qty-btn ht-cart-item__qty-btn--minus" type="button"
                            data-ht-cart-step="-1" <?php disabled($minus_off); ?>>
                        <span aria-hidden="true">&minus;</span>
                        <span class="ht-visually-hidden"><?php esc_html_e('Scade cantitatea', 'herbal-therapy'); ?></span>
                    </button>
                    <input class="ht-cart-item__qty-input" type="text" inputmode="numeric" size="3"
                           value="<?php echo esc_attr($item['quantity']); ?>"
                           data-ht-cart-qty
                           data-max="<?php echo esc_attr($item['max']); ?>"
                           aria-label="<?php esc_attr_e('Cantitate', 'herbal-therapy'); ?>">
                    <button class="ht-cart-item__qty-btn ht-cart-item__qty-btn--plus" type="button"
                            data-ht-cart-step="1" <?php disabled($plus_off); ?>>
                        <span aria-hidden="true">+</span>
                        <span class="ht-visually-hidden"><?php esc_html_e('Crește cantitatea', 'herbal-therapy'); ?></span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="ht-cart-item__price">
                <span class="ht-cart-item__price-value"><?php echo wp_kses_post($item['price']); ?></span>
                <?php if ($item['price_old']) : ?>
                    <span class="ht-cart-item__price-old"><?php echo wp_kses_post($item['price_old']); ?></span>
                <?php endif; ?>
            </div>

            <button class="ht-cart-item__delete" type="button" data-ht-cart-remove>
                <?php ht_icon('trash', 'ht-cart-item__delete-icon'); ?>
                <span class="ht-visually-hidden"><?php esc_html_e('Șterge din coș', 'herbal-therapy'); ?></span>
            </button>

            <?php ht_favorite_button($item['parent_id'], 'ht-cart-item__fav'); ?>
        </div>
    </li>
    <?php
}

/**
 * Metodele de livrare din sumar - "Livrare curier" si celelalte configurate in
 * WooCommerce. Randul vine din woocommerce/cart/cart-shipping.php (acelasi ca
 * la finalizare); aici doar il invelim si il stilizam ca in restul sumarului.
 *
 * Alegerea se trimite prin AJAX (cart.js -> ht_cart_shipping_ajax()) si
 * totalul se reface odata cu fragmentul.
 */
function ht_cart_shipping()
{
    $cart = WC()->cart;

    if (!$cart->needs_shipping() || !$cart->show_shipping()) {
        return;
    }
    ?>
    <div class="ht-cart-summary__shipping" data-ht-cart-shipping>
        <?php
        /* fara "Livrare catre ... / Schimba adresa": in cos raman doar metodele */
        add_filter('woocommerce_shipping_calculator_enable_city', '__return_false');
        add_filter('woocommerce_shipping_show_shipping_calculator', '__return_false');
        wc_cart_totals_shipping_html();
        remove_filter('woocommerce_shipping_show_shipping_calculator', '__return_false');
        ?>
    </div>
    <?php
}

/**
 * Sumarul din dreapta: total, numar de produse, reducere, cod promotional, buton.
 */
function ht_cart_summary()
{
    $cart = WC()->cart;
    $discount = ht_cart_discount_total();
    $applied = wc_coupons_enabled() ? $cart->get_applied_coupons() : array();
    ?>
    <div class="ht-cart-summary">

        <?php ht_shipping_progress(); ?>

        <div class="ht-cart-summary__total">
            <span><?php esc_html_e('Total', 'herbal-therapy'); ?></span>
            <span class="ht-cart-summary__total-value"><?php echo wp_kses_post($cart->get_total()); ?></span>
        </div>

        <hr class="ht-cart-summary__line"/>

        <div class="ht-cart-summary__row">
            <span><?php echo esc_html(ht_cart_count_label()); ?></span>
            <span><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></span>
        </div>

        <?php ht_cart_shipping(); ?>

        <?php if ($applied && $discount > 0) : ?>
            <div class="ht-cart-summary__row">
                <span><?php esc_html_e('Reducere', 'herbal-therapy'); ?></span>
                <span class="ht-cart-summary__discount">
                    <span>&minus;</span><?php echo wp_kses_post(wc_price($discount)); ?>
                </span>
            </div>
        <?php endif; ?>

        <p class="ht-cart-summary__vat"><?php esc_html_e('TVA inclus', 'herbal-therapy'); ?></p>

        <?php if (wc_coupons_enabled()) : ?>
            <div class="ht-cart-promo" data-ht-cart-promo>
                <label class="ht-cart-promo__field">
                    <input class="ht-cart-promo__input" type="text" name="ht_coupon" autocomplete="off"
                           placeholder="<?php esc_attr_e('Cod promoțional', 'herbal-therapy'); ?>"
                           data-ht-coupon-input>
                    <button class="ht-cart-promo__apply" type="button" data-ht-coupon-apply disabled>
                        <?php esc_html_e('Aplică', 'herbal-therapy'); ?>
                    </button>
                </label>

                <?php if ($applied) : ?>
                    <ul class="ht-cart-promo__applied">
                        <?php foreach ($applied as $code) : ?>
                            <li class="ht-cart-promo__applied-item">
                                <span><?php echo esc_html(wc_format_coupon_code($code)); ?></span>
                                <button type="button" data-ht-coupon-remove="<?php echo esc_attr($code); ?>">
                                    <?php esc_html_e('Anulează', 'herbal-therapy'); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a class="ht-cart-summary__submit" href="<?php echo esc_url(wc_get_checkout_url()); ?>">
            <?php esc_html_e('Finalizează comanda', 'herbal-therapy'); ?>
        </a>
    </div>
    <?php
}

/**
 * Starea de cos gol.
 */
function ht_cart_empty()
{
    ?>
    <div class="ht-cart-empty">
        <p class="ht-cart-empty__title"><?php esc_html_e('Coșul tău este gol', 'herbal-therapy'); ?></p>
        <p class="ht-cart-empty__text">
            <?php esc_html_e('Alege produsele care îți plac și revino aici ca să finalizezi comanda.', 'herbal-therapy'); ?>
        </p>
        <a class="ht-cart-empty__button" href="<?php echo esc_url(ht_minicart_shop_url()); ?>">
            <?php esc_html_e('Către catalog', 'herbal-therapy'); ?>
        </a>
    </div>
    <?php
}

/**
 * Tot ce se schimba dupa o actiune din cos - lista si sumarul. Bucata asta se
 * inlocuieste prin AJAX, la fel ca in cosul rapid.
 *
 * @return string
 */
function ht_cart_body()
{
    /*
     * Shortcode-ul WooCommerce facea pasul asta inainte de randare; fara el
     * pachetele de livrare raman necalculate si sumarul iese fara metode.
     */
    if (function_exists('WC') && WC()->cart) {
        WC()->cart->calculate_totals();
    }

    $items = ht_cart_items();

    ob_start();
    ?>
    <div class="ht-cart__body" data-ht-cart-body>
        <p class="ht-cart__alert" data-ht-cart-alert role="status"></p>

        <?php if (!$items) : ?>

            <?php ht_cart_empty(); ?>

        <?php else : ?>

            <div class="ht-cart__layout">
                <ul class="ht-cart__list">
                    <?php foreach ($items as $item) : ?>
                        <?php ht_cart_row($item); ?>
                    <?php endforeach; ?>
                </ul>

                <aside class="ht-cart__aside">
                    <?php ht_cart_summary(); ?>
                </aside>
            </div>

        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

/* ---------------------------------------------------------------------------
 * Sincronizarea prin AJAX
 * ------------------------------------------------------------------------ */

/**
 * Fragmentul paginii de cos.
 *
 * Se adauga doar cand cererea vine din pagina de cos (cart.js trimite
 * 'ht_cart_page'), ca adaugarile din cardurile de pe restul site-ului sa nu
 * randeze degeaba lista intreaga.
 *
 * @param array $fragments Fragmentele existente.
 *
 * @return array
 */
function ht_cart_fragments($fragments)
{
    if (empty($_POST['ht_cart_page'])) { // phpcs:ignore WordPress.Security.NonceVerification -- nonce-ul e verificat de actiunea care apeleaza filtrul.
        return $fragments;
    }

    $fragments['div[data-ht-cart-body]'] = ht_cart_body();

    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'ht_cart_fragments');

/**
 * Schimbarea metodei de livrare din sumar.
 *
 * Aceeasi logica pe care o face WooCommerce in WC_AJAX::update_shipping_method(),
 * dar cu raspunsul comun al temei (contor, fragmente, mesaj), ca restul
 * actiunilor din cos.
 */
function ht_cart_shipping_ajax()
{
    check_ajax_referer('ht-minicart', 'nonce');

    $chosen = WC()->session->get('chosen_shipping_methods');
    $chosen = is_array($chosen) ? $chosen : array();

    $posted = isset($_POST['shipping_method']) ? wc_clean(wp_unslash($_POST['shipping_method'])) : array();

    if (is_array($posted)) {
        foreach ($posted as $index => $value) {
            $chosen[(int)$index] = $value;
        }
    }

    WC()->session->set('chosen_shipping_methods', $chosen);

    ht_minicart_send();
}

add_action('wc_ajax_ht_cart_shipping', 'ht_cart_shipping_ajax');
