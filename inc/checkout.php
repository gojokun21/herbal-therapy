<?php
/**
 * Pagina de finalizare a comenzii (WooCommerce > Finalizare comanda).
 *
 * Ca si pagina de cont, finalizarea e o pagina obisnuita cu shortcode, deci
 * WooCommerce nu o trece prin propriul incarcator de sabloane - fara filtrul de
 * mai jos ar iesi prin page.php, in coloana ingusta a articolelor. Aici o mutam
 * pe templates/checkout.php, iar bucatile din interior sunt suprascrise in
 * woocommerce/checkout/.
 *
 * Layout-ul e pe doua coloane: formularul in stanga (facturare, livrare, plata),
 * sumarul comenzii lipit la scroll in dreapta. Panoul din dreapta reia limbajul
 * cosului rapid (minicart.css): fundal deschis, buton negru, cod promotional cu
 * chenar punctat.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Sablonul si stilurile
 * ------------------------------------------------------------------------ */

/**
 * Scoate finalizarea comenzii din page.php si o duce pe sablonul temei.
 *
 * Acopera si endpoint-urile 'order-received' (multumim) si 'order-pay', pentru
 * ca amandoua traiesc pe aceeasi pagina.
 *
 * @param string $template Calea aleasa de WordPress.
 *
 * @return string
 */
function ht_checkout_template($template)
{
    if (!is_checkout()) {
        return $template;
    }

    $custom = HT_DIR . '/templates/checkout.php';

    return file_exists($custom) ? $custom : $template;
}

add_filter('template_include', 'ht_checkout_template', 99);

/**
 * Stilurile si scriptul paginii.
 *
 * Foaia depinde de 'ht-shop': de acolo vin notificarile WooCommerce.
 */
function ht_enqueue_checkout_assets()
{
    if (!is_checkout()) {
        return;
    }

    $deps = array('ht-shop');

    /*
     * Dupa plasarea comenzii, WooCommerce randeaza aceleasi tabele si adrese ca
     * in contul clientului. Regulile lor sunt scrise o singura data, in
     * account.css (blocul de tabele prinde si '.ht-checkout'), deci pe ecranele
     * astea aducem foaia acolo unde e nevoie de ea, in loc sa o copiem.
     */
    if (is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay')) {
        wp_enqueue_style(
            'ht-account',
            ht_asset_uri('/assets/css/account.css'),
            array('ht-shop'),
            ht_asset_version('/assets/css/account.css')
        );

        $deps[] = 'ht-account';
    }

    wp_enqueue_style(
        'ht-checkout',
        ht_asset_uri('/assets/css/checkout.css'),
        $deps,
        ht_asset_version('/assets/css/checkout.css')
    );

    wp_enqueue_script(
        'ht-checkout',
        ht_asset_uri('/assets/js/checkout.js'),
        array('jquery', 'woocommerce'),
        ht_asset_version('/assets/js/checkout.js'),
        true
    );

    wp_localize_script('ht-checkout', 'htCheckoutData', array(
        'i18n' => array(
            'empty' => __('Introdu un cod promoțional.', 'herbal-therapy'),
            'error' => __('Nu am putut aplica codul. Încearcă din nou.', 'herbal-therapy'),
        ),
    ));
}

add_action('wp_enqueue_scripts', 'ht_enqueue_checkout_assets', 21);

/* ---------------------------------------------------------------------------
 * Ce scoatem din randarea implicita
 * ------------------------------------------------------------------------ */

/*
 * Cuponul: WooCommerce il pune deasupra formularului, ca un mesaj cu link care
 * desface un al doilea <form>. La noi campul sta in sumar, in interiorul
 * formularului de comanda, deci nu poate fi un formular separat - se trimite
 * prin AJAX, pe acelasi endpoint ca in cosul rapid (vezi assets/js/checkout.js).
 */
remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);

