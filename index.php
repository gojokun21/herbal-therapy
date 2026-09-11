<?php
/**
 * Sablonul implicit - lista de articole.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="ht-container">

        <?php if (have_posts()) : ?>

            <?php if (!is_front_page()) : ?>
                <header class="page-header">
                    <h1 class="page-title"><?php echo esc_html(ht_archive_title()); ?></h1>
                </header>
            <?php endif; ?>

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
