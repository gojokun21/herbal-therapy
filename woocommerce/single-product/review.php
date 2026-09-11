<?php
/**
 * O recenzie din lista.
 *
 * Suprascrie woocommerce/templates/single-product/review.php
 * Eticheta </li> lipseste intentionat - o inchide WordPress.
 *
 * Hook-urile raman in ordinea pluginului, care e si ordinea din design: nota,
 * randul cu autorul, textul. Atributele 'data-rating' si 'data-time' de pe <li>
 * sunt ce citeste sortarea din assets/js/single-product.js.
 *
 * @package Herbal_Therapy
 * @version 2.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$ht_rating = (int)get_comment_meta($comment->comment_ID, 'rating', true);
$ht_images = ht_review_images($comment);
?>
<li <?php comment_class('ht-rev__item'); ?> id="li-comment-<?php comment_ID(); ?>"
    data-rating="<?php echo esc_attr($ht_rating); ?>"
    data-time="<?php echo esc_attr(get_comment_date('U')); ?>">

    <div id="comment-<?php comment_ID(); ?>" class="comment_container ht-rev__item-inner">

        <?php
        /**
         * Hook-ul woocommerce_review_before.
         *
         * Gravatar-ul e scos din inc/reviews.php - designul nu are imagine de autor.
         */
        do_action('woocommerce_review_before', $comment);
        ?>

        <div class="comment-text ht-rev__content">

            <?php
            /**
             * Hook-ul woocommerce_review_before_comment_meta.
             *
             * @hooked woocommerce_review_display_rating - 10
             */
            do_action('woocommerce_review_before_comment_meta', $comment);

            /**
             * Hook-ul woocommerce_review_meta.
             *
             * @hooked woocommerce_review_display_meta - 10
             */
            do_action('woocommerce_review_meta', $comment);

            do_action('woocommerce_review_before_comment_text', $comment);

            /**
             * Hook-ul woocommerce_review_comment_text.
             *
             * @hooked woocommerce_review_display_comment_text - 10
             */
            do_action('woocommerce_review_comment_text', $comment);

            do_action('woocommerce_review_after_comment_text', $comment);
            ?>

            <?php if ($ht_images) : ?>
                <ul class="ht-rev__photos">
                    <?php foreach ($ht_images as $ht_image) : ?>
                        <li class="ht-rev__photo">
                            <img src="<?php echo esc_url($ht_image); ?>"
                                 width="120" height="161" alt="" loading="lazy" decoding="async">
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
