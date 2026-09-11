<?php
/**
 * Filtrele paginii de catalog.
 *
 * Bara laterala din stanga listarii (tip de produs, pret, disponibilitate),
 * etichetele active de deasupra grilei si conditiile pe care le adauga in
 * interogarea principala.
 *
 * Parametrii din adresa:
 *   cat       - slug-uri de categorii, separate prin virgula
 *   price     - intervale predefinite de pret ('0-200', '200-500', ...)
 *   stock     - 'instock', 'outofstock', 'new'
 *   min_price / max_price - intervalul liber de pret; le trateaza WooCommerce
 *
 * Contoarele de langa optiuni se calculeaza in inc/shop-facets.php, pe
 * listarea curenta si tinand cont de celelalte filtre bifate.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Context
 * ------------------------------------------------------------------------ */

/**
 * Pagina curenta e o listare de produse (magazin, categorie sau eticheta)?
 *
 * @return bool
 */
function ht_is_shop_archive()
{
    if (!function_exists('is_shop')) {
        return false;
    }

    return (is_shop() || is_product_taxonomy()) && !is_search();
}

/**
 * Grupurile de filtre, in ordinea din design.
 *
 * @return array
 */
function ht_shop_filter_keys()
{
    return array('cat', 'price', 'stock');
}

/**
 * Memoreaza un calcul pe durata cererii.
 *
 * Toate valorile pe care le citim o singura data pe cerere (parametrii din
 * adresa, randurile de produse, contoarele) trec pe aici, ca sa poata fi
 * golite dintr-un loc - vezi ht_shop_memo_flush(), pe care se sprijina testele.
 *
 * @param string   $key     Cheia calculului.
 * @param callable $compute Calculul propriu-zis; se ruleaza o singura data.
 *
 * @return mixed
 */
function ht_shop_memo($key, callable $compute)
{
    if (!isset($GLOBALS['ht_shop_memo']) || !is_array($GLOBALS['ht_shop_memo'])) {
        $GLOBALS['ht_shop_memo'] = array();
    }

    if (!array_key_exists($key, $GLOBALS['ht_shop_memo'])) {
        $GLOBALS['ht_shop_memo'][$key] = $compute();
    }

    return $GLOBALS['ht_shop_memo'][$key];
}

/**
 * Uita tot ce a memorat ht_shop_memo().
 */
function ht_shop_memo_flush()
{
    $GLOBALS['ht_shop_memo'] = array();
}

/* ---------------------------------------------------------------------------
 * Citirea parametrilor
 * ------------------------------------------------------------------------ */

/**
 * Valorile bifate pentru un grup.
 *
 * Accepta si forma 'a,b' (link-urile temei), si forma 'cat[]=a&cat[]=b'
 * pe care o trimite formularul din bara laterala.
 *
 * @param string $key Numele parametrului.
 *
 * @return array Slug-uri unice.
 */
function ht_shop_filter_values($key)
{
    return ht_shop_memo('values:' . $key, function () use ($key) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtru public, fara efecte.
        $raw = isset($_GET[$key]) ? wp_unslash($_GET[$key]) : '';
        $list = is_array($raw) ? $raw : ('' === $raw ? array() : explode(',', (string)$raw));

        $list = array_map('sanitize_title', array_map('strval', $list));

        return array_values(array_unique(array_filter($list)));
    });
}

/**
 * O valoare e bifata?
 *
 * @param string $key   Numele parametrului.
 * @param string $value Valoarea cautata.
 *
 * @return bool
 */
function ht_shop_filter_checked($key, $value)
{
    return in_array((string)$value, ht_shop_filter_values($key), true);
}

/**
 * Capatul liber de pret din adresa.
 *
 * @param string $key 'min_price' sau 'max_price'.
 *
 * @return float|null Null cand parametrul lipseste.
 */
function ht_shop_price_input($key)
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtru public, fara efecte.
    if (!isset($_GET[$key])) {
        return null;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $raw = preg_replace('/[^\d.]/', '', (string)wp_unslash($_GET[$key]));

    return ('' === $raw) ? null : (float)$raw;
}

/* ---------------------------------------------------------------------------
 * Optiunile afisate
 * ------------------------------------------------------------------------ */

