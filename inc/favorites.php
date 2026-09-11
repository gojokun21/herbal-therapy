<?php
/**
 * Favorite - lista de produse salvate de vizitator.
 *
 * Stocare pe doua niveluri, ca lista sa nu depinda de browser:
 *  - utilizator autentificat -> meta de utilizator (_ht_favorites), deci lista
 *    il urmeaza pe orice dispozitiv;
 *  - vizitator -> un cookie citit direct in PHP, ca starea inimii sa fie deja
 *    corecta la primul render, fara sa clipeasca dupa incarcarea scriptului.
 *
 * La autentificare, cookie-ul se contopeste in contul utilizatorului.
 *
 * Scrierile vin prin REST (herbal-therapy/v1/favorites), nu prin admin-ajax.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Constante si reglaje
 * ------------------------------------------------------------------------ */

if (!defined('HT_FAVORITES_META')) {
    define('HT_FAVORITES_META', '_ht_favorites');
}

if (!defined('HT_FAVORITES_COOKIE')) {
    define('HT_FAVORITES_COOKIE', 'ht_favorites');
}

/**
 * Cate produse incap in lista. Limita tine cookie-ul sub 4 KB si opreste
 * umflarea meta-ului de utilizator.
 *
 * @return int
 */
function ht_favorites_limit()
{
    return (int)apply_filters('ht_favorites_limit', 100);
}

/**
 * Cat traieste cookie-ul vizitatorului, in secunde.
 *
 * @return int
 */
function ht_favorites_cookie_life()
{
    return (int)apply_filters('ht_favorites_cookie_life', YEAR_IN_SECONDS);
}

/* ---------------------------------------------------------------------------
 * Citire / scriere
 * ------------------------------------------------------------------------ */

/**
 * Curata o lista de identificatori: intregi pozitivi, unici, in limita.
 *
 * @param mixed $ids Lista bruta.
 *
 * @return int[]
 */
function ht_favorites_sanitize($ids)
{
    if (is_string($ids)) {
        $ids = explode(',', $ids);
    }

    if (!is_array($ids)) {
        return array();
    }

    $ids = array_map('absint', $ids);
    $ids = array_values(array_unique(array_filter($ids)));

    return array_slice($ids, 0, ht_favorites_limit());
}

/**
 * Produsul poate fi salvat? Filtreaza ce a fost sters sau depublicat intre timp.
 *
 * @param int $product_id Identificatorul produsului.
 *
 * @return bool
 */
function ht_favorites_is_valid_product($product_id)
{
    $product_id = absint($product_id);

    if (!$product_id) {
        return false;
    }

    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        $valid = $product instanceof WC_Product && 'publish' === $product->get_status();
    } else {
        $valid = 'publish' === get_post_status($product_id);
    }

    return (bool)apply_filters('ht_favorites_is_valid_product', $valid, $product_id);
}

/**
 * Lista curenta, cea mai recenta intrare prima.
 *
 * @return int[]
 */
function ht_favorites_get()
{
    $user_id = get_current_user_id();

    if ($user_id) {
        $ids = ht_favorites_sanitize(get_user_meta($user_id, HT_FAVORITES_META, true));
    } else {
        $raw = isset($_COOKIE[HT_FAVORITES_COOKIE]) ? wp_unslash($_COOKIE[HT_FAVORITES_COOKIE]) : '';
        $ids = ht_favorites_sanitize(sanitize_text_field($raw));
    }

    return ht_favorites_localize($ids);
}

/**
 * Aduce lista in limba curenta (Polylang).
 *
 * Inima se apasa pe produsul dintr-o limba, dar acelasi produs exista si in
 * cealalta. Fara traducere, un produs salvat pe /ru/ ar fi invizibil pe pagina
 * romaneasca de favorite (filtrul de limba il ascunde), desi contorul l-ar
 * numara - si nici nu ar mai putea fi scos din lista. Un identificator fara
 * traducere in limba curenta ramane cum e, ca produsul sa nu se piarda.
 *
 * @param int[]  $ids  Lista bruta.
 * @param string $lang Limba ceruta; gol = limba curenta a cererii.
 *
 * @return int[] Lista tradusa, fara dubluri, in aceeasi ordine.
 */
