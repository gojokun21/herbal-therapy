<?php
/**
 * Pagina de produs - structura din Figma (nodul 141:8984, "Single Product").
 *
 * Pagina are patru blocuri, in ordinea din design:
 *   1. blocul de sus - galerie + coloana de informatii + cardul de cumparare;
 *   2. detaliile     - taburile de text + tabelul de ingrediente;
 *   3. recenziile    - woocommerce/single-product-reviews.php;
 *   4. produsele asemanatoare - caruselul comun al temei.
 *
 * Textele care nu au corespondent in WooCommerce (beneficii, livrare, mod de
 * utilizare, atentionari, ingrediente) au valori statice, luate din design, si
 * se pot muta pe ACF fara sa se atinga sablonul: fiecare functie citeste intai
 * campul ACF cu numele scris in comentariu, apoi cade pe valoarea statica.
 *
 * Se incarca din inc/woocommerce.php, deci doar cand pluginul e activ.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Randarea implicita a pluginului
 * ------------------------------------------------------------------------ */

/*
 * Blocul de sus il deseneaza tema, mai jos, deci scoatem callback-urile
 * pluginului ca sa nu iasa de doua ori. Hook-urile raman si se declanseaza in
 * ht_single_product_top() si ht_single_product_info() - fara ele, extensiile
 * care scriu in sumar (variante de abonament, etichete de stoc, butoane de
 * partajare) nu ar avea unde sa apara.
 */
remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10);
remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

/*
 * Blocul cu SKU, categorii si etichete nu e in designul paginii. Se aduce inapoi,
 * la finalul coloanei de informatii, cu:
 *
 *   add_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
 */
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);

/*
 * Sub blocul de sus designul are taburi proprii, recenziile pe toata latimea si
 * un singur carusel de produse. Taburile pluginului, upsell-urile (afisate de
 * tema in cardul de cumparare, ca upgrade de pachet) si caruselul lui de produse
 * asociate se scot; ce urmeaza il randeaza ht_single_product_*.
 */
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);

/* ---------------------------------------------------------------------------
 * Continutul redactional (ACF, cu valorile din design ca rezerva)
 * ------------------------------------------------------------------------ */

/**
 * Valoarea unui camp ACF al produsului, daca pluginul e instalat.
 *
 * @param string $key Numele campului.
 * @param int    $id  ID-ul produsului.
 *
 * @return mixed|null Null cand campul lipseste sau e gol.
 */
function ht_pp_field($key, $id)
{
    if (!function_exists('get_field')) {
        return null;
    }

    $value = get_field($key, $id);

    return ('' === $value || null === $value || array() === $value) ? null : $value;
}

/**
 * Normalizeaza o valoare de camp intr-o lista de randuri de text.
 *
 * Accepta un sir (cu randuri separate prin linie noua sau scrise ca <li>), o
 * lista simpla sau o lista de randuri ACF - din care ia prima valoare a
 * fiecarui rand.
 *
 * @param mixed $value Valoarea campului.
 *
 * @return array Lista de siruri.
 */
function ht_pp_lines($value)
{
    if (null === $value || '' === $value) {
        return array();
    }

    $items = array();

    if (is_array($value)) {
        foreach ($value as $row) {
            if (is_array($row)) {
                $row = reset($row);
            }

            $items[] = (string)$row;
        }
    } else {
        $text = (string)$value;

        if (preg_match_all('~<li[^>]*>(.*?)</li>~is', $text, $matches)) {
            $items = $matches[1];
        } else {
            $items = preg_split('~<br\s*/?>|</p>|\r\n|\n~i', $text);
        }
    }

    /*
     * Bulina o deseneaza CSS-ul, deci taiem semnul cu care e scris randul in
     * continut - liniuta, bulina sau bifa verde - ca sa nu iasa doua.
     */
    $items = array_map(function ($item) {
        $item = trim(wp_strip_all_tags($item));

        return trim(preg_replace('~^(?:[-–—•*·]|✅|✔️?|☑️?|👉|➤|►)\s*~u', '', $item));
    }, $items);

    return array_values(array_filter($items, function ($item) {
        return '' !== $item;
    }));
}

/**
 * Beneficiile de sub titlu.
 *
 * ACF: campul 'beneficii' (textarea cu un beneficiu pe rand sau repeater).
 * Rezerva: punctele din descrierea scurta. Fara ele, sectiunea nu se afiseaza.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array
 */
function ht_product_benefits($product)
{
    $items = ht_pp_lines(ht_pp_field('beneficii', $product->get_id()));

    if (!$items) {
        $items = ht_pp_lines($product->get_short_description());
    }

    $limit = (int)apply_filters('ht_product_benefits_limit', 6, $product);

    return apply_filters('ht_product_benefits', array_slice($items, 0, $limit), $product);
}

/**
 * Intervalul estimat de livrare, calculat de la ziua curenta.
 *
 * Ex. "17–21 august"; cand capetele pica in luni diferite, "30 august – 3 septembrie".
 * Numele lunii urmeaza limba curenta (wp_date). Offset-urile in zile se pot
 * ajusta prin filtrul 'ht_delivery_estimate_days'.
 *
 * @return string Intervalul formatat.
 */
function ht_delivery_estimate()
{
    $days  = apply_filters('ht_delivery_estimate_days', array('start' => 3, 'end' => 7));
    $start = time() + DAY_IN_SECONDS * max(0, (int)$days['start']);
    $end   = time() + DAY_IN_SECONDS * max(0, (int)$days['end']);

    if (wp_date('Y-m', $start) === wp_date('Y-m', $end)) {
        return wp_date('j', $start) . '–' . wp_date('j F', $end);
    }

    return wp_date('j F', $start) . ' – ' . wp_date('j F', $end);
}

/**
 * Randurile de livrare si garantii, cu iconitele din design.
 *
 * Sunt aceleasi pe tot magazinul, deci se schimba dintr-un singur loc: fie prin
 * filtrul 'ht_product_delivery', fie legandu-le de o pagina de optiuni ACF.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array Randuri cu 'icon' (fisierul din assets/img/product) si 'text'.
 */
