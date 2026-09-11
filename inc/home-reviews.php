<?php
/**
 * Sectiunea de recenzii de pe prima pagina.
 *
 * Referinta de design nu are blocul, asa ca e construit in limbajul temei:
 * scheletul sectiunii (capul cu titlu si sageti) e cel al caruselului de
 * produse, stelele sunt cele din inc/reviews.php, iar cardul arata o recenzie
 * cu stele, text, autor si, optional, produsul recenzat.
 *
 * Continutul se scrie din prima pagina, in grupul ACF "Recenzii (prima
 * pagina)" (acf-json/group_ht_home_reviews.json) - repeater-ul 'ht_recenzii'.
 * Fara ACF sau fara randuri, sectiunea nu se afiseaza.
 *
 * Se randeaza cu ht_home_reviews_section() dintr-un template sau cu
 * shortcode-ul [ht_recenzii] din continutul unei pagini.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Recenziile afisate, din repeater-ul ACF al paginii curente.
 *
 * Randurile fara text se sar - o nota fara cuvinte nu convinge pe nimeni.
 * Cand randul are produsul legat, cardul primeste poza si link-ul lui;
 * produsele sterse sau ascunse din catalog raman doar fara randul de produs,
 * recenzia se afiseaza in continuare.
 *
 * @param array $args 'limit' - cate recenzii, 0 inseamna toate.
 *
 * @return array Lista de carduri normalizate.
 */
function ht_home_reviews_data($args = array())
{
    $args = wp_parse_args($args, array(
        'limit' => 0,
    ));

    if (!function_exists('get_field')) {
        return array();
    }

    $rows = get_field('ht_recenzii', get_queried_object_id());

    if (!is_array($rows)) {
        return array();
    }

    $cards = array();

    foreach ($rows as $row) {
        $text = isset($row['text']) ? trim((string)$row['text']) : '';

        if ('' === $text) {
            continue;
        }

        $rating = isset($row['nota']) ? (float)$row['nota'] : 5;
        $author = isset($row['autor']) ? trim((string)$row['autor']) : '';

        $product = array();
        $product_id = isset($row['produs']) ? (int)$row['produs'] : 0;

        if ($product_id && function_exists('wc_get_product')) {
            $object = wc_get_product($product_id);

            if ($object && $object->is_visible()) {
                $image = array();
                $image_id = function_exists('ht_product_image_id')
                    ? ht_product_image_id($object)
                    : (int)$object->get_image_id();

                if ($image_id) {
                    $src = wp_get_attachment_image_src($image_id, 'thumbnail');

                    if ($src) {
                        $image = array('src' => $src[0]);
                    }
                }

                $product = array(
                    'title' => $object->get_name(),
                    'url'   => $object->get_permalink(),
                    'image' => $image,
                );
            }
        }

        $cards[] = array(
            'rating'   => max(1, min(5, $rating ? $rating : 5)),
            'text'     => $text,
            'author'   => '' !== $author ? $author : __('Client', 'herbal-therapy'),
            'product'  => $product,
        );

        if ($args['limit'] > 0 && count($cards) >= (int)$args['limit']) {
            break;
        }
    }

    return apply_filters('ht_home_reviews_data', $cards, $args);
}

/**
 * Initiala pentru avatarul autorului.
 *
 * @param string $name Numele autorului.
 *
 * @return string
 */
function ht_home_reviews_initial($name)
{
    $name = trim((string)$name);

    return '' === $name ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
}

/**
 * Randeaza un card de recenzie.
 *
 * @param array $card Cardul, din ht_home_reviews_data().
 */
function ht_home_reviews_card($card)
{
    $product = isset($card['product']) ? $card['product'] : array();
    ?>
    <article class="ht-home-review">

        <?php if (function_exists('ht_stars')) : ?>
            <?php echo ht_stars($card['rating']); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit si escapat in ht_stars(). ?>
        <?php endif; ?>

        <p class="ht-home-review__text"><?php echo esc_html($card['text']); ?></p>

        <div class="ht-home-review__author">
            <span class="ht-home-review__avatar" aria-hidden="true">
                <?php echo esc_html(ht_home_reviews_initial($card['author'])); ?>
            </span>
            <span class="ht-home-review__who">
                <span class="ht-home-review__name"><?php echo esc_html($card['author']); ?></span>
            </span>
        </div>

        <?php if (!empty($product['url'])) : ?>
            <a class="ht-home-review__product" href="<?php echo esc_url($product['url']); ?>">
                <?php if (!empty($product['image']['src'])) : ?>
                    <img class="ht-home-review__product-img"
                         src="<?php echo esc_url($product['image']['src']); ?>"
                         width="40" height="40" alt=""
                         loading="lazy" decoding="async">
                <?php endif; ?>
                <span><?php echo esc_html($product['title']); ?></span>
            </a>
        <?php endif; ?>
    </article>
    <?php
}

/**
 * Randeaza sectiunea de recenzii.
 *
 * @param array $args 'title' si 'limit'.
 */
function ht_home_reviews_section($args = array())
{
    $args = wp_parse_args($args, array(
        'title' => __('Ce spun clienții', 'herbal-therapy'),
        'limit' => 0,
    ));

    $cards = ht_home_reviews_data($args);

    if (!$cards) {
        return;
    }
    ?>
    <section class="ht-products ht-home-reviews" data-ht-home-reviews>
        <div class="ht-wrapper">

            <div class="ht-products__head">
                <?php if ($args['title'] !== '') : ?>
                    <h2 class="ht-products__title"><?php echo esc_html($args['title']); ?></h2>
                <?php endif; ?>

                <div class="ht-products__actions">
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

            <div class="swiper ht-home-reviews__swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($cards as $card) : ?>
                        <div class="swiper-slide ht-home-reviews__slide">
                            <?php ht_home_reviews_card($card); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Shortcode: [ht_recenzii title="..." limit="8"]
 *
 * @param array $atts Atributele shortcode-ului.
 *
 * @return string
 */
function ht_home_reviews_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'title' => __('Ce spun clienții', 'herbal-therapy'),
        'limit' => 0,
    ), $atts, 'ht_recenzii');

    ob_start();
    ht_home_reviews_section(array(
        'title' => $atts['title'],
        'limit' => (int)$atts['limit'],
    ));

    return ob_get_clean();
}

add_shortcode('ht_recenzii', 'ht_home_reviews_shortcode');
add_shortcode('ht_reviews', 'ht_home_reviews_shortcode');
