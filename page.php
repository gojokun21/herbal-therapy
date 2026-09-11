<?php
/**
 * Sablonul pentru pagini.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="ht-container">
        <?php
        while (have_posts()) :
            the_post();
            get_template_part('template-parts/content', 'page');
        endwhile;
        ?>
    </div>
</main>

<?php
get_footer();
