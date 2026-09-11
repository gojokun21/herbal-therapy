<?php
/**
 * Bara laterala a contului: cardul utilizatorului si meniul.
 *
 * Suprascrie woocommerce/templates/myaccount/navigation.php (9.3.0).
 *
 * Fata de sablonul original: fiecare intrare primeste o iconita
 * (ht_account_menu_icon()), iar inaintea dezautentificarii intra link-ul catre
 * pagina de favorite a temei - nu e endpoint WooCommerce, deci nu vine prin
 * woocommerce_account_menu_items; se adauga aici, sub cheia 'ht-favorites',
 * dupa care filtrul 'ht_account_menu_items' lasa extensiile (ex. pluginul
 * Herbal B2B) sa se aseze fata de el.
 *
 * @package Herbal_Therapy
 * @version 9.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_before_account_navigation');

$ht_user = wp_get_current_user();
$ht_items = wc_get_account_menu_items();

/* dezautentificarea sta la final, sub o linie - o scoatem din lista si o randam separat */
$ht_logout = null;

if (isset($ht_items['customer-logout'])) {
    $ht_logout = $ht_items['customer-logout'];
    unset($ht_items['customer-logout']);
}

/* favoritele, ca intrare obisnuita; ordinea finala e la mana filtrului */
$ht_items['ht-favorites'] = __('Favorite', 'herbal-therapy');
$ht_items = apply_filters('ht_account_menu_items', $ht_items);
?>

<aside class="ht-account__aside">

    <div class="ht-account__user">
        <span class="ht-account__avatar" aria-hidden="true"><?php echo esc_html(ht_account_initials($ht_user)); ?></span>

        <span class="ht-account__identity">
            <span class="ht-account__name"><?php echo esc_html(ht_account_display_name($ht_user)); ?></span>
            <span class="ht-account__email"><?php echo esc_html($ht_user->user_email); ?></span>
        </span>
    </div>

    <nav class="woocommerce-MyAccount-navigation ht-account__nav"
         aria-label="<?php esc_attr_e('Meniul contului', 'herbal-therapy'); ?>">
        <ul class="ht-account__menu">
            <?php foreach ($ht_items as $ht_endpoint => $ht_label) : ?>
                <?php
                $ht_icon = ht_account_menu_icon($ht_endpoint);
                $ht_url = 'ht-favorites' === $ht_endpoint
                    ? ht_header_link('wishlist')
                    : wc_get_account_endpoint_url($ht_endpoint);
                ?>

                <li class="<?php echo esc_attr(wc_get_account_menu_item_classes($ht_endpoint)); ?> ht-account__menu-item">
                    <a class="ht-account__menu-link" href="<?php echo esc_url($ht_url); ?>"
                        <?php echo wc_is_current_account_menu_item($ht_endpoint) ? 'aria-current="page"' : ''; ?>>
                        <?php
                        if ('' !== $ht_icon) {
                            ht_icon($ht_icon, 'ht-account__menu-icon');
                        }
                        ?>
                        <span><?php echo esc_html($ht_label); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>

            <?php if (null !== $ht_logout) : ?>
                <li class="<?php echo esc_attr(wc_get_account_menu_item_classes('customer-logout')); ?> ht-account__menu-item ht-account__menu-item--logout">
                    <a class="ht-account__menu-link" href="<?php echo esc_url(wc_get_account_endpoint_url('customer-logout')); ?>">
                        <?php ht_icon('logout', 'ht-account__menu-icon'); ?>
                        <span><?php echo esc_html($ht_logout); ?></span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

</aside>

<?php do_action('woocommerce_after_account_navigation'); ?>
