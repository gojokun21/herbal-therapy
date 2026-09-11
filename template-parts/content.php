<?php
/**
 * Un articol in listari.
 *
 * @package Herbal_Therapy
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('entry-card'); ?>>
    <?php ht_post_thumbnail(); ?>

    <div class="entry-card__body">
        <h2 class="entry-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>

        <div class="entry-meta">
            <?php ht_posted_on(); ?>
        </div>

        <div class="entry-excerpt">
            <?php the_excerpt(); ?>
        </div>

        <a class="entry-more" href="<?php the_permalink(); ?>">
            <?php esc_html_e('Citește mai mult', 'herbal-therapy'); ?>
        </a>
    </div>
</article>
