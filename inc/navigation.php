<?php
/**
 * Meniuri.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once HT_DIR . '/inc/class-ht-nav-walker.php';

/**
 * Meniul principal, randat cu HT_Nav_Walker.
 *
 * @param string $extra_class Clase suplimentare pe <ul>.
 */
function ht_main_menu($extra_class = '')
{
    if (!has_nav_menu('primary-menu')) {
        if (current_user_can('edit_theme_options')) {
            printf(
                '<ul class="ht-menu %1$s"><li class="ht-menu__item"><a class="ht-menu__link" href="%2$s">%3$s</a></li></ul>',
                esc_attr($extra_class),
                esc_url(admin_url('nav-menus.php')),
                esc_html__('Setează meniul principal', 'herbal-therapy')
            );
        }

        return;
    }

    wp_nav_menu(array(
        'theme_location' => 'primary-menu',
        'container'      => false,
        'menu_class'     => trim('ht-menu ' . $extra_class),
        'depth'          => 0,
        'walker'         => new HT_Nav_Walker(),
        'fallback_cb'    => false,
    ));
}

/**
 * Paginare cu numere pentru arhive.
 */
function ht_pagination()
{
    the_posts_pagination(array(
        'mid_size'  => 1,
        'prev_text' => esc_html__('Înapoi', 'herbal-therapy'),
        'next_text' => esc_html__('Înainte', 'herbal-therapy'),
        'class'     => 'ht-pagination',
    ));
}
