<?php
/**
 * Sablonul de arhiva (categorii, etichete, autori, date, tipuri de continut).
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="ht-container">

        <?php if (have_posts()) : ?>

            <header class="page-header">
                <h1 class="page-title"><?php echo esc_html(ht_archive_title()); ?></h1>
                <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
            </header>

            <div class="posts-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    get_template_part('template-parts/content', get_post_type());
                endwhile;
                ?>
            </div>

            <?php ht_pagination(); ?>

        <?php else : ?>

            <?php get_template_part('template-parts/content', 'none'); ?>

        <?php endif; ?>

    </div>
</main>

<?php
get_footer();
