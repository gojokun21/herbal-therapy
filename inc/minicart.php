<?php
/**
 * Cosul rapid - panoul care aluneca din dreapta la apasarea iconitei de cos.
 *
 * Fisierul se incarca din inc/woocommerce.php, deci doar cand pluginul e activ.
 * Structura si dimensiunile sunt masurate pe referinta de design, la latimea
 * de proiectare de 1440px: panou de 650px, gutiera de 96px, text de 14px.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Date
 * ------------------------------------------------------------------------ */

/**
 * Randul unui produs din cos, normalizat pentru sablon.
 *
 * @param string $key  Cheia liniei din cos.
 * @param array  $item Linia din WC()->cart->get_cart().
 *
 * @return array|null Null daca produsul nu mai exista.
 */
function ht_minicart_item_data($key, $item)
{
    $product = isset($item['data']) ? $item['data'] : null;

    if (!$product || !$product->exists() || $item['quantity'] <= 0) {
        return null;
    }

    $qty = (int)$item['quantity'];
    $max = $product->get_max_purchase_quantity();
    $image_id = $product->get_image_id();
    $src = $image_id ? wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail') : false;

    /* pretul vechi apare doar cand linia chiar are reducere */
    $regular = (float)$product->get_regular_price();
    $active = (float)$product->get_price();
    $price_old = ($product->is_on_sale() && $regular > $active) ? wc_price($regular * $qty) : '';

    return array(
        'key'       => $key,
        'id'        => $product->get_id(),
        /* la variatii inima tine de produsul parinte */
        'parent_id' => $item['product_id'],
        'url'       => $product->is_visible() ? $product->get_permalink($item) : '',
        'title'     => $product->get_name(),
        'meta'      => wc_get_formatted_cart_item_data($item, true),
        'image'     => $src ? $src[0] : wc_placeholder_img_src('woocommerce_thumbnail'),
        'quantity'  => $qty,
        'max'       => ($max > 0) ? (int)$max : 0,
        'fixed'     => $product->is_sold_individually(),
        /* la pachete filtrul aduna si componentele (WooCommerce Product Bundles) */
        'price'     => apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($product, $qty), $item, $key),
        'price_old' => $price_old,
    );
}

/**
 * Liniile din cos, gata de randat.
 *
 * @return array
 */
function ht_minicart_items()
{
    $items = array();

    if (!function_exists('WC') || !WC()->cart) {
        return $items;
    }

    foreach (WC()->cart->get_cart() as $key => $item) {
        if (!apply_filters('woocommerce_widget_cart_item_visible', true, $item, $key)) {
            continue;
        }

        $data = ht_minicart_item_data($key, $item);

        if ($data) {
            $items[] = $data;
        }
    }

    return $items;
}

/**
 * Ascunde componentele pachetelor (WooCommerce Product Bundles) din cosul
 * rapid si din pagina de cos: ramane doar randul pachetului, cu pretul
 * intreg (agregat de plugin prin 'woocommerce_cart_item_subtotal').
 *
 * @param bool  $visible Vizibilitatea liniei.
 * @param array $item    Linia din WC()->cart->get_cart().
 *
 * @return bool
 */
function ht_hide_bundled_cart_items($visible, $item)
{
    if (!$visible || !function_exists('wc_pb_is_bundled_cart_item') || !wc_pb_is_bundled_cart_item($item)) {
        return $visible;
    }

    /* daca pachetul nu are rand propriu in cos, componentele raman vizibile */
    $container = wc_pb_get_bundled_cart_item_container($item);
    $bundle = ($container && isset($container['data'])) ? $container['data'] : null;

    if ($bundle instanceof WC_Product_Bundle
        && !WC_Product_Bundle::group_mode_has($bundle->get_group_mode(), 'parent_item')
    ) {
        return $visible;
    }

    return false;
}

add_filter('woocommerce_cart_item_visible', 'ht_hide_bundled_cart_items', 10, 2);
add_filter('woocommerce_widget_cart_item_visible', 'ht_hide_bundled_cart_items', 10, 2);

/**
 * Reducerile active, pe cupon.
 *
 * @return array Lista de array-uri cu cheile 'label' si 'amount'.
 */