function ht_favorites_localize($ids, $lang = '')
{
    if (!function_exists('pll_get_post')) {
        return $ids;
    }

    $localized = array();

    foreach ($ids as $id) {
        $translated = (int)pll_get_post($id, $lang);

        if (!$translated) {
            $translated = $id;
        }

        if (!in_array($translated, $localized, true)) {
            $localized[] = $translated;
        }
    }

    return $localized;
}

/**
 * Toate traducerile unui produs, inclusiv el insusi (Polylang).
 *
 * Operatiile pe lista trec prin acest grup: cererea REST poate veni cu id-ul
 * din limba paginii, in timp ce in lista sta id-ul din limba in care s-a
 * apasat prima data inima - ambele inseamna acelasi produs.
 *
 * @param int $product_id Identificatorul produsului.
 *
 * @return int[]
 */
function ht_favorites_translations($product_id)
{
    $product_id = absint($product_id);

    if (!$product_id) {
        return array();
    }

    if (function_exists('pll_get_post_translations')) {
        $ids = array_map('absint', array_values(pll_get_post_translations($product_id)));
        $ids = array_values(array_unique(array_filter($ids)));

        if (!in_array($product_id, $ids, true)) {
            $ids[] = $product_id;
        }

        return $ids;
    }

    return array($product_id);
}

/**
 * Scrie lista, acolo unde ii este locul pentru vizitatorul curent.
 *
 * @param int[] $ids Lista noua.
 *
 * @return int[] Lista salvata, dupa curatare.
 */
function ht_favorites_set($ids)
{
    $ids = ht_favorites_sanitize($ids);
    $user_id = get_current_user_id();

    if ($user_id) {
        if ($ids) {
            update_user_meta($user_id, HT_FAVORITES_META, $ids);
        } else {
            delete_user_meta($user_id, HT_FAVORITES_META);
        }
    } else {
        ht_favorites_write_cookie($ids);
    }

    do_action('ht_favorites_updated', $ids, $user_id);

    return $ids;
}

/**
 * Scrie (sau sterge) cookie-ul vizitatorului si actualizeaza $_COOKIE, ca
 * restul cererii curente sa vada deja valoarea noua.
 *
 * @param int[] $ids Lista.
 */
