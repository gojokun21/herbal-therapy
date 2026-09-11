<?php
/**
 * Walker pentru mega-meniul din header.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Randeaza meniul pe oricate niveluri, sub forma de mega-dropdown.
 *
 * Conventie: daca un element de meniu are in campul "Description" un URL de imagine,
 * este randat ca un card cu poza in loc de link simplu.
 */
class HT_Nav_Walker extends Walker_Nav_Menu
{
    /**
     * Stiva cu copiii elementului curent, ca sa stim in start_lvl ce fel de lista deschidem.
     *
     * @var array
     */
    protected $child_stack = array();

    /**
     * Retine copiii elementului inainte de a-l randa.
     *
     * @param object $element           Elementul curent.
     * @param array  $children_elements Copiii tuturor elementelor.
     * @param int    $max_depth         Adancimea maxima.
     * @param int    $depth             Adancimea curenta.
     * @param array  $args              Argumentele meniului.
     * @param string $output            Markup-ul acumulat.
     */
    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output)
    {
        $id_field = $this->db_fields['id'];
        $id = $element->$id_field;

        $this->child_stack[] = isset($children_elements[$id]) ? $children_elements[$id] : array();

        parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);

        array_pop($this->child_stack);
    }

    /**
     * URL-ul imaginii, daca elementul e definit ca un card.
     *
     * @param object $item Elementul de meniu.
     *
     * @return string
     */
    public static function item_image($item)
    {
        $description = isset($item->description) ? trim(wp_strip_all_tags($item->description)) : '';

        if ('' === $description) {
            return '';
        }

        if (!preg_match('~^https?://\S+\.(jpe?g|png|webp|gif|svg|avif)(\?\S*)?$~i', $description)) {
            return '';
        }

        return $description;
    }

    /**
     * Copiii elementului curent sunt carduri cu imagine?
     *
     * @return bool
     */
    protected function children_are_cards()
    {
        $children = end($this->child_stack);

        if (empty($children)) {
            return false;
        }

        foreach ($children as $child) {
            if ('' !== self::item_image($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deschide un nivel de submeniu.
     *
     * @param string $output Markup-ul acumulat.
     * @param int    $depth  Adancimea.
     * @param array  $args   Argumentele meniului.
     */
    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        $cards = $this->children_are_cards() ? ' ht-mega__list--cards' : '';

        if (0 === $depth) {
            $root = $cards ? '' : ' ht-mega__list--root';

            $output .= '<div class="ht-mega">'
                . '<div class="ht-wrapper ht-mega__inner">'
                . '<ul class="ht-mega__list' . $root . $cards . '">';

            return;
        }

        $output .= '<div class="ht-mega__sub">'
            . '<ul class="ht-mega__list ht-mega__list--depth-' . (int)$depth . $cards . '">';
    }

    /**
     * Inchide un nivel de submeniu.
     *
     * @param string $output Markup-ul acumulat.
     * @param int    $depth  Adancimea.
     * @param array  $args   Argumentele meniului.
     */
    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        $output .= 0 === $depth ? '</ul></div></div>' : '</ul></div>';
    }

    /**
     * Randeaza un element de meniu.
     *
     * @param string $output Markup-ul acumulat.
     * @param object $item   Elementul de meniu.
     * @param int    $depth  Adancimea.
     * @param array  $args   Argumentele meniului.
     * @param int    $id     ID-ul elementului.
     */
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $classes = empty($item->classes) ? array() : (array)$item->classes;
        $classes[] = 'menu-item-' . $item->ID;

        $has_children = in_array('menu-item-has-children', $classes, true);
        $image = self::item_image($item);

        $classes[] = 0 === $depth ? 'ht-menu__item' : 'ht-mega__item';

        if ('' !== $image) {
            $classes[] = 'ht-mega__item--card';
        }

        $class_names = join(' ', array_filter(array_unique(
            apply_filters('nav_menu_css_class', $classes, $item, $args, $depth)
        )));

        $output .= '<li class="' . esc_attr($class_names) . '">';

        $title = apply_filters('the_title', $item->title, $item->ID);
        $url = !empty($item->url) ? $item->url : '';
        $is_link = '' !== $url && '#' !== $url;

        if ('' !== $image) {
            $output .= $this->render_card($item, $title, $url, $image, $is_link);

            return;
        }

        $output .= $this->render_link($item, $title, $url, $depth, $is_link, $has_children, $classes);
    }

    /**
     * Inchide elementul de meniu.
     *
     * @param string $output Markup-ul acumulat.
     * @param object $item   Elementul de meniu.
     * @param int    $depth  Adancimea.
     * @param array  $args   Argumentele meniului.
     */
    public function end_el(&$output, $item, $depth = 0, $args = null)
    {
        $output .= '</li>';
    }

    /**
     * Card cu imagine.
     *
     * @param object $item    Elementul de meniu.
     * @param string $title   Titlul.
     * @param string $url     URL-ul.
     * @param string $image   URL-ul imaginii.
     * @param bool   $is_link Elementul are link real?
     *
     * @return string
     */
    protected function render_card($item, $title, $url, $image, $is_link)
    {
        $tag = $is_link ? 'a' : 'span';
        $href = $is_link ? ' href="' . esc_url($url) . '"' : '';

        return '<' . $tag . $href . ' class="ht-mega__card">'
            . '<img class="ht-mega__card-img" src="' . esc_url($image) . '" alt="' . esc_attr($title) . '" '
            . 'loading="lazy" decoding="async" width="132" height="132">'
            . '<span class="ht-mega__card-title">' . esc_html($title) . '</span>'
            . '</' . $tag . '>';
    }

    /**
     * Link normal de meniu.
     *
     * @param object $item         Elementul de meniu.
     * @param string $title        Titlul.
     * @param string $url          URL-ul.
     * @param int    $depth        Adancimea.
     * @param bool   $is_link      Elementul are link real?
     * @param bool   $has_children Are submeniu?
     * @param array  $classes      Clasele elementului.
     *
     * @return string
     */
    protected function render_link($item, $title, $url, $depth, $is_link, $has_children, $classes)
    {
        $attrs = ' class="' . (0 === $depth ? 'ht-menu__link' : 'ht-mega__link') . '"';

        if ($is_link) {
            $attrs .= ' href="' . esc_url($url) . '"';
        } else {
            $attrs .= ' role="button" tabindex="0"';
        }

        if (!empty($item->target)) {
            $attrs .= ' target="' . esc_attr($item->target) . '" rel="noopener"';
        }

        if (!empty($item->attr_title)) {
            $attrs .= ' title="' . esc_attr($item->attr_title) . '"';
        }

        if ($has_children) {
            $attrs .= ' aria-haspopup="true" aria-expanded="false"';
        }

        /* culoare proprie prin clasa CSS de forma "color-f11716" */
        foreach ($classes as $class) {
            if (preg_match('~^color-([0-9a-f]{3,6})$~i', $class, $matches)) {
                $attrs .= ' style="color:#' . esc_attr($matches[1]) . '"';
                break;
            }
        }

        $tag = $is_link ? 'a' : 'span';
        $arrow = $has_children ? ht_get_icon('chevron', 'ht-icon ht-mega__arrow') : '';

        return '<' . $tag . $attrs . '>'
            . '<span class="ht-menu__text">' . esc_html($title) . '</span>'
            . $arrow
            . '</' . $tag . '>';
    }
}
