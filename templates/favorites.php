<?php
/**
 * Template Name: Favorite
 *
 * Listeaza produsele salvate de vizitator (inc/favorites.php), cu acelasi card
 * ca in restul magazinului. Scoaterea unui produs se face din inima cardului,
 * iar assets/js/favorites.js scoate cardul din grila.
 *
 * @package Herbal_Therapy
 */

get_header();

$ht_products = ht_favorites_products();
$ht_shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');

/* butonul de cos din card lucreaza prin AJAX si in afara paginilor de magazin */
if ($ht_products && function_exists('WC')) {
    wp_enqueue_script('wc-add-to-cart');
}
?>

<main id="primary" class="site-main ht-favorites">
    <div class="ht-wrapper">

        <header class="ht-favorites__head">
            <h1 class="ht-favorites__title"><?php echo esc_html(get_the_title()); ?></h1>

            <button class="ht-favorites__clear" type="button" data-ht-favorites-clear
                    <?php echo $ht_products ? '' : 'hidden'; ?>>
                <?php esc_html_e('Golește lista', 'herbal-therapy'); ?>
            </button>
        </header>

        <?php
        while (have_posts()) :
            the_post();

            if ('' !== trim(get_the_content())) :
                ?>
                <div class="ht-favorites__intro"><?php the_content(); ?></div>
            <?php
            endif;
        endwhile;
        ?>

        <ul class="ht-favorites__grid" data-ht-products data-ht-favorites-grid
            <?php echo $ht_products ? '' : 'hidden'; ?>>
            <?php foreach ($ht_products as $ht_index => $ht_product) : ?>
                <li class="ht-favorites__item" data-favorite-item="<?php echo esc_attr($ht_product->get_id()); ?>">
                    <?php ht_product_card(ht_product_card_data($ht_product), $ht_index); ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="ht-favorites__empty" data-ht-favorites-empty <?php echo $ht_products ? 'hidden' : ''; ?>>
            <?php ht_icon('heart-card', 'ht-favorites__empty-icon'); ?>

            <p class="ht-favorites__empty-text">
                <?php esc_html_e('Nu ai niciun produs salvat.', 'herbal-therapy'); ?>
            </p>

            <p class="ht-favorites__empty-hint">
                <?php esc_html_e('Apasă inima de pe un produs ca să îl găsești mai târziu aici.', 'herbal-therapy'); ?>
            </p>

            <a class="ht-favorites__empty-link" href="<?php echo esc_url($ht_shop_url); ?>">
                <?php esc_html_e('Vezi produsele', 'herbal-therapy'); ?>
            </a>
        </div>

    </div>
</main>

<?php
get_footer();
