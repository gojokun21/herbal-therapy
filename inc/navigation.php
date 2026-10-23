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
 * Paginare cu numere pentru arhive si pentru cautare.
 *
 * 'class' inlocuieste clasa implicita "pagination" a lui WordPress, deci
 * stilurile stau pe .ht-pagination (style.css). Sagetile sunt doar iconite;
 * textul ramane pentru cititoarele de ecran.
 *
 * @param array $args Suprascrieri pentru the_posts_pagination(), ex. 'aria_label'.
 */
function ht_pagination($args = array())
{
    $hidden = '<span class="ht-visually-hidden">%s</span>';

    the_posts_pagination(array_merge(array(
        'mid_size'  => 1,
        'prev_text' => ht_get_icon('chevron', 'ht-pagination__icon ht-pagination__icon--prev')
            . sprintf($hidden, esc_html__('Înapoi', 'herbal-therapy')),
        'next_text' => ht_get_icon('chevron', 'ht-pagination__icon')
            . sprintf($hidden, esc_html__('Înainte', 'herbal-therapy')),
        'class'     => 'ht-pagination',
    ), $args));
}
