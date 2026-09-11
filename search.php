<?php
/**
 * Rezultatele cautarii.
 *
 * Produsele sunt pe primul tab si folosesc acelasi card si aceeasi grila ca
 * listarile de magazin; articolele raman pe al doilea tab. Ce anume aduce
 * interogarea principala se decide in inc/search.php (ht_search_pre_get_posts).
 *
 * @package Herbal_Therapy
 */

get_header();

$ht_query = get_search_query();
$ht_tab = ht_search_tab();
$ht_shop = ht_search_has_shop();
$ht_has_query = '' !== trim($ht_query);

$ht_products_count = ($ht_shop && $ht_has_query) ? ht_search_count('product', $ht_query) : 0;
$ht_posts_count = $ht_has_query ? ht_search_count(array('post', 'page'), $ht_query) : 0;

/* citit o singura data: have_posts() deruleaza bucla inapoi cand o epuizezi */
$ht_found = $ht_has_query && have_posts();

/* butonul de cos din card cere scriptul WooCommerce, care nu se incarca aici implicit */
if ($ht_shop && 'products' === $ht_tab) {
    wp_enqueue_script('wc-add-to-cart');
}
?>

<main id="primary" class="site-main ht-results">
    <div class="ht-wrapper">

        <header class="ht-results__head">
            <?php if ($ht_has_query) : ?>
                <h1 class="ht-results__title">
                    <?php esc_html_e('Rezultate pentru', 'herbal-therapy'); ?>
                    <em>&laquo;<?php echo esc_html($ht_query); ?>&raquo;</em>
                </h1>
            <?php else : ?>
                <h1 class="ht-results__title"><?php esc_html_e('Caută în magazin', 'herbal-therapy'); ?></h1>
            <?php endif; ?>

            <?php get_search_form(); ?>
        </header>

        <?php if ($ht_has_query && ($ht_products_count || $ht_posts_count)) : ?>
            <nav class="ht-results__tabs" aria-label="<?php esc_attr_e('Tipul rezultatelor', 'herbal-therapy'); ?>">
                <?php if ($ht_shop) : ?>
                    <a class="ht-results__tab<?php echo 'products' === $ht_tab ? ' is-active' : ''; ?>"
                       href="<?php echo esc_url(ht_search_url($ht_query)); ?>">
                        <?php esc_html_e('Produse', 'herbal-therapy'); ?>
                        <span class="ht-results__tab-count"><?php echo esc_html($ht_products_count); ?></span>
                    </a>
                <?php endif; ?>

                <a class="ht-results__tab<?php echo 'posts' === $ht_tab ? ' is-active' : ''; ?>"
                   href="<?php echo esc_url(ht_search_url($ht_query, 'posts')); ?>">
                    <?php esc_html_e('Articole', 'herbal-therapy'); ?>
                    <span class="ht-results__tab-count"><?php echo esc_html($ht_posts_count); ?></span>
                </a>
            </nav>
        <?php endif; ?>

        <?php if ($ht_found) : ?>

            <?php if ('products' === $ht_tab) : ?>

                <ul class="ht-shop__grid" data-ht-products>
                    <?php
                    $ht_index = 0;

                    while (have_posts()) :
                        the_post();
                        $ht_product = wc_get_product(get_the_ID());

                        if (!$ht_product) {
                            continue;
                        }
                        ?>
                        <li class="ht-shop__item">
                            <?php ht_product_card(ht_product_card_data($ht_product), $ht_index); ?>
                        </li>
                        <?php
                        $ht_index++;
                    endwhile;
                    ?>
                </ul>

            <?php else : ?>

                <?php /* acelasi card ca in caruselul de articole de pe prima pagina */ ?>
                <div class="ht-results__posts ht-blog">
                    <?php
                    while (have_posts()) :
                        the_post();
                        ht_blog_card(ht_blog_card_data(get_post()));
                    endwhile;
                    ?>
                </div>

            <?php endif; ?>

            <?php ht_pagination(); ?>

        <?php else : ?>

            <div class="ht-results__none">
                <?php if ($ht_has_query) : ?>
                    <p class="ht-results__none-title">
                        <?php esc_html_e('Nu am găsit nimic pentru această căutare', 'herbal-therapy'); ?>
                    </p>
                    <p class="ht-results__none-text">
                        <?php esc_html_e('Verifică scrierea, încearcă un cuvânt mai scurt sau pornește de la o categorie.', 'herbal-therapy'); ?>
                    </p>
                <?php else : ?>
                    <p class="ht-results__none-title">
                        <?php esc_html_e('Scrie ce cauți', 'herbal-therapy'); ?>
                    </p>
                    <p class="ht-results__none-text">
                        <?php esc_html_e('Poți căuta după denumirea produsului, categorie sau codul de pe ambalaj.', 'herbal-therapy'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php $ht_categories = ht_search_top_categories(8); ?>
            <?php if ($ht_categories) : ?>
                <div class="ht-results__suggestions">
                    <h2 class="ht-results__suggestions-title"><?php esc_html_e('Categorii populare', 'herbal-therapy'); ?></h2>
                    <ul class="ht-results__cats">
                        <?php foreach ($ht_categories as $ht_term) : ?>
                            <?php $ht_link = get_term_link($ht_term); ?>
                            <?php if (!is_wp_error($ht_link)) : ?>
                                <li><a href="<?php echo esc_url($ht_link); ?>"><?php echo esc_html($ht_term->name); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <?php
    /* fara rezultate, macar plecam cu ceva din catalog in fata */
    if ($ht_shop && !$ht_found) {
        ht_products_carousel(array(
            'title' => __('Poate te interesează', 'herbal-therapy'),
            'limit' => 8,
        ));
    }
    ?>
</main>

<?php
get_footer();
