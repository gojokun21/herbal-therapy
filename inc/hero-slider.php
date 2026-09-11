<?php
/**
 * Carusel principal (hero) - structura si comportament ca in referinta.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Slide-urile carusel-ului.
 *
 * Sursa: repeater-ul ACF "ht_hero_slides" de pe prima pagina
 * (acf-json/group_ht_hero.json). Fara randuri, caruselul nu se afiseaza.
 *
 * Se suprascrie complet cu: add_filter('ht_hero_slides', ...)
 *
 * @return array
 */
function ht_hero_slides()
{
    $slides = array();

    if (function_exists('get_field')) {
        $rows = get_field('ht_hero_slides', get_queried_object_id());

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (empty($row['image']['url'])) {
                    continue;
                }

                $button = (isset($row['button']) && is_array($row['button'])) ? $row['button'] : array();

                $slides[] = array(
                    'desktop'       => $row['image']['url'],
                    'mobile'        => !empty($row['mobile']['url']) ? $row['mobile']['url'] : $row['image']['url'],
                    'alt'           => isset($row['image']['alt']) ? $row['image']['alt'] : '',
                    'title'         => isset($row['title']) ? $row['title'] : '',
                    'text'          => isset($row['text']) ? $row['text'] : '',
                    'button_url'    => isset($button['url']) ? $button['url'] : '',
                    'button_label'  => !empty($button['title']) ? $button['title'] : __('Descoperă produsele', 'herbal-therapy'),
                    'button_target' => !empty($button['target']) ? $button['target'] : '_self',
                );
            }
        }
    }

    return apply_filters('ht_hero_slides', $slides);
}

/**
 * Durata implicita a unui slide, in milisecunde.
 */
function ht_hero_delay()
{
    return (int)apply_filters('ht_hero_delay', 9000);
}

/**
 * Randeaza carusel-ul. Se poate apela din orice template.
 */
function ht_hero_slider()
{
    $slides = ht_hero_slides();

    if (empty($slides)) {
        return;
    }

    $default_delay = ht_hero_delay();
    $ad_label = apply_filters('ht_hero_ad_label', __('Publicitate', 'herbal-therapy'));
    $index = 0;
    ?>
    <section class="ht-hero">
        <div class="swiper ht-hero__swiper" data-ht-hero>
            <div class="swiper-wrapper">
                <?php foreach ($slides as $slide) :
                    $index++;
                    $desktop = isset($slide['desktop']) ? $slide['desktop'] : '';
                    $mobile = isset($slide['mobile']) ? $slide['mobile'] : $desktop;

                    if ($desktop === '' && $mobile === '') {
                        continue;
                    }

                    $url = isset($slide['url']) ? $slide['url'] : '';
                    $target = isset($slide['target']) ? $slide['target'] : '_self';
                    $alt = isset($slide['alt']) ? $slide['alt'] : sprintf(
                        /* translators: %d: pozitia slide-ului in carusel. */
                        __('Slide %d', 'herbal-therapy'),
                        $index
                    );
                    $delay = isset($slide['delay']) ? (int)$slide['delay'] : $default_delay;
                    $is_ad = !empty($slide['ad']);
                    $tag = $url !== '' ? 'a' : 'div';

                    $title = isset($slide['title']) ? trim((string)$slide['title']) : '';
                    $text = isset($slide['text']) ? trim((string)$slide['text']) : '';
                    $btn_url = isset($slide['button_url']) ? $slide['button_url'] : '';
                    $btn_label = isset($slide['button_label']) ? $slide['button_label'] : '';
                    $btn_target = isset($slide['button_target']) ? $slide['button_target'] : '_self';
                    ?>
                    <div class="swiper-slide ht-hero__slide"
                         data-swiper-autoplay="<?php echo esc_attr($delay); ?>"
                         style="--slide-duration: <?php echo esc_attr($delay); ?>ms;">
                        <<?php echo $tag; ?> class="ht-hero__slide-inner"
                            <?php if ($url !== '') : ?>
                                href="<?php echo esc_url($url); ?>"
                                target="<?php echo esc_attr($target); ?>"
                                <?php echo $target === '_blank' ? 'rel="noopener"' : ''; ?>
                            <?php endif; ?>>
                            <picture>
                                <?php if ($desktop !== '' && $desktop !== $mobile) : ?>
                                    <source srcset="<?php echo esc_url($desktop); ?>" media="(min-width: 651px)">
                                <?php endif; ?>
                                <img class="ht-hero__img"
                                     src="<?php echo esc_url($mobile !== '' ? $mobile : $desktop); ?>"
                                     alt="<?php echo esc_attr($alt); ?>"
                                     width="1440" height="656"
                                     loading="<?php echo $index === 1 ? 'eager' : 'lazy'; ?>"
                                     fetchpriority="<?php echo $index === 1 ? 'high' : 'auto'; ?>"
                                     decoding="async">
                            </picture>
                            <?php if ($title !== '' || $text !== '' || $btn_url !== '') : ?>
                                <div class="ht-hero__content">
                                    <div class="ht-wrapper ht-hero__content-inner">
                                        <?php if ($title !== '') : ?>
                                            <h2 class="ht-hero__title"><?php echo esc_html($title); ?></h2>
                                        <?php endif; ?>
                                        <?php if ($text !== '') : ?>
                                            <p class="ht-hero__text"><?php echo wp_kses($text, array('br' => array(), 'strong' => array(), 'em' => array())); ?></p>
                                        <?php endif; ?>
                                        <?php if ($btn_url !== '') : ?>
                                            <a class="ht-hero__btn"
                                               href="<?php echo esc_url($btn_url); ?>"
                                               target="<?php echo esc_attr($btn_target); ?>"
                                                <?php echo $btn_target === '_blank' ? 'rel="noopener"' : ''; ?>><?php echo esc_html($btn_label); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($is_ad) : ?>
                                <span class="ht-hero__ad"><?php echo esc_html($ad_label); ?></span>
                            <?php endif; ?>
                        </<?php echo $tag; ?>>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($slides) > 1) : ?>
                <div class="swiper-button-prev ht-hero__arrow ht-hero__arrow--prev"></div>
                <div class="swiper-button-next ht-hero__arrow ht-hero__arrow--next"></div>
                <div class="swiper-pagination ht-hero__pagination"></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
