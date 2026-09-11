<?php
/**
 * Continutul paginii de produs.
 *
 * Suprascrie woocommerce/templates/content-single-product.php
 *
 * Blocul de sus (galerie + informatii) e desenat de tema, prin
 * ht_single_product_top(). Callback-urile cu care il desena pluginul sunt scoase
 * in inc/single-product.php, dar hook-urile 'woocommerce_before_single_product_summary'
 * si 'woocommerce_single_product_summary' se declanseaza in continuare, de acolo,
 * ca sa aiba unde scrie extensiile. Ce vine dupa (descriere, produse asociate)
 * ramane pe hook-urile WooCommerce.
 *
 * @package Herbal_Therapy
 * @version 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $product;

if (post_password_required()) {
    echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup din nucleu.

    return;
}

do_action('woocommerce_before_single_product');
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class('ht-pp', $product); ?>>

    <?php
    /*
     * Deseneaza galeria si coloana de informatii si declanseaza pe parcurs
     * 'woocommerce_before_single_product_summary' si 'woocommerce_single_product_summary'
     * (vezi inc/single-product.php). Datele structurate vin de pe al doilea, de la
     * prioritatea 60, ca in randarea implicita - nu mai e nevoie de un apel aparte.
     */
    ht_single_product_top($product);

    /* descrierea, produsele complementare si cele asociate */
    do_action('woocommerce_after_single_product_summary');
    ?>
</div>
<?php
do_action('woocommerce_after_single_product');
