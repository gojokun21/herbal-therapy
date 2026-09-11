<?php
/**
 * Template Name: Reduceri
 *
 * Listeaza produsele aflate la reducere, cu acelasi card ca in restul
 * magazinului. Paginarea merge prin parametrul "pg" din adresa, ca sa nu se
 * loveasca de paginarea de continut a paginilor obisnuite (<!--nextpage-->).
 *
 * @package Herbal_Therapy
 */

get_header();

$ht_shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$ht_paged    = isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- doar paginare, fara actiuni.

$ht_products = array();
$ht_total    = 0;
$ht_pages    = 0;

if (function_exists('wc_get_products')) {
    /* doar id-urile aflate acum la reducere; lista e tinuta in cache de WooCommerce */
    $ht_sale_ids = array_filter(array_map('intval', wc_get_product_ids_on_sale()));

    if ($ht_sale_ids) {
        $ht_query = wc_get_products(array(
            'status'     => 'publish',
            'visibility' => 'catalog',
            'include'    => $ht_sale_ids,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'limit'      => 12,
            'page'       => $ht_paged,
            'paginate'   => true,
        ));

        $ht_products = $ht_query->products;
        $ht_total    = (int)$ht_query->total;
        $ht_pages    = (int)$ht_query->max_num_pages;
    }
}

/* butonul de cos din card lucreaza prin AJAX si in afara paginilor de magazin */
if ($ht_products && function_exists('WC')) {
    wp_enqueue_script('wc-add-to-cart');
}
?>

<main id="primary" class="site-main ht-reduceri">
    <div class="ht-wrapper">

        <header class="ht-reduceri__head">
            <h1 class="ht-reduceri__title"><?php echo esc_html(get_the_title()); ?></h1>

            <?php if ($ht_total) : ?>
                <p class="ht-reduceri__count">
                    <?php
                    printf(
                        /* translators: %s: numarul de produse la reducere. */
                        esc_html(_n('%s produs la reducere', '%s produse la reducere', $ht_total, 'herbal-therapy')),
                        esc_html(number_format_i18n($ht_total))
                    );
                    ?>
                </p>
            <?php endif; ?>
        </header>

        <?php
        while (have_posts()) :
            the_post();

            if ('' !== trim(get_the_content())) :
                ?>
                <div class="ht-reduceri__intro"><?php the_content(); ?></div>
            <?php
            endif;
        endwhile;
        ?>

        <?php if ($ht_products) : ?>

            <ul class="ht-reduceri__grid" data-ht-products>
                <?php foreach ($ht_products as $ht_index => $ht_product) : ?>
                    <li class="ht-reduceri__item">
                        <?php ht_product_card(ht_product_card_data($ht_product), $ht_index); ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            if ($ht_pages > 1) :
                $ht_links = paginate_links(array(
                    'base'      => get_permalink() . '%_%',
                    'format'    => '?pg=%#%',
                    'current'   => $ht_paged,
                    'total'     => $ht_pages,
                    'type'      => 'array',
                    'end_size'  => 1,
                    'mid_size'  => 1,
                    'prev_text' => ht_get_icon('chevron', 'ht-reduceri__pager-icon ht-reduceri__pager-icon--prev'),
                    'next_text' => ht_get_icon('chevron', 'ht-reduceri__pager-icon'),
                ));

                if ($ht_links) :
                    ?>
                    <nav class="ht-reduceri__pager" aria-label="<?php esc_attr_e('Paginare reduceri', 'herbal-therapy'); ?>">
                        <?php foreach ($ht_links as $ht_link) : ?>
                            <?php echo $ht_link; // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit de paginate_links(). ?>
                        <?php endforeach; ?>
                    </nav>
                <?php
                endif;
            endif;
            ?>

        <?php else : ?>

            <div class="ht-reduceri__empty">
                <?php ht_icon('bag', 'ht-reduceri__empty-icon'); ?>

                <p class="ht-reduceri__empty-text">
                    <?php esc_html_e('Momentan nu avem produse la reducere.', 'herbal-therapy'); ?>
                </p>

                <p class="ht-reduceri__empty-hint">
                    <?php esc_html_e('Revino în curând sau vezi restul produselor din magazin.', 'herbal-therapy'); ?>
                </p>

                <a class="ht-reduceri__empty-link" href="<?php echo esc_url($ht_shop_url); ?>">
                    <?php esc_html_e('Vezi produsele', 'herbal-therapy'); ?>
                </a>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php
get_footer();
