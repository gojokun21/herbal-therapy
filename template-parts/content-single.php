<?php
/**
 * Continutul unui articol.
 *
 * @package Herbal_Therapy
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('entry entry--single'); ?>>

    <header class="entry-header">
        <h1 class="entry-title"><?php the_title(); ?></h1>

        <div class="entry-meta">
            <?php
            ht_posted_on();
            ht_posted_by();
            ?>
        </div>
    </header>

    <?php ht_post_thumbnail(); ?>

    <div class="entry-content">
        <?php
        the_content();

        wp_link_pages(array(
            'before' => '<div class="page-links">',
            'after'  => '</div>',
        ));
        ?>
    </div>

    <?php if (has_category() || has_tag()) : ?>
        <footer class="entry-footer">
            <?php
            the_category('<span class="sep">, </span>');
            the_tags('<span class="entry-tags">', '<span class="sep">, </span>', '</span>');
            ?>
        </footer>
    <?php endif; ?>

</article>
