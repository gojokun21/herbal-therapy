<?php
/**
 * Configurarea temei: suporturi, meniuri, dimensiuni de imagine.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Suporturile temei.
 */
function ht_setup()
{
    load_theme_textdomain('herbal-therapy', HT_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('automatic-feed-links');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('customize-selective-refresh-widgets');

    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));

    add_theme_support('custom-logo', array(
        'height'               => 100,
        'width'                => 300,
        'flex-width'           => true,
        'flex-height'          => true,
        'unlink-homepage-logo' => true,
    ));

    set_post_thumbnail_size(825, 510, true);
    add_image_size('ht-card', 640, 640, true);

    register_nav_menus(array(
        'primary-menu' => __('Meniu principal', 'herbal-therapy'),
        'footer-menu'  => __('Meniu footer', 'herbal-therapy'),
        'top-menu'     => __('Meniu de sus', 'herbal-therapy'),
    ));

    /* extrasul e util si pe pagini (carduri, listari) */
    add_post_type_support('page', 'excerpt');
}

add_action('after_setup_theme', 'ht_setup');

/**
 * Latimea maxima a continutului (folosita de WP pentru embed-uri si imagini).
 */
function ht_content_width()
{
    $GLOBALS['content_width'] = apply_filters('ht_content_width', 1440);
}

add_action('after_setup_theme', 'ht_content_width', 0);

/**
 * Zonele de widget-uri.
 */
function ht_widgets_init()
{
    register_sidebar(array(
        'name'          => __('Bara laterală', 'herbal-therapy'),
        'id'            => 'sidebar-1',
        'description'   => __('Widget-uri afișate în bara laterală.', 'herbal-therapy'),
        'before_widget' => '<aside id="%1$s" class="widget %2$s">',
        'after_widget'  => '</aside>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));

    register_sidebar(array(
        'name'          => __('Footer', 'herbal-therapy'),
        'id'            => 'footer-1',
        'description'   => __('Widget-uri afișate în subsolul paginii.', 'herbal-therapy'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
}

add_action('widgets_init', 'ht_widgets_init');
