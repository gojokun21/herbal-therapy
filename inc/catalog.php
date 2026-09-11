<?php
/**
 * Catalogul din header: butonul verde si panoul care se deschide sub el.
 *
 * Panoul se alimenteaza din taxonomia 'product_cat'. Are doua infatisari, alese
 * automat dupa cum arata arborele de categorii:
 *
 *   - cu subcategorii: coloana din stanga cu categoriile principale, iar in
 *     dreapta subcategoriile celei active (comportamentul de mega-meniu);
 *   - fara subcategorii: o grila simpla de placi, ca sa nu ramana jumatate de
 *     panou gol.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Date
 * ------------------------------------------------------------------------ */

/**
 * Arborele de categorii aratat in panou.
 *
 * Se intoarce doar primul nivel, fiecare cu copiii lui directi. Categoria
 * implicita din WooCommerce ("Uncategorized") este scoasa: nu e o categorie de
 * magazin, ci cosul de gunoi al produselor neclasificate.
 *
 * @return array Lista de array-uri cu cheile 'term' si 'children'.
 */
function ht_catalog_tree()
{
    /* butonul si panoul cer acelasi arbore, deci il calculam o singura data */
    static $tree = null;

    if (null !== $tree) {
        return $tree;
    }

    $tree = array();

    if (!taxonomy_exists('product_cat')) {
        return $tree;
    }

    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'exclude'    => array((int)get_option('default_product_cat')),
    ));

    if (is_wp_error($terms) || empty($terms)) {
        return $tree;
    }

    /* grupam copiii pe parinte intr-o singura trecere, fara interogari in plus */
    $by_parent = array();

    foreach ($terms as $term) {
        $by_parent[(int)$term->parent][] = $term;
    }

    foreach (isset($by_parent[0]) ? $by_parent[0] : array() as $root) {
        $tree[] = array(
            'term'     => $root,
            'children' => isset($by_parent[$root->term_id]) ? $by_parent[$root->term_id] : array(),
        );
    }

    $tree = apply_filters('ht_catalog_tree', $tree);

    return $tree;
}

/**
 * Vreuna dintre categoriile principale are subcategorii?
 *
 * @param array $tree Arborele intors de ht_catalog_tree().
 *
 * @return bool
 */
function ht_catalog_has_children($tree)
{
    foreach ($tree as $branch) {
        if (!empty($branch['children'])) {
            return true;
        }
    }

    return false;
}

/**
 * Adresa magazinului, folosita si ca destinatie a butonului cand lipseste JS.
 *
 * @return string
 */
function ht_catalog_url()
{
    $url = '';

    if (function_exists('wc_get_page_permalink')) {
        $url = (string)wc_get_page_permalink('shop');
    }

    if ('' === $url) {
        $url = home_url('/');
    }

    return apply_filters('ht_catalog_url', $url);
}

/**
 * Imaginea unei categorii, daca are una setata.
 *
 * @param WP_Term $term Categoria.
 * @param string  $size Dimensiunea imaginii.
 *
 * @return string HTML-ul imaginii sau sir gol.
 */
function ht_catalog_term_image($term, $size = 'thumbnail')
{
    $id = (int)get_term_meta($term->term_id, 'thumbnail_id', true);

    if (!$id) {
        return '';
    }

    return (string)wp_get_attachment_image($id, $size, false, array(
        'class'    => 'ht-catalog__thumb',
        'alt'      => '',
        'loading'  => 'lazy',
        'decoding' => 'async',
    ));
}

/* ---------------------------------------------------------------------------
 * Markup
 * ------------------------------------------------------------------------ */

/**
 * Butonul verde "Catalog" din bara de sus.
 *
 * Este un link catre magazin, nu un <button>: fara JavaScript vizitatorul
 * ajunge tot la produse, iar cu JavaScript scriptul din header ii preia clicul
 * si deschide panoul.
 */
function ht_catalog_button()
{
    if (!ht_catalog_tree()) {
        return;
    }
    ?>
    <a class="ht-catalog__btn" href="<?php echo esc_url(ht_catalog_url()); ?>"
       data-ht-catalog-toggle aria-haspopup="true" aria-expanded="false" aria-controls="htCatalog">
        <?php ht_icon('catalog', 'ht-icon ht-catalog__btn-icon'); ?>
        <span class="ht-catalog__btn-text"><?php esc_html_e('Catalog', 'herbal-therapy'); ?></span>
    </a>
    <?php
}

/**
 * Panoul cu categorii, randat la finalul header-ului.
 */
function ht_catalog_panel()
{
    $tree = ht_catalog_tree();

    if (!$tree) {
        return;
    }

    $has_children = ht_catalog_has_children($tree);
    $first = $tree[0]['term']->term_id;
    ?>
    <div class="ht-catalog" id="htCatalog">
        <div class="ht-catalog__panel">
            <div class="ht-wrapper ht-catalog__inner<?php echo $has_children ? '' : ' ht-catalog__inner--flat'; ?>">

                <ul class="ht-catalog__roots">
                    <?php foreach ($tree as $branch) :
                        $term = $branch['term'];
                        /* fara panouri in dreapta nu exista categorie "activa" */
                        $active = $has_children && ($term->term_id === $first);
                        ?>
                        <li class="ht-catalog__root<?php echo $active ? ' is-active' : ''; ?>">
                            <a class="ht-catalog__root-link" href="<?php echo esc_url(get_term_link($term)); ?>"
                               data-ht-catalog-cat="<?php echo esc_attr($term->term_id); ?>">
                                <?php
                                $image = ht_catalog_term_image($term);
                                echo $image; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image.
                                ?>
                                <span class="ht-catalog__root-name"><?php echo esc_html($term->name); ?></span>
                                <?php if (!empty($branch['children'])) : ?>
                                    <?php ht_icon('chevron', 'ht-icon ht-catalog__arrow'); ?>
                                <?php elseif ($term->count) : ?>
                                    <span class="ht-catalog__count"><?php echo esc_html($term->count); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($has_children) : ?>
                    <div class="ht-catalog__panes">
                        <?php foreach ($tree as $branch) :
                            $term = $branch['term'];
                            ?>
                            <div class="ht-catalog__pane<?php echo ($term->term_id === $first) ? ' is-active' : ''; ?>"
                                 data-ht-catalog-pane="<?php echo esc_attr($term->term_id); ?>">

                                <a class="ht-catalog__pane-title" href="<?php echo esc_url(get_term_link($term)); ?>">
                                    <?php echo esc_html($term->name); ?>
                                </a>

                                <?php if (!empty($branch['children'])) : ?>
                                    <ul class="ht-catalog__subs">
                                        <?php foreach ($branch['children'] as $child) : ?>
                                            <li>
                                                <a class="ht-catalog__sub"
                                                   href="<?php echo esc_url(get_term_link($child)); ?>">
                                                    <?php echo esc_html($child->name); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <p class="ht-catalog__empty">
                                        <?php esc_html_e('Vezi toate produsele din această categorie.', 'herbal-therapy'); ?>
                                    </p>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <a class="ht-catalog__all" href="<?php echo esc_url(ht_catalog_url()); ?>">
                    <span><?php esc_html_e('Vezi toate produsele', 'herbal-therapy'); ?></span>
                    <?php ht_icon('arrow'); ?>
                </a>

            </div>
        </div>

        <div class="ht-catalog__backdrop" data-ht-catalog-close></div>
    </div>
    <?php
}
