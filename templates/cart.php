<?php
/**
 * Invelisul paginii de cos - "Produse in cosul tau".
 *
 * Nu e un sablon de pagina din lista WordPress: il pune inc/cart.php pe
 * template_include, pentru tot ce trece prin is_cart(). Continutul paginii
 * (shortcode sau bloc) nu se foloseste: lista si sumarul sunt desenate de tema.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main ht-cart">
    <div class="ht-wrapper">

        <?php ht_cart_breadcrumb(); ?>

        <h1 class="ht-cart__title"><?php esc_html_e('Produse în coșul tău', 'herbal-therapy'); ?></h1>

        <?php
        /* notificarile WooCommerce (cupon aplicat din URL, produs indisponibil) */
        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }

        echo ht_cart_body(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern.
        ?>

    </div>
</main>

<?php
get_footer();
