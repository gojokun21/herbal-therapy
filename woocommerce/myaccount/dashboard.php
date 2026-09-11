<?php
/**
 * Panoul de control al contului.
 *
 * Suprascrie woocommerce/templates/myaccount/dashboard.php (4.4.0).
 *
 * Fata de sablonul original, cele doua paragrafe cu link-uri in text sunt
 * inlocuite cu un salut si carduri de acces rapid (ht_account_dashboard_cards()).
 * Hook-urile raman pe loc, ca extensiile sa isi gaseasca locul.
 *
 * @package Herbal_Therapy
 * @version 4.4.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<div class="ht-account__welcome">
    <p class="ht-account__hello">
        <?php
        printf(
            /* translators: %s: numele clientului. */
            esc_html__('Bună, %s!', 'herbal-therapy'),
            '<strong>' . esc_html(ht_account_display_name($current_user)) . '</strong>'
        );
        ?>
    </p>

    <p class="ht-account__hint">
        <?php esc_html_e('De aici îți urmărești comenzile, îți ții adresele la zi și îți schimbi datele contului.', 'herbal-therapy'); ?>
    </p>
</div>

<ul class="ht-account__cards">
    <?php foreach (ht_account_dashboard_cards() as $ht_key => $ht_card) : ?>
        <li class="ht-account__card ht-account__card--<?php echo esc_attr($ht_key); ?>">
            <a class="ht-account__card-link" href="<?php echo esc_url($ht_card['url']); ?>">
                <?php ht_icon($ht_card['icon'], 'ht-account__card-icon'); ?>

                <span class="ht-account__card-body">
                    <span class="ht-account__card-title"><?php echo esc_html($ht_card['title']); ?></span>
                    <span class="ht-account__card-text"><?php echo esc_html($ht_card['text']); ?></span>
                </span>

                <?php ht_icon('chevron', 'ht-account__card-arrow'); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php
/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */
do_action('woocommerce_account_dashboard');

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action('woocommerce_before_my_account');

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action('woocommerce_after_my_account');