/*
 * Blocul de plata si butonul de comanda se muta din sumar in coloana din stanga,
 * ca pas separat. Fragmentele de AJAX nu au de suferit: WC_AJAX::update_order_review()
 * apeleaza direct woocommerce_checkout_payment(), nu prin hook-ul de aici.
 */
remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);

/**
 * Cosul gol: ramanem pe finalizare, cu un mesaj propriu.
 *
 * Implicit WooCommerce redirectioneaza spre pagina de cos, care in tema asta nu
 * are sablon - cosul e panoul lateral. Mesajul e in templates/checkout.php.
 *
 * @return bool
 */
function ht_checkout_keep_empty_cart()
{
    return false;
}

add_filter('woocommerce_checkout_redirect_empty_cart', 'ht_checkout_keep_empty_cart');

/* ---------------------------------------------------------------------------
 * Campurile formularului
 * ------------------------------------------------------------------------ */

/**
 * Latimea fiecarui camp pe grila de doua coloane.
 *
 * Cheile sunt numele campurilor fara prefixul de grup ('billing_' / 'shipping_'),
 * valorile - clasa de latime. Ce nu e in lista ramane pe toata latimea. Perechile
 * ies din prioritatile implicite ale pluginului: oras (70) + judet (80).
 *
 * @return array
 */
function ht_checkout_field_widths()
{
    return apply_filters('ht_checkout_field_widths', array(
        'first_name' => 'form-row-first',
        'last_name'  => 'form-row-last',
        'city'       => 'form-row-first',
        'state'      => 'form-row-last',
    ));
}

/**
 * Inlocuieste clasa de latime dintr-o lista de clase.
 *
 * Restul claselor raman neatinse - de ele depinde scriptul pluginului cand arata
 * sau ascunde campuri in functie de tara ('address-field', 'validate-required'
 * si celelalte).
 *
 * @param array  $classes Clasele campului.
 * @param string $name    Numele campului, fara prefixul de grup.
 *
 * @return array
 */
function ht_checkout_field_class($classes, $name)
{
    $widths = ht_checkout_field_widths();
    $sizes = array('form-row-wide', 'form-row-first', 'form-row-last');

    $classes = array_values(array_diff((array)$classes, $sizes));
    $classes[] = isset($widths[$name]) ? $widths[$name] : 'form-row-wide';

    return $classes;
}

/**
 * Aseaza campurile de finalizare pe doua coloane.
 *
 * @param array $fields Campurile de finalizare.
 *
 * @return array
 */
function ht_checkout_fields($fields)
{
    /*
     * Campul "Companie" nu apare in design (Figma 173:1415). Cine are nevoie
     * de el il pune la loc cu add_filter('ht_checkout_hide_company', '__return_false').
     */
    if (apply_filters('ht_checkout_hide_company', true)) {
        unset($fields['billing']['billing_company'], $fields['shipping']['shipping_company']);
    }

    foreach ($fields as $group => $group_fields) {
        foreach ($group_fields as $key => $field) {
            $name = preg_replace('~^(billing|shipping)_~', '', $key);
            $classes = isset($field['class']) ? $field['class'] : array();

            $fields[$group][$key]['class'] = ht_checkout_field_class($classes, $name);
        }
    }

    return $fields;
}

add_filter('woocommerce_checkout_fields', 'ht_checkout_fields', 20);

/**
 * Codul postal nu se cere.
 *
 * Din campurile implicite de adresa pornesc si facturarea, si livrarea, si
 * formularele de adresa din contul clientului, deci de aici dispare de peste
 * tot. Cine il vrea inapoi il pune la loc cu
 * add_filter('ht_checkout_hide_postcode', '__return_false').
 *
 * @param array $fields Campurile implicite de adresa.
 *
 * @return array
 */
function ht_checkout_drop_postcode($fields)
{
    if (apply_filters('ht_checkout_hide_postcode', true)) {
        unset($fields['postcode']);
    }

    return $fields;
}

