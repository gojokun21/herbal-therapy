<?php
/**
 * Un rezultat de cautare.
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

        <div class="entry-excerpt">
            <?php the_excerpt(); ?>
        </div>
    </div>
</article>
