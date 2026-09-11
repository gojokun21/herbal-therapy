<?php
/**
 * Pagina de listare a articolelor ("Blog").
 *
 * Se randeaza cu ht_blog_archive() dintr-un sablon de pagina. Structura si
 * masuratorile vin din Figma (frame-ul "Blog", 1920x3620), luate pe un
 * container de 1440px: bara de teme 298px, spatiu 67px, grila 1075px cu trei
 * carduri de 345px la 20px distanta.
 *
 * Caruselul de pe prima pagina si coloana laterala a articolului stau in
 * inc/blog.php; aici e doar listarea.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parametrul din URL care poarta temele bifate.
 *
 * @return string
 */
function ht_blog_archive_key()
{
    return (string)apply_filters('ht_blog_archive_key', 'tema');
}

/**
 * Cate articole intra pe o pagina.
 *
 * In referinta grila are trei randuri a cate trei carduri.
 *
 * @return int
 */
function ht_blog_archive_per_page()
{
    return (int)apply_filters('ht_blog_archive_per_page', 9);
}

/**
 * Formatul datei de sub titlul cardului.
 *
 * @return string
 */
function ht_blog_archive_date_format()
{
    return (string)apply_filters('ht_blog_archive_date_format', 'F j, Y');
}

/**
 * Adresa paginii pe care sta listarea, fara numarul de pagina.
 *
 * @return string
 */
function ht_blog_archive_base_url()
{
    $id = (int)get_queried_object_id();
    $url = $id ? (string)get_permalink($id) : ht_blog_url();

    return apply_filters('ht_blog_archive_base_url', $url, $id);
}

/**
 * Temele afisate in bara laterala: toate categoriile cu articole.
 *
 * Intra si categoria implicita ("Necategorizat"): pana cand articolele sunt
 * incadrate pe teme, ea e singura care exista, iar bara trebuie sa se vada.
 *
 * @return array Lista de WP_Term.
 */
function ht_blog_archive_terms()
{
    $terms = get_terms(array(
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));

    if (is_wp_error($terms) || !$terms) {
        return array();
    }

    return apply_filters('ht_blog_archive_terms', array_values($terms));
}

/**
 * Temele bifate, citite din URL.
 *
 * Se pastreaza doar slug-urile care exista, ca sa nu ajunga valori inventate
 * in interogare.
 *
 * @return array Lista de slug-uri.
 */