add_filter('woocommerce_default_address_fields', 'ht_checkout_drop_postcode', 20);

/**
 * Telefonul e obligatoriu la finalizarea comenzii?
 *
 * Implicit da: curierul suna clientul inainte de livrare, deci fara numar
 * comanda nu se poate onora. Cine il vrea optional il lasa la latitudinea
 * setarii din WooCommerce cu add_filter('ht_checkout_require_phone', '__return_false').
 *
 * @return bool
 */
function ht_checkout_require_phone()
{
    return (bool)apply_filters('ht_checkout_require_phone', true);
}

/**
 * Fixeaza setarea WooCommerce pentru campul telefon pe "obligatoriu".
 *
 * De la optiunea 'woocommerce_checkout_phone_field' (hidden / optional /
 * required) pornesc si campurile implicite de adresa, si cele de facturare,
 * si finalizarea pe blocuri, deci fixata la sursa acopera toate formularele
 * deodata si nu mai poate fi slabita din greseala din admin.
 *
 * @param mixed $value Valoarea scurtcircuitata (false = citeste din baza).
 *
 * @return mixed
 */
function ht_checkout_phone_option($value)
{
    return ht_checkout_require_phone() ? 'required' : $value;
}

add_filter('pre_option_woocommerce_checkout_phone_field', 'ht_checkout_phone_option');

/**
 * Si pe campurile de finalizare deja construite, dupa ce si-au spus cuvantul
 * celelalte filtre: un plugin care il face optional nu trece de aici.
 *
 * @param array $fields Campurile de finalizare.
 *
 * @return array
 */
function ht_checkout_phone_required($fields)
{
    if (!ht_checkout_require_phone() || !isset($fields['billing']['billing_phone'])) {
        return $fields;
    }

    $fields['billing']['billing_phone']['required'] = true;

    return $fields;
}

add_filter('woocommerce_checkout_fields', 'ht_checkout_phone_required', 100);

/* ---------------------------------------------------------------------------
 * Mesajele de validare
 * ------------------------------------------------------------------------ */

/**
 * Rescrie un mesaj de validare al WooCommerce intr-o forma citibila.
 *
 * Pluginul pune in fata etichetei grupul campului ("Facturare Telefon este un
 * camp obligatoriu"), ceea ce suna a traducere automata. Aici eticheta ramane
 * curata, iar pentru livrare primeste "(livrare)" dupa nume - grupul conteaza
 * doar cand clientul a cerut livrarea la alta adresa si are ambele seturi de
 * campuri pe ecran.
 *
 * Functia e pura, ca sa poata fi testata fara WooCommerce.
 *
 * @param string     $code     Codul erorii: '<camp>_required' sau '<camp>_validation'.
 * @param string     $message  Mesajul original al pluginului (cu <strong> in jurul etichetei).
 * @param array|null $field    array('label' => 'Telefon', 'group' => 'billing'), null pentru erori fara camp.
 * @param array      $prefixes Prefixele pe grup, asa cum le pune pluginul: array('billing' => 'Facturare ', ...).
 *
 * @return string
 */
function ht_checkout_error_message($code, $message, $field, $prefixes)
{
    if (!$field || '' === $field['label']) {
        return $message;
    }

    $label = $field['label'];

    if ('shipping' === $field['group']) {
        $label = sprintf(__('%s (livrare)', 'herbal-therapy'), $label);
    }

    $strong = '<strong>' . esc_html($label) . '</strong>';

    if (preg_match('~_required$~', $code)) {
        return sprintf(__('Te rugăm să completezi câmpul %s.', 'herbal-therapy'), $strong);
    }

    if (preg_match('~_phone_validation$~', $code)) {
        return sprintf(__('%s nu pare un număr de telefon valid.', 'herbal-therapy'), $strong);
    }

    if (preg_match('~_email_validation$~', $code)) {
        return sprintf(__('%s nu pare o adresă de email validă.', 'herbal-therapy'), $strong);
    }

    /* orice alt mesaj: doar eticheta curatata de prefix */
    $prefix = isset($prefixes[$field['group']]) ? $prefixes[$field['group']] : '';

    return str_replace(
        '<strong>' . esc_html($prefix . $field['label']) . '</strong>',
        $strong,
        $message
    );
}