function ht_minicart_discounts()
{
    $rows = array();

    foreach (WC()->cart->get_coupons() as $code => $coupon) {
        $rows[] = array(
            'label'  => wc_cart_totals_coupon_label($coupon, false),
            'amount' => wc_price(WC()->cart->get_coupon_discount_amount($code, WC()->cart->display_cart_ex_tax)),
        );
    }

    return $rows;
}

/**
 * Livrarea, ca sa se inteleaga diferenta dintre valoarea produselor si suma
 * de plata. Eticheta e numele metodei alese (Livrare curier, Ridicare locala),
 * suma e costul ei sau "Gratuit".
 *
 * @return array|null Null cand cosul nu are nevoie de livrare.
 */
function ht_minicart_shipping()
{
    $cart = WC()->cart;

    if (!$cart->needs_shipping() || !$cart->show_shipping()) {
        return null;
    }

    /*
     * Pe paginile obisnuite totalurile vin din sesiune si pachetele de livrare
     * nu sunt calculate; fara ele nu stim ce metoda e aleasa.
     */
    if (!WC()->shipping()->get_packages()) {
        $cart->calculate_shipping();
    }

    $label = __('Livrare', 'herbal-therapy');
    $chosen = (array)WC()->session->get('chosen_shipping_methods');

    foreach (WC()->shipping()->get_packages() as $i => $package) {
        $id = isset($chosen[$i]) ? $chosen[$i] : '';

        if ($id && isset($package['rates'][$id])) {
            /* "Livrare gratuita - Gratuit" ar fi redundant: metoda gratuita ramane "Livrare" */
            $label = 'free_shipping' === $package['rates'][$id]->get_method_id()
                ? __('Livrare', 'herbal-therapy')
                : $package['rates'][$id]->get_label();
            break;
        }
    }

    $total = (float)$cart->get_shipping_total();

    if ($cart->display_prices_including_tax()) {
        $total += (float)$cart->get_shipping_tax();
    }

    return array(
        'label'  => $label,
        'free'   => $total <= 0,
        'amount' => $total > 0 ? wc_price($total) : __('Gratuit', 'herbal-therapy'),
    );
}

/**
 * Link catre catalog, pentru cosul gol.
 *
 * @return string
 */
function ht_minicart_shop_url()
{
    $url = wc_get_page_permalink('shop');

    return $url ? $url : home_url('/');
}

/* ---------------------------------------------------------------------------
 * Randare
 * ------------------------------------------------------------------------ */

/**
 * Blocul cu produsele, cuponul si totalurile - partea care se reincarca prin AJAX.
 *
 * @return string
 */
