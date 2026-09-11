<?php
/**
 * Sectiunea "Top vânzări" - carusel de produse cu taburi de categorii
 * (frame-ul "Acasa", nodul 33:4611 din Figma).
 *
 * Refoloseste cardul si caruselul de produse (inc/products.php, ht-products);
 * aici sunt doar taburile, alegerea celor mai vandute produse si ruta REST
 * prin care un tab isi aduce produsele fara reincarcarea paginii.
 *
 * Se randeaza cu ht_top_sales_section() dintr-un template sau cu shortcode-ul
 * [ht_top_vanzari] din continutul unei pagini.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Categoriile aratate ca taburi.
 *
 * Implicit: subcategoriile cu produse, in ordinea numarului de produse - in
 * design taburile sunt categorii "frunza" (Șampoane, Creme...), nu radacinile.
 * Un magazin cu taxonomie plata ramane pe categoriile de prim nivel, fara cea
 * implicita din WooCommerce. Lista se poate fixa prin 'categories' (slug-uri,
 * in ordinea dorita) sau prin filtrul 'ht_top_sales_terms'.
 *
 * @param array $args 'categories' (slug-uri) si 'tabs' (numarul maxim de taburi).
 *
 * @return WP_Term[]
 */
function ht_top_sales_terms($args = array())
{
    $args = wp_parse_args($args, array(
        'categories' => array(),
        'tabs'       => 9,
    ));

    if (!empty($args['categories'])) {
        $slugs = array_values(array_filter(array_map('trim', (array)$args['categories'])));
        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'slug'       => $slugs,
        ));

        if (is_wp_error($terms)) {
            $terms = array();
        }

        /* get_terms nu pastreaza ordinea slug-urilor cerute; o refacem noi */
        $order = array_flip($slugs);
        usort($terms, function ($a, $b) use ($order) {
            return $order[$a->slug] <=> $order[$b->slug];
        });
    } else {
        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
        ));

        if (is_wp_error($terms)) {
            $terms = array();
        }

        /* "Uncategorized" nu e categorie de magazin, ci cosul produselor neclasificate */
        $default = (int)get_option('default_product_cat');
        $terms = array_filter($terms, function ($term) use ($default) {
            return (int)$term->term_id !== $default;
        });

        $children = array_filter($terms, function ($term) {
            return (int)$term->parent > 0;
        });

        if ($children) {
            $terms = $children;
        }

        usort($terms, function ($a, $b) {
            return (int)$b->count <=> (int)$a->count;
        });
    }

    $terms = array_slice(array_values($terms), 0, max(1, (int)$args['tabs']));

    return apply_filters('ht_top_sales_terms', $terms, $args);
}

/**
 * ID-urile celor mai vandute produse dintr-o categorie, subcategoriile incluse.
 *
 * Ordinea e data de meta 'total_sales', pe care WooCommerce o tine pe fiecare
 * produs (macar 0), cu data publicarii ca departajare - un magazin proaspat,
 * fara vanzari, arata produsele noi in loc de un rand gol.
 *
 * @param int $term_id Categoria.
 * @param int $limit   Cate produse.
 *
 * @return int[]
 */
function ht_top_sales_product_ids($term_id, $limit)
{
    $tax_query = array(
        array(
            'taxonomy'         => 'product_cat',
            'field'            => 'term_id',
            'terms'            => (int)$term_id,
            'include_children' => true,
        ),
    );

    /* produsele ascunse din catalog nu au ce cauta pe prima pagina */
    if (function_exists('wc_get_product_visibility_term_ids')) {
        $visibility = wc_get_product_visibility_term_ids();

        if (!empty($visibility['exclude-from-catalog'])) {
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'term_taxonomy_id',
                'terms'    => array($visibility['exclude-from-catalog']),
                'operator' => 'NOT IN',
            );
        }
    }

    $query = new WP_Query(apply_filters('ht_top_sales_query_args', array(
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'fields'              => 'ids',
        'posts_per_page'      => (int)$limit,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'meta_key'            => 'total_sales',
        'orderby'             => array('meta_value_num' => 'DESC', 'date' => 'DESC'),
        'tax_query'           => $tax_query,
    )));

    return array_map('intval', $query->posts);
}

/**
 * Cardurile celor mai vandute produse dintr-o categorie.
 *
 * @param int $term_id Categoria.
 * @param int $limit   Cate produse.
 *
 * @return array Lista de carduri normalizate, ca in ht_products_data().
 */
function ht_top_sales_cards($term_id, $limit = 10)
{
    /* fara WooCommerce nu exista nici produse, nici vanzari */
    if (!function_exists('wc_get_product')) {
        return array();
    }

    $cards = array();

    foreach (ht_top_sales_product_ids($term_id, $limit) as $id) {
        $product = wc_get_product($id);

        if ($product) {
            $cards[] = ht_product_card_data($product);
        }
    }

    return $cards;
}

/**
 * Randeaza slide-urile caruselului - acelasi markup si la incarcarea paginii,
 * si in raspunsul rutei REST.
 *
 * @param array $cards Cardurile, din ht_top_sales_cards().
 */
function ht_top_sales_slides($cards)
{
    foreach ($cards as $index => $card) {
        ?>
        <div class="swiper-slide ht-products__slide">
            <?php ht_product_card($card, $index); ?>
        </div>
        <?php
    }
}

