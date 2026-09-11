<?php
/**
 * Continutul unei pagini.
 *
 * @package Herbal_Therapy
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('entry entry--page'); ?>>

    <?php if (!is_front_page()) : ?>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
        </header>
    <?php endif; ?>

    <div class="entry-content">
        <?php
        the_content();

        wp_link_pages(array(
            'before' => '<div class="page-links">',
            'after'  => '</div>',
        ));
        ?>
    </div>

    <?php if (get_edit_post_link()) : ?>
        <footer class="entry-footer">
            <?php edit_post_link(esc_html__('Editează', 'herbal-therapy'), '<span class="edit-link">', '</span>'); ?>
        </footer>
    <?php endif; ?>

</article>
