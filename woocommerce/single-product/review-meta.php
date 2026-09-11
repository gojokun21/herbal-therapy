<?php
/**
 * Randul cu autorul, data si numarul de raspunsuri.
 *
 * Suprascrie woocommerce/templates/single-product/review-meta.php
 *
 * @package Herbal_Therapy
 * @version 3.4.0
 */

defined('ABSPATH') || exit;

global $comment;

$ht_verified = wc_review_is_from_verified_owner($comment->comment_ID);
$ht_show_badge = 'yes' === get_option('woocommerce_review_rating_verification_label') && $ht_verified;
$ht_replies = ht_review_replies($comment);

if ('0' === $comment->comment_approved) : ?>

    <p class="meta ht-rev__meta">
        <em class="woocommerce-review__awaiting-approval ht-rev__pending">
            <?php esc_html_e('Recenzia ta așteaptă aprobarea', 'herbal-therapy'); ?>
        </em>
    </p>

<?php else : ?>

    <p class="meta ht-rev__meta">
        <strong class="woocommerce-review__author ht-rev__author"><?php comment_author(); ?></strong>

        <time class="woocommerce-review__published-date ht-rev__date"
              datetime="<?php echo esc_attr(get_comment_date('c')); ?>">
            <?php echo esc_html(get_comment_date('d.m.Y')); ?>
        </time>

        <?php if ($ht_replies > 0) : ?>
            <a class="ht-rev__replies" href="<?php echo esc_url(get_comment_link($comment)); ?>">
                <?php
                printf(
                    /* translators: %s: numarul de raspunsuri. */
                    esc_html(_n('%s comentariu', '%s comentarii', $ht_replies, 'herbal-therapy')),
                    esc_html(number_format_i18n($ht_replies))
                );
                ?>
            </a>
        <?php endif; ?>

        <?php if ($ht_show_badge) : ?>
            <span class="woocommerce-review__verified verified ht-rev__badge">
                <?php esc_html_e('Cumpărător verificat', 'herbal-therapy'); ?>
            </span>
        <?php endif; ?>
    </p>

<?php endif; ?>
