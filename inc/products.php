<?php
/**
 * Carusel de produse - structura si comportament ca in referinta.
 *
 * Se randeaza cu ht_products_carousel() dintr-un template sau cu
 * shortcode-ul [ht_produse] din continutul unei pagini.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pretul formatat. Foloseste WooCommerce daca e activ, altfel formatarea locala.
 *
 * @param float|string $amount Suma.
 *
 * @return string
 */
function ht_format_price($amount)
{
    if (function_exists('wc_price')) {
        return wc_price($amount);
    }

    return esc_html(number_format_i18n((float)$amount, 0)) . ' ' . esc_html__('lei', 'herbal-therapy');
}

/**
 * ID-ul imaginii cu care se reprezinta produsul in listari.
 *
 * Imaginea reprezentativa a produsului. Zero inseamna ca produsul nu are
 * nicio imagine.
 *
 * @param WC_Product $product Produsul.
 *
 * @return int
 */
function ht_product_image_id($product)
{
    return (int)apply_filters('ht_product_image_id', (int)$product->get_image_id(), $product);
}

/**
 * Imaginea unui produs WooCommerce, normalizata pentru card.
 *
 * Cardul arata o singura poza - imaginea reprezentativa a produsului.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array Gol daca produsul nu are imagine si nu exista substitut.
 */
function ht_product_card_image($product)
{
    /*
     * 'ht-card' (640x640, crop), nu 'woocommerce_thumbnail' (300x300): cardul
     * ajunge la ~340px CSS, deci pe ecranele cu DPR > 1 varianta de 300px iese
     * neclara. 'sizes' descrie grila reala (2 / 3 / 4 coloane), ca browserul
     * sa poata alege si candidatii mai mari din srcset.
     */
    $id = ht_product_image_id($product);
    $src = $id ? wp_get_attachment_image_src($id, 'ht-card') : false;

    if ($src) {
        return array(
            'src'    => $src[0],
            'width'  => $src[1],
            'height' => $src[2],
            'srcset' => (string)wp_get_attachment_image_srcset($id, 'ht-card'),
            'sizes'  => '(max-width: 767px) 50vw, (max-width: 1199px) 33vw, 340px',
            'alt'    => (string)get_post_meta($id, '_wp_attachment_image_alt', true),
        );
    }

    if (function_exists('wc_placeholder_img_src')) {
        return array(
            'src'    => wc_placeholder_img_src('woocommerce_thumbnail'),
            'width'  => 300,
            'height' => 300,
            'srcset' => '',
            'sizes'  => '',
            'alt'    => '',
        );
    }

    return array();
}

/**
 * Etichetele afisate peste imagine (colt stanga-sus).
 *
 * @param WC_Product $product Produsul.
 *
 * @return array Lista de array-uri cu cheile 'type' si 'text'.
 */
function ht_product_card_flags($product)
{
    $flags = array();

    if ($product->is_on_sale()) {
        $regular = (float)$product->get_regular_price();
        $sale = (float)$product->get_price();
        $percent = ($regular > 0 && $sale > 0 && $sale < $regular)
            ? (int)round(100 - ($sale / $regular * 100))
            : 0;

        $flags[] = array(
            'type' => 'discount',
            /* translators: %d: procentul reducerii. */
            'text' => $percent ? sprintf(__('-%d%%', 'herbal-therapy'), $percent) : __('Reducere', 'herbal-therapy'),
        );
    }

    if ($product->is_featured()) {
        $flags[] = array('type' => 'featured', 'text' => __('Recomandat', 'herbal-therapy'));
    }

    return apply_filters('ht_product_card_flags', $flags, $product);
}

/**
 * Categoria afisata deasupra pretului, in forma "Parinte / Copil".
 *
 * Se ia termenul cel mai adanc din product_cat; daca are parinte, il punem in
 * fata, ca in design ("Produse Cosmetice / Creme").
 *
 * @param WC_Product $product Produsul.
 *
 * @return string Text simplu, gata de escapat.
 */
function ht_product_card_category($product)
{
    $terms = get_the_terms($product->get_id(), 'product_cat');

    if (!$terms || is_wp_error($terms)) {
        return '';
    }

    $term = null;
    $depth = -1;

    foreach ($terms as $candidate) {
        $level = count(get_ancestors($candidate->term_id, 'product_cat'));

        if ($level > $depth) {
            $depth = $level;
            $term = $candidate;
        }
    }

    if (!$term) {
        return '';
    }

    $names = array($term->name);

    if ($term->parent) {
        $parent = get_term($term->parent, 'product_cat');

        if ($parent && !is_wp_error($parent)) {
            array_unshift($names, $parent->name);
        }
    }

    return implode(' / ', $names);
}

