<?php
/**
 * Un produs din listari.
 *
 * Suprascrie woocommerce/templates/content-product.php
 *
 * Randeaza cardul temei (ht_product_card), acelasi cu cel din caruselul de pe
 * prima pagina, in locul markup-ului implicit construit din hook-uri. Cardul
 * acopera imaginea, titlul, pretul si butonul de cos, deci callback-urile
 * pluginului care le desenau sunt scoase in inc/woocommerce.php.
 *
 * Hook-urile raman insa pe pozitii, in aceeasi ordine ca in sablonul original:
 * pe ele isi pun extensiile etichetele, quick view-ul, compararea si listele de
 * dorinte. Implicit nu mai are nimeni nimic legat de ele, deci nu produc niciun
 * markup in plus.
 *
 * @package Herbal_Therapy
 * @version 9.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $product;

if (!is_a($product, 'WC_Product') || !$product->is_visible()) {
    return;
}

/* citit inainte de wc_product_class(), care incrementeaza contorul buclei */
$ht_index = (int)wc_get_loop_prop('loop', 0);
?>
<li <?php wc_product_class('ht-shop__item', $product); ?>>
    <?php
    do_action('woocommerce_before_shop_loop_item');
    do_action('woocommerce_before_shop_loop_item_title');

    ht_product_card(ht_product_card_data($product), $ht_index);

    do_action('woocommerce_shop_loop_item_title');
    do_action('woocommerce_after_shop_loop_item_title');
    do_action('woocommerce_after_shop_loop_item');
    ?>
</li>
