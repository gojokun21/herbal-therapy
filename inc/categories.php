<?php
/**
 * Sectiunea "Categorii de produse" - structura si masuratorile din Figma
 * (frame-ul "Acasa", nodul 29:49).
 *
 * Se randeaza cu ht_categories_carousel() dintr-un template sau cu shortcode-ul
 * [ht_categorii] din continutul unei pagini.
 *
 * Cardul e o placa patrata: fotografia categoriei umple placa, iar peste ea, jos,
 * sta o eticheta translucida cu iconita, numele si numarul de produse. Fotografia
 * vine din imaginea categoriei (meta 'thumbnail_id', cea pusa de WooCommerce in
 * Produse -> Categorii).
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Paletele placilor, in ordinea din design.
 *
 * Fiecare paleta da fundalul placii, vizibil doar cand categoria nu are imagine.
 * Se rotesc pe pozitie, deci o a cincea categorie reia prima paleta. Patratul
 * iconitei nu mai ia culoarea paletei: e verdele temei la toate placile.
 *
 * @return array Lista de array-uri cu cheia 'bg'.
 */
function ht_category_palettes()
{
    return apply_filters('ht_category_palettes', array(
        array('bg' => '#ffe5d6'),
        array('bg' => '#ead4ff'),
        array('bg' => '#fff0bd'),
        array('bg' => '#d9dbff'),
    ));
}

/**
 * Imaginea unei categorii, normalizata pentru placa.
 *
 * @param WP_Term $term Categoria.
 *
 * @return array Gol daca imaginea categoriei lipseste.
 */
function ht_category_card_image($term)
{
    $id = (int)get_term_meta($term->term_id, 'thumbnail_id', true);

    if (!$id) {
        return array();
    }

    /* placa are 345px in design, deci 'medium_large' (768px) acopera si ecranele retina */
    $src = wp_get_attachment_image_src($id, 'medium_large');

    if (!$src) {
        return array();
    }

    return array(
        'src'    => $src[0],
        'width'  => $src[1],
        'height' => $src[2],
        'srcset' => (string)wp_get_attachment_image_srcset($id, 'medium_large'),
        'sizes'  => (string)wp_get_attachment_image_sizes($id, 'medium_large'),
        'alt'    => (string)get_post_meta($id, '_wp_attachment_image_alt', true),
    );
}

/**
 * Numarul de produse dintr-o categorie, subcategoriile incluse.
 *
 * WooCommerce tine in $term->count doar produsele legate direct de categorie.
 * Pentru o categorie parinte numarul acela ar arata 0, desi copiii ei au produse,
 * asa ca acolo numaram cu o interogare care coboara in arbore. Un produs pus in
 * doua subcategorii se numara o singura data.
 *
 * @param WP_Term $term Categoria.
 *
 * @return int
 */
function ht_category_product_count($term)
{
    static $cache = array();

    $key = (int)$term->term_id;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $children = get_term_children($key, 'product_cat');

    if (is_wp_error($children) || empty($children)) {
        $cache[$key] = (int)$term->count;

        return $cache[$key];
    }

    $query = new WP_Query(array(
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'fields'              => 'ids',
        'posts_per_page'      => 1,
        'ignore_sticky_posts' => true,
        'tax_query'           => array(
            array(
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => $key,
                'include_children' => true,
            ),
        ),
    ));

    $cache[$key] = (int)$query->found_posts;

    return $cache[$key];
}

/**
 * Categoriile alese pentru prima pagina, in ordinea de afisare.
 *
 * Prima pagina e vitrina, nu catalog: arata doar categoriile mari si cele care
 * definesc brandul, iar restul raman in meniu si pe pagina magazinului. Lista e
 * data prin slug-urile din romana (limba de baza), ca sa nu depinda de ID-urile
 * din baza de date; ht_home_category_ids() o traduce in limba curenta.
 *
 * Ordinea e manuala, dupa volumul catalogului si intentia de cautare. Poate fi
 * schimbata sezonier prin filtrul 'ht_home_category_slugs' (de exemplu siropurile
 * pe primul loc iarna).
 *
 * @return string[] Slug-uri de product_cat.
 */
function ht_home_category_slugs()
{
    return apply_filters('ht_home_category_slugs', array(
        'vitamine-si-suplimente',
        'unguente',
        'siropuri',
        'cosmetica-medicala',
        'ingrijire-faciala',
        'hidratare-pentru-maini-si-corp',
        'sampoane',
    ));
}

/**
 * ID-urile categoriilor de pe prima pagina, in limba curenta.
 *
 * Slug-urile se cauta in toate limbile ('lang' => '' opreste filtrul Polylang),
 * apoi fiecare termen se inlocuieste cu traducerea lui in limba curenta. O
 * categorie fara traducere sau cu slug-ul schimbat e sarita, nu strica lista.
 *
 * @return int[] Gol daca nicio categorie nu exista.
 */
