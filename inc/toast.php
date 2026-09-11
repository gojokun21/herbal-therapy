<?php
/**
 * Notificarile de cos.
 *
 * WooCommerce anunta adaugarea in cos printr-un mesaj de text asezat in capul
 * paginii ("... a fost adaugat in cos"). Fisierul inlocuieste mesajul cu un
 * cartonas care arata chiar produsul adaugat - imagine, nume, cantitate, pret -
 * si care se stinge singur.
 *
 * Drumurile prin care ajunge cartonasul in pagina sunt doua:
 *
 *   - adaugare prin AJAX (butonul din card sau din pagina produsului): markup-ul
 *     pleaca odata cu fragmentele WooCommerce, in cheia 'div[data-ht-toast-slot]';
 *   - adaugare clasica, cu reincarcare (JS oprit sau ?add-to-cart= in URL):
 *     markup-ul e tinut in sesiune si iese la urmatoarea randare, in <template>.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Produsele adaugate in cererea curenta
 * ------------------------------------------------------------------------ */

/**
 * Coada produselor adaugate, tinuta pe durata unei singure cereri.
 *
 * @param array|null $add Linia de adaugat: 'id' si 'qty'.
 *
 * @return array Coada completa.
 */
function ht_toast_items($add = null)
{
    static $items = array();

    if (is_array($add)) {
        $items[] = $add;
    }

    return $items;
}

/**
 * Retine ce s-a adaugat. Se declanseaza si la AJAX, si la trimiterea clasica.
 *
 * @param string $cart_item_key Cheia liniei din cos.
 * @param int    $product_id    Produsul.
 * @param int    $quantity      Cantitatea adaugata acum.
 * @param int    $variation_id  Variatia, cand exista.
 */
function ht_toast_collect($cart_item_key, $product_id, $quantity, $variation_id = 0)
{
    ht_toast_items(array(
        /* la produsele variabile, cartonasul arata variatia aleasa */
        'id'  => $variation_id ? (int)$variation_id : (int)$product_id,
        'qty' => max(1, (int)$quantity),
    ));
}

add_action('woocommerce_add_to_cart', 'ht_toast_collect', 10, 4);

/**
 * Scoate mesajul implicit al pluginului.
 *
 * wc_add_notice() ignora mesajele goale, deci sirul vid opreste notificarea
 * inainte sa ajunga in coada - nu ramane nici cutia goala in pagina.
 *
 * @return string
 */
function ht_toast_mute_message()
{
    return '';
}

add_filter('wc_add_to_cart_message_html', 'ht_toast_mute_message', 99);

/* ---------------------------------------------------------------------------
 * Markup
 * ------------------------------------------------------------------------ */

/**
 * Cartonasul unui produs.
 *
 * @param WC_Product $product Produsul adaugat.
 * @param int        $qty     Cantitatea adaugata acum.
 *
 * @return string
 */
function ht_toast_card($product, $qty)
{
    $image_id = (int)$product->get_image_id();
    $src = $image_id ? wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail') : false;
    $image = $src ? $src[0] : '';

    if (!$image && function_exists('wc_placeholder_img_src')) {
        $image = wc_placeholder_img_src('woocommerce_thumbnail');
    }

    /* pretul unei bucati, cu TVA-ul afisat dupa setarea din Woo */
    $unit = function_exists('wc_get_price_to_display')
        ? (float)wc_get_price_to_display($product)
        : (float)$product->get_price();

    $line = wc_price($unit * $qty);

    /* la o singura bucata ajunge pretul; de la doua incolo se arata si calculul */
    $meta = ($qty > 1)
        ? sprintf('%s &times; %s = %s', number_format_i18n($qty), wc_price($unit), $line)
        : $line;

    ob_start();
    ?>
    <article class="ht-toast__item" data-ht-toast-item data-ht-toast-key="<?php echo esc_attr($product->get_id()); ?>">

        <?php if ($image) : ?>
            <div class="ht-toast__media">
                <img src="<?php echo esc_url($image); ?>" alt="" width="80" height="80" loading="lazy" decoding="async">
            </div>
        <?php endif; ?>

        <div class="ht-toast__main">
            <p class="ht-toast__label">
                <?php ht_icon('check', 'ht-toast__check'); ?>
                <span><?php esc_html_e('Adăugat în coș', 'herbal-therapy'); ?></span>
            </p>

            <p class="ht-toast__title">
                <?php if ($product->is_visible()) : ?>
                    <a href="<?php echo esc_url($product->get_permalink()); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                <?php else : ?>
                    <?php echo esc_html($product->get_name()); ?>
                <?php endif; ?>
            </p>

            <p class="ht-toast__meta"><?php echo wp_kses_post($meta); ?></p>

            <div class="ht-toast__actions">
                <button class="ht-toast__btn ht-toast__btn--ghost" type="button" data-ht-minicart-open>
                    <?php esc_html_e('Vezi coșul', 'herbal-therapy'); ?>
                </button>
                <a class="ht-toast__btn ht-toast__btn--solid" href="<?php echo esc_url(wc_get_checkout_url()); ?>">
                    <?php esc_html_e('Finalizează', 'herbal-therapy'); ?>
                </a>
            </div>
        </div>

        <button class="ht-toast__close" type="button" data-ht-toast-close>
            <?php ht_icon('close', 'ht-toast__close-icon'); ?>
            <span class="ht-visually-hidden"><?php esc_html_e('Închide notificarea', 'herbal-therapy'); ?></span>
        </button>

        <span class="ht-toast__bar" aria-hidden="true"></span>
    </article>
    <?php

    return trim((string)ob_get_clean());
}

