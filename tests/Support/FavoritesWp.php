<?php
/**
 * Inlocuitori minimali pentru functiile WordPress / WooCommerce pe care le
 * atinge inc/favorites.php: utilizatorul curent, meta de utilizator, optiuni,
 * starea articolelor si o versiune redusa de wc_get_products.
 *
 * Starea sta in HT_Test_State si se aduce la zero intre teste.
 *
 * @package Herbal_Therapy
 */

final class HT_Test_State
{
    /** @var int Utilizatorul autentificat (0 = vizitator). */
    public static $user_id = 0;

    /** @var array Meta de utilizator: [user_id][cheie] => valoare. */
    public static $user_meta = array();

    /** @var array Optiuni: [nume] => valoare. */
    public static $options = array();

    /** @var array Starea articolelor: [id] => status. */
    public static $post_status = array();

    /** @var array Meta de articol: [id][cheie] => valoare. */
    public static $post_meta = array();

    /** @var array Pagini dupa slug: [post_name] => id. */
    public static $pages = array();

    /** @var array Rutele REST inregistrate: [ruta] => argumente. */
    public static $rest_routes = array();

    /** @var array Traduceri Polylang in limba curenta: [id] => id tradus. */
    public static $translations = array();

    /** @var array Grupuri de traduceri: [id] => array(limba => id). */
    public static $translation_groups = array();

    /** @var int Urmatorul ID la wp_insert_post(). */
    public static $next_post_id = 900;

    /**
     * Aduce totul la zero, intre teste.
     */
    public static function reset()
    {
        self::$user_id = 0;
        self::$user_meta = array();
        self::$options = array();
        self::$post_status = array();
        self::$post_meta = array();
        self::$pages = array();
        self::$rest_routes = array();
        self::$translations = array();
        self::$translation_groups = array();
        self::$next_post_id = 900;
        $_COOKIE = array();
    }

    /**
     * Inregistreaza un produs (un articol cu un status).
     *
     * @param int    $id     ID-ul.
     * @param string $status Statusul.
     */
    public static function product($id, $status = 'publish')
    {
        self::$post_status[(int)$id] = $status;
    }
}

class WP_REST_Server
{
    const READABLE = 'GET';
    const CREATABLE = 'POST';
}

class WP_User
{
    public $ID = 0;

    public function __construct($id = 0)
    {
        $this->ID = (int)$id;
    }
}

class WC_Product
{
}

/**
 * Produs redus la ce foloseste inc/favorites.php: get_id() si get_status().
 */
class HT_Test_Product extends WC_Product
{
    private $id;

    public function __construct($id)
    {
        $this->id = (int)$id;
    }

    public function get_id()
    {
        return $this->id;
    }

    public function get_status()
    {
        return get_post_status($this->id);
    }
}

/**
 * Cererea REST, cat sa mearga get_param(). Nu aplica valorile implicite din
 * schema rutei - testele trimit parametrii explicit.
 */
class WP_REST_Request
{
    private $params;

    public function __construct(array $params = array())
    {
        $this->params = $params;
    }

    public function get_param($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}

/* ---- functii ----------------------------------------------------------- */

function absint($maybeint)
{
    return abs((int)$maybeint);
}

function sanitize_text_field($str)
{
    $str = trim(preg_replace('/[\r\n\t ]+/', ' ', (string)$str));

    return strip_tags($str);
}

function do_action($tag)
{
    if (empty(HT_Test_Wp::$hooks[$tag])) {
        return;
    }

    $args = array_slice(func_get_args(), 1);
    $levels = HT_Test_Wp::$hooks[$tag];
    ksort($levels);

    foreach ($levels as $callbacks) {
        foreach ($callbacks as $callback) {
            call_user_func_array($callback, $args);
        }
    }
}

function get_current_user_id()
{
    return HT_Test_State::$user_id;
}

function get_user_meta($user_id, $key, $single = false)
{
    if (isset(HT_Test_State::$user_meta[$user_id][$key])) {
        return HT_Test_State::$user_meta[$user_id][$key];
    }

    return $single ? '' : array();
}

function update_user_meta($user_id, $key, $value)
{
    HT_Test_State::$user_meta[$user_id][$key] = $value;

    return true;
}

function delete_user_meta($user_id, $key)
{
    unset(HT_Test_State::$user_meta[$user_id][$key]);

    return true;
}

function is_ssl()
{
    return false;
}

function get_post_status($post = null)
{
    $id = is_object($post) ? (int)$post->ID : (int)$post;

    return isset(HT_Test_State::$post_status[$id]) ? HT_Test_State::$post_status[$id] : false;
}

function get_option($name, $default = false)
{
    return array_key_exists($name, HT_Test_State::$options) ? HT_Test_State::$options[$name] : $default;
}

function update_option($name, $value)
{
    HT_Test_State::$options[$name] = $value;

    return true;
}

function get_page_by_path($path)
{
    if (!isset(HT_Test_State::$pages[$path])) {
        return null;
    }

    $page = new stdClass();
    $page->ID = HT_Test_State::$pages[$path];

    return $page;
}

function wp_insert_post($args)
{
    $id = HT_Test_State::$next_post_id++;

    HT_Test_State::$post_status[$id] = isset($args['post_status']) ? $args['post_status'] : 'draft';

    if (!empty($args['post_name'])) {
        HT_Test_State::$pages[$args['post_name']] = $id;
    }

    return $id;
}

function update_post_meta($post_id, $key, $value)
{
    HT_Test_State::$post_meta[$post_id][$key] = $value;

    return true;
}

function rest_ensure_response($response)
{
    return $response;
}

function register_rest_route($ns, $route, $args = array())
{
    HT_Test_State::$rest_routes[$ns . $route] = $args;

    return true;
}

/**
 * Varianta redusa: un produs pentru orice articol cu status cunoscut.
 */
function wc_get_product($id)
{
    $id = (int)$id;

    return false === get_post_status($id) ? false : new HT_Test_Product($id);
}

/**
 * Traducerea unui articol. Cu limba explicita cauta in $translation_groups;
 * fara, foloseste harta $translations ("limba curenta" a testului - goala
 * inseamna fara limba curenta, ca intr-o cerere REST).
 */
function pll_get_post($post_id, $lang = '')
{
    $post_id = (int)$post_id;

    if ('' !== (string)$lang) {
        return isset(HT_Test_State::$translation_groups[$post_id][$lang])
            ? HT_Test_State::$translation_groups[$post_id][$lang]
            : null;
    }

    return isset(HT_Test_State::$translations[$post_id])
        ? HT_Test_State::$translations[$post_id]
        : null;
}

/**
 * Grupul de traduceri al unui articol, ca in Polylang: array(limba => id).
 */
function pll_get_post_translations($post_id)
{
    $post_id = (int)$post_id;

    return isset(HT_Test_State::$translation_groups[$post_id])
        ? HT_Test_State::$translation_groups[$post_id]
        : array();
}

function sanitize_key($key)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$key));
}

function ht_icon($name, $classes = 'ht-icon')
{
    printf('<svg class="%s" data-icon="%s"></svg>', esc_attr($classes), esc_attr($name));
}
