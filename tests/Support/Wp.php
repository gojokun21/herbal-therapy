<?php
/**
 * Inlocuitori minimali pentru functiile WordPress / WooCommerce pe care le
 * atinge inc/shop-filters.php.
 *
 * Starea "lumii" (ce pagina e, ce termeni exista) sta in HT_Test_Wp si se
 * seteaza din teste. Hook-urile sunt reale in miniatura: add_filter() /
 * apply_filters() functioneaza, ca sa putem injecta randurile de produse.
 *
 * @package Herbal_Therapy
 */

final class HT_Test_Wp
{
    /** @var WP_Term|null Termenul vizitat (null = pagina de magazin). */
    public static $queried = null;

    /** @var WP_Term[] Toti termenii, indexati dupa ID. */
    public static $terms = array();

    /** @var array Callback-uri per hook. */
    public static $hooks = array();

    /** @var string Adresa paginii de magazin. */
    public static $shop_url = 'https://example.test/produse/';

    /** @var bool Pagina curenta e o cautare? */
    public static $is_search = false;

    /**
     * Aduce totul la zero, intre teste.
     */
    public static function reset()
    {
        self::$queried = null;
        self::$terms = array();
        self::$hooks = array();
        self::$is_search = false;
        $_GET = array();

        ht_shop_memo_flush();
    }

    /**
     * Inregistreaza un termen.
     *
     * @param int    $id       ID-ul.
     * @param string $slug     Slug-ul.
     * @param string $name     Numele afisat.
     * @param int    $parent   ID-ul parintelui.
     * @param string $taxonomy Taxonomia.
     *
     * @return WP_Term
     */
    public static function term($id, $slug, $name = '', $parent = 0, $taxonomy = 'product_cat')
    {
        $term = new WP_Term();
        $term->term_id = (int)$id;
        $term->slug = $slug;
        $term->name = '' === $name ? ucfirst($slug) : $name;
        $term->parent = (int)$parent;
        $term->taxonomy = $taxonomy;

        self::$terms[$term->term_id] = $term;

        return $term;
    }
}

class WP_Term
{
    public $term_id = 0;
    public $slug = '';
    public $name = '';
    public $parent = 0;
    public $taxonomy = 'product_cat';
    public $count = 0;
}

class WP_Error
{
    public $code;
    public $message;
    public $data;

    public function __construct($code = '', $message = '', $data = '')
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code()
    {
        return $this->code;
    }

    public function get_error_message()
    {
        return $this->message;
    }

    public function get_error_data()
    {
        return $this->data;
    }
}

/**
 * Interogarea principala, cat sa mearga hook-urile de filtrare.
 */
class WP_Query
{
    public $vars = array();

    public function get($key, $default = '')
    {
        return isset($this->vars[$key]) ? $this->vars[$key] : $default;
    }

    public function set($key, $value)
    {
        $this->vars[$key] = $value;
    }

    public function is_main_query()
    {
        return true;
    }
}

/**
 * $wpdb, doar cat sa se poata construi conditiile SQL.
 */
class HT_Test_Wpdb
{
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $term_relationships = 'wp_term_relationships';
    public $term_taxonomy = 'wp_term_taxonomy';

    public function prepare($query)
    {
        $args = array_slice(func_get_args(), 1);
        $query = str_replace(array('%s', '%f', '%d'), array("'%s'", '%F', '%d'), $query);

        return vsprintf($query, $args);
    }

    public function get_var($query)
    {
        return null;
    }

    public function get_results($query)
    {
        return array();
    }
}

$GLOBALS['wpdb'] = new HT_Test_Wpdb();

/* ---- hook-uri ---------------------------------------------------------- */

function add_filter($tag, $callback, $priority = 10, $accepted_args = 1)
{
    HT_Test_Wp::$hooks[$tag][$priority][] = $callback;

    return true;
}

function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
{
    return add_filter($tag, $callback, $priority, $accepted_args);
}

function remove_filter($tag, $callback, $priority = 10)
{
    if (isset(HT_Test_Wp::$hooks[$tag][$priority])) {
        HT_Test_Wp::$hooks[$tag][$priority] = array_values(array_filter(
            HT_Test_Wp::$hooks[$tag][$priority],
            function ($registered) use ($callback) {
                return $registered !== $callback;
            }
        ));
    }

    return true;
}

