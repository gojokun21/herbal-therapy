<?php
/**
 * Sectiunea "Noutăți din blog" - structura si comportament ca in referinta.
 *
 * Se randeaza cu ht_blog_carousel() dintr-un template sau cu shortcode-ul
 * [ht_blog] din continutul unei pagini.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Numarul maxim de etichete afisate peste imagine.
 *
 * In referinta un card are intre una si trei etichete; peste trei randul se
 * rupe si impinge continutul in jos.
 *
 * @return int
 */
function ht_blog_tag_limit()
{
    return (int)apply_filters('ht_blog_tag_limit', 3);
}

/**
 * Adresa listarii de articole.
 *
 * @return string
 */
function ht_blog_url()
{
    $page = (int)get_option('page_for_posts');

    return $page ? (string)get_permalink($page) : (string)home_url('/');
}

/**
 * Etichetele unui articol: categoriile, fara cea implicita.
 *
 * @param int $post_id ID-ul articolului.
 *
 * @return array Lista de siruri.
 */
function ht_blog_card_tags($post_id)
{
    $terms = get_the_terms($post_id, 'category');
    $tags = array();

    if (is_array($terms)) {
        $default = (int)get_option('default_category');

        foreach ($terms as $term) {
            /* categoria implicita ("Necategorizat") nu spune nimic cititorului */
            if ((int)$term->term_id === $default) {
                continue;
            }

            $tags[] = $term->name;
        }
    }

    return array_slice($tags, 0, ht_blog_tag_limit());
}

/**
 * Imaginea de card a unui articol.
 *
 * Referinta pune imaginea ca fundal, ca sa poata aseza etichetele in flux peste
 * ea; pastram acelasi mecanism, deci avem nevoie doar de URL.
 *
 * @param int $post_id ID-ul articolului.
 *
 * @return string URL sau sir gol.
 */
function ht_blog_card_image($post_id)
{
    $id = (int)get_post_thumbnail_id($post_id);

    if (!$id) {
        return '';
    }

    $src = wp_get_attachment_image_src($id, 'ht-card');

    return $src ? (string)$src[0] : '';
}

/**
 * Datele unui card, pornind de la un articol.
 *
 * @param WP_Post $post Articolul.
 *
 * @return array
 */
function ht_blog_card_data($post)
{
    $data = array(
        'id'     => $post->ID,
        'url'    => get_permalink($post),
        'title'  => get_the_title($post),
        'image'  => ht_blog_card_image($post->ID),
        'tags'   => ht_blog_card_tags($post->ID),
        'date'   => get_the_date('', $post),
        'author' => get_the_author_meta('display_name', $post->post_author),
    );

    return apply_filters('ht_blog_card_data', $data, $post);
}

/**
 * Articolele afisate in carusel.
 *
 * @param array $args 'limit', 'orderby', 'order', 'category'.
 *
 * @return array Lista de carduri normalizate.
 */
function ht_blog_data($args = array())
{
    $args = wp_parse_args($args, array(
        'limit'   => 4,
        'orderby' => 'date',
        'order'   => 'DESC',
    ));

    $query = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => (int)$args['limit'],
        'orderby'             => $args['orderby'],
        'order'               => $args['order'],
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );

    if (!empty($args['category'])) {
        $query['category_name'] = is_array($args['category'])
            ? implode(',', $args['category'])
            : $args['category'];
    }

    $posts = get_posts(apply_filters('ht_blog_query_args', $query, $args));

    return array_map('ht_blog_card_data', $posts);
}

/**
 * Randeaza un card de articol.
 *
 * @param array $card Datele cardului, din ht_blog_card_data().
 */