function ht_home_category_ids()
{
    $slugs = ht_home_category_slugs();

    if (!$slugs || !taxonomy_exists('product_cat')) {
        return array();
    }

    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'slug'       => $slugs,
        'hide_empty' => false,
        'lang'       => '',
    ));

    if (is_wp_error($terms) || empty($terms)) {
        return array();
    }

    $by_slug = array();

    foreach ($terms as $term) {
        $by_slug[$term->slug] = (int)$term->term_id;
    }

    $lang = function_exists('pll_current_language') ? (string)pll_current_language() : '';
    $ids = array();

    foreach ($slugs as $slug) {
        if (empty($by_slug[$slug])) {
            continue;
        }

        $id = $by_slug[$slug];

        if ($lang !== '' && function_exists('pll_get_term')) {
            $id = (int)pll_get_term($id, $lang);
        }

        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

/**
 * Categoriile afisate in carusel.
 *
 * Se iau categoriile de pe primul nivel, in ordinea din administrare, fara cea
 * implicita din WooCommerce ("Uncategorized"): aceea nu e o categorie de magazin,
 * ci cosul produselor neclasificate.
 *
 * Sectiunea e o punte catre catalog, nu o listare de produse, deci arata si
 * categoriile inca goale - randul ramane intreg cat timp magazinul se umple.
 * Cu 'hide_empty' => true raman doar cele care au produse.
 *
 * @param array $args 'limit', 'orderby', 'order', 'hide_empty', 'parent', 'include', 'exclude'.
 *
 * @return array Lista de placi normalizate.
 */
function ht_categories_data($args = array())
{
    $args = wp_parse_args($args, array(
        'limit'      => 8,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'hide_empty' => false,
        'parent'     => 0,
    ));

    if (!taxonomy_exists('product_cat')) {
        return array();
    }

    $query = array(
        'taxonomy'   => 'product_cat',
        'orderby'    => $args['orderby'],
        'order'      => $args['order'],
        'hide_empty' => (bool)$args['hide_empty'],
        'parent'     => (int)$args['parent'],
        'exclude'    => array((int)get_option('default_product_cat')),
    );

    if ((int)$args['limit'] > 0) {
        $query['number'] = (int)$args['limit'];
    }

    /* 'include' primeste ID-uri de la shortcode; atunci ordinea e cea data acolo */
    if (!empty($args['include'])) {
        $query['include'] = array_map('intval', (array)$args['include']);
        $query['orderby'] = 'include';
        unset($query['parent']);
    }

    if (!empty($args['exclude'])) {
        $query['exclude'] = array_merge($query['exclude'], array_map('intval', (array)$args['exclude']));
    }

    $terms = get_terms(apply_filters('ht_categories_query_args', $query, $args));

    if (is_wp_error($terms) || empty($terms)) {
        return array();
    }

    $palettes = ht_category_palettes();
    $cards = array();
    $index = 0;

    foreach ($terms as $term) {
        $palette = $palettes ? $palettes[$index % count($palettes)] : array();

        $cards[] = apply_filters('ht_category_card_data', array(
            'id'      => (int)$term->term_id,
            'url'     => (string)get_term_link($term),
            'name'    => $term->name,
            'count'   => ht_category_product_count($term),
            'image'   => ht_category_card_image($term),
            'palette' => $palette,
        ), $term, $index);

        $index++;
    }

    return $cards;
}

/**
 * Randeaza o placa de categorie.
 *
 * @param array $card  Datele placii, din ht_categories_data().
 * @param int   $index Pozitia in lista - prima imagine se incarca eager.
 */
function ht_category_card($card, $index = 0)
{
    $image = isset($card['image']) ? $card['image'] : array();
    $palette = isset($card['palette']) ? $card['palette'] : array();

    /* culorile placii se dau ca variabile inline: raman in CSS, dar vin din date */
    $style = array();

    if (!empty($palette['bg'])) {
        $style[] = '--ht-cat-bg:' . $palette['bg'];
    }

    $count = isset($card['count']) ? (int)$card['count'] : 0;
    ?>
    <a class="ht-cat" href="<?php echo esc_url($card['url']); ?>"
        <?php if ($style) : ?>style="<?php echo esc_attr(implode(';', $style)); ?>"<?php endif; ?>>

        <?php if (!empty($image['src'])) : ?>
            <img class="ht-cat__img"
                 src="<?php echo esc_url($image['src']); ?>"
                 <?php if (!empty($image['srcset'])) : ?>
                     srcset="<?php echo esc_attr($image['srcset']); ?>"
                     sizes="<?php echo esc_attr($image['sizes']); ?>"
                 <?php endif; ?>
                 width="<?php echo esc_attr($image['width']); ?>"
                 height="<?php echo esc_attr($image['height']); ?>"
                 alt="<?php echo esc_attr(!empty($image['alt']) ? $image['alt'] : $card['name']); ?>"
                 loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
                 decoding="async">
        <?php endif; ?>

        <span class="ht-cat__pill">
            <span class="ht-cat__icon">
                <?php ht_icon('category', 'ht-icon ht-cat__icon-glyph'); ?>
            </span>
            <span class="ht-cat__text">
                <span class="ht-cat__name"><?php echo esc_html($card['name']); ?></span>
                <span class="ht-cat__count">
                    <?php
                    printf(
                        /* translators: %s: numarul de produse din categorie. */
                        esc_html(_n('%s produs', '%s produse', $count, 'herbal-therapy')),
                        esc_html(number_format_i18n($count))
                    );
                    ?>
                </span>
            </span>
        </span>
    </a>
    <?php
}

/**
 * Randeaza caruselul de categorii.
 *
 * Cu 'link' nevid, sub carusel apare butonul 'link_text' (implicit "Vezi toate
 * categoriile"), pentru lista completa din magazin.
 *
 * @param array $args 'title', 'link', 'link_text' + argumentele de interogare ale ht_categories_data().
 */
function ht_categories_carousel($args = array())
{
    $args = wp_parse_args($args, array(
        'title'     => __('Categorii de produse', 'herbal-therapy'),
        'limit'     => 8,
        'link'      => '',
        'link_text' => __('Vezi toate categoriile', 'herbal-therapy'),
    ));

    $cards = ht_categories_data($args);

    if (!$cards) {
        return;
    }
    ?>
    <section class="ht-cats" data-ht-cats>
        <div class="ht-wrapper">

            <div class="ht-cats__head">
                <?php if ($args['title'] !== '') : ?>
                    <h2 class="ht-cats__title"><?php echo esc_html($args['title']); ?></h2>
                <?php endif; ?>

                <div class="ht-cats__nav">
                    <button class="ht-cats__arrow ht-cats__arrow--prev" type="button">
                        <?php ht_icon('chevron'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Înapoi', 'herbal-therapy'); ?></span>
                    </button>
                    <button class="ht-cats__arrow ht-cats__arrow--next" type="button">
                        <?php ht_icon('chevron'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Înainte', 'herbal-therapy'); ?></span>
                    </button>
                </div>
            </div>

            <div class="swiper ht-cats__swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($cards as $index => $card) : ?>
                        <div class="swiper-slide ht-cats__slide">
                            <?php ht_category_card($card, $index); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="swiper-pagination ht-cats__pagination"></div>

            <?php if ($args['link'] !== '') : ?>
                <div class="ht-cats__foot">
                    <a class="ht-cats__more" href="<?php echo esc_url($args['link']); ?>">
                        <?php echo esc_html($args['link_text']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

/**
 * Shortcode: [ht_categorii title="..." limit="8" include="12,15" orderby="name" link="shop" link_text="..."]
 *
 * Cu include="home" se ia lista aleasa pentru prima pagina (ht_home_category_ids()).
 * Cu link="shop" butonul de sub carusel trimite la pagina magazinului; se poate
 * da si un URL.
 *
 * @param array $atts Atributele shortcode-ului.
 *
 * @return string
 */
function ht_categories_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'title'      => __('Categorii de produse', 'herbal-therapy'),
        'limit'      => 8,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'hide_empty' => 'no',
        'parent'     => 0,
        'include'    => '',
        'exclude'    => '',
        'link'       => '',
        'link_text'  => __('Vezi toate categoriile', 'herbal-therapy'),
    ), $atts, 'ht_categorii');

    $link = trim((string)$atts['link']);

    if (strtolower($link) === 'shop') {
        $link = function_exists('wc_get_page_permalink') ? (string)wc_get_page_permalink('shop') : '';
    }

    $args = array(
        'title'      => $atts['title'],
        'limit'      => (int)$atts['limit'],
        'orderby'    => $atts['orderby'],
        'order'      => $atts['order'],
        'hide_empty' => in_array(strtolower((string)$atts['hide_empty']), array('yes', '1', 'true'), true),
        'parent'     => (int)$atts['parent'],
        'link'       => $link,
        'link_text'  => $atts['link_text'],
    );

    if (strtolower(trim((string)$atts['include'])) === 'home') {
        $atts['include'] = implode(',', ht_home_category_ids());
    }

    foreach (array('include', 'exclude') as $key) {
        if ($atts[$key] !== '') {
            $args[$key] = array_map('trim', explode(',', $atts[$key]));
        }
    }

    ob_start();
    ht_categories_carousel($args);

    return ob_get_clean();
}

add_shortcode('ht_categorii', 'ht_categories_shortcode');
add_shortcode('ht_categories', 'ht_categories_shortcode');