function ht_product_delivery($product)
{
    $items = array(
        array(
            'icon' => 'icon-delivery',
            'text' => sprintf(
                /* translators: %s: intervalul estimat de livrare, ex. "17–21 august". */
                __('Comandă acum, livrare estimată între %s', 'herbal-therapy'),
                ht_delivery_estimate()
            ),
        ),
        array(
            'icon' => 'icon-package',
            'text' => __('Plată la livrare sau online, securizat cu cardul', 'herbal-therapy'),
        ),
        array(
            'icon' => 'icon-lock',
            'text' => __('Livrare gratuită la comenzi de peste 350 MDL', 'herbal-therapy'),
        ),
    );

    return apply_filters('ht_product_delivery', $items, $product);
}

/**
 * Taburile de text de sub blocul de sus.
 *
 * ACF: 'descriere_detaliata', 'mod_de_utilizare', 'atentionari' (wysiwyg).
 * Rezerva pentru primul tab e descrierea produsului din WooCommerce. Taburile
 * fara continut nu se afiseaza.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array Taburi cu 'id', 'title' si 'content' (HTML).
 */
function ht_product_tabs($product)
{
    $id = $product->get_id();
    $description = ht_pp_field('descriere_detaliata', $id);

    if (null === $description) {
        $description = $product->get_description();
    }

    $candidates = array(
        array('id' => 'descriere', 'title' => __('Descriere', 'herbal-therapy'), 'content' => $description),
        array('id' => 'utilizare', 'title' => __('Mod de Utilizare', 'herbal-therapy'), 'content' => ht_pp_field('mod_de_utilizare', $id)),
        array('id' => 'atentionari', 'title' => __('Atenționări și Mențiuni', 'herbal-therapy'), 'content' => ht_pp_field('atentionari', $id)),
    );

    $tabs = array();

    foreach ($candidates as $tab) {
        if ('' === trim(wp_strip_all_tags((string)$tab['content']))) {
            continue;
        }

        $tabs[] = $tab;
    }

    return apply_filters('ht_product_tabs', $tabs, $product);
}

/**
 * Randurile tabelului de ingrediente.
 *
 * ACF: 'ingrediente' - repeater cu randuri 'text' si 'strong'. Se accepta si o
 * textarea cu un ingredient pe rand; acolo, randurile care incep cu '*' se scriu
 * ingrosat, ca notele din design.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array Randuri cu 'text' si 'strong'.
 */
function ht_product_ingredients($product)
{
    $value = ht_pp_field('ingrediente', $product->get_id());

    /*
     * Randurile repeater-ului au bifa de ingrosare proprie, deci nu mai trec
     * prin ht_pp_lines() - care ar pastra doar textul.
     */
    if (is_array($value) && isset($value[0]) && is_array($value[0]) && array_key_exists('text', $value[0])) {
        $rows = array();

        foreach ($value as $row) {
            $text = trim(wp_strip_all_tags((string)$row['text']));

            if ('' === $text) {
                continue;
            }

            $rows[] = array(
                'text'   => $text,
                'strong' => !empty($row['strong']),
            );
        }

        if ($rows) {
            return apply_filters('ht_product_ingredients', $rows, $product);
        }
    }

    $lines = ht_pp_lines($value);

    $rows = array();

    foreach ($lines as $line) {
        $strong = ('*' === substr($line, 0, 1));

        $rows[] = array(
            'text'   => $strong ? ltrim(substr($line, 1)) : $line,
            'strong' => $strong,
        );
    }

    return apply_filters('ht_product_ingredients', $rows, $product);
}

/* ---------------------------------------------------------------------------
 * Datele blocului de sus
 * ------------------------------------------------------------------------ */

/**
 * Imaginile galeriei: imaginea reprezentativa urmata de cele din galerie.
 *
 * Fiecare intrare are 'full' (slide-ul mare) si 'thumb' (miniatura).
 *
 * @param WC_Product $product Produsul.
 *
 * @return array
 */
function ht_product_gallery($product)
{
    $ids = array_filter(array_merge(
        array(ht_product_image_id($product)),
        array_map('intval', $product->get_gallery_image_ids())
    ));

    $ids = array_values(array_unique($ids));
    $images = array();
    $name = $product->get_name();

    foreach ($ids as $index => $id) {
        $full = wp_get_attachment_image_src($id, 'full');

        if (!$full) {
            continue;
        }

        $thumb = wp_get_attachment_image_src($id, 'woocommerce_thumbnail');
        $alt = (string)get_post_meta($id, '_wp_attachment_image_alt', true);

        if ('' === $alt) {
            /* translators: 1: numele produsului, 2: pozitia imaginii in galerie. */
            $alt = sprintf(__('%1$s - imaginea %2$d', 'herbal-therapy'), $name, $index + 1);
        }

        $images[] = array(
            /*
             * Fara srcset: slide-ul mare incarca mereu originalul, ca sa nu
             * ajunga in pagina o varianta recomprimata (600px etc.) mai slaba.
             */
            'full'  => array(
                'src'    => $full[0],
                'width'  => $full[1],
                'height' => $full[2],
                'srcset' => '',
                'sizes'  => '',
            ),
            'thumb' => array(
                'src'    => $thumb ? $thumb[0] : $full[0],
                'width'  => $thumb ? $thumb[1] : $full[1],
                'height' => $thumb ? $thumb[2] : $full[2],
                'srcset' => $thumb ? (string)wp_get_attachment_image_srcset($id, 'woocommerce_thumbnail') : '',
                'sizes'  => '(max-width: 560px) 84px, 114px',
            ),
            'alt'   => $alt,
        );
    }

    if (!$images && function_exists('wc_placeholder_img_src')) {
        $images[] = array(
            'full'  => array(
                'src'    => wc_placeholder_img_src('woocommerce_single'),
                'width'  => 600,
                'height' => 600,
                'srcset' => '',
                'sizes'  => '',
            ),
            'thumb' => array(
                'src'    => wc_placeholder_img_src('woocommerce_thumbnail'),
                'width'  => 300,
                'height' => 300,
                'srcset' => '',
                'sizes'  => '',
            ),
            'alt'   => $name,
        );
    }

    return apply_filters('ht_product_gallery', $images, $product);
}

