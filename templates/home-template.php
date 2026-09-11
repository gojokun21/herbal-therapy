<?php
/**
 * Template Name: Prima pagina
 *
 * Caruselul hero este randat din header.php, imediat sub header.
 *
 * @package Herbal_Therapy
 */

get_header();

$ht_content = get_post_field('post_content', get_queried_object_id());
?>

<main id="primary" class="site-main home-template">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;

    if (!has_shortcode((string)$ht_content, 'ht_categorii')) {
        /* lista aleasa pentru vitrina (ht_home_category_slugs()); fara ea se cade pe primele 8 dupa nume */
        $ht_home_cats = ht_home_category_ids();

        ht_categories_carousel(array(
            'title'   => __('Categorii de produse', 'herbal-therapy'),
            'limit'   => 8,
            'include' => $ht_home_cats ? $ht_home_cats : '',
            'link'    => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '',
        ));
    }

    if (!has_shortcode((string)$ht_content, 'ht_produse')) {
        ht_products_carousel(array(
            'title'   => __('Oferte speciale', 'herbal-therapy'),
            'link'    => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '',
            'limit'   => 10,
            /* sectiunea arata doar produsele aflate la reducere */
            'on_sale' => true,
        ));
    }

    if (!has_shortcode((string)$ht_content, 'ht_top_vanzari')) {
        ht_top_sales_section();
    }

    if (!has_shortcode((string)$ht_content, 'ht_recenzii')) {
        ht_home_reviews_section();
    }

    if (!has_shortcode((string)$ht_content, 'ht_beneficii')) {
        ht_home_benefits_section();
    }

    if (!has_shortcode((string)$ht_content, 'ht_blog')) {
        ht_blog_carousel(array(
            'title' => __('Noutăți', 'herbal-therapy'),
            'limit' => 4,
        ));
    }

    if (!has_shortcode((string)$ht_content, 'ht_despre_noi')) {
        ht_home_about();
    }
    ?>
</main>

<?php
get_footer();
