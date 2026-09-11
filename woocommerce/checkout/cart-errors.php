<?php
/**
 * Cosul are probleme - se afiseaza in locul formularului de finalizare.
 *
 * Suprascrie checkout/cart-errors.php din WooCommerce 3.5.0. Mesajul cu motivul
 * il tipareste WooCommerce deasupra, in zona de notificari; aici ramane doar
 * drumul inapoi. Tema nu are pagina de cos - cosul e panoul lateral - deci
 * butonul duce in catalog.
 *
 * @package Herbal_Therapy
 * @version 3.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ht-checkout-empty">

    <p class="ht-checkout-empty__title"><?php esc_html_e('Coșul are nevoie de o verificare', 'herbal-therapy'); ?></p>

    <p class="ht-checkout-empty__text">
        <?php esc_html_e('Unele produse nu mai pot fi comandate așa cum sunt în coș. Deschide coșul, ajustează-le și revino la finalizare.', 'herbal-therapy'); ?>
    </p>

    <?php do_action('woocommerce_cart_has_errors'); ?>

    <a class="ht-checkout-empty__button" href="<?php echo esc_url(ht_minicart_shop_url()); ?>">
        <?php esc_html_e('Către catalog', 'herbal-therapy'); ?>
    </a>

</div>
