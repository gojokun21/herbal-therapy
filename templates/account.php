<?php
/**
 * Invelisul paginii de cont.
 *
 * Nu e un sablon de pagina din lista WordPress: il pune inc/account.php pe
 * template_include, pentru tot ce trece prin is_account_page(). Continutul
 * propriu-zis vine din shortcode, iar bucatile lui sunt in woocommerce/myaccount/.
 *
 * @package Herbal_Therapy
 */

get_header();

$ht_guest = !is_user_logged_in();

$ht_narrow = $ht_guest
    || is_wc_endpoint_url('lost-password')
    || is_wc_endpoint_url('reset-password');

/*
 * Pe ecranul de logare / inregistrare titlul H1 se randeaza in coloana
 * formularului (woocommerce/myaccount/form-login.php, dupa macheta Figma),
 * asa ca antetul de aici se sare. Lost/reset password isi pastreaza antetul.
 */
$ht_login_screen = $ht_guest
    && !is_wc_endpoint_url('lost-password')
    && !is_wc_endpoint_url('reset-password');

$ht_classes = 'site-main ht-account';
$ht_classes .= $ht_guest ? ' ht-account--guest' : '';
$ht_classes .= $ht_narrow ? ' ht-account--narrow' : '';
?>

<main id="primary" class="<?php echo esc_attr($ht_classes); ?>">
    <div class="ht-wrapper">

        <?php
        while (have_posts()) :
            the_post();
            ?>

            <?php if (!$ht_login_screen) : ?>
                <header class="ht-account__head">
                    <h1 class="ht-account__title"><?php echo esc_html(ht_account_title()); ?></h1>

                    <?php if ($ht_guest) : ?>
                        <p class="ht-account__lead">
                            <?php esc_html_e('Autentifică-te ca să vezi comenzile, adresele și produsele salvate.', 'herbal-therapy'); ?>
                        </p>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <?php the_content(); ?>

        <?php endwhile; ?>

    </div>
</main>

<?php
get_footer();