/**
 * Trece toate erorile de la finalizare prin ht_checkout_error_message().
 *
 * @param array    $data   Datele trimise (nefolosite).
 * @param WP_Error $errors Erorile adunate de plugin.
 */
function ht_checkout_clean_errors($data, $errors)
{
    $fields = array();

    foreach (WC()->checkout()->get_checkout_fields() as $group => $group_fields) {
        foreach ($group_fields as $key => $field) {
            $fields[$key] = array(
                'label' => isset($field['label']) ? $field['label'] : '',
                'group' => $group,
            );
        }
    }

    $prefixes = array(
        'billing'  => sprintf(_x('Billing %s', 'checkout-validation', 'woocommerce'), ''),
        'shipping' => sprintf(_x('Shipping %s', 'checkout-validation', 'woocommerce'), ''),
    );

    $rebuilt = array();

    foreach ($errors->get_error_codes() as $code) {
        $key   = preg_replace('~_(required|validation)$~', '', $code);
        $field = isset($fields[$key]) ? $fields[$key] : null;

        $rebuilt[$code] = array(
            'data'     => $errors->get_error_data($code),
            'messages' => array_map(function ($message) use ($code, $field, $prefixes) {
                return ht_checkout_error_message($code, $message, $field, $prefixes);
            }, $errors->get_error_messages($code)),
        );
    }

    /* se rescriu toate, in aceeasi ordine, ca lista de sus sa ramana neschimbata */
    foreach ($rebuilt as $code => $entry) {
        $errors->remove($code);

        foreach ($entry['messages'] as $message) {
            $errors->add($code, $message, $entry['data']);
        }
    }
}

add_action('woocommerce_after_checkout_validation', 'ht_checkout_clean_errors', 10, 2);

/**
 * Aceleasi latimi si in setarile pe tara.
 *
 * Fara pasul asta latimile de mai sus tin doar pana la primul tur al scriptului
 * wc-address-i18n: el rescrie clasele campurilor de adresa din setarile tarii
 * alese, deci le-ar trece pe toate inapoi pe un rand intreg. Filtrul prinde si
 * formularele de adresa din contul clientului, care folosesc aceeasi grila.
 *
 * @param array $locale Setarile pe tara.
 *
 * @return array
 */
function ht_checkout_country_locale($locale)
{
    foreach ($locale as $country => $fields) {
        foreach ($fields as $name => $field) {
            /* doar campurile care chiar au o latime scrisa in setari */
            if (!isset($field['class'])) {
                continue;
            }

            $locale[$country][$name]['class'] = ht_checkout_field_class($field['class'], $name);
        }
    }

    return $locale;
}

/**
 * Acelasi lucru pentru setarile implicite, de la care pornesc toate tarile.
 *
 * WooCommerce le adauga dupa filtrul de mai sus, sub cheia 'default', si tot de
 * acolo isi ia scriptul latimile cand tara aleasa nu are ceva scris explicit -
 * cazul Republicii Moldova.
 *
 * @param array $fields Campurile implicite de adresa.
 *
 * @return array
 */
function ht_checkout_locale_default($fields)
{
    foreach ($fields as $name => $field) {
        if (!isset($field['class'])) {
            continue;
        }

        $fields[$name]['class'] = ht_checkout_field_class($field['class'], $name);
    }

    return $fields;
}

if (!is_admin()) {
    add_filter('woocommerce_get_country_locale', 'ht_checkout_country_locale', 20);
    add_filter('woocommerce_get_country_locale_default', 'ht_checkout_locale_default', 20);
    add_filter('woocommerce_get_country_locale_base', 'ht_checkout_locale_default', 20);
}