function ht_minicart_body()
{
    $items = ht_minicart_items();

    ob_start();
    ?>
    <div class="ht-minicart__body" data-ht-minicart-body>

        <?php if (!$items) : ?>

            <div class="ht-minicart-empty">
                <div class="ht-minicart-empty__content">
                    <p class="ht-minicart-empty__title"><?php esc_html_e('Coșul tău este gol', 'herbal-therapy'); ?></p>
                    <p class="ht-minicart-empty__text"><?php esc_html_e('Alege produsele care îți plac și revino aici ca să finalizezi comanda.', 'herbal-therapy'); ?></p>
                    <a class="ht-minicart-empty__button" href="<?php echo esc_url(ht_minicart_shop_url()); ?>">
                        <?php esc_html_e('Către catalog', 'herbal-therapy'); ?>
                    </a>
                </div>
            </div>

        <?php else : ?>

            <?php ht_shipping_progress(); ?>

            <ul class="ht-minicart__list">
                <?php foreach ($items as $item) : ?>
                    <li class="ht-minicart__item"><?php ht_minicart_product($item); ?></li>
                <?php endforeach; ?>
            </ul>

            <?php
            ht_minicart_promo();
            ht_minicart_totals();
            ?>

        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Un produs din lista cosului.
 *
 * @param array $item Datele din ht_minicart_item_data().
 */
function ht_minicart_product($item)
{
    $minus_off = ($item['quantity'] <= 1);
    $plus_off = ($item['max'] > 0 && $item['quantity'] >= $item['max']);
    ?>
    <div class="ht-minicart-product" data-ht-cart-item="<?php echo esc_attr($item['key']); ?>">

        <span class="ht-minicart-product__img"
              style="background-image:url('<?php echo esc_url($item['image']); ?>')"></span>

        <div class="ht-minicart-product__info">

            <div class="ht-minicart-product__top">
                <div class="ht-minicart-product__heading">
                    <?php if ($item['url']) : ?>
                        <a class="ht-minicart-product__title" href="<?php echo esc_url($item['url']); ?>">
                            <?php echo esc_html($item['title']); ?>
                        </a>
                    <?php else : ?>
                        <span class="ht-minicart-product__title"><?php echo esc_html($item['title']); ?></span>
                    <?php endif; ?>

                    <?php if ($item['meta']) : ?>
                        <div class="ht-minicart-product__meta"><?php echo wp_kses_post($item['meta']); ?></div>
                    <?php endif; ?>
                </div>

                <button class="ht-minicart-product__delete" type="button" data-ht-cart-remove>
                    <?php ht_icon('close', 'ht-minicart-product__delete-icon'); ?>
                    <span class="ht-visually-hidden"><?php esc_html_e('Șterge din coș', 'herbal-therapy'); ?></span>
                </button>
            </div>

            <div class="ht-minicart-product__bottom">

                <?php if ($item['fixed']) : ?>
                    <span class="ht-minicart-product__single"><?php esc_html_e('1 buc.', 'herbal-therapy'); ?></span>
                <?php else : ?>
                    <div class="ht-minicart-product__qty">
                        <button class="ht-minicart-product__qty-btn" type="button" data-ht-cart-step="-1"
                            <?php disabled($minus_off); ?>>
                            <span aria-hidden="true">&minus;</span>
                            <span class="ht-visually-hidden"><?php esc_html_e('Scade cantitatea', 'herbal-therapy'); ?></span>
                        </button>
                        <input class="ht-minicart-product__qty-input" type="text" inputmode="numeric" size="3"
                               value="<?php echo esc_attr($item['quantity']); ?>"
                               data-ht-cart-qty
                               data-max="<?php echo esc_attr($item['max']); ?>"
                               aria-label="<?php esc_attr_e('Cantitate', 'herbal-therapy'); ?>">
                        <button class="ht-minicart-product__qty-btn" type="button" data-ht-cart-step="1"
                            <?php disabled($plus_off); ?>>
                            <span aria-hidden="true">+</span>
                            <span class="ht-visually-hidden"><?php esc_html_e('Crește cantitatea', 'herbal-therapy'); ?></span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php ht_favorite_button($item['parent_id'], 'ht-minicart-product__fav'); ?>

                <div class="ht-minicart-product__price">
                    <?php if ($item['price_old']) : ?>
                        <span class="ht-minicart-product__price-old"><?php echo wp_kses_post($item['price_old']); ?></span>
                    <?php endif; ?>
                    <span class="ht-minicart-product__price-value"><?php echo wp_kses_post($item['price']); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Campul de cod promotional, cu lista cupoanelor deja aplicate.
 */
function ht_minicart_promo()
{
    if (!wc_coupons_enabled()) {
        return;
    }

    $applied = WC()->cart->get_applied_coupons();
    ?>
    <div class="ht-minicart-promo">
        <div class="ht-minicart-promo__label">
            <input class="ht-minicart-promo__input" type="text" name="ht_coupon" autocomplete="off"
                   placeholder="<?php esc_attr_e('Ai un cod promoțional?', 'herbal-therapy'); ?>"
                   data-ht-coupon-input>
            <div class="ht-minicart-promo__buttons">
                <button class="ht-minicart-promo__send" type="button" data-ht-coupon-apply disabled>
                    <span class="ht-visually-hidden"><?php esc_html_e('Activează', 'herbal-therapy'); ?></span>
                </button>
            </div>
        </div>

        <?php if ($applied) : ?>
            <ul class="ht-minicart-promo__applied">
                <?php foreach ($applied as $code) : ?>
                    <li class="ht-minicart-promo__applied-item">
                        <span><?php echo esc_html(wc_format_coupon_code($code)); ?></span>
                        <button type="button" data-ht-coupon-remove="<?php echo esc_attr($code); ?>">
                            <?php esc_html_e('Anulează', 'herbal-therapy'); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Subtotalul, reducerile, livrarea si totalul de plata.
 */
function ht_minicart_totals()
{
    $discounts = ht_minicart_discounts();
    $shipping = ht_minicart_shipping();
    ?>
    <div class="ht-minicart-total">

        <div class="ht-minicart-total__row">
            <span class="ht-minicart-total__text"><?php esc_html_e('Valoarea produselor', 'herbal-therapy'); ?></span>
            <span class="ht-minicart-total__value"><?php echo wp_kses_post(WC()->cart->get_cart_subtotal()); ?></span>
        </div>

        <?php foreach ($discounts as $discount) : ?>
            <div class="ht-minicart-total__row ht-minicart-total__row--discount">
                <span class="ht-minicart-total__text"><?php echo esc_html($discount['label']); ?></span>
                <span class="ht-minicart-total__value">&minus;&nbsp;<?php echo wp_kses_post($discount['amount']); ?></span>
            </div>
        <?php endforeach; ?>

        <?php if ($shipping) : ?>
            <div class="ht-minicart-total__row ht-minicart-total__row--shipping<?php echo $shipping['free'] ? ' is-free' : ''; ?>">
                <span class="ht-minicart-total__text"><?php echo esc_html($shipping['label']); ?></span>
                <span class="ht-minicart-total__value"><?php echo $shipping['free'] ? esc_html($shipping['amount']) : wp_kses_post($shipping['amount']); ?></span>
            </div>
        <?php endif; ?>

        <div class="ht-minicart-total__row ht-minicart-total__row--sum">
            <span class="ht-minicart-total__text"><?php esc_html_e('De plată', 'herbal-therapy'); ?></span>
            <span class="ht-minicart-total__value"><?php echo wp_kses_post(WC()->cart->get_total()); ?></span>
        </div>
    </div>
    <?php
}

/**
 * Bara de jos, cu butonul de finalizare. Goala cand cosul e gol.
 *
 * @return string
 */
function ht_minicart_foot()
{
    ob_start();
    ?>
    <div class="ht-minicart__foot" data-ht-minicart-foot>
        <?php if (ht_cart_count()) : ?>
            <?php
            /*
             * Eticheta se scrie lipita de tag-uri: butonul e pe 'white-space: pre-wrap',
             * ca in referinta, deci si indentarea din sablon ar ajunge text vizibil.
             */
            $ht_label = sprintf(
                /* translators: %s: totalul de plata. */
                esc_html__('Finalizează comanda - %s', 'herbal-therapy'),
                esc_html(wp_strip_all_tags(WC()->cart->get_total()))
            );
            ?>
            <a class="ht-minicart__cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php esc_html_e('Mergi la coș', 'herbal-therapy'); ?></a>
            <a class="ht-minicart__submit" href="<?php echo esc_url(wc_get_checkout_url()); ?>"><?php echo $ht_label; // phpcs:ignore WordPress.Security.EscapeOutput -- componentele sunt escapate mai sus. ?></a>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Caruselul de recomandari din josul panoului.
 *
 * Se randeaza o singura data, la incarcarea paginii: nu depinde de continutul
 * cosului, deci nu are rost sa intre in fragmentele AJAX.
 */
function ht_minicart_recommendations()
{
    /*
     * Interogarea ruleaza pe fiecare pagina, odata cu panoul. Se opreste cu:
     *   add_filter('ht_minicart_show_products', '__return_false');
     */
    if (!apply_filters('ht_minicart_show_products', true)) {
        return;
    }

    /*
     * 'popularity' e o sortare de catalog, nu una pe care o intelege
     * wc_get_products; bestsellerii se cer direct pe contorul de vanzari.
     */
    $cards = ht_products_data(apply_filters('ht_minicart_products_args', array(
        'limit'    => 10,
        'orderby'  => 'meta_value_num',
        'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        'order'    => 'DESC',
    )));

    if (!$cards) {
        return;
    }
    ?>
    <section class="ht-minicart__products ht-products" data-ht-minicart-products>
        <h3 class="ht-minicart__products-title"><?php esc_html_e('Cele mai vândute', 'herbal-therapy'); ?></h3>

        <?php /* pista si sagetile stau impreuna: sagetile se centreaza pe imagine, nu pe sectiune */ ?>
        <div class="ht-minicart__products-track">

            <div class="swiper ht-products__swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($cards as $index => $card) : ?>
                        <div class="swiper-slide ht-products__slide">
                            <?php ht_product_card($card, $index + 1); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="ht-products__arrow ht-products__arrow--prev ht-minicart__arrow ht-minicart__arrow--prev" type="button">
                <?php ht_icon('chevron'); ?>
                <span class="ht-visually-hidden"><?php esc_html_e('Înapoi', 'herbal-therapy'); ?></span>
            </button>
            <button class="ht-products__arrow ht-products__arrow--next ht-minicart__arrow ht-minicart__arrow--next" type="button">
                <?php ht_icon('chevron'); ?>
                <span class="ht-visually-hidden"><?php esc_html_e('Înainte', 'herbal-therapy'); ?></span>
            </button>
        </div>
    </section>
    <?php
}

/**
 * Panoul complet. Se scoate in subsol, o singura data pe pagina.
 */
function ht_minicart()
{
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $count = ht_cart_count();
    ?>
    <div class="ht-minicart" id="htMinicart" aria-hidden="true">

        <div class="ht-minicart__overlay" data-ht-minicart-close></div>

        <div class="ht-minicart__panel" role="dialog" aria-modal="true" aria-labelledby="htMinicartTitle">
            <div class="ht-minicart__inner">

                <div class="ht-minicart__top">
                    <div class="ht-minicart__head">
                        <h2 class="ht-minicart__title" id="htMinicartTitle">
                            <?php esc_html_e('Coșul tău', 'herbal-therapy'); ?>
                            <span data-ht-minicart-count><?php echo $count ? esc_html($count) : ''; ?></span>
                        </h2>
                        <button class="ht-minicart__close" type="button" data-ht-minicart-close>
                            <?php ht_icon('close', 'ht-minicart__close-icon'); ?>
                            <span class="ht-visually-hidden"><?php esc_html_e('Închide', 'herbal-therapy'); ?></span>
                        </button>
                    </div>
                    <p class="ht-minicart__alert" data-ht-minicart-alert></p>
                </div>

                <div class="ht-minicart__scroll" data-ht-minicart-scroll>
                    <?php
                    echo ht_minicart_body(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern.
                    ht_minicart_recommendations();
                    ?>
                </div>

                <?php echo ht_minicart_foot(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern. ?>
            </div>
        </div>
    </div>
    <?php
}

/* inainte de prioritatea 20, unde WordPress tipareste scripturile din subsol */
add_action('wp_footer', 'ht_minicart', 5);

/* ---------------------------------------------------------------------------
 * Sincronizarea prin AJAX
 * ------------------------------------------------------------------------ */

/**
 * Fragmentele pe care le inlocuieste scriptul dupa orice modificare a cosului.
 *
 * Cheile sunt selectori CSS - acelasi format pe care il foloseste WooCommerce
 * pentru 'woocommerce_add_to_cart_fragments', deci panoul se actualizeaza si la
 * adaugarile facute din cardurile de produs.
 *
 * @param array $fragments Fragmentele existente.
 *
 * @return array
 */
function ht_minicart_fragments($fragments)
{
    $count = ht_cart_count();

    $fragments['span[data-ht-minicart-count]'] = sprintf(
        '<span data-ht-minicart-count>%s</span>',
        $count ? esc_html($count) : ''
    );

    $fragments['div[data-ht-minicart-body]'] = ht_minicart_body();
    $fragments['div[data-ht-minicart-foot]'] = ht_minicart_foot();

    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'ht_minicart_fragments');

/**
 * Primul mesaj de eroare din coada WooCommerce, ca text simplu.
 *
 * @return string
 */
function ht_minicart_first_error()
{
    $notices = wc_get_notices('error');
    wc_clear_notices();

    if (!$notices) {
        return '';
    }

    $first = reset($notices);
    $text = wp_strip_all_tags(is_array($first) ? $first['notice'] : $first);

    /* mesajul ajunge in textContent, deci entitatile trebuie intoarse la caractere */
    return wp_specialchars_decode($text, ENT_QUOTES);
}

/**
 * Raspunsul comun al tuturor actiunilor: contorul, fragmentele si mesajul.
 *
 * @param string $notice Mesajul afisat in panou.
 * @param bool   $error  Mesajul e de eroare?
 */
function ht_minicart_send($notice = '', $error = false)
{
    WC()->cart->calculate_totals();

    wp_send_json(array(
        'count'     => ht_cart_count(),
        'fragments' => apply_filters('woocommerce_add_to_cart_fragments', array()),
        'notice'    => $notice,
        'error'     => (bool)$error,
    ));
}

/**
 * Punctul de intrare pentru modificarile facute din panou.
 *
 * Merge pe endpoint-ul WooCommerce (/?wc-ajax=ht_minicart), care nu incarca
 * zona de administrare - de cateva ori mai rapid decat admin-ajax.php.
 */
function ht_minicart_ajax()
{
    check_ajax_referer('ht-minicart', 'nonce');

    $action = isset($_POST['ht_action']) ? sanitize_key(wp_unslash($_POST['ht_action'])) : '';
    $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
    $code = isset($_POST['code']) ? wc_format_coupon_code(sanitize_text_field(wp_unslash($_POST['code']))) : '';

    switch ($action) {

        case 'qty':
            $qty = isset($_POST['quantity']) ? absint(wp_unslash($_POST['quantity'])) : 0;

            if (!$key || !WC()->cart->get_cart_item($key)) {
                ht_minicart_send(__('Produsul nu mai este în coș.', 'herbal-therapy'), true);
            }

            /* zero inseamna stergere, la fel ca in pagina de cos */
            if ($qty < 1) {
                WC()->cart->remove_cart_item($key);
                ht_minicart_send(__('Produsul a fost șters din coș.', 'herbal-therapy'));
            }

            WC()->cart->set_quantity($key, $qty, true);
            $error = ht_minicart_first_error();

            ht_minicart_send($error, (bool)$error);
            break;

        case 'remove':
            if ($key) {
                WC()->cart->remove_cart_item($key);
            }

            ht_minicart_send(__('Produsul a fost șters din coș.', 'herbal-therapy'));
            break;

        case 'coupon':
            if ('' === $code) {
                ht_minicart_send(__('Introdu un cod promoțional.', 'herbal-therapy'), true);
            }

            WC()->cart->apply_coupon($code);
            $error = ht_minicart_first_error();
            wc_clear_notices();

            ht_minicart_send(
                $error ? $error : __('Codul promoțional a fost activat.', 'herbal-therapy'),
                (bool)$error
            );
            break;

        case 'remove_coupon':
            if ($code) {
                WC()->cart->remove_coupon($code);
            }

            wc_clear_notices();
            ht_minicart_send(__('Codul promoțional a fost anulat.', 'herbal-therapy'));
            break;

        default:
            ht_minicart_send();
    }
}

add_action('wc_ajax_ht_minicart', 'ht_minicart_ajax');

/* ---------------------------------------------------------------------------
 * Fisierele panoului
 * ------------------------------------------------------------------------ */

/**
 * CSS si JS. Panoul e in subsolul fiecarei pagini, deci se incarca peste tot.
 */
function ht_minicart_assets()
{
    wp_enqueue_style(
        'ht-minicart',
        ht_asset_uri('/assets/css/minicart.css'),
        array('ht-style', 'ht-products'),
        ht_asset_version('/assets/css/minicart.css')
    );

    /* caruselul de recomandari refoloseste galeria din cardul de produs */
    wp_enqueue_script('ht-products');

    wp_enqueue_script(
        'ht-minicart',
        ht_asset_uri('/assets/js/minicart.js'),
        array('swiper', 'ht-products'),
        ht_asset_version('/assets/js/minicart.js'),
        true
    );

    /* butonul rapid din carduri trimite prin scriptul WooCommerce, care ne da fragmentele */
    wp_enqueue_script('wc-add-to-cart');

    wp_localize_script('ht-minicart', 'htMinicartData', array(
        'url'   => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('ht_minicart') : admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ht-minicart'),
        'i18n'  => array(
            'error' => __('Nu am putut actualiza coșul. Încearcă din nou.', 'herbal-therapy'),
        ),
    ));
}

add_action('wp_enqueue_scripts', 'ht_minicart_assets', 20);
