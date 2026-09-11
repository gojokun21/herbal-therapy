<?php
/**
 * Template Name: Blog
 *
 * Listarea articolelor, cu bara de teme in stanga si grila in dreapta.
 * Continutul paginii, daca exista, se afiseaza deasupra listarii.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main site-main--blog">
    <?php
    while (have_posts()) :
        the_post();

        $ht_content = trim((string)get_the_content());

        if ($ht_content !== '') {
            echo '<div class="ht-wrapper"><div class="ht-blog-page__intro entry-content">';
            the_content();
            echo '</div></div>';
        }
    endwhile;

    ht_blog_archive(array(
        'title' => get_the_title(),
    ));
    ?>
</main>

<?php
get_footer();