function remove_action($tag, $callback, $priority = 10)
{
    return remove_filter($tag, $callback, $priority);
}

function apply_filters($tag, $value)
{
    if (empty(HT_Test_Wp::$hooks[$tag])) {
        return $value;
    }

    $args = array_slice(func_get_args(), 2);
    $levels = HT_Test_Wp::$hooks[$tag];
    ksort($levels);

    foreach ($levels as $callbacks) {
        foreach ($callbacks as $callback) {
            $value = call_user_func_array($callback, array_merge(array($value), $args));
        }
    }

    return $value;
}

/* ---- siruri ------------------------------------------------------------ */

function __($text, $domain = 'default')
{
    return $text;
}

function esc_html($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text)
{
    return esc_html($text);
}

function esc_url($url)
{
    return htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8');
}

function esc_html__($text, $domain = 'default')
{
    return esc_html($text);
}

function esc_html_e($text, $domain = 'default')
{
    echo esc_html($text);
}

function esc_attr_e($text, $domain = 'default')
{
    echo esc_attr($text);
}

function checked($checked, $current = true, $echo = true)
{
    $out = ((string)$checked === (string)$current) ? " checked='checked'" : '';

    if ($echo) {
        echo $out;
    }

    return $out;
}

function number_format_i18n($number, $decimals = 0)
{
    return number_format((float)$number, $decimals, '.', ',');
}

function wp_unslash($value)
{
    return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string)$value);
}

function sanitize_title($title)
{
    $title = strtolower(trim((string)$title));
    $title = preg_replace('/[^a-z0-9_\-]+/', '-', $title);

    return trim($title, '-');
}

/* ---- adrese ------------------------------------------------------------ */

function home_url($path = '')
{
    return 'https://example.test' . $path;
}

function add_query_arg($args, $url)
{
    $parts = parse_url($url);
    $query = array();

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    foreach ($args as $key => $value) {
        if (false === $value) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $base = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['path']) ? $parts['path'] : '');

    return $query ? $base . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $base;
}

/* ---- context ----------------------------------------------------------- */

function is_shop()
{
    return null === HT_Test_Wp::$queried;
}

function is_product_taxonomy()
{
    return null !== HT_Test_Wp::$queried;
}

function is_search()
{
    return HT_Test_Wp::$is_search;
}

function is_admin()
{
    return false;
}

function get_queried_object()
{
    return HT_Test_Wp::$queried;
}

function wc_get_page_id($page)
{
    return 7;
}

function get_permalink($id)
{
    return HT_Test_Wp::$shop_url;
}

function get_term_link($term)
{
    return 'https://example.test/' . $term->slug . '/';
}

function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

/* ---- termeni ----------------------------------------------------------- */

function get_term_by($field, $value, $taxonomy)
{
    foreach (HT_Test_Wp::$terms as $term) {
        if ($term->taxonomy === $taxonomy && (string)$term->$field === (string)$value) {
            return $term;
        }
    }

    return false;
}

function get_terms($args)
{
    $out = array();

    foreach (HT_Test_Wp::$terms as $term) {
        if ($term->taxonomy !== $args['taxonomy']) {
            continue;
        }

        if (!empty($args['include']) && !in_array($term->term_id, array_map('intval', $args['include']), true)) {
            continue;
        }

        $out[] = $term;
    }

    usort($out, function ($a, $b) {
        return strcmp($a->name, $b->name);
    });

    return $out;
}

function is_taxonomy_hierarchical($taxonomy)
{
    return 'product_cat' === $taxonomy;
}

function get_term_children($term_id, $taxonomy)
{
    $out = array();

    foreach (HT_Test_Wp::$terms as $term) {
        if ($term->taxonomy === $taxonomy && $term->parent === (int)$term_id) {
            $out[] = $term->term_id;
            $out = array_merge($out, get_term_children($term->term_id, $taxonomy));
        }
    }

    return $out;
}

function wc_get_product_visibility_term_ids()
{
    return array('exclude-from-catalog' => 0);
}
