<?php
/**
 * Template Name: B2B
 *
 * Titlul din firimituri vine din pagina; restul - titlul mare, textul de sub
 * el, fotografia si formularul - din grupul ACF "Pagina B2B".
 * Vezi inc/b2b.php.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main site-main--b2b">
    <?php
    while (have_posts()) :
        the_post();

        ht_b2b_page(array(
            'title' => get_the_title(),
        ));
    endwhile;
    ?>
</main>

<?php
get_footer();