/**
 * Categoriile de produse din bara laterala.
 *
 * Pe magazin: toate categoriile care au produse vizibile. Pe o pagina de
 * categorie: doar subcategoriile ei (cele care au produse); categoria curenta
 * nu se listeaza pe ea insasi, iar categoriile straine nu au ce cauta - ar
 * intoarce mereu zero rezultate.
 *
 * @return WP_Term[]
 */
function ht_shop_filter_categories()
{
    return ht_shop_memo('categories', 'ht_shop_filter_categories_compute');
}

/**
 * Calculul din spatele ht_shop_filter_categories().
 *
 * @return WP_Term[]
 */
function ht_shop_filter_categories_compute()
{
    $rows = ht_shop_facet_rows();
    $ids = array();

    foreach ($rows as $row) {
        foreach ($row['cats'] as $term_id) {
            $ids[(int)$term_id] = true;
        }
    }

    $scope = ht_shop_scope_term();

    if ($scope && 'product_cat' === $scope->taxonomy) {
        unset($ids[(int)$scope->term_id]);
    }

    if (!$ids) {
        return array();
    }

    $found = get_terms(array(
        'taxonomy'   => 'product_cat',
        'include'    => array_keys($ids),
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));

    return apply_filters('ht_shop_filter_categories', is_wp_error($found) ? array() : $found);
}

/**
 * Intervalele predefinite de pret.
 *
 * 'max' null inseamna "fara plafon".
 *
 * @return array
 */
function ht_shop_price_buckets()
{
    return apply_filters('ht_shop_price_buckets', array(
        array('key' => '0-200', 'label' => __('Sub 200', 'herbal-therapy'), 'min' => 0, 'max' => 200),
        array('key' => '200-500', 'label' => __('200 - 500', 'herbal-therapy'), 'min' => 200, 'max' => 500),
        array('key' => '500-1000', 'label' => __('500 - 1 000', 'herbal-therapy'), 'min' => 500, 'max' => 1000),
        array('key' => '1000-2000', 'label' => __('1 000 - 2 000', 'herbal-therapy'), 'min' => 1000, 'max' => 2000),
    ));
}

/**
 * Optiunile de disponibilitate.
 *
 * @return array
 */
function ht_shop_stock_options()
{
    return apply_filters('ht_shop_stock_options', array(
        array('key' => 'instock', 'label' => __('În Stoc', 'herbal-therapy')),
        array('key' => 'outofstock', 'label' => __('Stoc epuizat', 'herbal-therapy')),
        array('key' => 'new', 'label' => __('Nou', 'herbal-therapy')),
    ));
}

/**
 * De cate zile un produs mai e considerat noutate.
 *
 * Aceeasi valoare ca eticheta "Nou" de pe card (inc/products.php).
 *
 * @return int
 */
function ht_shop_new_days()
{
    return (int)apply_filters('ht_product_new_days', 30);
}

/* ---------------------------------------------------------------------------
 * Numerele din dreapta optiunilor
 * ------------------------------------------------------------------------ */

/**
 * Produsele ascunse din catalog.
 *
 * @return int[]
 */
function ht_shop_hidden_product_ids()
{
    if (!function_exists('wc_get_product_visibility_term_ids')) {
        return array();
    }

    $terms = wc_get_product_visibility_term_ids();

    if (empty($terms['exclude-from-catalog'])) {
        return array();
    }

    $ids = get_objects_in_term((int)$terms['exclude-from-catalog'], 'product_visibility');

    return is_wp_error($ids) ? array() : array_map('absint', $ids);
}

/**
 * Termenul care restrange listarea curenta (categoria sau eticheta vizitata).
 *
 * @return WP_Term|null Null pe pagina de magazin.
 */
function ht_shop_scope_term()
{
    if (!function_exists('is_product_taxonomy') || !is_product_taxonomy()) {
        return null;
    }

    $term = get_queried_object();

    return ($term instanceof WP_Term) ? $term : null;
}

/**
 * ID-urile termenului si ale tuturor urmasilor lui.
 *
 * @param WP_Term $term Termenul de pornire.
 *
 * @return int[]
 */