function ht_favorites_write_cookie($ids)
{
    $value = implode(',', $ids);
    $expire = $ids ? time() + ht_favorites_cookie_life() : time() - YEAR_IN_SECONDS;

    if ($ids) {
        $_COOKIE[HT_FAVORITES_COOKIE] = $value;
    } else {
        unset($_COOKIE[HT_FAVORITES_COOKIE]);
    }

    if (headers_sent()) {
        return;
    }

    setcookie(
        HT_FAVORITES_COOKIE,
        $value,
        array(
            'expires'  => $expire,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );
}

/* ---------------------------------------------------------------------------
 * Operatii
 * ------------------------------------------------------------------------ */

/**
 * Produsul e in lista? Orice traducere a lui conteaza drept acelasi produs.
 *
 * @param int $product_id Identificatorul produsului.
 *
 * @return bool
 */
function ht_is_favorite($product_id)
{
    $ids = ht_favorites_get();

    if (!$ids) {
        return false;
    }

    return (bool)array_intersect(ht_favorites_translations($product_id), $ids);
}

/**
 * Numarul de produse salvate.
 *
 * @return int
 */
function ht_favorites_count()
{
    return count(ht_favorites_get());
}

/**
 * Adauga un produs. Intrarile noi stau in fata, ca pagina de favorite sa arate
 * intai ce a fost salvat ultima data.
 *
 * @param int $product_id Identificatorul produsului.
 *
 * @return int[] Lista rezultata.
 */
function ht_favorites_add($product_id)
{
    $product_id = absint($product_id);

    if (!ht_favorites_is_valid_product($product_id)) {
        return ht_favorites_get();
    }

    $ids = ht_favorites_get();

    /* orice traducere deja salvata inseamna acelasi produs */
    if (ht_is_favorite($product_id)) {
        return $ids;
    }

    array_unshift($ids, $product_id);

    return ht_favorites_set($ids);
}

/**
 * Scoate un produs din lista, impreuna cu traducerile lui - id-ul primit
 * poate fi in alta limba decat cel stocat.
 *
 * @param int $product_id Identificatorul produsului.
 *
 * @return int[] Lista rezultata.
 */
function ht_favorites_remove($product_id)
{
    $ids = ht_favorites_get();
    $left = array_values(array_diff($ids, ht_favorites_translations($product_id)));

    if (count($left) === count($ids)) {
        return $ids;
    }

    return ht_favorites_set($left);
}

/**
 * Comuta un produs.
 *
 * @param int $product_id Identificatorul produsului.
 *
 * @return bool Starea de dupa comutare.
 */
function ht_favorites_toggle($product_id)
{
    if (ht_is_favorite($product_id)) {
        ht_favorites_remove($product_id);

        return false;
    }

    ht_favorites_add($product_id);

    return ht_is_favorite($product_id);
}

/**
 * Adauga mai multe produse dintr-o data (contopirea listelor).
 *
 * @param int[] $ids Identificatori.
 *
 * @return int[] Lista rezultata.
 */
function ht_favorites_merge($ids)
{
    $incoming = array_filter(ht_favorites_sanitize($ids), 'ht_favorites_is_valid_product');

    if (!$incoming) {
        return ht_favorites_get();
    }

    return ht_favorites_set(array_merge($incoming, ht_favorites_get()));
}

/**
 * Goleste lista.
 *
 * @return int[]
 */
function ht_favorites_clear()
{
    return ht_favorites_set(array());
}

/* ---------------------------------------------------------------------------
 * Autentificare - lista din cookie trece in cont
 * ------------------------------------------------------------------------ */

/**
 * La login, produsele salvate ca vizitator intra in contul utilizatorului, iar
 * cookie-ul dispare. Fara asta, lista adunata inainte de autentificare s-ar pierde.
 *
 * @param string  $user_login Numele de utilizator.
 * @param WP_User $user       Utilizatorul.
 */
function ht_favorites_merge_on_login($user_login, $user)
{
    $raw = isset($_COOKIE[HT_FAVORITES_COOKIE]) ? wp_unslash($_COOKIE[HT_FAVORITES_COOKIE]) : '';
    $guest = ht_favorites_sanitize(sanitize_text_field($raw));

    /* stergem cookie-ul chiar daca era gol - de acum conteaza doar contul */
    ht_favorites_write_cookie(array());

    if (!$guest) {
        return;
    }

    $saved = ht_favorites_sanitize(get_user_meta($user->ID, HT_FAVORITES_META, true));
    $merged = ht_favorites_sanitize(array_merge($guest, $saved));

    if ($merged) {
        update_user_meta($user->ID, HT_FAVORITES_META, $merged);
    }
}

add_action('wp_login', 'ht_favorites_merge_on_login', 10, 2);

/* ---------------------------------------------------------------------------
 * REST
 * ------------------------------------------------------------------------ */

/**
 * Ruta prin care scriptul citeste si scrie lista.
 *
 * permission_callback este deschis pentru ca lista apartine sesiunii care face
 * cererea: un vizitator isi scrie propriul cookie, iar un utilizator autentificat
 * este identificat de WordPress din cookie-ul de login plus nonce-ul 'wp_rest'.
 */
function ht_favorites_register_rest()
{
    register_rest_route('herbal-therapy/v1', '/favorites', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'ht_favorites_rest_read',
            'permission_callback' => '__return_true',
            'args'                => array(
                'lang' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
            ),
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'ht_favorites_rest_write',
            'permission_callback' => '__return_true',
            'args'                => array(
                'action' => array(
                    'type'    => 'string',
                    'default' => 'toggle',
                    'enum'    => array('toggle', 'add', 'remove', 'clear'),
                ),
                'id'     => array(
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
                'ids'    => array(
                    'type'    => 'array',
                    'default' => array(),
                    'items'   => array('type' => 'integer'),
                ),
                'lang'   => array(
                    'type'    => 'string',
                    'default' => '',
                ),
            ),
        ),
    ));
}

