<?php
/**
 * Blocul "Despre noi" de la finalul primei pagini - structura si masuratorile
 * din Figma (frame-ul "Acasa", nodul 29:699).
 *
 * O placa gri cu text in stanga si fotografia fabricii in dreapta. Continutul se
 * rescrie din prima pagina, in grupul ACF "Blocul despre noi (prima pagina)"
 * (acf-json/group_ht_home_about.json). Fara ACF - sau cu campurile goale - blocul
 * arata textele si fotografia din design, ca sa fie complet imediat ce sablonul
 * e asignat.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ID-ul paginii care foloseste sablonul "Despre noi".
 *
 * Este destinatia implicita a butonului, cand link-ul nu e completat din ACF.
 *
 * @return int ID-ul paginii sau 0 cand niciuna nu foloseste sablonul.
 */
function ht_about_page_id()
{
    static $id = null;

    if (null !== $id) {
        return $id;
    }

    $pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'   => '_wp_page_template',
                'value' => 'templates/about.php',
            ),
        ),
    ));

    $id = $pages ? (int)$pages[0] : 0;

    return (int)apply_filters('ht_about_page_id', $id);
}

/**
 * Citeste un camp ACF de pe pagina curenta.
 *
 * @param string $name Numele campului.
 *
 * @return mixed Null cand ACF lipseste sau campul nu are valoare.
 */
function ht_home_about_field($name)
{
    if (!function_exists('get_field')) {
        return null;
    }

    $id = (int)get_queried_object_id();

    if (!$id) {
        return null;
    }

    $value = get_field($name, $id);

    return ('' === $value || null === $value || array() === $value) ? null : $value;
}

/**
 * Datele blocului: ce e completat in ACF, restul din design.
 *
 * Se suprascrie complet cu: add_filter('ht_home_about', ...)
 *
 * @return array
 */
function ht_home_about_data()
{
    $image = ht_home_about_field('ht_home_about_image');
    $button = ht_home_about_field('ht_home_about_button');
    $button = is_array($button) ? $button : array();

    $url = !empty($button['url']) ? $button['url'] : '';

    if ('' === $url) {
        $page = ht_about_page_id();
        $url = $page ? (string)get_permalink($page) : '';
    }

    $data = array(
        'title'        => (string)ht_home_about_field('ht_home_about_title'),
        'text'         => (string)ht_home_about_field('ht_home_about_text'),
        'image'        => !empty($image['url']) ? $image['url'] : '',
        'image_alt'    => !empty($image['alt']) ? $image['alt'] : '',
        'image_width'  => !empty($image['width']) ? (int)$image['width'] : 0,
        'image_height' => !empty($image['height']) ? (int)$image['height'] : 0,
        'url'          => $url,
        'label'        => !empty($button['title']) ? $button['title'] : __('Despre noi', 'herbal-therapy'),
        'target'       => !empty($button['target']) ? $button['target'] : '_self',
    );

    /* rezervele din design, folosite doar acolo unde ACF n-a spus nimic */
    if ('' === $data['title']) {
        $data['title'] = __('Fabrica Farmaceutică din Chișinău, Republica Moldova', 'herbal-therapy');
    }

    if ('' === $data['text']) {
        $data['text'] = __(
            'Herbal Therapy este un brand care vine în ajutorul tău, ca tu să ai acces la nenumăratele beneficii ale naturii.',
            'herbal-therapy'
        );
    }

    if ('' === $data['image']) {
        $data['image'] = ht_asset_uri('/assets/img/home/factory.webp');
        $data['image_width'] = 1446;
        $data['image_height'] = 930;
    }

    return apply_filters('ht_home_about', $data);
}

/**
 * Randeaza blocul.
 */
function ht_home_about()
{
    $data = ht_home_about_data();

    if ('' === $data['title'] && '' === $data['image']) {
        return;
    }
    ?>
    <section class="ht-home-about">
        <div class="ht-wrapper">
            <div class="ht-home-about__box">

                <div class="ht-home-about__text">
                    <?php if ($data['title'] !== '') : ?>
                        <h2 class="ht-home-about__title"><?php echo esc_html($data['title']); ?></h2>
                    <?php endif; ?>

                    <?php if ($data['text'] !== '') : ?>
                        <p class="ht-home-about__lead"><?php echo wp_kses_post(nl2br($data['text'])); ?></p>
                    <?php endif; ?>

                    <?php if ($data['url'] !== '') : ?>
                        <a class="ht-home-about__btn" href="<?php echo esc_url($data['url']); ?>"
                           target="<?php echo esc_attr($data['target']); ?>"
                            <?php echo '_blank' === $data['target'] ? ' rel="noopener"' : ''; ?>>
                            <?php echo esc_html($data['label']); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($data['image'] !== '') : ?>
                    <div class="ht-home-about__media">
                        <img class="ht-home-about__img"
                             src="<?php echo esc_url($data['image']); ?>"
                             <?php if ($data['image_width'] && $data['image_height']) : ?>
                                 width="<?php echo esc_attr($data['image_width']); ?>"
                                 height="<?php echo esc_attr($data['image_height']); ?>"
                             <?php endif; ?>
                             alt="<?php echo esc_attr($data['image_alt'] !== '' ? $data['image_alt'] : $data['title']); ?>"
                             loading="lazy"
                             decoding="async">
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>
    <?php
}

/**
 * Shortcode: [ht_despre_noi]
 *
 * @return string
 */
function ht_home_about_shortcode()
{
    ob_start();
    ht_home_about();

    return ob_get_clean();
}

add_shortcode('ht_despre_noi', 'ht_home_about_shortcode');
add_shortcode('ht_home_about', 'ht_home_about_shortcode');