function ht_blog_card($card)
{
    $tags = isset($card['tags']) ? $card['tags'] : array();
    $image = isset($card['image']) ? $card['image'] : '';
    ?>
    <article class="ht-blog__card">
        <a class="ht-blog__link" href="<?php echo esc_url($card['url']); ?>">

            <div class="ht-blog__img"<?php if ($image !== '') : ?> style="background-image: url(<?php echo esc_url($image); ?>);"<?php endif; ?>>
                <?php if ($tags) : ?>
                    <ul class="ht-blog__tags">
                        <?php foreach ($tags as $tag) : ?>
                            <li class="ht-blog__tag"><?php echo esc_html($tag); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="ht-blog__content">
                <h3 class="ht-blog__subtitle"><?php echo esc_html($card['title']); ?></h3>

                <div class="ht-blog__bottom">
                    <span><?php echo esc_html($card['date']); ?></span>
                    <?php if (!empty($card['author'])) : ?>
                        <span><?php echo esc_html($card['author']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </article>
    <?php
}

/**
 * Randeaza caruselul de articole.
 *
 * @param array $args 'title', 'link', 'link_text', 'limit' + argumente de interogare.
 */
function ht_blog_carousel($args = array())
{
    $args = wp_parse_args($args, array(
        'title'     => __('Noutăți', 'herbal-therapy'),
        'link'      => ht_blog_url(),
        'link_text' => __('Vezi mai multe articole', 'herbal-therapy'),
        'limit'     => 4,
    ));

    $cards = ht_blog_data($args);

    if (!$cards) {
        return;
    }
    ?>
    <section class="ht-blog" data-ht-blog>

        <div class="ht-blog__head ht-wrapper">
            <?php if ($args['title'] !== '') : ?>
                <h2 class="ht-blog__title"><?php echo esc_html($args['title']); ?></h2>
            <?php endif; ?>

            <?php if ($args['link'] !== '') : ?>
                <a class="ht-blog__more" href="<?php echo esc_url($args['link']); ?>">
                    <?php echo esc_html($args['link_text']); ?>
                    <?php ht_icon('chevron', 'ht-blog__more-icon'); ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="ht-blog__slider ht-wrapper">
            <div class="swiper ht-blog__swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($cards as $card) : ?>
                        <div class="swiper-slide ht-blog__slide">
                            <?php ht_blog_card($card); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="ht-blog__arrow ht-blog__arrow--prev" type="button">
                <?php ht_icon('chevron'); ?>
                <span class="ht-visually-hidden"><?php esc_html_e('Înapoi', 'herbal-therapy'); ?></span>
            </button>
            <button class="ht-blog__arrow ht-blog__arrow--next" type="button">
                <?php ht_icon('chevron'); ?>
                <span class="ht-visually-hidden"><?php esc_html_e('Înainte', 'herbal-therapy'); ?></span>
            </button>
        </div>
    </section>
    <?php
}

/**
 * Shortcode: [ht_blog title="..." link="..." limit="4" category="ingrijire"]
 *
 * @param array $atts Atributele shortcode-ului.
 *
 * @return string
 */
function ht_blog_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'title'     => __('Noutăți din blog', 'herbal-therapy'),
        'link'      => ht_blog_url(),
        'link_text' => __('Mergi la blog', 'herbal-therapy'),
        'limit'     => 4,
        'category'  => '',
        'orderby'   => 'date',
        'order'     => 'DESC',
    ), $atts, 'ht_blog');

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

    ob_start();
    ht_blog_carousel($args);

    return ob_get_clean();
}

add_shortcode('ht_blog', 'ht_blog_shortcode');
add_shortcode('ht_articole', 'ht_blog_shortcode');

/**
 * Articolele propuse in coloana laterala.
 *
 * Intai cele din aceleasi categorii, apoi, daca nu sunt destule, cele recente.
 *
 * @param int $post_id ID-ul articolului curent.
 * @param int $limit   Numarul de propuneri.
 *
 * @return array Lista de carduri normalizate.
 */
function ht_blog_related_posts($post_id, $limit = 4)
{
    $post_id = (int)$post_id;
    $limit = (int)$limit;

    $base = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => $limit,
        'post__not_in'        => array($post_id),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );

    $posts = array();
    $terms = wp_get_post_terms($post_id, 'category', array('fields' => 'ids'));

    if (!is_wp_error($terms) && $terms) {
        $posts = get_posts(array_merge($base, array('category__in' => $terms)));
    }

    /* prea putine articole inrudite - completam cu cele mai noi */
    if (count($posts) < $limit) {
        $exclude = array($post_id);

        foreach ($posts as $found) {
            $exclude[] = $found->ID;
        }

        $fill = get_posts(array_merge($base, array(
            'post__not_in'   => $exclude,
            'posts_per_page' => $limit - count($posts),
        )));

        $posts = array_merge($posts, $fill);
    }

    return array_map('ht_blog_card_data', $posts);
}

/**
 * Coloana laterala de pe pagina unui articol.
 *
 * @param array $args 'post_id', 'title', 'limit', 'link_text'.
 */
function ht_blog_sidebar($args = array())
{
    $args = wp_parse_args($args, array(
        'post_id'   => get_queried_object_id(),
        'title'     => __('Citește mai multe articole', 'herbal-therapy'),
        'limit'     => 4,
        'link_text' => __('Toate articolele', 'herbal-therapy'),
    ));

    $cards = ht_blog_related_posts($args['post_id'], $args['limit']);

    if (!$cards) {
        return;
    }
    ?>
    <aside class="ht-aside" aria-labelledby="ht-aside-title">
        <div class="ht-aside__inner">
            <h2 class="ht-aside__title" id="ht-aside-title"><?php echo esc_html($args['title']); ?></h2>

            <ul class="ht-aside__list">
                <?php foreach ($cards as $card) : ?>
                    <li class="ht-aside__item">
                        <a class="ht-aside__link" href="<?php echo esc_url($card['url']); ?>">
                            <span class="ht-aside__img<?php echo $card['image'] === '' ? ' ht-aside__img--empty' : ''; ?>"
                                <?php if ($card['image'] !== '') : ?>style="background-image: url(<?php echo esc_url($card['image']); ?>);"<?php endif; ?>></span>

                            <span class="ht-aside__body">
                                <span class="ht-aside__name"><?php echo esc_html($card['title']); ?></span>
                                <span class="ht-aside__date"><?php echo esc_html($card['date']); ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <a class="ht-aside__more" href="<?php echo esc_url(ht_blog_url()); ?>">
                <?php echo esc_html($args['link_text']); ?>
                <?php ht_icon('chevron', 'ht-aside__more-icon'); ?>
            </a>
        </div>
    </aside>
    <?php
}