add_action('rest_api_init', 'ht_favorites_register_rest');

/**
 * Limba trimisa de client. In REST, Polylang nu stie limba paginii de pe care
 * a venit cererea, asa ca scriptul o trimite el.
 *
 * @param WP_REST_Request $request Cererea.
 *
 * @return string
 */
function ht_favorites_rest_lang($request)
{
    return sanitize_key((string)$request->get_param('lang'));
}

/**
 * Raspunsul comun al rutei.
 *
 * @param bool|null $active Starea produsului atins de cerere, daca e cazul.
 * @param string    $lang   Limba paginii clientului.
 *
 * @return array
 */
function ht_favorites_rest_payload($active = null, $lang = '')
{
    $ids = ht_favorites_localize(ht_favorites_get(), $lang);

    $payload = array(
        'ids'   => $ids,
        'count' => count($ids),
    );

    if (null !== $active) {
        $payload['active'] = (bool)$active;
    }

    return $payload;
}

/**
 * GET - lista curenta.
 *
 * @return WP_REST_Response
 */
function ht_favorites_rest_read($request)
{
    return rest_ensure_response(ht_favorites_rest_payload(null, ht_favorites_rest_lang($request)));
}

/**
 * POST - comuta, adauga, scoate sau goleste.
 *
 * @param WP_REST_Request $request Cererea.
 *
 * @return WP_REST_Response|WP_Error
 */
function ht_favorites_rest_write($request)
{
    $action = $request->get_param('action');
    $id = absint($request->get_param('id'));
    $ids = ht_favorites_sanitize($request->get_param('ids'));
    $lang = ht_favorites_rest_lang($request);

    if ('clear' === $action) {
        ht_favorites_clear();

        return rest_ensure_response(ht_favorites_rest_payload(null, $lang));
    }

    /* 'ids' acopera contopirea listei vechi din localStorage */
    if ('add' === $action && $ids) {
        ht_favorites_merge($ids);

        return rest_ensure_response(ht_favorites_rest_payload(null, $lang));
    }

    if (!$id) {
        return new WP_Error(
            'ht_favorites_missing_id',
            __('Lipsește produsul.', 'herbal-therapy'),
            array('status' => 400)
        );
    }

    if ('remove' === $action) {
        ht_favorites_remove($id);

        return rest_ensure_response(ht_favorites_rest_payload(false, $lang));
    }

    /*
     * Un produs deja salvat se poate scoate chiar daca intre timp a fost
     * depublicat - altfel ar ramane blocat in lista.
     */
    if (!ht_is_favorite($id) && !ht_favorites_is_valid_product($id)) {
        return new WP_Error(
            'ht_favorites_invalid_product',
            __('Produsul nu mai este disponibil.', 'herbal-therapy'),
            array('status' => 404)
        );
    }

    if ('add' === $action) {
        ht_favorites_add($id);

        return rest_ensure_response(ht_favorites_rest_payload(true, $lang));
    }

    $active = ht_favorites_toggle($id);

    return rest_ensure_response(ht_favorites_rest_payload($active, $lang));
}

/* ---------------------------------------------------------------------------
 * Pagina de favorite
 * ------------------------------------------------------------------------ */

/**
 * Identificatorul paginii care foloseste sablonul de favorite.
 *
 * @return int 0 daca pagina lipseste.
 */
function ht_favorites_page_id()
{
    $id = (int)get_option('ht_favorites_page_id');

    if ($id && 'publish' === get_post_status($id)) {
        return $id;
    }

    $page = get_page_by_path('favorite');

    if ($page && 'publish' === get_post_status($page)) {
        update_option('ht_favorites_page_id', $page->ID);

        return (int)$page->ID;
    }

    return 0;
}

