<?php
/**
 * Autentificare si inregistrare.
 *
 * Suprascrie woocommerce/templates/myaccount/form-login.php (9.9.0).
 *
 * Fata de sablonul original: layout dupa macheta Figma (nodul 122:2378) -
 * imagine rotunjita in stanga, in dreapta titlul paginii si formularul, fara
 * card cu chenar. Sub butonul de autentificare sta link-ul "Înregistrare",
 * care comuta pe formularul de cont nou (account.js); account.css deseneaza
 * layout-ul. Titlul H1 se randeaza aici, nu in templates/account.php. Cand
 * inregistrarea e oprita din setari, ramane doar formularul de autentificare.
 * Campurile, hook-urile si nonce-urile raman neatinse.
 *
 * @package Herbal_Therapy
 * @version 9.9.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

do_action('woocommerce_before_customer_login_form');

$ht_registration = 'yes' === get_option('woocommerce_enable_myaccount_registration');
$ht_button_class = wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : '';

/* Dupa un formular de inregistrare respins ramanem pe panoul de inregistrare. */
$ht_active = ($ht_registration && !empty($_POST['register'])) ? 'register' : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
?>

<div class="ht-account__auth ht-account__auth--media" id="customer_login" data-active="<?php echo esc_attr($ht_active); ?>">

    <div class="ht-account__auth-media" aria-hidden="true">
        <img src="<?php echo esc_url(ht_asset_uri('/assets/img/account/contul-meu.webp')); ?>" alt="" />
    </div>

    <section class="ht-account__panel ht-account__auth-card">

        <h1 class="ht-account__auth-title"><?php echo esc_html(ht_account_title()); ?></h1>

        <?php if ($ht_registration) : ?>
            <div class="ht-account__tabs" role="tablist" aria-label="<?php esc_attr_e('Autentificare sau cont nou', 'herbal-therapy'); ?>">
                <button type="button" class="ht-account__tab<?php echo 'login' === $ht_active ? ' is-active' : ''; ?>" id="ht-auth-tab-login" data-tab="login" role="tab" aria-controls="ht-auth-panel-login" aria-selected="<?php echo 'login' === $ht_active ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Autentificare', 'herbal-therapy'); ?>
                </button>
                <button type="button" class="ht-account__tab<?php echo 'register' === $ht_active ? ' is-active' : ''; ?>" id="ht-auth-tab-register" data-tab="register" role="tab" aria-controls="ht-auth-panel-register" aria-selected="<?php echo 'register' === $ht_active ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Înregistrare', 'herbal-therapy'); ?>
                </button>
            </div>
        <?php endif; ?>

        <div class="ht-account__tab-panel" id="ht-auth-panel-login"<?php echo $ht_registration ? ' role="tabpanel" aria-labelledby="ht-auth-tab-login"' : ''; ?><?php echo 'login' === $ht_active ? '' : ' hidden'; ?>>

            <form class="woocommerce-form woocommerce-form-login login ht-form" method="post" novalidate>

                <?php do_action('woocommerce_login_form_start'); ?>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="username"><?php esc_html_e('Nume utilizator sau adresă email', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" placeholder="<?php esc_attr_e('Introduceți datele dvs.', 'herbal-therapy'); ?>" value="<?php echo (!empty($_POST['username']) && is_string($_POST['username'])) ? esc_attr(wp_unslash($_POST['username'])) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
                </p>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="password"><?php esc_html_e('Parola', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                    <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" placeholder="<?php esc_attr_e('Introduceți parola', 'herbal-therapy'); ?>" required aria-required="true" />
                </p>

                <?php do_action('woocommerce_login_form'); ?>

                <div class="ht-form__row ht-form__row--between">
                    <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme ht-form__check">
                        <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
                        <span><?php esc_html_e('Ține-mă minte', 'herbal-therapy'); ?></span>
                    </label>

                    <a class="ht-form__link" href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                        <?php esc_html_e('Ai uitat parola?', 'herbal-therapy'); ?>
                    </a>
                </div>

                <p class="form-row ht-form__actions">
                    <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
                    <button type="submit" class="woocommerce-button button woocommerce-form-login__submit<?php echo esc_attr($ht_button_class); ?>" name="login" value="<?php esc_attr_e('Autentificare', 'herbal-therapy'); ?>"><?php esc_html_e('Autentificare', 'herbal-therapy'); ?></button>
                </p>

                <?php do_action('woocommerce_login_form_end'); ?>

            </form>

            <?php if ($ht_registration) : ?>
                <p class="ht-account__auth-switch">
                    <?php esc_html_e('Nu ești înregistrat?', 'herbal-therapy'); ?>
                    <button type="button" class="ht-account__auth-switch-link" data-tab="register"><?php esc_html_e('Înregistrare', 'herbal-therapy'); ?></button>
                </p>
            <?php endif; ?>

        </div>

        <?php if ($ht_registration) : ?>

            <div class="ht-account__tab-panel" id="ht-auth-panel-register" role="tabpanel" aria-labelledby="ht-auth-tab-register"<?php echo 'register' === $ht_active ? '' : ' hidden'; ?>>

                <p class="ht-account__panel-text">
                    <?php esc_html_e('Creează-ți un cont ca să comanzi mai repede și să îți vezi istoricul comenzilor.', 'herbal-therapy'); ?>
                </p>

                <form method="post" class="woocommerce-form woocommerce-form-register register ht-form" <?php do_action('woocommerce_register_form_tag'); ?>>

                    <?php do_action('woocommerce_register_form_start'); ?>

                    <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>

                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="reg_username"><?php esc_html_e('Nume de utilizator', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" placeholder="<?php esc_attr_e('Alege un nume de utilizator', 'herbal-therapy'); ?>" value="<?php echo (!empty($_POST['username'])) ? esc_attr(wp_unslash($_POST['username'])) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
                        </p>

                    <?php endif; ?>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="reg_email"><?php esc_html_e('Adresa de email', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                        <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" placeholder="<?php esc_attr_e('exemplu@email.com', 'herbal-therapy'); ?>" value="<?php echo (!empty($_POST['email'])) ? esc_attr(wp_unslash($_POST['email'])) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
                    </p>

                    <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>

                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="reg_password"><?php esc_html_e('Parola', 'herbal-therapy'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Obligatoriu', 'herbal-therapy'); ?></span></label>
                            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" placeholder="<?php esc_attr_e('Alege o parolă', 'herbal-therapy'); ?>" required aria-required="true" />
                        </p>

                    <?php else : ?>

                        <p class="ht-form__note"><?php esc_html_e('Vei primi pe email un link pentru setarea parolei.', 'herbal-therapy'); ?></p>

                    <?php endif; ?>

                    <?php do_action('woocommerce_register_form'); ?>

                    <p class="woocommerce-form-row form-row ht-form__actions">
                        <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
                        <button type="submit" class="woocommerce-Button woocommerce-button button<?php echo esc_attr($ht_button_class); ?> woocommerce-form-register__submit" name="register" value="<?php esc_attr_e('Creează cont', 'herbal-therapy'); ?>"><?php esc_html_e('Creează cont', 'herbal-therapy'); ?></button>
                    </p>

                    <?php do_action('woocommerce_register_form_end'); ?>

                </form>

                <p class="ht-account__auth-switch">
                    <?php esc_html_e('Ai deja cont?', 'herbal-therapy'); ?>
                    <button type="button" class="ht-account__auth-switch-link" data-tab="login"><?php esc_html_e('Autentificare', 'herbal-therapy'); ?></button>
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php do_action('woocommerce_after_customer_login_form'); ?>