/**
 * Randeaza sectiunea "Top vânzări".
 *
 * @param array $args 'title', 'limit', 'categories', 'tabs'.
 */
function ht_top_sales_section($args = array())
{
    $args = wp_parse_args($args, array(
        'title'      => __('Top vânzări', 'herbal-therapy'),
        'limit'      => 10,
        'categories' => array(),
        'tabs'       => 9,
    ));

    $terms = ht_top_sales_terms($args);

    if (!$terms) {
        return;
    }

    /*
     * Tabul activ e primul care chiar are carduri: o categorie poate avea doar
     * produse ascunse din catalog, iar sectiunea nu trebuie sa porneasca goala.
     * Taburile fara produse vizibile ies din rand.
     */
    $cards = array();
    $active = 0;

    foreach ($terms as $index => $term) {
        $cards = ht_top_sales_cards($term->term_id, $args['limit']);

        if ($cards) {
            $active = (int)$term->term_id;
            break;
        }

        unset($terms[$index]);
    }

    if (!$cards) {
        return;
    }

    /* scriptul de adaugare in cos prin AJAX nu se incarca implicit in afara paginilor de magazin */
    if (function_exists('WC')) {
        wp_enqueue_script('wc-add-to-cart');
    }
    ?>
    <section class="ht-products ht-top-sales" data-ht-top-sales
             data-limit="<?php echo esc_attr((int)$args['limit']); ?>">
        <div class="ht-wrapper">

            <?php /* fara sagetile din antet: randul de taburi are deja o pereche,
                     iar caruselul de produse se conduce din punctele de paginare */ ?>
            <div class="ht-products__head">
                <?php if ($args['title'] !== '') : ?>
                    <h2 class="ht-products__title"><?php echo esc_html($args['title']); ?></h2>
                <?php endif; ?>
            </div>

            <div class="ht-top-sales__tabs-row">
                <button class="ht-top-sales__tabs-arrow ht-top-sales__tabs-arrow--prev" type="button">
                    <?php ht_icon('chevron'); ?>
                    <span class="ht-visually-hidden"><?php esc_html_e('Înapoi', 'herbal-therapy'); ?></span>
                </button>

                <div class="swiper ht-top-sales__tabs">
                    <div class="swiper-wrapper">
                        <?php foreach ($terms as $term) : ?>
                            <button class="swiper-slide ht-top-sales__tab<?php echo (int)$term->term_id === $active ? ' is-active' : ''; ?>"
                                    type="button"
                                    data-cat="<?php echo esc_attr($term->term_id); ?>"
                                    aria-pressed="<?php echo (int)$term->term_id === $active ? 'true' : 'false'; ?>">
                                <?php echo esc_html($term->name); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button class="ht-top-sales__tabs-arrow ht-top-sales__tabs-arrow--next" type="button">
                    <?php ht_icon('chevron'); ?>
                    <span class="ht-visually-hidden"><?php esc_html_e('Înainte', 'herbal-therapy'); ?></span>
                </button>
            </div>

            <div class="swiper ht-products__swiper">
                <div class="swiper-wrapper">
                    <?php ht_top_sales_slides($cards); ?>
                </div>
            </div>

            <div class="swiper-pagination ht-products__pagination"></div>
        </div>
    </section>
    <?php
}

/**
 * Shortcode: [ht_top_vanzari title="..." limit="10" categories="sampoane,creme" tabs="9"]
 *
 * @param array $atts Atributele shortcode-ului.
 *
 * @return string
 */
function ht_top_sales_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'title'      => __('Top vânzări', 'herbal-therapy'),
        'limit'      => 10,
        'categories' => '',
        'tabs'       => 9,
    ), $atts, 'ht_top_vanzari');

    $args = array(
        'title' => $atts['title'],
        'limit' => (int)$atts['limit'],
        'tabs'  => (int)$atts['tabs'],
    );

    if ($atts['categories'] !== '') {
        $args['categories'] = array_map('trim', explode(',', $atts['categories']));
    }

    ob_start();
    ht_top_sales_section($args);

    return ob_get_clean();
}

add_shortcode('ht_top_vanzari', 'ht_top_sales_shortcode');
add_shortcode('ht_top_sales', 'ht_top_sales_shortcode');

/**
 * Ruta REST din spatele taburilor: produsele unei categorii, gata randate.
 */
function ht_top_sales_register_rest()
{
    register_rest_route('herbal-therapy/v1', '/top-sales', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ht_top_sales_rest',
        'permission_callback' => '__return_true',
        'args'                => array(
            'cat'   => array(
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ),
            'limit' => array(
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ),
            /*
             * Polylang citeste 'lang' pe 'rest_pre_dispatch' si isi seteaza
             * limba curenta - ca la ruta de cautare (inc/search.php).
             */
            'lang'  => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
            ),
        ),
    ));
}

add_action('rest_api_init', 'ht_top_sales_register_rest');

/**
 * Raspunsul rutei: slide-urile gata randate, ca fragment HTML.
 *
 * @param WP_REST_Request $request Cererea.
 *
 * @return WP_REST_Response
 */
function ht_top_sales_rest($request)
{
    $limit = min(20, max(1, (int)$request['limit']));
    $cards = ht_top_sales_cards((int)$request['cat'], $limit);

    ob_start();
    ht_top_sales_slides($cards);

    return rest_ensure_response(array(
        'html'  => (string)ob_get_clean(),
        'count' => count($cards),
    ));
}