/**
 * Link-ul din header catre lista de favorite.
 *
 * @param string $url URL-ul implicit.
 *
 * @return string
 */
function ht_favorites_url($url)
{
    $id = ht_favorites_page_id();

    return $id ? get_permalink($id) : $url;
}

add_filter('ht_wishlist_url', 'ht_favorites_url');

/**
 * Creeaza pagina /favorite/ cu sablonul temei, daca nu exista deja.
 *
 * Ruleaza o singura data: la activarea temei sau, pentru instalarile in care
 * tema era deja activa, la prima incarcare a zonei de administrare.
 */
function ht_favorites_install_page()
{
    if (get_option('ht_favorites_page_installed')) {
        return;
    }

    update_option('ht_favorites_page_installed', 1);

    if (ht_favorites_page_id()) {
        return;
    }

    $page_id = wp_insert_post(array(
        'post_title'     => __('Favorite', 'herbal-therapy'),
        'post_name'      => 'favorite',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_content'   => '',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
    ));

    if (!$page_id || is_wp_error($page_id)) {
        return;
    }

    update_post_meta($page_id, '_wp_page_template', 'templates/favorites.php');
    update_option('ht_favorites_page_id', $page_id);
}

add_action('after_switch_theme', 'ht_favorites_install_page');
add_action('admin_init', 'ht_favorites_install_page');

/**
 * Produsele salvate, ca obiecte WooCommerce, in ordinea din lista.
 *
 * @return WC_Product[]
 */
function ht_favorites_products()
{
    $ids = ht_favorites_get();

    if (!$ids || !function_exists('wc_get_product')) {
        return array();
    }

    /*
     * Incarcare directa, produs cu produs, in ordinea listei: o interogare
     * wc_get_products() ar trece prin filtrul de limba al Polylang, care ar
     * ascunde produsele fara traducere in limba curenta - ele trebuie sa
     * ramana vizibile, altfel contorul numara ceva ce pagina nu arata.
     */
    $products = array();

    foreach ($ids as $id) {
        $product = wc_get_product($id);

        if ($product && 'publish' === $product->get_status()) {
            $products[] = $product;
        }
    }

    return $products;
}

/* ---------------------------------------------------------------------------
 * Markup
 * ------------------------------------------------------------------------ */

/**
 * Butonul inima, cu starea deja randata pe server.
 *
 * Acelasi buton pe cardul din listari si pe pagina de produs; difera doar clasa
 * de baza, iar iconita primeste clasa "<baza>-icon".
 *
 * @param int    $product_id Identificatorul produsului.
 * @param string $base_class Clasa de baza a butonului.
 */
function ht_favorite_button($product_id, $base_class = 'ht-card__fav')
{
    $product_id = absint($product_id);
    $active = $product_id && ht_is_favorite($product_id);

    $label_add = __('Adaugă la favorite', 'herbal-therapy');
    $label_remove = __('Șterge din favorite', 'herbal-therapy');
    ?>
    <button class="<?php echo esc_attr($base_class . ($active ? ' is-active' : '')); ?>" type="button"
            data-ht-favorite
            data-product-id="<?php echo esc_attr($product_id); ?>"
            data-label-add="<?php echo esc_attr($label_add); ?>"
            data-label-remove="<?php echo esc_attr($label_remove); ?>"
            aria-pressed="<?php echo $active ? 'true' : 'false'; ?>"
            aria-label="<?php echo esc_attr($active ? $label_remove : $label_add); ?>">
        <?php ht_icon('heart-card', $base_class . '-icon'); ?>
    </button>
    <?php
}

/**
 * Contorul de langa inima din header.
 */
function ht_favorites_count_badge()
{
    $count = ht_favorites_count();

    printf(
        '<span class="ht-icons__count" data-ht-favorites-count>%s</span>',
        $count ? esc_html($count) : ''
    );
}
