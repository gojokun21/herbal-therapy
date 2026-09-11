<?php
/**
 * Deschiderea listarii de produse.
 *
 * Suprascrie woocommerce/templates/loop/loop-start.php
 *
 * data-ht-products porneste galeriile din card, favoritele si butoanele de cos
 * (assets/js/products.js) - aceleasi ca la caruselul de pe prima pagina.
 *
 * @package Herbal_Therapy
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<ul class="ht-shop__grid" data-ht-products>