/**
 * Beneficiile scurte afisate pe card.
 *
 * Sunt aceleasi randuri ca pe pagina produsului: campul ACF 'beneficii', iar
 * daca lipseste, punctele din descrierea scurta. Pe card nu punem textele de
 * rezerva din design, deci produsele fara continut raman cu lista goala, iar
 * locul ei ramane rezervat din CSS ca sa nu se miste randurile de sub el.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array Lista de randuri simple, gata de escapat.
 */
function ht_product_card_benefits($product)
{
    $items = array();

    if (function_exists('ht_pp_lines')) {
        if (function_exists('ht_pp_field')) {
            $items = ht_pp_lines(ht_pp_field('beneficii', $product->get_id()));
        }

        if (!$items) {
            $items = ht_pp_lines($product->get_short_description());
        }
    }

    $limit = (int)apply_filters('ht_product_card_benefits_limit', 3, $product);

    return apply_filters('ht_product_card_benefits', array_slice($items, 0, $limit), $product);
}

/**
 * Nota medie, scrisa fara zecimale inutile: 5 in loc de 5.0, 4.5 ramane 4.5.
 *
 * @param float $rating Nota medie.
 *
 * @return string
 */
function ht_format_rating($rating)
{
    $rating = round((float)$rating, 1);

    return number_format_i18n($rating, ($rating == (int)$rating) ? 0 : 1);
}

/**
 * Datele unui card, pornind de la un produs WooCommerce.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array
 */
function ht_product_card_data($product)
{
    $regular = (float)$product->get_regular_price();
    $active = (float)$product->get_price();
    $ajax = $product->supports('ajax_add_to_cart') && $product->is_purchasable() && $product->is_in_stock();

    $data = array(
        'id'        => $product->get_id(),
        'url'       => $product->get_permalink(),
        'title'     => $product->get_name(),
        /*
         * get_price_html() scrie pretul vechi inaintea celui nou; in design
         * ordinea e inversa, deci la reducerile cu pret unic il compunem noi si
         * lasam pretul vechi pe seama lui 'price_old'. Produsele variabile, unde
         * pretul e un interval, raman pe formatul WooCommerce.
         */
        'price'     => ($product->is_on_sale() && $regular > $active)
            ? ht_format_price($active)
            : $product->get_price_html(),
        'price_old' => ($product->is_on_sale() && $regular > $active) ? ht_format_price($regular) : '',
        'category'  => ht_product_card_category($product),
        'benefits'  => ht_product_card_benefits($product),
        'rating'    => (float)$product->get_average_rating(),
        'reviews'   => (int)$product->get_review_count(),
        'flags'     => ht_product_card_flags($product),
        'image'     => ht_product_card_image($product),
        /*
         * Butonul rapid apare doar la produsele care se pot adauga direct in cos.
         * Restul (variabile, externe, stoc epuizat) duc pe pagina produsului prin link-ul cardului.
         */
        'cart'      => array(
            'ajax' => $ajax,
            'text' => $product->add_to_cart_text(),
            'sku'  => $product->get_sku(),
        ),
    );

    return apply_filters('ht_product_card_data', $data, $product);
}

/**
 * Produsele afisate in carusel.
 *
 * @param array $args Argumente de interogare (vezi wc_get_products);
 *                    'on_sale' => true restrange lista la produsele la reducere.
 *
 * @return array Lista de carduri normalizate.
 */
function ht_products_data($args = array())
{
    $args = wp_parse_args($args, array(
        'limit'   => 10,
        'status'  => 'publish',
        'orderby' => 'date',
        'order'   => 'DESC',
    ));

    /* cheile de prezentare nu au ce cauta in interogare */
    unset($args['title'], $args['link'], $args['link_text']);

    /* fara WooCommerce nu exista catalog, deci nici carduri */
    if (!function_exists('wc_get_products')) {
        return array();
    }

    /* doar reducerile: interogarea se restrange la ID-urile produselor la reducere */
    if (!empty($args['on_sale'])) {
        $sale_ids = wc_get_product_ids_on_sale();

        if (isset($args['include'])) {
            $sale_ids = array_intersect((array)$args['include'], $sale_ids);
        }

        if (!$sale_ids) {
            return array();
        }

        $args['include'] = $sale_ids;
    }

    unset($args['on_sale']);

    if (!isset($args['visibility'])) {
        $args['visibility'] = 'catalog';
    }

    $products = wc_get_products(apply_filters('ht_products_query_args', $args));

    return array_map('ht_product_card_data', $products);
}

