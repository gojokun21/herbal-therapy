<?php
/**
 * Template Name: Despre noi
 *
 * Titlul din firimituri vine din pagina; restul continutului - din grupul ACF
 * "Pagina despre noi". Ce nu e completat nu se afiseaza; textele din design
 * se scriu in pagina cu bin/seed.php. Vezi inc/about.php.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main site-main--about">
    <?php
    while (have_posts()) :
        the_post();

        ht_about_page(array(
            'title' => get_the_title(),
        ));
    endwhile;
    ?>
</main>

<?php
get_footer();