/**
 * Procentul reducerii, pentru eticheta rosie de langa pretul vechi.
 *
 * @param WC_Product $product Produsul.
 *
 * @return int 0 daca produsul nu e la reducere.
 */
function ht_product_discount($product)
{
    if (!$product->is_on_sale()) {
        return 0;
    }

    $regular = (float)$product->get_regular_price();
    $active = (float)$product->get_price();

    if ($regular <= 0 || $active <= 0 || $active >= $regular) {
        return 0;
    }

    return (int)round(100 - ($active / $regular * 100));
}

/**
 * Produsele din caruselul "Oamenii cumpără acest produs împreună cu".
 *
 * Sursa e campul "Cross-sells" (Produse legate), cu produsele asociate automat
 * de WooCommerce ca rezerva. Upsell-urile nu intra aici: ele sunt ambalajele mai
 * mari, propuse in cardul "Upgrade la pachet" (vezi ht_product_upgrades()).
 *
 * @param WC_Product $product Produsul.
 * @param int        $limit   Numarul maxim de carduri.
 *
 * @return array Carduri normalizate, ca in ht_product_card_data().
 */
function ht_product_bought_together($product, $limit = 8)
{
    $ids = array_map('intval', $product->get_cross_sell_ids());

    if (!$ids) {
        $ids = array_map('intval', wc_get_related_products($product->get_id(), $limit));
    }

    $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, $limit);
    $ids = apply_filters('ht_product_bought_together_ids', $ids, $product);
    $cards = array();

    foreach ($ids as $id) {
        $item = wc_get_product($id);

        if ($item && $item->is_visible()) {
            $cards[] = ht_product_card_data($item);
        }
    }

    return $cards;
}

/* ---------------------------------------------------------------------------
 * Randarea blocului de sus
 * ------------------------------------------------------------------------ */

/**
 * Galeria: imaginea mare si randul de miniaturi de sub ea.
 *
 * @param WC_Product $product Produsul.
 */