/**
 * Cartonasele pentru toata coada cererii.
 *
 * @return string Sir gol cand nu s-a adaugat nimic.
 */
function ht_toast_markup()
{
    $items = ht_toast_items();

    if (!$items || !function_exists('wc_get_product')) {
        return '';
    }

    $html = '';

    foreach ($items as $item) {
        $product = wc_get_product($item['id']);

        if ($product instanceof WC_Product) {
            $html .= ht_toast_card($product, $item['qty']);
        }
    }

    if ($html) {
        ht_toast_delivered(true);
    }

    return $html;
}

/**
 * A plecat deja cartonasul catre browser in cererea asta?
 *
 * Fara steag, o adaugare prin AJAX ar fi anuntata de doua ori: o data prin
 * fragmente si inca o data din sesiune, la urmatoarea pagina deschisa.
 *
 * @param bool $set Marcheaza livrarea.
 *
 * @return bool
 */
function ht_toast_delivered($set = false)
{
    static $done = false;

    if ($set) {
        $done = true;
    }

    return $done;
}

/* ---------------------------------------------------------------------------
 * Livrarea prin AJAX
 * ------------------------------------------------------------------------ */

/**
 * Cartonasul calatoreste cu fragmentele WooCommerce.
 *
 * Cheia e un selector CSS, ca la restul fragmentelor, dar scriptul nu asteapta
 * inlocuirea din DOM: citeste valoarea direct din evenimentul 'added_to_cart'.
 *
 * Cartonasele stau in <template> pentru ca fragmentul ajunge oricum si in pagina,
 * unde ar mai descarca o data imaginea produsului.
 *
 * @param array $fragments Fragmentele existente.
 *
 * @return array
 */
function ht_toast_fragments($fragments)
{
    $fragments['div[data-ht-toast-slot]'] = sprintf(
        '<div class="ht-toast__slot" data-ht-toast-slot hidden><template>%s</template></div>',
        ht_toast_markup()
    );

    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'ht_toast_fragments');

/* ---------------------------------------------------------------------------
 * Livrarea dupa reincarcarea paginii
 * ------------------------------------------------------------------------ */

/**
 * Pastreaza cartonasul in sesiune cand cererea nu a apucat sa deseneze subsolul.
 *
 * Asa arata notificarea si dupa adaugarea clasica, unde WooCommerce raspunde cu
 * o redirectionare inainte de orice HTML.
 */
function ht_toast_stash()
{
    if (ht_toast_delivered() || wp_doing_ajax() || !function_exists('WC')) {
        return;
    }

    $session = WC()->session;

    if (!$session) {
        return;
    }

    $markup = ht_toast_markup();

    if ($markup) {
        $session->set('ht_toast', $markup);
    }
}

add_action('shutdown', 'ht_toast_stash', 0);

/**
 * Cartonasul lasat in sesiune de cererea anterioara. Se citeste o singura data.
 *
 * @return string
 */
function ht_toast_pending()
{
    if (!function_exists('WC') || !WC()->session) {
        return '';
    }

    $markup = (string)WC()->session->get('ht_toast', '');

    if ($markup) {
        WC()->session->set('ht_toast', '');
        ht_toast_delivered(true);
    }

    return $markup;
}

/* ---------------------------------------------------------------------------
 * Sablonul din subsol
 * ------------------------------------------------------------------------ */

/**
 * Stiva notificarilor plus cartonasele in asteptare.
 *
 * Cartonasele vin din doua locuri: sesiunea lasata de cererea anterioara (cand
 * WooCommerce a raspuns cu o redirectionare) si coada cererii curente (cand
 * adaugarea s-a facut cu ?add-to-cart= si pagina s-a desenat pe loc).
 *
 * <template> tine markup-ul in afara randarii: imaginea nu se descarca decat
 * daca scriptul chiar arata notificarea.
 */
function ht_toast_stack()
{
    $pending = ht_toast_pending() . ht_toast_markup();
    ?>
    <div class="ht-toast" data-ht-toast role="region" aria-live="polite"
         aria-label="<?php esc_attr_e('Notificări coș', 'herbal-therapy'); ?>"></div>

    <div class="ht-toast__slot" data-ht-toast-slot hidden></div>

    <?php if ($pending) : ?>
        <template data-ht-toast-pending><?php
            echo $pending; // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern.
        ?></template>
    <?php endif; ?>
    <?php
}

add_action('wp_footer', 'ht_toast_stack', 6);

/* ---------------------------------------------------------------------------
 * Fisierele componentei
 * ------------------------------------------------------------------------ */

/**
 * CSS si JS. Notificarea poate aparea pe orice pagina cu carduri de produs.
 */
function ht_toast_assets()
{
    wp_enqueue_style(
        'ht-toast',
        ht_asset_uri('/assets/css/toast.css'),
        array('ht-style'),
        ht_asset_version('/assets/css/toast.css')
    );

    /*
     * 'added_to_cart' e un eveniment jQuery al pluginului, deci nu se aude prin
     * addEventListener; scriptul are nevoie de jQuery ca sa il asculte.
     */
    wp_enqueue_script(
        'ht-toast',
        ht_asset_uri('/assets/js/toast.js'),
        array('jquery'),
        ht_asset_version('/assets/js/toast.js'),
        true
    );

    wp_localize_script('ht-toast', 'htToastData', array(
        /* cat sta pe ecran, in milisecunde */
        'life' => (int)apply_filters('ht_toast_life', 6000),
        /* cate cartonase stau deodata, ca sa nu acopere pagina */
        'max'  => (int)apply_filters('ht_toast_max', 3),
    ));
}

add_action('wp_enqueue_scripts', 'ht_toast_assets', 20);
