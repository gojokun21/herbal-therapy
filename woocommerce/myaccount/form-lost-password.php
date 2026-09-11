<?php
/**
 * Recuperarea parolei.
 *
 * Suprascrie woocommerce/templates/myaccount/form-lost-password.php (5.5.0).
 * Fata de original, formularul e pus intr-un card ingust, ca la autentificare.
 *
 * @package Herbal_Therapy
 * @version 9.2.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_lost_password_form');

$ht_button_class = wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : '';
?>

<div class="ht-account__auth">

    <section class="ht-account__panel">

        <form method="post" class="woocommerce-ResetPassword lost_reset_password ht-form">

            <p class="ht-account__panel-text">
                <?php echo apply_filters('woocommerce_lost_password_message', esc_html__('Scrie numele de utilizator sau adresa de email. Îți trimitem pe email un link cu care îți setezi o parolă nouă.', 'herbal-therapy')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="user_login"><?php esc_html_e('Nume de utilizator sau email', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                <input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autocomplete="username" required aria-required="true" />
            </p>

            <?php do_action('woocommerce_lostpassword_form'); ?>

            <p class="woocommerce-form-row form-row ht-form__actions">
                <input type="hidden" name="wc_reset_password" value="true" />
                <button type="submit" class="woocommerce-Button button<?php echo esc_attr($ht_button_class); ?>" value="<?php esc_attr_e('Trimite link-ul', 'herbal-therapy'); ?>"><?php esc_html_e('Trimite link-ul', 'herbal-therapy'); ?></button>

                <a class="ht-form__link" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                    <?php esc_html_e('Înapoi la autentificare', 'herbal-therapy'); ?>
                </a>
            </p>

            <?php wp_nonce_field('lost_password', 'woocommerce-lost-password-nonce'); ?>

        </form>

    </section>

</div>

<?php
do_action('woocommerce_after_lost_password_form');