/**
 * Comentariul la comanda: fara eticheta deasupra, doar text in camp.
 *
 * @param array $fields Campurile de finalizare.
 *
 * @return array
 */
function ht_checkout_order_notes($fields)
{
    if (isset($fields['order']['order_comments'])) {
        $fields['order']['order_comments']['placeholder'] = __(
            'Bloc, scară, interfon, ora la care vrei livrarea...',
            'herbal-therapy'
        );
    }

    return $fields;
}

add_filter('woocommerce_checkout_fields', 'ht_checkout_order_notes', 25);

/* ---------------------------------------------------------------------------
 * Butonul de comanda
 * ------------------------------------------------------------------------ */

/**
 * Butonul ramane in blocul de plata, langa metode?
 *
 * Implicit nu: il mutam in sumar, ca sa fie mereu vizibil langa total. Blocul
 * din jurul lui (conditii, nonce, hook-urile 'woocommerce_review_order_*_submit')
 * pleaca odata cu el, deci nu se mai reincarca prin AJAX la schimbarea adresei.
 * Procesatoarele care isi deseneaza butoane proprii acolo - de tip Stripe sau
 * PayPal Express - au nevoie de reincarcare, deci pentru ele se pune la loc cu:
 *
 *   add_filter('ht_checkout_detach_submit', '__return_false');
 *
 * @return bool
 */
function ht_checkout_detach_submit()
{
    return (bool)apply_filters('ht_checkout_detach_submit', true);
}

/**
 * Eticheta butonului de finalizare.
 *
 * Fara total in text, spre deosebire de butonul din cosul rapid: aici totalul e
 * chiar deasupra butonului si se reincarca prin AJAX, in timp ce eticheta nu -
 * checkout.js o reface din atributul 'data-value' de fiecare data cand se
 * schimba metoda de plata, deci o suma scrisa in ea ar ramane in urma.
 *
 * @return string
 */
function ht_checkout_order_button_text()
{
    return apply_filters('woocommerce_order_button_text', __('Plasează comanda', 'herbal-therapy'));
}

/**
 * Blocul cu conditii, buton si nonce, asa cum il stie WooCommerce.
 *
 * E acelasi markup ca in checkout/payment.php, doar ca traieste in sumar. Se
 * randeaza o singura data pe pagina - vezi ht_checkout_detach_submit().
 */
function ht_checkout_place_order()
{
    if (!ht_checkout_detach_submit()) {
        return;
    }

    $label = ht_checkout_order_button_text();
    ?>
    <div class="form-row place-order ht-checkout__place">

        <?php wc_get_template('checkout/terms.php'); ?>

        <?php do_action('woocommerce_review_order_before_submit'); ?>

        <button type="submit" class="button alt ht-checkout__submit" name="woocommerce_checkout_place_order"
                id="place_order" value="<?php echo esc_attr($label); ?>"
                data-value="<?php echo esc_attr($label); ?>">
            <?php echo esc_html($label); ?>
        </button>

        <?php do_action('woocommerce_review_order_after_submit'); ?>

        <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
    </div>
    <?php
}

/* ---------------------------------------------------------------------------
 * Sumarul comenzii
 * ------------------------------------------------------------------------ */

/**
 * Randul unui produs din sumar, normalizat pentru sablon.
 *
 * Aceleasi chei ca la cosul rapid, minus butoanele: in finalizare linia e doar
 * de citit, cu imagine, cantitate si pret.
 *
 * @param string $key  Cheia liniei din cos.
 * @param array  $item Linia din WC()->cart->get_cart().
 *
 * @return array|null Null daca produsul nu mai poate fi cumparat.
 */
