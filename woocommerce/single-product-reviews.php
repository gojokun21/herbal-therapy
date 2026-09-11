<?php
/**
 * Blocul de recenzii de pe pagina de produs.
 *
 * Suprascrie woocommerce/templates/single-product-reviews.php
 *
 * Structura e cea din Figma (nodul 141:9976): in stanga rezumatul cu nota medie,
 * distributia notelor si butonul de scriere, in dreapta numarul de recenzii,
 * sortarea si lista. Functiile ajutatoare stau in inc/reviews.php.
 *
 * @package Herbal_Therapy
 * @version 9.7.0
 */

defined('ABSPATH') || exit;

global $product;

if (!comments_open()) {
    return;
}

$ht_count = (int)$product->get_review_count();
$ht_average = (float)$product->get_average_rating();

/* pluginul lasa formularul doar cumparatorilor, daca asa e setat din Woo */
$ht_can_review = 'no' === get_option('woocommerce_review_rating_verification_required')
    || wc_customer_bought_product('', get_current_user_id(), $product->get_id());
?>
<div id="reviews" class="ht-rev" data-ht-rev>

    <h2 class="ht-visually-hidden"><?php esc_html_e('Recenzii', 'herbal-therapy'); ?></h2>

    <div class="ht-rev__aside">
        <div class="ht-rev__summary">
            <div class="ht-rev__score">
                <span class="ht-rev__score-value"><?php echo esc_html(ht_format_rating($ht_average)); ?></span>
                <?php echo ht_stars($ht_average, array('size' => 'lg')); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern. ?>
            </div>

            <p class="ht-rev__based">
                <?php
                printf(
                    /* translators: %s: numarul de recenzii. */
                    esc_html(_n('Bazat pe %s recenzie', 'Bazat pe %s recenzii', $ht_count, 'herbal-therapy')),
                    esc_html(number_format_i18n($ht_count))
                );
                ?>
            </p>

            <ul class="ht-rev__bars">
                <?php foreach (ht_review_breakdown($product) as $ht_row) : ?>
                    <li class="ht-rev__bar-row">
                        <span class="ht-rev__bar-label">
                            <?php echo esc_html($ht_row['stars']); ?>
                            <?php echo ht_star_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern. ?>
                        </span>
                        <span class="ht-rev__bar">
                            <span class="ht-rev__bar-fill" style="width:<?php echo esc_attr($ht_row['percent']); ?>%"></span>
                        </span>
                        <span class="ht-rev__bar-count"><?php echo esc_html(number_format_i18n($ht_row['count'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($ht_can_review) : ?>
            <a class="ht-rev__cta" href="#respond"><?php esc_html_e('Scrie o recenzie', 'herbal-therapy'); ?></a>
        <?php endif; ?>
    </div>

    <div class="ht-rev__body">

        <div class="ht-rev__head">
            <div class="ht-rev__tabs">
                <span class="ht-rev__tab">
                    <?php
                    printf(
                        /* translators: %s: numarul de recenzii. */
                        esc_html(_n('%s Recenzie', '%s Recenzii', $ht_count, 'herbal-therapy')),
                        esc_html(number_format_i18n($ht_count))
                    );
                    ?>
                </span>
            </div>

            <button class="ht-rev__sort" type="button" data-ht-rev-sort
                    data-label-date="<?php esc_attr_e('După dată', 'herbal-therapy'); ?>"
                    data-label-rating="<?php esc_attr_e('După notă', 'herbal-therapy'); ?>">
                <span data-ht-rev-sort-label><?php esc_html_e('După dată', 'herbal-therapy'); ?></span>
                <?php ht_product_asset_icon('icon-filter', 20); ?>
            </button>
        </div>

        <div class="ht-rev__panel is-active">
            <?php if (have_comments()) : ?>
                <ol class="commentlist ht-rev__list">
                    <?php wp_list_comments(apply_filters('woocommerce_product_review_list_args', array('callback' => 'woocommerce_comments'))); ?>
                </ol>

                <?php
                if (get_comment_pages_count() > 1 && get_option('page_comments')) :
                    echo '<nav class="woocommerce-pagination">';
                    paginate_comments_links(
                        apply_filters(
                            'woocommerce_comment_pagination_args',
                            array(
                                'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
                                'next_text' => is_rtl() ? '&larr;' : '&rarr;',
                                'type'      => 'list',
                            )
                        )
                    );
                    echo '</nav>';
                endif;
                ?>
            <?php else : ?>
                <div class="ht-rev__empty">
                    <p class="ht-rev__empty-title"><?php esc_html_e('Produsul nu are încă recenzii', 'herbal-therapy'); ?></p>
                    <p class="ht-rev__empty-text">
                        <?php esc_html_e('Ai încercat produsul? Spune-le celorlalți cum ți s-a părut.', 'herbal-therapy'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($ht_can_review) : ?>
                <div id="review_form_wrapper" class="ht-rev__form-wrap">
                    <div id="review_form">
                        <?php
                        /*
                         * Argumentele formularului - campurile, stelele si etichetele -
                         * se pun din ht_review_form_args(), pe filtrul de mai jos.
                         */
                        comment_form(apply_filters('woocommerce_product_review_comment_form_args', array(
                            'title_reply'    => __('Scrie o recenzie', 'herbal-therapy'),
                            /* translators: %s: numele autorului recenziei la care se raspunde. */
                            'title_reply_to' => __('Răspunde lui %s', 'herbal-therapy'),
                        )));
                        ?>
                    </div>
                </div>
            <?php else : ?>
                <p class="ht-rev__notice woocommerce-verification-required">
                    <?php esc_html_e('Recenzii pot lăsa doar clienții autentificați care au cumpărat acest produs.', 'herbal-therapy'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