/**
 * Randeaza un card de produs.
 *
 * @param array $card  Datele cardului, din ht_product_card_data().
 * @param int   $index Pozitia in lista - prima imagine se incarca eager.
 */
function ht_product_card($card, $index = 0)
{
    $image = isset($card['image']) ? $card['image'] : array();

    if (empty($image['src'])) {
        return;
    }

    $flags = isset($card['flags']) ? $card['flags'] : array();
    $cart = isset($card['cart']) ? $card['cart'] : array();

    $cart_text = !empty($cart['text']) ? $cart['text'] : __('Adaugă în coș', 'herbal-therapy');
    $rating = isset($card['rating']) ? (float)$card['rating'] : 0.0;
    $reviews = isset($card['reviews']) ? (int)$card['reviews'] : 0;
    $category = isset($card['category']) ? $card['category'] : '';
    $benefits = isset($card['benefits']) ? (array)$card['benefits'] : array();
    ?>
    <article class="ht-card" data-ht-card>

        <div class="ht-card__media">

            <?php /* link doar pe imagine: titlul are propriul link, deci il scoatem din ordinea de tabulare */ ?>
            <a class="ht-card__img-link" href="<?php echo esc_url($card['url']); ?>"
               tabindex="-1" aria-hidden="true">
                <img class="ht-card__img"
                     src="<?php echo esc_url($image['src']); ?>"
                     <?php if (!empty($image['srcset'])) : ?>
                         srcset="<?php echo esc_attr($image['srcset']); ?>"
                         sizes="<?php echo esc_attr($image['sizes']); ?>"
                     <?php endif; ?>
                     width="<?php echo esc_attr($image['width']); ?>"
                     height="<?php echo esc_attr($image['height']); ?>"
                     alt="<?php echo esc_attr(!empty($image['alt']) ? $image['alt'] : $card['title']); ?>"
                     loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
                     decoding="async">
            </a>

            <?php /* toate etichetele stau sus-stanga peste imagine; reducerea vine prima */ ?>
            <?php if ($flags) : ?>
                <ul class="ht-card__flags">
                    <?php foreach ($flags as $flag) : ?>
                        <li class="ht-card__flag ht-card__flag--<?php echo esc_attr($flag['type']); ?>">
                            <?php echo esc_html($flag['text']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php ht_favorite_button($card['id'], 'ht-card__fav'); ?>
        </div>

        <div class="ht-card__info">

            <?php if ($category !== '') : ?>
                <span class="ht-card__cat"><?php echo esc_html($category); ?></span>
            <?php endif; ?>

            <div class="ht-card__prices">
                <span class="ht-card__price"><?php echo wp_kses_post($card['price']); ?></span>
                <?php if (!empty($card['price_old'])) : ?>
                    <span class="ht-card__price-old"><?php echo wp_kses_post($card['price_old']); ?></span>
                <?php endif; ?>
            </div>

            <a class="ht-card__name" href="<?php echo esc_url($card['url']); ?>">
                <?php echo esc_html($card['title']); ?>
            </a>

            <?php /* lista ramane si goala: rezerva loc, ca sa nu se miste randul de sub ea */ ?>
            <ul class="ht-card__benefits">
                <?php foreach ($benefits as $benefit) : ?>
                    <li class="ht-card__benefit"><?php echo esc_html($benefit); ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="ht-card__meta">
                <span class="ht-card__meta-item ht-card__rating">
                    <?php ht_icon('star-card', 'ht-card__meta-icon ht-card__meta-icon--star'); ?>
                    <span class="ht-card__meta-value"><?php echo esc_html(ht_format_rating($rating)); ?></span>
                    <span class="ht-visually-hidden"><?php esc_html_e('Nota medie', 'herbal-therapy'); ?></span>
                </span>
                <span class="ht-card__meta-item ht-card__reviews">
                    <?php ht_icon('comment-card', 'ht-card__meta-icon ht-card__meta-icon--comment'); ?>
                    <span class="ht-card__meta-value"><?php echo esc_html(number_format_i18n($reviews)); ?></span>
                    <span class="ht-visually-hidden"><?php esc_html_e('Recenzii', 'herbal-therapy'); ?></span>
                </span>
            </div>

            <?php if (!empty($cart['ajax'])) : ?>
                <?php /* data-added-text: eticheta aratata cateva secunde dupa adaugarea prin AJAX */ ?>
                <button class="ht-card__cart add_to_cart_button ajax_add_to_cart" type="button"
                        data-quantity="1"
                        data-product_id="<?php echo esc_attr($card['id']); ?>"
                        data-product_sku="<?php echo esc_attr($cart['sku']); ?>"
                        data-added-text="<?php esc_attr_e('În coș', 'herbal-therapy'); ?>">
                    <?php ht_icon('cart-btn', 'ht-card__cart-icon'); ?>
                    <span class="ht-card__cart-text"><?php echo esc_html($cart_text); ?></span>
                </button>
            <?php else : ?>
                <?php /* variabile, externe sau fara stoc: butonul duce pe pagina produsului */ ?>
                <a class="ht-card__cart" href="<?php echo esc_url($card['url']); ?>">
                    <?php ht_icon('cart-btn', 'ht-card__cart-icon'); ?>
                    <span class="ht-card__cart-text"><?php echo esc_html($cart_text); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/**
 * Randeaza caruselul de produse.
 *
 * @param array $args 'title', 'link', 'link_text', 'limit' + argumente de interogare.
 */
function ht_products_carousel($args = array())
{
    $args = wp_parse_args($args, array(
        'title'     => __('Oferte speciale', 'herbal-therapy'),
        'link'      => '',
        'link_text' => __('Vezi toate', 'herbal-therapy'),
        'limit'     => 10,
    ));

    $cards = ht_products_data($args);

    if (!$cards) {
        return;
    }

    /* scriptul de adaugare in cos prin AJAX nu se incarca implicit in afara paginilor de magazin */
    if (function_exists('WC')) {
        wp_enqueue_script('wc-add-to-cart');
    }
    ?>
    <section class="ht-products" data-ht-products>
        <div class="ht-wrapper">

            <div class="ht-products__head">
                <?php if ($args['title'] !== '') : ?>
                    <h2 class="ht-products__title"><?php echo esc_html($args['title']); ?></h2>
                <?php endif; ?>

                <div class="ht-products__actions">
                    <?php if ($args['link'] !== '') : ?>
                        <a class="ht-products__more" href="<?php echo esc_url($args['link']); ?>">
                            <?php echo esc_html($args['link_text']); ?>
                        </a>
                    <?php endif; ?>

                    <div class="ht-products__nav">
                        <button class="ht-products__arrow ht-products__arrow--prev" type="button">
                            <?php ht_icon('chevron'); ?>
                            <span class="ht-visually-hidden"><?php esc_html_e('Înapoi', 'herbal-therapy'); ?></span>
                        </button>
                        <button class="ht-products__arrow ht-products__arrow--next" type="button">
                            <?php ht_icon('chevron'); ?>
                            <span class="ht-visually-hidden"><?php esc_html_e('Înainte', 'herbal-therapy'); ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="swiper ht-products__swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($cards as $index => $card) : ?>
                        <div class="swiper-slide ht-products__slide">
                            <?php ht_product_card($card, $index); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="swiper-pagination ht-products__pagination"></div>
        </div>
    </section>
    <?php
}

/**
 * Shortcode: [ht_produse title="..." link="..." limit="10" category="ceaiuri" on_sale="1"]
 *
 * @param array $atts Atributele shortcode-ului.
 *
 * @return string
 */
function ht_products_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'title'     => __('Oferte speciale', 'herbal-therapy'),
        'link'      => '',
        'link_text' => __('Vezi toate', 'herbal-therapy'),
        'limit'     => 10,
        'category'  => '',
        'on_sale'   => '',
        'orderby'   => 'date',
        'order'     => 'DESC',
    ), $atts, 'ht_produse');

    $args = array(
        'title'     => $atts['title'],
        'link'      => $atts['link'],
        'link_text' => $atts['link_text'],
        'limit'     => (int)$atts['limit'],
        'orderby'   => $atts['orderby'],
        'order'     => $atts['order'],
    );

    if ($atts['category'] !== '') {
        $args['category'] = array_map('trim', explode(',', $atts['category']));
    }

    if (filter_var($atts['on_sale'], FILTER_VALIDATE_BOOLEAN)) {
        $args['on_sale'] = true;
    }

    ob_start();
    ht_products_carousel($args);

    return ob_get_clean();
}

add_shortcode('ht_produse', 'ht_products_shortcode');
add_shortcode('ht_products', 'ht_products_shortcode');