function ht_checkout_item_data($key, $item)
{
    $product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $key);

    if (!$product instanceof WC_Product || !$product->exists() || $item['quantity'] <= 0) {
        return null;
    }

    if (!apply_filters('woocommerce_checkout_cart_item_visible', true, $item, $key)) {
        return null;
    }

    $image_id = $product->get_image_id();
    $src = $image_id ? wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail') : false;

    return array(
        'key'      => $key,
        'url'      => $product->is_visible() ? $product->get_permalink($item) : '',
        'title'    => apply_filters('woocommerce_cart_item_name', $product->get_name(), $item, $key),
        'meta'     => wc_get_formatted_cart_item_data($item, true),
        'image'    => $src ? $src[0] : wc_placeholder_img_src('woocommerce_thumbnail'),
        /*
         * Filtrul e cel al pluginului, dar valoarea de pornire e a temei: in
         * sablonul original cantitatea vine invelita in '<strong class="product-quantity">',
         * pe cand aici e doar cifra din coltul imaginii. Extensiile care adauga
         * ceva la ea merg mai departe; cele care se asteapta la markup-ul original
         * il pot reface, pentru ca primesc si linia din cos.
         */
        'quantity' => apply_filters(
            'woocommerce_checkout_cart_item_quantity',
            (string)(int)$item['quantity'],
            $item,
            $key
        ),
        'total'    => apply_filters(
            'woocommerce_cart_item_subtotal',
            WC()->cart->get_product_subtotal($product, $item['quantity']),
            $item,
            $key
        ),
        'class'    => apply_filters('woocommerce_cart_item_class', 'cart_item', $item, $key),
    );
}

/**
 * Liniile din sumar, gata de randat.
 *
 * @return array
 */
function ht_checkout_items()
{
    $items = array();

    if (!function_exists('WC') || !WC()->cart) {
        return $items;
    }

    foreach (WC()->cart->get_cart() as $key => $item) {
        $data = ht_checkout_item_data($key, $item);

        if ($data) {
            $items[] = $data;
        }
    }

    return $items;
}

/**
 * Numarul de produse din cos, pentru titlul sumarului.
 *
 * @return int
 */
function ht_checkout_count()
{
    return function_exists('ht_cart_count') ? ht_cart_count() : 0;
}

/**
 * Campul de cod promotional din sumar.
 *
 * Sta in afara fragmentului de AJAX, deci nu se rescrie la fiecare recalculare;
 * cupoanele deja aplicate se afiseaza in schimb inauntru, in review-order.php,
 * ca sa dispara singure cand se schimba cosul.
 */
function ht_checkout_coupon_field()
{
    if (!wc_coupons_enabled()) {
        return;
    }
    ?>
    <div class="ht-checkout-promo" data-ht-checkout-promo>
        <div class="ht-checkout-promo__label">
            <input class="ht-checkout-promo__input" type="text" autocomplete="off"
                   placeholder="<?php esc_attr_e('Cod promoțional', 'herbal-therapy'); ?>"
                   aria-label="<?php esc_attr_e('Cod promoțional', 'herbal-therapy'); ?>"
                   data-ht-checkout-coupon>
            <button class="ht-checkout-promo__send" type="button" data-ht-checkout-coupon-apply disabled>
                <?php esc_html_e('Aplică', 'herbal-therapy'); ?>
            </button>
        </div>
        <p class="ht-checkout-promo__note" data-ht-checkout-promo-note></p>
    </div>
    <?php
}

/* ---------------------------------------------------------------------------
 * Titlul paginii
 * ------------------------------------------------------------------------ */

/**
 * Titlul afisat deasupra formularului.
 *
 * @return string
 */
function ht_checkout_title()
{
    if (is_wc_endpoint_url('order-received')) {
        return __('Mulțumim pentru comandă', 'herbal-therapy');
    }

    if (is_wc_endpoint_url('order-pay')) {
        return __('Plata comenzii', 'herbal-therapy');
    }

    return __('Finalizare comandă', 'herbal-therapy');
}
