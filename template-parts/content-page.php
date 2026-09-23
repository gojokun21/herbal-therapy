<?php
/**
 * Continutul unei pagini.
 *
 * @package Herbal_Therapy
 */
?>

<?php $ht_text_page = ht_is_text_page(); ?>

<article id="post-<?php the_ID(); ?>" <?php post_class($ht_text_page ? 'entry entry--page ht-page' : 'entry entry--page'); ?>>

    <?php if (!is_front_page()) : ?>
        <header class="entry-header">
            <?php if ($ht_text_page) : ?>
                <nav class="ht-page__crumbs" aria-label="<?php esc_attr_e('Firimituri', 'herbal-therapy'); ?>">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Acasă', 'herbal-therapy'); ?></a>
                    <span class="ht-page__crumb-sep" aria-hidden="true">/</span>
                    <span class="ht-page__crumb-current" aria-current="page"><?php the_title(); ?></span>
                </nav>
            <?php endif; ?>
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