function ht_single_product_gallery($product)
{
    $images = ht_product_gallery($product);

    if (!$images) {
        return;
    }

    $has_slider = count($images) > 1;

    /*
     * Designul nu are etichete peste imaginea mare - reducerea si stocul se vad
     * in cardul de cumparare. Se aduc inapoi cu:
     *
     *   add_filter('ht_single_product_flags', 'ht_product_card_flags', 10, 2);
     */
    $flags = (array)apply_filters('ht_single_product_flags', array(), $product);
    ?>
    <div class="ht-pp__gallery" data-ht-gallery>

        <div class="ht-pp__stage">
            <div class="swiper ht-pp__stage-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($images as $index => $image) : ?>
                        <div class="swiper-slide ht-pp__slide">
                            <a class="ht-pp__slide-link"
                               href="<?php echo esc_url($image['full']['src']); ?>"
                               data-fancybox="ht-product-gallery">
                                <img class="ht-pp__slide-img"
                                     src="<?php echo esc_url($image['full']['src']); ?>"
                                     <?php if ('' !== $image['full']['srcset']) : ?>
                                         srcset="<?php echo esc_attr($image['full']['srcset']); ?>"
                                         sizes="<?php echo esc_attr($image['full']['sizes']); ?>"
                                     <?php endif; ?>
                                     width="<?php echo esc_attr($image['full']['width']); ?>"
                                     height="<?php echo esc_attr($image['full']['height']); ?>"
                                     alt="<?php echo esc_attr($image['alt']); ?>"
                                     loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
                                     decoding="async">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($flags) : ?>
                <ul class="ht-pp__flags">
                    <?php foreach ($flags as $flag) : ?>
                        <li class="ht-pp__flag ht-pp__flag--<?php echo esc_attr($flag['type']); ?>">
                            <?php echo esc_html($flag['text']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($has_slider) : ?>
                <button class="ht-pp__arrow ht-pp__arrow--prev" type="button">
                    <?php ht_icon('chevron'); ?>
                    <span class="ht-visually-hidden"><?php esc_html_e('Imaginea anterioară', 'herbal-therapy'); ?></span>
                </button>
                <button class="ht-pp__arrow ht-pp__arrow--next" type="button">
                    <?php ht_icon('chevron'); ?>
                    <span class="ht-visually-hidden"><?php esc_html_e('Imaginea următoare', 'herbal-therapy'); ?></span>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($has_slider) : ?>
            <div class="ht-pp__thumbs">
                <div class="swiper ht-pp__thumbs-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($images as $image) : ?>
                            <div class="swiper-slide ht-pp__thumb">
                                <img class="ht-pp__thumb-img"
                                     src="<?php echo esc_url($image['thumb']['src']); ?>"
                                     <?php if ('' !== $image['thumb']['srcset']) : ?>
                                         srcset="<?php echo esc_attr($image['thumb']['srcset']); ?>"
                                         sizes="<?php echo esc_attr($image['thumb']['sizes']); ?>"
                                     <?php endif; ?>
                                     width="<?php echo esc_attr($image['thumb']['width']); ?>"
                                     height="<?php echo esc_attr($image['thumb']['height']); ?>"
                                     alt="<?php echo esc_attr($image['alt']); ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Coloana din mijloc: titlu, nota, beneficii si randurile de livrare.
 *
 * @param WC_Product $product Produsul.
 */
function ht_single_product_info($product)
{
    $rating = (float)$product->get_average_rating();
    $reviews = (int)$product->get_review_count();
    $sku = $product->get_sku();
    $benefits = ht_product_benefits($product);
    $delivery = ht_product_delivery($product);
    ?>
    <div class="ht-pp__info">

        <div class="ht-pp__head">
            <h1 class="ht-pp__name"><?php echo esc_html($product->get_name()); ?></h1>

            <div class="ht-pp__meta">
                <span class="ht-pp__meta-item">
                    <?php ht_icon('star-card', 'ht-pp__meta-icon ht-pp__meta-icon--star'); ?>
                    <span><?php echo esc_html(ht_format_rating($rating)); ?></span>
                    <span class="ht-visually-hidden"><?php esc_html_e('Nota medie', 'herbal-therapy'); ?></span>
                </span>

                <a class="ht-pp__meta-item" href="#reviews">
                    <?php ht_product_asset_icon('icon-comment', 20); ?>
                    <span>
                        <?php
                        printf(
                            /* translators: %s: numarul de recenzii. */
                            esc_html(_n('%s recenzie', '%s recenzii', $reviews, 'herbal-therapy')),
                            esc_html(number_format_i18n($reviews))
                        );
                        ?>
                    </span>
                </a>

                <?php if ('' !== $sku) : ?>
                    <span class="ht-pp__meta-item ht-pp__meta-item--sku">
                        <?php
                        printf(
                            /* translators: %s: codul produsului (SKU). */
                            esc_html__('Cod produs: %s', 'herbal-therapy'),
                            esc_html($sku)
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($benefits) : ?>
            <div class="ht-pp__benefits">
                <p class="ht-pp__benefits-title"><?php esc_html_e('Beneficii:', 'herbal-therapy'); ?></p>

                <ul class="ht-pp__benefits-list">
                    <?php foreach ($benefits as $benefit) : ?>
                        <li class="ht-pp__benefit"><?php echo esc_html($benefit); ?></li>
                    <?php endforeach; ?>
                </ul>

                <a class="ht-pp__benefits-more" href="#descriere" data-ht-pp-goto="descriere">
                    <?php esc_html_e('Descriere detaliată', 'herbal-therapy'); ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($delivery) : ?>
            <ul class="ht-pp__delivery">
                <?php foreach ($delivery as $item) : ?>
                    <li class="ht-pp__delivery-item">
                        <?php ht_product_asset_icon($item['icon'], 24); ?>
                        <span><?php echo esc_html($item['text']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php ht_single_product_upgrade($product); ?>

        <?php
        /*
         * Sumarul, asa cum il stiu extensiile. Callback-urile pluginului care ar
         * fi desenat titlul, pretul si butonul sunt scoase mai sus, deci implicit
         * de aici nu iese decat partajarea (goala) si datele structurate.
         */
        do_action('woocommerce_single_product_summary');
        ?>
    </div>
    <?php
}

/* ---------------------------------------------------------------------------
 * Continutul pachetelor (WooCommerce Product Bundles)
 * ------------------------------------------------------------------------ */

/**
 * Cardurile produselor din care e format pachetul.
 *
 * Se afiseaza doar cand pachetul nu cere alegeri de la client (bundle static):
 * la cele cu optiuni lista o deseneaza formularul pluginului, cu campurile de
 * selectie, prin woocommerce_template_single_add_to_cart().
 *
 * @param WC_Product $product Produsul.
 *
 * @return bool Daca lista a fost desenata.
 */
function ht_single_product_bundle($product)
{
    if (!$product->is_type('bundle') || !method_exists($product, 'get_bundled_items')) {
        return false;
    }

    if ($product->requires_input()) {
        return false;
    }

    $rows = array();

    foreach ($product->get_bundled_items() as $item) {
        $child = $item->get_product();

        if ($child && $item->is_visible()) {
            $rows[] = array($item, $child);
        }
    }

    if (!$rows) {
        return false;
    }
    ?>
    <div class="ht-pp__bundle">
        <p class="ht-pp__bundle-title"><?php esc_html_e('Ce conține pachetul:', 'herbal-therapy'); ?></p>

        <div class="ht-pp__bundle-list">
            <?php foreach ($rows as $row) :
                list($item, $child) = $row;
                $qty = max(1, (int)$item->get_quantity());
                $link = $child->is_visible() ? get_permalink($child->get_id()) : '';
                $title = $item->get_raw_title(true);
                $excerpt = wp_strip_all_tags($child->get_short_description());
                $rating = (float)$child->get_average_rating();
                $reviews = (int)$child->get_review_count();
                ?>
                <article class="ht-pp__bundle-item">
                    <?php if ($link) : ?>
                        <a class="ht-pp__bundle-media" href="<?php echo esc_url($link); ?>"
                           aria-label="<?php echo esc_attr($title); ?>" tabindex="-1">
                            <?php echo wp_kses_post($child->get_image('woocommerce_thumbnail')); ?>
                        </a>
                    <?php else : ?>
                        <span class="ht-pp__bundle-media">
                            <?php echo wp_kses_post($child->get_image('woocommerce_thumbnail')); ?>
                        </span>
                    <?php endif; ?>

                    <div class="ht-pp__bundle-body">
                        <?php if ($link) : ?>
                            <a class="ht-pp__bundle-name" href="<?php echo esc_url($link); ?>">
                                <?php echo esc_html($title); ?>
                            </a>
                        <?php else : ?>
                            <span class="ht-pp__bundle-name"><?php echo esc_html($title); ?></span>
                        <?php endif; ?>

                        <?php if ($excerpt) : ?>
                            <p class="ht-pp__bundle-desc"><?php echo esc_html(wp_trim_words($excerpt, 24)); ?></p>
                        <?php endif; ?>

                        <p class="ht-pp__bundle-specs">
                            <span>
                                <?php esc_html_e('Cantitate:', 'herbal-therapy'); ?>
                                <strong><?php echo esc_html(number_format_i18n($qty)); ?></strong>
                            </span>
                        </p>

                        <?php if ($reviews > 0 || $link) : ?>
                            <p class="ht-pp__bundle-rating">
                                <?php if ($reviews > 0) : ?>
                                    <span class="ht-pp__bundle-rating-avg">
                                        <?php ht_icon('star-card', 'ht-pp__meta-icon ht-pp__meta-icon--star'); ?>
                                        <span><?php echo esc_html(ht_format_rating($rating)); ?></span>
                                    </span>
                                    <span class="ht-pp__bundle-rating-count">
                                        <?php echo esc_html('(' . number_format_i18n($reviews) . ')'); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($link) : ?>
                                    <a class="ht-pp__bundle-review" href="<?php echo esc_url($link . '#reviews'); ?>">
                                        <?php esc_html_e('Scrie o recenzie', 'herbal-therapy'); ?>
                                    </a>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return true;
}

/**
 * Steperul de cantitate si butonul de adaugare in cos.
 *
 * Produsele simple primesc butonul din design, cu adaugare prin AJAX. Restul
 * (variabile, grupate, externe) pastreaza formularul WooCommerce, pentru ca au
 * nevoie de campurile lui.
 *
 * @param WC_Product $product Produsul.
 */
function ht_single_product_buttons($product)
{
    $quick = $product->is_purchasable() && $product->is_in_stock() && $product->supports('ajax_add_to_cart');
    $form_id = 'ht-pp-cart-' . $product->get_id();

    /*
     * Limitele vin de la WooCommerce, ca sa nu inventam alta regula decat cea
     * din cos: maximul e -1 cand nu se tine stoc, iar produsele vandute strict
     * bucata au maximul 1 si atunci steperul nu mai are ce alege.
     */
    $min = max(1, (int)$product->get_min_purchase_quantity());
    $max = (int)$product->get_max_purchase_quantity();
    $step = max(1, (int)apply_filters('woocommerce_quantity_input_step', 1, $product));
    $has_qty = $quick && !$product->is_sold_individually() && ($max < 0 || $max > $min);
    ?>
    <div class="ht-pp__actions">

        <?php if ($has_qty) : ?>
            <div class="ht-pp__qty">
                <button class="ht-pp__qty-btn" type="button" data-ht-pp-step="-1" disabled>
                    <span aria-hidden="true">&minus;</span>
                    <span class="ht-visually-hidden"><?php esc_html_e('Scade cantitatea', 'herbal-therapy'); ?></span>
                </button>

                <?php
                /*
                 * Campul sta in afara formularului, dar 'form' il leaga de el:
                 * asa ramane o singura sursa a cantitatii si pentru trimiterea
                 * clasica, nu doar pentru AJAX.
                 */
                ?>
                <input class="ht-pp__qty-input" type="text" inputmode="numeric" autocomplete="off"
                       name="quantity"
                       form="<?php echo esc_attr($form_id); ?>"
                       value="<?php echo esc_attr($min); ?>"
                       data-ht-pp-qty
                       data-min="<?php echo esc_attr($min); ?>"
                       data-max="<?php echo esc_attr($max > 0 ? $max : 0); ?>"
                       data-step="<?php echo esc_attr($step); ?>"
                       aria-label="<?php esc_attr_e('Cantitate', 'herbal-therapy'); ?>">

                <button class="ht-pp__qty-btn" type="button" data-ht-pp-step="1"
                    <?php disabled($max > 0 && $max <= $min); ?>>
                    <span aria-hidden="true">+</span>
                    <span class="ht-visually-hidden"><?php esc_html_e('Crește cantitatea', 'herbal-therapy'); ?></span>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($quick) : ?>
            <form class="ht-pp__cart-form" id="<?php echo esc_attr($form_id); ?>" method="post"
                  enctype="multipart/form-data">
                <?php if (!$has_qty) : ?>
                    <input type="hidden" name="quantity" value="<?php echo esc_attr($min); ?>">
                <?php endif; ?>
                <button class="ht-pp__cart add_to_cart_button ajax_add_to_cart" type="submit"
                        name="add-to-cart"
                        value="<?php echo esc_attr($product->get_id()); ?>"
                        data-quantity="<?php echo esc_attr($min); ?>"
                        data-product_id="<?php echo esc_attr($product->get_id()); ?>"
                        data-product_sku="<?php echo esc_attr($product->get_sku()); ?>">
                    <?php ht_icon('cart-btn', 'ht-pp__cart-icon'); ?>
                    <span><?php echo esc_html($product->add_to_cart_text()); ?></span>
                </button>
            </form>
        <?php else : ?>
            <div class="ht-pp__cart-form ht-pp__cart-form--default">
                <?php woocommerce_template_single_add_to_cart(); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Caruselul mic din cardul de cumparare.
 *
 * @param WC_Product $product Produsul.
 */
function ht_single_product_together($product)
{
    $cards = ht_product_bought_together($product);

    if (!$cards) {
        return;
    }
    ?>
    <div class="ht-pp__together" data-ht-pp-together>
        <div class="ht-pp__together-head">
            <p class="ht-pp__together-title">
                <?php esc_html_e('Oamenii cumpără acest produs împreună cu', 'herbal-therapy'); ?>
            </p>

            <div class="ht-pp__together-nav">
                <button class="ht-pp__together-arrow ht-pp__together-arrow--prev" type="button">
                    <?php ht_icon('chevron'); ?>
                    <span class="ht-visually-hidden"><?php esc_html_e('Înapoi', 'herbal-therapy'); ?></span>
                </button>
                <button class="ht-pp__together-arrow ht-pp__together-arrow--next" type="button">
                    <?php ht_icon('chevron'); ?>
                    <span class="ht-visually-hidden"><?php esc_html_e('Înainte', 'herbal-therapy'); ?></span>
                </button>
            </div>
        </div>

        <div class="swiper ht-pp__together-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($cards as $index => $card) : ?>
                    <div class="swiper-slide ht-pp__together-slide">
                        <?php ht_product_card($card, $index + 1); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Numarul de bucati dintr-un ambalaj (30 la "Calciu 1000 mg N30").
 *
 * Marimea nu e un atribut al produsului, e scrisa in nume, in forma "N30".
 * Filtrul lasa loc unei surse mai bune (atribut, camp ACF) cand va exista.
 *
 * @param WC_Product $product Produsul.
 *
 * @return int Numarul de bucati sau 0 cand nu se poate deduce.
 */
function ht_product_pack_size($product)
{
    $size = 0;

    if (preg_match('/\bN(\d+)\b/u', $product->get_name(), $m)) {
        $size = (int)$m[1];
    }

    return (int)apply_filters('ht_product_pack_size', $size, $product);
}

/**
 * Felul bucatilor din ambalaj: 'capsule' cand numele o spune, altfel 'tablete'.
 *
 * @param WC_Product $product Produsul.
 *
 * @return string 'tablete' sau 'capsule'.
 */
function ht_product_pack_unit($product)
{
    /* 'capsul' prinde 'capsule' (ro); 'капсул' prinde 'капсулы' (ru) */
    $unit = preg_match('/capsul|капсул/iu', $product->get_name()) ? 'capsule' : 'tablete';

    return apply_filters('ht_product_pack_unit', $unit, $product);
}

/**
 * Ambalajele mai mari propuse in locul produsului (upgrade-ul de pachet).
 *
 * Sursa e campul WooCommerce "Upsells" (Produse legate), ales de administrator
 * la fiecare produs: la N30 se propune N60. Cand ambele marimi se pot deduce
 * din nume, se calculeaza si pretul pe bucata, cu economia in procente.
 *
 * @param WC_Product $product Produsul.
 * @param int        $limit   Numarul maxim de propuneri.
 *
 * @return array Randuri cu 'product', 'size', 'unit' (pretul pe bucata) si
 *               'saving' (procentul economisit pe bucata).
 */
function ht_product_upgrades($product, $limit = 2)
{
    $ids = array_map('intval', $product->get_upsell_ids());
    $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, $limit);
    $ids = apply_filters('ht_product_upgrade_ids', $ids, $product);

    $size = ht_product_pack_size($product);
    $price = (float)wc_get_price_to_display($product);
    $unit = ($size > 0 && $price > 0) ? $price / $size : 0;
    $rows = array();

    foreach ($ids as $id) {
        $item = wc_get_product($id);

        if (!$item || $id === $product->get_id() || !$item->is_visible() || !$item->is_purchasable()) {
            continue;
        }

        $item_size = ht_product_pack_size($item);
        $item_price = (float)wc_get_price_to_display($item);
        $item_unit = ($item_size > 0 && $item_price > 0) ? $item_price / $item_size : 0;
        $saving = ($unit > 0 && $item_unit > 0 && $item_unit < $unit)
            ? (int)round(100 - ($item_unit / $unit * 100))
            : 0;

        $rows[] = array(
            'product' => $item,
            'size'    => $item_size,
            'unit'    => $item_unit,
            'saving'  => $saving,
        );
    }

    return $rows;
}

/**
 * Cardul "Alege varianta mai avantajoasă", la finalul coloanei de informatii.
 *
 * @param WC_Product $product Produsul.
 */
function ht_single_product_upgrade($product)
{
    $rows = ht_product_upgrades($product);

    if (!$rows) {
        return;
    }

    $size = ht_product_pack_size($product);
    $capsules = ('capsule' === ht_product_pack_unit($product));
    ?>
    <div class="ht-pp__upgrade">
        <p class="ht-pp__upgrade-title"><?php esc_html_e('Alege varianta mai avantajoasă', 'herbal-therapy'); ?></p>

        <div class="ht-pp__upgrade-list">
            <?php foreach ($rows as $row) :
                $item = $row['product'];
                $link = get_permalink($item->get_id());
                $ajax = $item->supports('ajax_add_to_cart') && $item->is_in_stock();
                ?>
                <article class="ht-pp__upgrade-item">
                    <a class="ht-pp__upgrade-media" href="<?php echo esc_url($link); ?>" tabindex="-1" aria-hidden="true">
                        <?php echo wp_kses_post($item->get_image('woocommerce_thumbnail')); ?>
                    </a>

                    <div class="ht-pp__upgrade-body">
                        <a class="ht-pp__upgrade-name" href="<?php echo esc_url($link); ?>">
                            <?php echo esc_html($item->get_name()); ?>
                        </a>

                        <?php if ($row['size'] > 0 && $size > 0 && $row['size'] !== $size) : ?>
                            <p class="ht-pp__upgrade-pack">
                                <?php
                                printf(
                                    $capsules
                                        /* translators: 1: numarul de capsule al variantei propuse, 2: al celei curente. */
                                        ? esc_html__('%1$s capsule în loc de %2$s', 'herbal-therapy')
                                        /* translators: 1: numarul de tablete al variantei propuse, 2: al celei curente. */
                                        : esc_html__('%1$s tablete în loc de %2$s', 'herbal-therapy'),
                                    '<strong>' . esc_html(number_format_i18n($row['size'])) . '</strong>',
                                    esc_html(number_format_i18n($size))
                                );
                                ?>
                            </p>
                        <?php endif; ?>

                        <p class="ht-pp__upgrade-prices">
                            <span class="ht-pp__upgrade-price">
                                <?php echo wp_kses_post(ht_format_price(wc_get_price_to_display($item))); ?>
                            </span>

                            <?php if ($row['unit'] > 0) : ?>
                                <span class="ht-pp__upgrade-unit">
                                    <?php
                                    printf(
                                        /* translators: %s: pretul unei bucati. */
                                        esc_html__('%s / buc.', 'herbal-therapy'),
                                        wp_kses_post(ht_format_price($row['unit']))
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </p>

                        <?php if ($row['saving'] > 0) : ?>
                            <p class="ht-pp__upgrade-save">
                                <?php
                                printf(
                                    /* translators: %d: procentul economisit la pretul pe bucata. */
                                    esc_html__('Economisești %d%% pe bucată', 'herbal-therapy'),
                                    (int)$row['saving']
                                );
                                ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($ajax) : ?>
                            <button class="ht-pp__upgrade-cta add_to_cart_button ajax_add_to_cart" type="button"
                                    data-quantity="1"
                                    data-product_id="<?php echo esc_attr($item->get_id()); ?>"
                                    data-product_sku="<?php echo esc_attr($item->get_sku()); ?>"
                                    data-added-text="<?php esc_attr_e('În coș', 'herbal-therapy'); ?>">
                                <?php ht_icon('cart-btn', 'ht-pp__cart-icon'); ?>
                                <span data-ht-cart-text><?php esc_html_e('Adaugă în coș', 'herbal-therapy'); ?></span>
                            </button>
                        <?php else : ?>
                            <a class="ht-pp__upgrade-cta" href="<?php echo esc_url($link); ?>">
                                <?php esc_html_e('Vezi produsul', 'herbal-therapy'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Cardul de cumparare din dreapta.
 *
 * @param WC_Product $product Produsul.
 */
function ht_single_product_buy($product)
{
    $in_stock = $product->is_in_stock();
    $discount = ht_product_discount($product);
    $regular = (float)$product->get_regular_price();
    ?>
    <aside class="ht-pp__buy">

        <div class="ht-pp__buy-head">
            <div class="ht-pp__buy-prices">
                <p class="ht-pp__stock ht-pp__stock--<?php echo $in_stock ? 'yes' : 'no'; ?>">
                    <?php echo esc_html($in_stock ? __('În stoc', 'herbal-therapy') : __('Stoc epuizat', 'herbal-therapy')); ?>
                </p>

                <div class="ht-pp__price-box">
                    <?php if ($discount > 0) : ?>
                        <p class="ht-pp__price-old">
                            <del><?php echo wp_kses_post(ht_format_price($regular)); ?></del>
                            <span class="ht-pp__discount">
                                <?php
                                /* translators: %d: procentul reducerii. */
                                printf(esc_html__('-%d%%', 'herbal-therapy'), (int)$discount);
                                ?>
                            </span>
                        </p>
                    <?php endif; ?>

                    <?php
                    /*
                     * La reducere pretul vechi e scris deja pe randul de deasupra,
                     * ca in design, deci aici ramane doar pretul curent; la
                     * produsele variabile, unde pretul e un interval, se pastreaza
                     * formatul WooCommerce.
                     */
                    $price = ($discount > 0 && !$product->is_type('variable'))
                        ? ht_format_price(wc_get_price_to_display($product))
                        : $product->get_price_html();
                    ?>
                    <p class="ht-pp__price"><?php echo wp_kses_post($price); ?></p>
                </div>
            </div>

            <?php ht_favorite_button($product->get_id(), 'ht-pp__fav'); ?>
        </div>

        <p class="ht-pp__tax"><?php esc_html_e('TVA inclus', 'herbal-therapy'); ?></p>

        <?php ht_single_product_buttons($product); ?>

        <div class="ht-pp__secure">
            <?php ht_product_asset_icon('icon-money', 24); ?>
            <div class="ht-pp__secure-body">
                <p class="ht-pp__secure-title"><?php esc_html_e('Plătești în siguranță cu', 'herbal-therapy'); ?></p>
                <img class="ht-pp__secure-logo"
                     src="<?php echo esc_url(ht_asset_uri('/assets/img/product/victoriabank.svg')); ?>"
                     width="164" height="24" alt="Victoriabank" loading="lazy" decoding="async">
            </div>
        </div>

        <?php
        /*
         * La pachetele statice, in locul caruselului sta lista produselor din
         * pachet; la restul raman recomandarile "cumparate impreuna".
         */
        if (!ht_single_product_bundle($product)) {
            ht_single_product_together($product);
        }
        ?>
    </aside>
    <?php
}

/**
 * Blocul de sus al paginii de produs.
 *
 * @param WC_Product|null $product Produsul; implicit cel din bucla.
 */
function ht_single_product_top($product = null)
{
    if (!$product) {
        $product = wc_get_product(get_the_ID());
    }

    if (!is_a($product, 'WC_Product')) {
        return;
    }

    /*
     * Se declanseaza inaintea containerului, nu inauntru: '.ht-pp__top' e un
     * flex cu doua coloane, iar orice element scris de o extensie ar deveni a treia.
     */
    do_action('woocommerce_before_single_product_summary');
    ?>
    <div class="ht-pp__top">
        <div class="ht-pp__cols">
            <?php
            ht_single_product_gallery($product);
            ht_single_product_info($product);
            ?>
        </div>

        <?php ht_single_product_buy($product); ?>
    </div>
    <?php
}

/* ---------------------------------------------------------------------------
 * Detaliile: taburile de text si tabelul de ingrediente
 * ------------------------------------------------------------------------ */

/**
 * Taburile de text plus cardul de ingrediente.
 */
function ht_single_product_details()
{
    global $product;

    if (!is_a($product, 'WC_Product')) {
        return;
    }

    $tabs = ht_product_tabs($product);
    $ingredients = ht_product_ingredients($product);

    if (!$tabs && !$ingredients) {
        return;
    }
    ?>
    <section class="ht-pp__details" id="descriere">

        <?php if ($tabs) : ?>
            <div class="ht-pp__tabs" data-ht-pp-tabs>
                <div class="ht-pp__tabs-nav" role="tablist">
                    <?php foreach ($tabs as $index => $tab) : ?>
                        <button class="ht-pp__tab<?php echo 0 === $index ? ' is-active' : ''; ?>"
                                type="button"
                                role="tab"
                                id="ht-tab-<?php echo esc_attr($tab['id']); ?>"
                                aria-controls="ht-panel-<?php echo esc_attr($tab['id']); ?>"
                                aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                                data-ht-pp-tab="<?php echo esc_attr($tab['id']); ?>">
                            <?php echo esc_html($tab['title']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($tabs as $index => $tab) : ?>
                    <div class="ht-pp__panel<?php echo 0 === $index ? ' is-active' : ''; ?>"
                         role="tabpanel"
                         id="ht-panel-<?php echo esc_attr($tab['id']); ?>"
                         aria-labelledby="ht-tab-<?php echo esc_attr($tab['id']); ?>"
                         data-ht-pp-panel="<?php echo esc_attr($tab['id']); ?>"
                        <?php echo 0 === $index ? '' : 'hidden'; ?>>
                        <?php echo wp_kses_post(wpautop($tab['content'])); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($ingredients) : ?>
            <div class="ht-pp__ingredients">
                <p class="ht-pp__ingredients-title"><?php esc_html_e('Ingrediente', 'herbal-therapy'); ?></p>

                <ul class="ht-pp__ingredients-list">
                    <?php foreach ($ingredients as $row) : ?>
                        <li class="ht-pp__ingredient<?php echo $row['strong'] ? ' is-strong' : ''; ?>">
                            <?php echo esc_html($row['text']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

add_action('woocommerce_after_single_product_summary', 'ht_single_product_details', 10);

/**
 * Blocul de recenzii, pe toata latimea.
 *
 * In randarea implicita recenziile stau intr-un tab; in design sunt o sectiune
 * de sine statatoare, sub detalii, deci sablonul se cheama direct.
 */
function ht_single_product_reviews()
{
    if (comments_open() || get_comments_number()) {
        comments_template();
    }
}

add_action('woocommerce_after_single_product_summary', 'ht_single_product_reviews', 20);

/**
 * Caruselul "Produse asemănătoare".
 */
function ht_single_product_related()
{
    global $product;

    if (!is_a($product, 'WC_Product')) {
        return;
    }

    $ids = array_map('intval', wc_get_related_products(
        $product->get_id(),
        (int)apply_filters('ht_product_related_limit', 10)
    ));

    if (!$ids) {
        return;
    }

    ht_products_carousel(array(
        'title'   => __('Produse asemănătoare', 'herbal-therapy'),
        'include' => $ids,
        'orderby' => 'none',
        'limit'   => count($ids),
    ));
}

add_action('woocommerce_after_single_product_summary', 'ht_single_product_related', 30);

/* ---------------------------------------------------------------------------
 * Iconitele exportate din Figma
 * ------------------------------------------------------------------------ */

/**
 * O iconita din assets/img/product, scrisa inline.
 *
 * Fisierele sunt exporturile din Figma, deci desenul e cel din design; le
 * scriem inline (nu prin <img>) ca sa nu mai fie inca o cerere pe fiecare rand
 * si ca sa se poata dimensiona din CSS.
 *
 * @param string $name Numele fisierului, fara extensie.
 * @param int    $size Latimea si inaltimea in px.
 */
function ht_product_asset_icon($name, $size = 24)
{
    static $cache = array();

    $name = preg_replace('~[^a-z0-9\-]~', '', (string)$name);

    if (!isset($cache[$name])) {
        $path = HT_DIR . '/assets/img/product/' . $name . '.svg';
        /* phpcs:ignore WordPress.WP.AlternativeFunctions -- fisier local al temei. */
        $cache[$name] = file_exists($path) ? (string)file_get_contents($path) : '';
    }

    if ('' === $cache[$name]) {
        return;
    }

    printf(
        '<span class="ht-pp__icon" style="width:%1$dpx;height:%1$dpx" aria-hidden="true">%2$s</span>',
        (int)$size,
        $cache[$name] // phpcs:ignore WordPress.Security.EscapeOutput -- SVG local al temei.
    );
}

/* ---------------------------------------------------------------------------
 * Firimiturile
 * ------------------------------------------------------------------------ */

/**
 * Separatorul din firimituri, ca in design.
 *
 * @param array $args Argumentele lui woocommerce_breadcrumb().
 *
 * @return array
 */
function ht_product_breadcrumb_args($args)
{
    $args['delimiter'] = '<span class="ht-crumbs__sep">/</span>';
    $args['wrap_before'] = '<nav class="woocommerce-breadcrumb ht-crumbs">';
    $args['wrap_after'] = '</nav>';

    return $args;
}

add_filter('woocommerce_breadcrumb_defaults', 'ht_product_breadcrumb_args');

/**
 * Prima firimitura poarta numele din design ("Acasă", nu "Prima pagină").
 *
 * WooCommerce nu are un filtru doar pentru textul ei, deci il schimbam in lista
 * gata construita, dupa legatura - asa nu atingem si celelalte firimituri.
 *
 * @param array $crumbs Firimiturile, ca perechi [nume, legatura].
 *
 * @return array
 */
function ht_product_breadcrumb_crumbs($crumbs)
{
    if (isset($crumbs[0][1]) && untrailingslashit($crumbs[0][1]) === untrailingslashit(home_url())) {
        $crumbs[0][0] = __('Acasă', 'herbal-therapy');
    }

    return $crumbs;
}

add_filter('woocommerce_get_breadcrumb', 'ht_product_breadcrumb_crumbs');

/* ---------------------------------------------------------------------------
 * Fisierele paginii
 * ------------------------------------------------------------------------ */

/**
 * CSS-ul si JS-ul paginii de produs.
 */
function ht_enqueue_single_product_assets()
{
    if (!is_product()) {
        return;
    }

    /* lightbox-ul galeriei; tinut local, ca Swiper */
    wp_enqueue_style(
        'fancybox',
        ht_asset_uri('/assets/css/fancybox.css'),
        array(),
        '5.0.36'
    );

    wp_enqueue_script(
        'fancybox',
        ht_asset_uri('/assets/js/fancybox.umd.js'),
        array(),
        '5.0.36',
        true
    );

    wp_enqueue_style(
        'ht-single-product',
        ht_asset_uri('/assets/css/single-product.css'),
        array('swiper', 'fancybox', 'ht-style', 'ht-shop'),
        ht_asset_version('/assets/css/single-product.css')
    );

    wp_enqueue_script(
        'ht-single-product',
        ht_asset_uri('/assets/js/single-product.js'),
        array('swiper', 'fancybox', 'ht-products'),
        ht_asset_version('/assets/js/single-product.js'),
        true
    );

    /* etichetele butoanelor din lightbox, in limba curenta */
    wp_localize_script('ht-single-product', 'htSingleProductData', array(
        'fancyboxL10n' => array(
            'CLOSE'             => __('Închide', 'herbal-therapy'),
            'NEXT'              => __('Imaginea următoare', 'herbal-therapy'),
            'PREV'              => __('Imaginea anterioară', 'herbal-therapy'),
            'MODAL'             => __('Poți închide această fereastră cu tasta ESC', 'herbal-therapy'),
            'ERROR'             => __('A apărut o problemă. Încearcă din nou mai târziu.', 'herbal-therapy'),
            'IMAGE_ERROR'       => __('Imaginea nu a fost găsită', 'herbal-therapy'),
            'TOGGLE_ZOOM'       => __('Mărește sau micșorează', 'herbal-therapy'),
            'TOGGLE_THUMBS'     => __('Arată sau ascunde miniaturile', 'herbal-therapy'),
            'TOGGLE_SLIDESHOW'  => __('Pornește sau oprește prezentarea', 'herbal-therapy'),
            'TOGGLE_FULLSCREEN' => __('Comută pe tot ecranul', 'herbal-therapy'),
            'DOWNLOAD'          => __('Descarcă', 'herbal-therapy'),
        ),
    ));

    /* butonul de cos foloseste acelasi script de AJAX ca listarile */
    wp_enqueue_script('wc-add-to-cart');
}

/* dupa ht_enqueue_shop_styles (20), ca dependenta 'ht-shop' sa fie inregistrata */
add_action('wp_enqueue_scripts', 'ht_enqueue_single_product_assets', 21);
