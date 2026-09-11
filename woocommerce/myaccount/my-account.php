<?php
/**
 * Pagina de cont - impartirea in bara laterala si continut.
 *
 * Suprascrie woocommerce/templates/myaccount/my-account.php (3.5.0).
 * Se randeaza doar pentru utilizatorii autentificati; vizitatorii primesc
 * form-login.php.
 *
 * @package Herbal_Therapy
 * @version 3.5.0
 */

defined('ABSPATH') || exit;
?>

<div class="ht-account__layout">

    <?php
    /**
     * Bara laterala: cardul utilizatorului + meniul (myaccount/navigation.php).
     *
     * @since 2.6.0
     */
    do_action('woocommerce_account_navigation');
    ?>

    <div class="woocommerce-MyAccount-content ht-account__content">
        <?php
        /**
         * Continutul endpoint-ului curent.
         *
         * @since 2.6.0
         */
        do_action('woocommerce_account_content');
        ?>
    </div>

</div>