function ht_shop_term_family($term)
{
    $ids = array((int)$term->term_id);

    if (is_taxonomy_hierarchical($term->taxonomy)) {
        $children = get_term_children((int)$term->term_id, $term->taxonomy);

        if (!is_wp_error($children)) {
            $ids = array_merge($ids, array_map('intval', $children));
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Conditiile de apartenenta care definesc listarea curenta.
 *
 * Fiecare element e o lista de ID-uri de termeni; produsul trebuie sa fie in
 * macar unul din fiecare lista. Doua surse: termenul vizitat (cu urmasii lui)
 * si, cu Polylang, limba curenta - interogarea principala e restransa la ea,
 * deci si contoarele trebuie sa fie.
 *
 * @return array Liste de ID-uri de termeni.
 */
function ht_shop_facet_restrictions()
{
    $restrictions = array();

    $scope = ht_shop_scope_term();

    if ($scope) {
        $restrictions[] = ht_shop_term_family($scope);
    }

    if (function_exists('pll_current_language')) {
        $language = pll_current_language('slug');
        $term = $language ? get_term_by('slug', $language, 'language') : false;

        if ($term instanceof WP_Term) {
            $restrictions[] = array((int)$term->term_id);
        }
    }

    return apply_filters('ht_shop_facet_restrictions', $restrictions);
}

/**
 * Randurile de produse din listarea curenta, fara filtrele bifate.
 *
 * O interogare pe tabelul de cautare al WooCommerce (pret, stoc) si una pe
 * legaturile cu categoriile; numaratoarea se face apoi in PHP
 * (inc/shop-facets.php), ca sa nu avem cate o interogare per optiune.
 *
 * Pe o pagina de categorie sau eticheta raman doar produsele din termenul
 * respectiv (cu tot cu subcategorii); produsele ascunse din catalog lipsesc
 * peste tot.
 *
 * @return array Randuri in forma descrisa in inc/shop-facets.php, indexate dupa ID.
 */
function ht_shop_facet_rows()
{
    return ht_shop_memo('rows', 'ht_shop_facet_rows_compute');
}

/**
 * Calculul din spatele ht_shop_facet_rows().
 *
 * Filtrul 'ht_shop_pre_facet_rows' poate intoarce randurile gata facute (un
 * array) si atunci baza de date nu se mai atinge - asa le injecteaza testele.
 *
 * @return array
 */
function ht_shop_facet_rows_compute()
{
    $pre = apply_filters('ht_shop_pre_facet_rows', null);

    if (is_array($pre)) {
        return $pre;
    }

    global $wpdb;

    $rows = array();
    $lookup = $wpdb->prefix . 'wc_product_meta_lookup';

    /* tabelul lipseste pe instalarile foarte vechi de WooCommerce */
    if ($lookup !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup))) {
        return $rows;
    }

    $where = '';
    $hidden = ht_shop_hidden_product_ids();

    if ($hidden) {
        $where .= ' AND p.ID NOT IN (' . implode(',', array_map('absint', $hidden)) . ')';
    }

    foreach (ht_shop_facet_restrictions() as $term_ids) {
        $term_ids = array_map('absint', $term_ids);
        $where .= " AND p.ID IN (SELECT tr.object_id FROM {$wpdb->term_relationships} AS tr"
            . " INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
            . ' WHERE tt.term_id IN (' . implode(',', $term_ids) . '))';
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- numele tabelului si listele de ID-uri sunt construite intern.
    $found = $wpdb->get_results(
        "SELECT p.ID, l.min_price, l.max_price, l.stock_status, p.post_date_gmt
         FROM {$lookup} AS l
         INNER JOIN {$wpdb->posts} AS p ON p.ID = l.product_id
         WHERE p.post_type = 'product' AND p.post_status = 'publish'{$where}"
    );

    if (!$found) {
        return $rows;
    }

    foreach ($found as $row) {
        $rows[(int)$row->ID] = array(
            'id'    => (int)$row->ID,
            'min'   => (float)$row->min_price,
            'max'   => (float)$row->max_price,
            'stock' => (string)$row->stock_status,
            'date'  => (string)$row->post_date_gmt,
            'cats'  => array(),
        );
    }

    $ids = implode(',', array_map('absint', array_keys($rows)));

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- lista de ID-uri e construita intern.
    $links = $wpdb->get_results(
        "SELECT tr.object_id, tt.term_id
         FROM {$wpdb->term_relationships} AS tr
         INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         WHERE tt.taxonomy = 'product_cat' AND tr.object_id IN ({$ids})"
    );

    foreach ((array)$links as $link) {
        $rows[(int)$link->object_id]['cats'][] = (int)$link->term_id;
    }

    return $rows;
}

/**
 * Filtrele active, in forma pe care o asteapta ht_shop_facet_counts().
 *
 * Slug-urile de categorie se traduc in ID-uri; un slug necunoscut (categorie
 * din alta listare, greseala de tastare) nu bifeaza nimic, la fel ca in
 * interogare.
 *
 * @return array
 */
function ht_shop_facet_selection_from_request()
{
    $cats = array();

    foreach (ht_shop_filter_values('cat') as $slug) {
        $term = get_term_by('slug', $slug, 'product_cat');

        if ($term instanceof WP_Term) {
            $cats[] = (int)$term->term_id;
        }
    }

    return ht_shop_facet_selection(array(
        'cat'       => $cats,
        'price'     => ht_shop_filter_values('price'),
        'min_price' => ht_shop_price_input('min_price'),
        'max_price' => ht_shop_price_input('max_price'),
        'stock'     => ht_shop_filter_values('stock'),
    ));
}

/**
 * Contoarele pentru categorii, pret si disponibilitate, plus plafonul glisorului.
 *
 * Numerele tin cont de listarea curenta (categoria vizitata) si de celelalte
 * grupuri de filtre bifate - vezi inc/shop-facets.php pentru regula.
 *
 * @return array {
 *     @type array $cat   Contor per ID de categorie.
 *     @type array $price Contor per cheie de interval.
 *     @type array $stock Contor per cheie de disponibilitate.
 *     @type float $max   Cel mai mare pret din listare.
 *     @type int   $total Produse ramase cu toate filtrele.
 * }
 */
function ht_shop_facets()
{
    return ht_shop_memo('facets', function () {
        return ht_shop_facet_counts(
            ht_shop_facet_rows(),
            ht_shop_facet_selection_from_request(),
            array(
                'buckets' => ht_shop_price_buckets(),
                'stock'   => ht_shop_stock_options(),
                'fresh'   => ht_shop_new_since(),
            )
        );
    });
}

/**
 * Data GMT de la care incoace un produs e considerat noutate.
 *
 * @return string 'Y-m-d H:i:s'
 */
function ht_shop_new_since()
{
    return gmdate('Y-m-d H:i:s', time() - (ht_shop_new_days() * DAY_IN_SECONDS));
}

/**
 * Contoarele, indexate dupa numele parametrului si valoarea optiunii -
 * forma pe care o primeste scriptul dupa o cerere AJAX.
 *
 * @return array cat => [slug => n], price => [cheie => n], stock => [cheie => n]
 */
function ht_shop_facet_payload()
{
    $facets = ht_shop_facets();
    $payload = array('cat' => array(), 'price' => array(), 'stock' => array());

    foreach (ht_shop_filter_categories() as $term) {
        $id = (int)$term->term_id;
        $payload['cat'][$term->slug] = isset($facets['cat'][$id]) ? (int)$facets['cat'][$id] : 0;
    }

    foreach (ht_shop_price_buckets() as $bucket) {
        $key = $bucket['key'];
        $payload['price'][$key] = isset($facets['price'][$key]) ? (int)$facets['price'][$key] : 0;
    }

    foreach (ht_shop_stock_options() as $option) {
        $key = $option['key'];
        $payload['stock'][$key] = isset($facets['stock'][$key]) ? (int)$facets['stock'][$key] : 0;
    }

    return $payload;
}

/**
 * Capetele glisorului de pret.
 *
 * @return array {
 *     @type int $min Capatul de jos.
 *     @type int $max Capatul de sus, rotunjit in sus la suta.
 * }
 */
function ht_shop_price_range()
{
    $facets = ht_shop_facets();
    $max = (float)$facets['max'];
    $max = $max > 0 ? (int)(ceil($max / 100) * 100) : 2000;

    return apply_filters('ht_shop_price_range', array('min' => 1, 'max' => $max));
}

/* ---------------------------------------------------------------------------
 * Adresele
 * ------------------------------------------------------------------------ */

/**
 * Adresa listarii curente, fara paginare si fara filtre.
 *
 * @return string
 */
function ht_shop_filter_base()
{
    $link = '';

    if (is_product_taxonomy()) {
        $term = get_queried_object();
        $link = ($term && !is_wp_error($term)) ? get_term_link($term) : '';
    } elseif (function_exists('wc_get_page_id')) {
        $shop = (int)wc_get_page_id('shop');
        $link = $shop > 0 ? get_permalink($shop) : '';
    }

    if (!$link || is_wp_error($link)) {
        $link = home_url('/');
    }

    return $link;
}

/**
 * Filtrele active, in forma de parametri de adresa.
 *
 * @return array
 */
function ht_shop_filter_query_args()
{
    $args = array();

    foreach (ht_shop_filter_keys() as $key) {
        $values = ht_shop_filter_values($key);

        if ($values) {
            $args[$key] = implode(',', $values);
        }
    }

    foreach (array('min_price', 'max_price') as $key) {
        $value = ht_shop_price_input($key);

        if (null !== $value) {
            /* 10.0 -> '10', 99.5 -> '99.5' */
            $args[$key] = (string)(floor($value) == $value ? (int)$value : $value);
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $orderby = isset($_GET['orderby']) ? sanitize_title(wp_unslash($_GET['orderby'])) : '';

    if ('' !== $orderby) {
        $args['orderby'] = $orderby;
    }

    return $args;
}

/**
 * Adresa listarii cu o valoare adaugata sau scoasa dintr-un grup.
 *
 * @param string $key   Numele parametrului.
 * @param string $value Valoarea comutata.
 *
 * @return string
 */
function ht_shop_toggle_url($key, $value)
{
    $args = ht_shop_filter_query_args();
    $values = ht_shop_filter_values($key);
    $value = (string)$value;

    if (in_array($value, $values, true)) {
        $values = array_values(array_diff($values, array($value)));
    } else {
        $values[] = $value;
    }

    if ($values) {
        $args[$key] = implode(',', $values);
    } else {
        unset($args[$key]);
    }

    return $args ? add_query_arg($args, ht_shop_filter_base()) : ht_shop_filter_base();
}

/**
 * Adresa listarii fara un grup de filtre; fara argument, fara niciun filtru.
 *
 * @param string $key Numele parametrului golit.
 *
 * @return string
 */
function ht_shop_clear_url($key = '')
{
    $args = ht_shop_filter_query_args();

    if ('' === $key) {
        $args = isset($args['orderby']) ? array('orderby' => $args['orderby']) : array();
    } else {
        unset($args[$key]);

        if ('price' === $key) {
            unset($args['min_price'], $args['max_price']);
        }
    }

    return $args ? add_query_arg($args, ht_shop_filter_base()) : ht_shop_filter_base();
}

/**
 * Exista vreun filtru activ?
 *
 * @return bool
 */
function ht_shop_has_filters()
{
    foreach (ht_shop_filter_keys() as $key) {
        if (ht_shop_filter_values($key)) {
            return true;
        }
    }

    return null !== ht_shop_price_input('min_price') || null !== ht_shop_price_input('max_price');
}

/**
 * Filtrele active, gata de afisat ca etichete deasupra grilei.
 *
 * @return array Lista de array-uri cu cheile 'label' si 'url' (adresa fara eticheta).
 */
function ht_shop_active_filters()
{
    $chips = array();

    foreach (ht_shop_filter_values('cat') as $slug) {
        $term = get_term_by('slug', $slug, 'product_cat');

        if ($term instanceof WP_Term) {
            $chips[] = array('label' => $term->name, 'url' => ht_shop_toggle_url('cat', $slug));
        }
    }

    foreach (ht_shop_price_buckets() as $bucket) {
        if (ht_shop_filter_checked('price', $bucket['key'])) {
            $chips[] = array('label' => $bucket['label'], 'url' => ht_shop_toggle_url('price', $bucket['key']));
        }
    }

    $min = ht_shop_price_input('min_price');
    $max = ht_shop_price_input('max_price');

    if (null !== $min || null !== $max) {
        $range = ht_shop_price_range();
        $args = ht_shop_filter_query_args();
        unset($args['min_price'], $args['max_price']);

        $chips[] = array(
            'label' => sprintf(
                '%s - %s',
                number_format_i18n(null === $min ? $range['min'] : $min),
                number_format_i18n(null === $max ? $range['max'] : $max)
            ),
            'url'   => $args ? add_query_arg($args, ht_shop_filter_base()) : ht_shop_filter_base(),
        );
    }

    foreach (ht_shop_stock_options() as $option) {
        if (ht_shop_filter_checked('stock', $option['key'])) {
            $chips[] = array('label' => $option['label'], 'url' => ht_shop_toggle_url('stock', $option['key']));
        }
    }

    return $chips;
}

/* ---------------------------------------------------------------------------
 * Interogarea
 * ------------------------------------------------------------------------ */

/**
 * Restrange listarea la categoriile bifate.
 *
 * @param WP_Query $query Interogarea de produse.
 */
function ht_shop_filter_categories_query($query)
{
    $slugs = ht_shop_filter_values('cat');

    if (!$slugs) {
        return;
    }

    $tax_query = (array)$query->get('tax_query');

    $tax_query[] = array(
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $slugs,
        'operator' => 'IN',
    );

    $query->set('tax_query', $tax_query);
}

add_action('woocommerce_product_query', 'ht_shop_filter_categories_query', 10, 1);

/**
 * Conditiile de pret si de disponibilitate.
 *
 * Merg pe tabelul de cautare al WooCommerce, cu alias propriu ('ht_pf'), si se
 * adauga pe prioritatea 20 - dupa ce pluginul si-a pus jonctiunea proprie pe
 * prioritatea 10, ca sa nu se incurce una cu alta.
 *
 * Intervalul liber (min_price / max_price) ramane in seama WooCommerce.
 *
 * @param array    $clauses Bucatile interogarii SQL.
 * @param WP_Query $query   Interogarea curenta.
 *
 * @return array
 */
function ht_shop_filter_clauses($clauses, $query)
{
    if (is_admin() || !$query->is_main_query() || !ht_is_shop_archive()) {
        return $clauses;
    }

    global $wpdb;

    $conditions = array();

    /* pret: reuniunea intervalelor bifate */
    $ranges = array();

    foreach (ht_shop_price_buckets() as $bucket) {
        if (!ht_shop_filter_checked('price', $bucket['key'])) {
            continue;
        }

        $top = null === $bucket['max'] ? PHP_INT_MAX : (float)$bucket['max'];
        $ranges[] = $wpdb->prepare('(ht_pf.max_price >= %f AND ht_pf.min_price <= %f)', (float)$bucket['min'], $top);
    }

    if ($ranges) {
        $conditions[] = '(' . implode(' OR ', $ranges) . ')';
    }

    /* disponibilitate: reuniunea optiunilor bifate */
    $stock = ht_shop_filter_values('stock');
    $states = array();

    if (in_array('instock', $stock, true)) {
        $states[] = "ht_pf.stock_status = 'instock'";
    }

    if (in_array('outofstock', $stock, true)) {
        $states[] = "ht_pf.stock_status IN ('outofstock', 'onbackorder')";
    }

    if (in_array('new', $stock, true)) {
        $states[] = $wpdb->prepare("{$wpdb->posts}.post_date_gmt >= %s", ht_shop_new_since());
    }

    if ($states) {
        $conditions[] = '(' . implode(' OR ', $states) . ')';
    }

    if (!$conditions) {
        return $clauses;
    }

    if (false === strpos($clauses['join'], 'ht_pf')) {
        $clauses['join'] .= " INNER JOIN {$wpdb->prefix}wc_product_meta_lookup AS ht_pf"
            . " ON {$wpdb->posts}.ID = ht_pf.product_id ";
    }

    $clauses['where'] .= ' AND ' . implode(' AND ', $conditions);

    return $clauses;
}

add_filter('posts_clauses', 'ht_shop_filter_clauses', 20, 2);

/* ---------------------------------------------------------------------------
 * Markup
 * ------------------------------------------------------------------------ */

/**
 * O optiune bifabila din bara laterala.
 *
 * @param string $key   Numele parametrului.
 * @param string $value Valoarea optiunii.
 * @param string $label Textul din stanga.
 * @param int    $count Numarul din dreapta.
 */
function ht_shop_filter_option($key, $value, $label, $count = 0)
{
    $checked = ht_shop_filter_checked($key, $value);
    ?>
    <li class="ht-filters__item">
        <label class="ht-check">
            <input class="ht-check__input"
                   type="checkbox"
                   name="<?php echo esc_attr($key); ?>[]"
                   value="<?php echo esc_attr($value); ?>"
                <?php checked($checked); ?>>
            <span class="ht-check__box" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 7.667 7.333 10 12 5" stroke="currentColor" stroke-width="1.5"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="ht-check__text">
                <span class="ht-check__label"><?php echo esc_html($label); ?></span>
                <span class="ht-check__count"><?php echo esc_html(number_format_i18n($count)); ?></span>
            </span>
        </label>
    </li>
    <?php
}

/**
 * Capul unui grup de filtre - titlul si legatura de golire.
 *
 * @param string $title Titlul grupului.
 * @param string $key   Parametrul golit de legatura; '' goleste tot.
 * @param bool   $icon  Cu iconita de cos de gunoi in fata textului.
 */
function ht_shop_filter_head($title, $key = '', $icon = false)
{
    /* legatura sta pe pozitie si cand nu e nimic bifat, ca in design;
       atunci doar reincarca listarea */
    ?>
    <div class="ht-filters__head">
        <h2 class="ht-filters__title"><?php echo esc_html($title); ?></h2>
        <a class="ht-filters__reset" href="<?php echo esc_url(ht_shop_clear_url($key)); ?>"
           data-ht-filters-reset="<?php echo esc_attr($key); ?>">
            <?php if ($icon) : ?>
                <span class="ht-filters__reset-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.333 6 12.003 13.564A1.333 1.333 0 0 1 10.69 14.667H5.31a1.333 1.333 0 0 1-1.313-1.103L2.667 6M14 4h-3.75m0 0V2.667A1.333 1.333 0 0 0 8.917 1.333H7.083A1.333 1.333 0 0 0 5.75 2.667V4m4.5 0h-4.5M2 4h3.75"
                              stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            <?php endif; ?>
            <?php echo $icon ? esc_html__('Șterge tot', 'herbal-therapy') : esc_html__('Resetare', 'herbal-therapy'); ?>
        </a>
    </div>
    <?php
}

/**
 * Bara laterala cu filtre.
 *
 * Formularul merge prin GET si se trimite singur la fiecare bifa
 * (assets/js/shop-filters.js); fara JavaScript ramane butonul de trimitere,
 * ascuns vizual dar accesibil de la tastatura.
 */
function ht_shop_filters_markup()
{
    $categories = ht_shop_filter_categories();
    $buckets = ht_shop_price_buckets();
    $facets = ht_shop_facets();
    $range = ht_shop_price_range();

    $min = ht_shop_price_input('min_price');
    $max = ht_shop_price_input('max_price');
    $min = null === $min ? $range['min'] : $min;
    $max = null === $max ? $range['max'] : $max;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $orderby = isset($_GET['orderby']) ? sanitize_title(wp_unslash($_GET['orderby'])) : '';
    ?>
    <aside class="ht-shop__filters" id="htShopFilters">
        <button class="ht-filters__toggle" type="button" data-ht-filters-toggle
                aria-expanded="false" aria-controls="htFiltersForm">
            <span class="ht-filters__toggle-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.5 5h15M5 10h10m-7.5 5h5" stroke="currentColor" stroke-width="1.5"
                          stroke-linecap="round"/>
                </svg>
            </span>
            <?php esc_html_e('Filtre', 'herbal-therapy'); ?>
        </button>

        <form class="ht-filters" id="htFiltersForm" method="get"
              action="<?php echo esc_url(ht_shop_filter_base()); ?>" data-ht-filters>
            <?php if ('' !== $orderby) : ?>
                <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
            <?php endif; ?>

            <?php if ($categories) : ?>
            <div class="ht-filters__group">
                <?php ht_shop_filter_head(__('Tip produs', 'herbal-therapy'), '', true); ?>

                <label class="ht-filters__search">
                    <span class="ht-visually-hidden"><?php esc_html_e('Căutare după categorii', 'herbal-therapy'); ?></span>
                    <input class="ht-filters__search-input"
                           type="search"
                           autocomplete="off"
                           placeholder="<?php esc_attr_e('Căutare după categorii', 'herbal-therapy'); ?>"
                           data-ht-filters-search>
                </label>

                <ul class="ht-filters__list" data-ht-filters-searchable>
                    <?php foreach ($categories as $term) : ?>
                        <?php
                        $count = isset($facets['cat'][(int)$term->term_id]) ? (int)$facets['cat'][(int)$term->term_id] : 0;
                        ht_shop_filter_option('cat', $term->slug, $term->name, $count);
                        ?>
                    <?php endforeach; ?>
                </ul>

                <p class="ht-filters__empty" data-ht-filters-empty hidden>
                    <?php esc_html_e('Nicio categorie găsită.', 'herbal-therapy'); ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="ht-filters__group">
                <?php ht_shop_filter_head(__('Preț', 'herbal-therapy'), 'price'); ?>

                <div class="ht-range__box">
                <div class="ht-range"
                     data-ht-range
                     data-floor="<?php echo esc_attr($range['min']); ?>"
                     data-ceil="<?php echo esc_attr($range['max']); ?>">
                    <div class="ht-range__rail"><span class="ht-range__fill" data-ht-range-fill></span></div>
                    <input class="ht-range__handle ht-range__handle--min"
                           type="range"
                           min="<?php echo esc_attr($range['min']); ?>"
                           max="<?php echo esc_attr($range['max']); ?>"
                           value="<?php echo esc_attr($min); ?>"
                           aria-label="<?php esc_attr_e('Preț minim', 'herbal-therapy'); ?>"
                           data-ht-range-min>
                    <input class="ht-range__handle ht-range__handle--max"
                           type="range"
                           min="<?php echo esc_attr($range['min']); ?>"
                           max="<?php echo esc_attr($range['max']); ?>"
                           value="<?php echo esc_attr($max); ?>"
                           aria-label="<?php esc_attr_e('Preț maxim', 'herbal-therapy'); ?>"
                           data-ht-range-max>
                </div>

                <div class="ht-range__fields">
                    <label class="ht-range__field">
                        <span class="ht-visually-hidden"><?php esc_html_e('Preț minim', 'herbal-therapy'); ?></span>
                        <input type="text" inputmode="numeric" name="min_price"
                               value="<?php echo esc_attr(number_format_i18n($min)); ?>" data-ht-range-input="min">
                    </label>
                    <span class="ht-range__sep" aria-hidden="true">-</span>
                    <label class="ht-range__field">
                        <span class="ht-visually-hidden"><?php esc_html_e('Preț maxim', 'herbal-therapy'); ?></span>
                        <input type="text" inputmode="numeric" name="max_price"
                               value="<?php echo esc_attr(number_format_i18n($max)); ?>" data-ht-range-input="max">
                    </label>
                </div>
                </div>

                <ul class="ht-filters__list">
                    <?php foreach ($buckets as $bucket) : ?>
                        <?php
                        $count = isset($facets['price'][$bucket['key']]) ? (int)$facets['price'][$bucket['key']] : 0;
                        ht_shop_filter_option('price', $bucket['key'], $bucket['label'], $count);
                        ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="ht-filters__group">
                <?php ht_shop_filter_head(__('Disponibilitate', 'herbal-therapy'), 'stock'); ?>

                <ul class="ht-filters__list">
                    <?php foreach (ht_shop_stock_options() as $option) : ?>
                        <?php
                        $count = isset($facets['stock'][$option['key']]) ? (int)$facets['stock'][$option['key']] : 0;
                        ht_shop_filter_option('stock', $option['key'], $option['label'], $count);
                        ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <button class="ht-filters__submit ht-visually-hidden" type="submit">
                <?php esc_html_e('Aplică filtrele', 'herbal-therapy'); ?>
            </button>
        </form>
    </aside>
    <?php
}

/**
 * Etichetele filtrelor active, in stanga barei de deasupra grilei.
 */
function ht_shop_chips_markup()
{
    $chips = ht_shop_active_filters();
    ?>
    <div class="ht-shop__chips">
        <?php foreach ($chips as $chip) : ?>
            <a class="ht-chip" href="<?php echo esc_url($chip['url']); ?>">
                <span class="ht-chip__label"><?php echo esc_html($chip['label']); ?></span>
                <span class="ht-chip__close" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="12" fill="currentColor" fill-opacity=".4"/>
                        <path d="m12 13.065-3.726 3.726a.74.74 0 0 1-1.063 0 .74.74 0 0 1 0-1.065L10.935 12 7.211 8.274a.74.74 0 0 1 0-1.063.74.74 0 0 1 1.063 0L12 10.935l3.726-3.724a.74.74 0 0 1 1.065 0 .74.74 0 0 1 0 1.063L13.065 12l3.726 3.726a.74.74 0 0 1 0 1.065.74.74 0 0 1-1.065 0L12 13.065Z"
                              fill="currentColor"/>
                    </svg>
                </span>
                <span class="ht-visually-hidden"><?php esc_html_e('Șterge filtrul', 'herbal-therapy'); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
