<?php
/**
 * Setarea unei parole noi.
 *
 * Suprascrie woocommerce/templates/myaccount/form-reset-password.php (9.5.0).
 * Fata de original, formularul e pus in acelasi card ingust ca la autentificare.
 *
 * @package Herbal_Therapy
 * @version 9.2.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_reset_password_form');

$ht_button_class = wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : '';
?>

<div class="ht-account__auth">

    <section class="ht-account__panel">

        <form method="post" class="woocommerce-ResetPassword lost_reset_password ht-form">

            <p class="ht-account__panel-text">
                <?php echo apply_filters('woocommerce_reset_password_message', esc_html__('Alege o parolă nouă pentru contul tău.', 'herbal-therapy')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="password_1"><?php esc_html_e('Parolă nouă', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_1" id="password_1" autocomplete="new-password" required aria-required="true" />
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="password_2"><?php esc_html_e('Repetă parola nouă', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_2" id="password_2" autocomplete="new-password" required aria-required="true" />
            </p>

            <input type="hidden" name="reset_key" value="<?php echo esc_attr($args['key']); ?>" />
            <input type="hidden" name="reset_login" value="<?php echo esc_attr($args['login']); ?>" />

            <?php do_action('woocommerce_resetpassword_form'); ?>

            <p class="woocommerce-form-row form-row ht-form__actions">
                <input type="hidden" name="wc_reset_password" value="true" />
                <button type="submit" class="woocommerce-Button button<?php echo esc_attr($ht_button_class); ?>" value="<?php esc_attr_e('Salvează parola', 'herbal-therapy'); ?>"><?php esc_html_e('Salvează parola', 'herbal-therapy'); ?></button>
            </p>

            <?php wp_nonce_field('reset_password', 'woocommerce-reset-password-nonce'); ?>

        </form>

    </section>

</div>

<?php
do_action('woocommerce_after_reset_password_form');
