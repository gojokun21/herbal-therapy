<?php
/**
 * Sablonul pentru un articol.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="ht-wrapper ht-single">

        <div class="ht-single__main">
            <?php
            while (have_posts()) :
                the_post();
                get_template_part('template-parts/content', 'single');

                the_post_navigation(array(
                    'prev_text' => '<span class="nav-label">' . esc_html__('Articolul anterior', 'herbal-therapy') . '</span> %title',
                    'next_text' => '<span class="nav-label">' . esc_html__('Articolul următor', 'herbal-therapy') . '</span> %title',
                    'class'     => 'post-navigation',
                ));
            endwhile;
            ?>
        </div>

        <?php ht_blog_sidebar(); ?>

    </div>
</main>

<?php
get_footer();