function ht_blog_archive_selected()
{
    $key = ht_blog_archive_key();

    if (!isset($_GET[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtrare publica, pe GET.
        return array();
    }

    $raw = wp_unslash($_GET[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    /* forma normala e "?tema[]=a&tema[]=b", dar acceptam si "?tema=a,b" */
    $raw = is_array($raw) ? $raw : explode(',', (string)$raw);
    $raw = array_filter(array_map('sanitize_title', $raw));

    if (!$raw) {
        return array();
    }

    $known = wp_list_pluck(ht_blog_archive_terms(), 'slug');

    return array_values(array_intersect($raw, $known));
}

/**
 * Pagina curenta a listarii.
 *
 * Pe un sablon de pagina WordPress foloseste 'page'; 'paged' apare doar cand
 * listarea sta pe o arhiva.
 *
 * @return int
 */
function ht_blog_archive_paged()
{
    $paged = (int)get_query_var('paged');

    if (!$paged) {
        $paged = (int)get_query_var('page');
    }

    return max(1, $paged);
}

/**
 * Interogarea listarii.
 *
 * @param array $args 'limit', 'terms' (slug-uri), 'paged'.
 *
 * @return WP_Query
 */
function ht_blog_archive_query($args = array())
{
    $args = wp_parse_args($args, array(
        'limit' => ht_blog_archive_per_page(),
        'terms' => ht_blog_archive_selected(),
        'paged' => ht_blog_archive_paged(),
    ));

    $query = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => (int)$args['limit'],
        'paged'               => (int)$args['paged'],
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
    );

    if (!empty($args['terms'])) {
        $query['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $args['terms'],
            ),
        );
    }

    return new WP_Query(apply_filters('ht_blog_archive_query_args', $query, $args));
}

/**
 * Firimiturile de deasupra titlului.
 *
 * @param string $current Textul ultimei firimituri.
 */
function ht_blog_archive_crumbs($current)
{
    ?>
    <nav class="ht-blog-page__crumbs" aria-label="<?php esc_attr_e('Firimituri', 'herbal-therapy'); ?>">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Acasă', 'herbal-therapy'); ?></a>
        <span class="ht-blog-page__crumb-sep" aria-hidden="true">/</span>
        <span class="ht-blog-page__crumb-current" aria-current="page"><?php echo esc_html($current); ?></span>
    </nav>
    <?php
}

/**
 * Bara laterala cu temele.
 *
 * Formularul trimite prin GET, deci filtrarea merge si fara JavaScript;
 * scriptul doar scoate butonul si trimite formularul la bifare.
 *
 * @param array $terms    Categoriile afisate.
 * @param array $selected Slug-urile bifate.
 */
function ht_blog_archive_filters($terms, $selected)
{
    if (!$terms) {
        return;
    }

    $key = ht_blog_archive_key();
    ?>
    <form class="ht-blog-filters"
          method="get"
          action="<?php echo esc_url(ht_blog_archive_base_url()); ?>"
          data-ht-blog-filters>

        <button class="ht-blog-filters__toggle" type="button" aria-expanded="false" aria-controls="ht-blog-themes">
            <?php ht_icon('grid', 'ht-blog-filters__toggle-icon'); ?>
            <?php esc_html_e('Toate temele', 'herbal-therapy'); ?>
        </button>

        <div class="ht-blog-filters__panel" id="ht-blog-themes">
            <h2 class="ht-blog-filters__title"><?php esc_html_e('Toate temele', 'herbal-therapy'); ?></h2>

            <ul class="ht-blog-filters__list">
                <?php foreach ($terms as $term) : ?>
                    <li class="ht-blog-filters__item">
                        <label class="ht-check">
                            <input class="ht-check__input"
                                   type="checkbox"
                                   name="<?php echo esc_attr($key); ?>[]"
                                   value="<?php echo esc_attr($term->slug); ?>"
                                <?php checked(in_array($term->slug, $selected, true)); ?>>
                            <span class="ht-check__box" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 7.667 7.333 10 12 5" stroke="currentColor" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="ht-check__text">
                                <span class="ht-check__label"><?php echo esc_html($term->name); ?></span>
                                <span class="ht-check__count"><?php echo esc_html(number_format_i18n($term->count)); ?></span>
                            </span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <button class="ht-blog-filters__submit" type="submit">
                <?php esc_html_e('Arată articolele', 'herbal-therapy'); ?>
            </button>
        </div>
    </form>
    <?php
}

/**
 * Un card din grila.
 *
 * @param WP_Post $post Articolul.
 */
function ht_blog_archive_card($post)
{
    $thumb = (int)get_post_thumbnail_id($post->ID);
    ?>
    <article class="ht-blog-item">
        <a class="ht-blog-item__link" href="<?php echo esc_url((string)get_permalink($post)); ?>">

            <div class="ht-blog-item__media<?php echo $thumb ? '' : ' ht-blog-item__media--empty'; ?>">
                <?php
                if ($thumb) {
                    echo wp_get_attachment_image($thumb, 'ht-card', false, array(
                        'class'    => 'ht-blog-item__img',
                        'alt'      => esc_attr(get_the_title($post)),
                        'loading'  => 'lazy',
                        'decoding' => 'async',
                    ));
                }
                ?>
            </div>

            <div class="ht-blog-item__body">
                <h2 class="ht-blog-item__title"><?php echo esc_html(get_the_title($post)); ?></h2>
                <time class="ht-blog-item__date" datetime="<?php echo esc_attr(get_the_date(DATE_W3C, $post)); ?>">
                    <?php echo esc_html(get_the_date(ht_blog_archive_date_format(), $post)); ?>
                </time>
            </div>
        </a>
    </article>
    <?php
}

/**
 * Paginarea listarii.
 *
 * @param WP_Query $query    Interogarea listarii.
 * @param array    $selected Temele bifate, pastrate in link-uri.
 */
function ht_blog_archive_pagination($query, $selected = array())
{
    $total = (int)$query->max_num_pages;

    if ($total < 2) {
        return;
    }

    $links = paginate_links(array(
        'base'      => trailingslashit(ht_blog_archive_base_url()) . '%_%',
        'format'    => user_trailingslashit('page/%#%', 'paged'),
        'current'   => ht_blog_archive_paged(),
        'total'     => $total,
        'type'      => 'array',
        'end_size'  => 1,
        'mid_size'  => 1,
        'add_args'  => $selected ? array(ht_blog_archive_key() => $selected) : false,
        'prev_text' => ht_get_icon('chevron', 'ht-blog-pager__icon ht-blog-pager__icon--prev'),
        'next_text' => ht_get_icon('chevron', 'ht-blog-pager__icon'),
    ));

    if (!$links) {
        return;
    }
    ?>
    <nav class="ht-blog-pager" aria-label="<?php esc_attr_e('Paginare articole', 'herbal-therapy'); ?>">
        <?php foreach ($links as $link) : ?>
            <?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit de paginate_links(). ?>
        <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * Pastreaza "/page/2/" pe sablonul de listare.
 *
 * Pe o pagina obisnuita numarul din adresa inseamna paginarea continutului
 * (<!--nextpage-->); fara asa ceva in continut, WordPress redirectioneaza
 * inapoi la prima pagina si ar rupe listarea.
 *
 * @param string $redirect  Adresa propusa de WordPress.
 * @param string $requested Adresa ceruta de vizitator.
 *
 * @return string|false
 */
function ht_blog_archive_keep_paged($redirect, $requested)
{
    if (is_page_template('templates/blog.php') && get_query_var('page')) {
        return false;
    }

    return $redirect;
}

add_filter('redirect_canonical', 'ht_blog_archive_keep_paged', 10, 2);

/**
 * Randeaza pagina de listare.
 *
 * @param array $args 'title', 'limit'.
 */
function ht_blog_archive($args = array())
{
    $args = wp_parse_args($args, array(
        'title' => __('Blog', 'herbal-therapy'),
        'limit' => ht_blog_archive_per_page(),
    ));

    $terms = ht_blog_archive_terms();
    $selected = ht_blog_archive_selected();
    $query = ht_blog_archive_query(array(
        'limit' => (int)$args['limit'],
        'terms' => $selected,
    ));
    ?>
    <div class="ht-blog-page">
        <div class="ht-wrapper">

            <?php ht_blog_archive_crumbs($args['title']); ?>

            <h1 class="ht-blog-page__title"><?php echo esc_html($args['title']); ?></h1>

            <?php /* fara teme de filtrat, grila ocupa toata latimea */ ?>
            <div class="ht-blog-page__layout<?php echo $terms ? '' : ' ht-blog-page__layout--full'; ?>">

                <?php ht_blog_archive_filters($terms, $selected); ?>

                <div class="ht-blog-page__main">
                    <?php if ($query->have_posts()) : ?>

                        <div class="ht-blog-grid">
                            <?php
                            foreach ($query->posts as $ht_post) {
                                ht_blog_archive_card($ht_post);
                            }
                            ?>
                        </div>

                        <?php ht_blog_archive_pagination($query, $selected); ?>

                    <?php else : ?>

                        <p class="ht-blog-page__empty">
                            <?php esc_html_e('Nu am găsit articole pentru temele alese.', 'herbal-therapy'); ?>
                        </p>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    wp_reset_postdata();
}
