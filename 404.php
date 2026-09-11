<?php
/**
 * Sablonul pentru pagina inexistenta.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="ht-container">
        <section class="error-404 not-found">
            <h1 class="page-title"><?php esc_html_e('Pagina nu a fost găsită', 'herbal-therapy'); ?></h1>
            <p><?php esc_html_e('Pagina căutată nu există sau a fost mutată. Încearcă o căutare sau întoarce-te la prima pagină.', 'herbal-therapy'); ?></p>

            <?php get_search_form(); ?>

            <p class="error-404__actions">
                <a class="btn-primary" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php esc_html_e('Înapoi la prima pagină', 'herbal-therapy'); ?>
                </a>
            </p>
        </section>
    </div>
</main>

<?php
get_footer();
