<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="profile" href="https://gmpg.org/xfn/11"/>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php
wp_body_open();

/* Pe prima pagina header-ul pluteste peste caruselul hero si devine alb la scroll. */
$ht_has_hero = ht_has_hero();

/*
 * Orasele sunt folosite doar in drawer-ul de pe mobil: in bara de sus locul lor
 * este luat de butonul "Catalog". Comutatorul are rost doar de la doua orase in sus.
 */
$cities = ht_header_cities();
$ht_many_cities = count($cities) > 1;
?>

<header class="ht-header<?php echo $ht_has_hero ? ' ht-header--overlay' : ''; ?>" id="htHeader">

    <div class="ht-wrapper ht-header__top">

        <?php /* burger si buton de catalog stau intr-un singur grup, ca sa nu strice grila din trei coloane */ ?>
        <div class="ht-header__left">

            <button class="ht-burger" type="button" data-ht-drawer-toggle aria-expanded="false" aria-controls="htDrawer">
                <?php ht_icon('burger'); ?>
                <span class="ht-visually-hidden"><?php esc_html_e('Meniu', 'herbal-therapy'); ?></span>
            </button>

            <?php ht_catalog_button(); ?>

        </div>

        <?php
        $ht_logo_id = get_theme_mod('custom_logo');
        $ht_logo = $ht_logo_id ? wp_get_attachment_image_src($ht_logo_id, 'full') : false;
        /* logo alb optional, folosit doar cat timp header-ul e transparent peste hero */
        $ht_logo_overlay = ($ht_has_hero && $ht_logo) ? ht_header_overlay_logo() : '';
        ?>
        <a class="ht-header__logo<?php echo $ht_logo_overlay ? ' ht-header__logo--dual' : ''; ?>"
           href="<?php echo esc_url(home_url('/')); ?>"
           aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <?php if ($ht_logo) : ?>
                <img class="ht-header__logo-img"
                     src="<?php echo esc_url($ht_logo[0]); ?>"
                     alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                     width="<?php echo esc_attr($ht_logo[1]); ?>"
                     height="<?php echo esc_attr($ht_logo[2]); ?>"/>
                <?php if ($ht_logo_overlay) : ?>
                    <img class="ht-header__logo-img ht-header__logo-img--overlay"
                         src="<?php echo esc_url($ht_logo_overlay); ?>"
                         alt="" aria-hidden="true"/>
                <?php endif; ?>
            <?php else : ?>
                <span class="ht-header__logo-text"><?php bloginfo('name'); ?></span>
            <?php endif; ?>
        </a>

        <nav class="ht-icons" aria-label="<?php esc_attr_e('Acțiuni rapide', 'herbal-therapy'); ?>">
            <ul class="ht-icons__list">
                <li>
                    <button class="ht-icons__link" type="button" data-ht-search-toggle aria-expanded="false"
                            aria-controls="htSearch">
                        <?php ht_icon('search-header'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Caută pe site', 'herbal-therapy'); ?></span>
                    </button>
                </li>
                <li>
                    <a class="ht-icons__link" href="<?php echo esc_url(ht_header_link('wishlist')); ?>">
                        <?php ht_icon('heart-header'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Favorite', 'herbal-therapy'); ?></span>
                        <?php ht_favorites_count_badge(); ?>
                    </a>
                </li>
                <li>
                    <a class="ht-icons__link" href="<?php echo esc_url(ht_header_link('account')); ?>">
                        <?php ht_icon('user-header'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Contul meu', 'herbal-therapy'); ?></span>
                    </a>
                </li>
                <li>
                    <?php /* fara JavaScript link-ul duce la pagina de cos; cu el, deschide panoul lateral */ ?>
                    <a class="ht-icons__link" href="<?php echo esc_url(ht_header_link('cart')); ?>"
                       data-ht-minicart-open>
                        <?php ht_icon('cart-header'); ?>
                        <span class="ht-visually-hidden"><?php esc_html_e('Coș', 'herbal-therapy'); ?></span>
                        <?php /* bulina cosului arata numarul si cand e gol, ca in Figma */ ?>
                        <span class="ht-icons__count" data-ht-cart-count><?php echo esc_html(ht_cart_count()); ?></span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <nav class="ht-header__nav" aria-label="<?php esc_attr_e('Meniu principal', 'herbal-therapy'); ?>">
        <div class="ht-wrapper">
            <?php ht_main_menu(); ?>
            <?php ht_language_switcher(); ?>
        </div>
    </nav>

    <?php ht_search_panel(); ?>

    <?php ht_catalog_panel(); ?>

</header>

<div class="ht-drawer" id="htDrawer" aria-hidden="true">
    <div class="ht-drawer__head">
        <span class="ht-drawer__title"><?php esc_html_e('Meniu', 'herbal-therapy'); ?></span>
        <button class="ht-drawer__close" type="button" data-ht-drawer-toggle>
            <?php ht_icon('close'); ?>
            <span class="ht-visually-hidden"><?php esc_html_e('Închide', 'herbal-therapy'); ?></span>
        </button>
    </div>
    <div class="ht-drawer__body">
        <?php ht_main_menu(); ?>

        <?php /* randul de meniu e ascuns sub 1025px, deci limbile se aleg de aici */ ?>
        <?php ht_language_drawer(); ?>

        <?php
        /*
         * Singurul loc in care se alege orasul: bara de sus arata butonul de
         * catalog, ca in Figma, deci lista sta desfasurata aici.
         */
        ?>
        <div class="ht-drawer__city">
            <span class="ht-drawer__city-title"><?php esc_html_e('Orașul tău', 'herbal-therapy'); ?></span>

            <?php if ($ht_many_cities) : ?>
                <ul class="ht-drawer__cities">
                    <?php foreach ($cities as $city) : ?>
                        <li>
                            <button class="ht-drawer__city-btn" type="button"
                                    data-ht-city="<?php echo esc_attr($city); ?>" aria-pressed="false">
                                <?php ht_icon('pin'); ?>
                                <span><?php echo esc_html($city); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <span class="ht-drawer__city-current">
                    <?php ht_icon('pin'); ?>
                    <span data-ht-city-label><?php echo esc_html(reset($cities)); ?></span>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ht-overlay" id="htOverlay"></div>

<?php
/* Carusel principal, doar pe prima pagina. Se poate apela ht_hero_slider() si din alte template-uri. */
if ($ht_has_hero) {
    ht_hero_slider();
}
?>
