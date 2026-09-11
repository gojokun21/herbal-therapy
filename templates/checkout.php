<?php
/**
 * Invelisul paginii de finalizare a comenzii.
 *
 * Nu e un sablon de pagina din lista WordPress: il pune inc/checkout.php pe
 * template_include, pentru tot ce trece prin is_checkout(). Continutul propriu-zis
 * vine din shortcode, iar bucatile lui sunt in woocommerce/checkout/.
 *
 * @package Herbal_Therapy
 */

get_header();

/* endpoint-urile traiesc pe aceeasi pagina, dar arata altfel */
$ht_received = is_wc_endpoint_url('order-received');
$ht_pay = is_wc_endpoint_url('order-pay');

/* cosul gol: redirectul implicit e oprit din inc/checkout.php, deci il tratam aici */
$ht_empty = !$ht_received && !$ht_pay && (!WC()->cart || WC()->cart->is_empty());

$ht_classes = 'site-main ht-checkout';
$ht_classes .= $ht_received ? ' ht-checkout--received' : '';
$ht_classes .= $ht_pay ? ' ht-checkout--pay' : '';
$ht_classes .= $ht_empty ? ' ht-checkout--empty' : '';
?>

<main id="primary" class="<?php echo esc_attr($ht_classes); ?>">
    <div class="ht-wrapper">

        <header class="ht-checkout__head">
            <h1 class="ht-checkout__title"><?php echo esc_html(ht_checkout_title()); ?></h1>
        </header>

        <?php if ($ht_empty) : ?>

            <div class="ht-checkout-empty">
                <p class="ht-checkout-empty__title"><?php esc_html_e('Coșul tău este gol', 'herbal-therapy'); ?></p>
                <p class="ht-checkout-empty__text">
                    <?php esc_html_e('Alege produsele care îți plac și revino aici ca să finalizezi comanda.', 'herbal-therapy'); ?>
                </p>
                <a class="ht-checkout-empty__button" href="<?php echo esc_url(ht_minicart_shop_url()); ?>">
                    <?php esc_html_e('Către catalog', 'herbal-therapy'); ?>
                </a>
            </div>

        <?php else : ?>

            <?php
            while (have_posts()) :
                the_post();
                the_content();
            endwhile;
            ?>

        <?php endif; ?>

    </div>
</main>

<?php
get_footer();
