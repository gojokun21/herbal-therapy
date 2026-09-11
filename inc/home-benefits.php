<?php
/**
 * Sectiunea "Puterea naturii" de pe prima pagina - argumentele brandului
 * (formule unice, eficienta, calitate, mediu), fiecare cu iconita, titlu si text.
 *
 * Reproduce blocul "multicolumn" de pe site-ul Shopify, cu care clientii sunt
 * deja obisnuiti. Referinta Figma nu il are, asa ca e scris in limbajul temei:
 * titlul de sectiune al caruselelor, placa gri a cardurilor, verdele din butoane.
 *
 * Continutul se rescrie din prima pagina, in grupul ACF "Beneficii (prima
 * pagina)" (acf-json/group_ht_home_benefits.json): titlul sectiunii, un
 * comutator de ascundere si repeater-ul 'ht_beneficii'. Fara ACF sau cu
 * repeater-ul gol, sectiunea arata cele patru carduri de pe site-ul vechi, cu
 * iconitele livrate cu tema (assets/img/home/benefits/), ca sa fie completa
 * imediat ce sablonul e asignat.
 *
 * Se randeaza cu ht_home_benefits_section() dintr-un template sau cu
 * shortcode-ul [ht_beneficii] din continutul unei pagini.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Citeste un camp ACF de pe pagina curenta.
 *
 * @param string $name Numele campului.
 *
 * @return mixed Null cand ACF lipseste sau campul nu are valoare.
 */
function ht_home_benefits_field($name)
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
 * Cardurile din design: continutul sectiunii de pe site-ul vechi.
 *
 * Textele contin <strong> pentru cuvintele scoase in evidenta, exact ca acolo.
 *
 * @return array
 */
function ht_home_benefits_defaults()
{
    $icons = ht_asset_uri('/assets/img/home/benefits/');

    return array(
        array(
            'icon'  => $icons . 'formule-unice.png',
            'title' => __('Formule Unice', 'herbal-therapy'),
            'text'  => __(
                'Produsele noastre conțin <strong>extracte personalizate</strong>, dezvoltate special <strong>pentru a aborda nevoile tale specifice</strong>.',
                'herbal-therapy'
            ),
        ),
        array(
            'icon'  => $icons . 'eficienta-maxima.png',
            'title' => __('Eficiență Maximă', 'herbal-therapy'),
            'text'  => __(
                'Realizarea internă a extractelor ne permite să <strong>maximizăm concentrația compușilor activi</strong>, oferind <strong>produse mai eficiente</strong> și <strong>rezultate mai rapide</strong>.',
                'herbal-therapy'
            ),
        ),
        array(
            'icon'  => $icons . 'calitate-garantata.png',
            'title' => __('Calitate Garantată', 'herbal-therapy'),
            'text'  => __(
                'Ne monitorizăm direct procesul de producție, asigurându-ne că <strong>plantele sunt cultivate organic</strong>, <strong>fără pesticide sau chimicale</strong> dăunătoare.',
                'herbal-therapy'
            ),
        ),
        array(
            'icon'  => $icons . 'grija-pentru-mediu.png',
            'title' => __('Grijă pentru Mediu', 'herbal-therapy'),
            'text'  => __(
                'Prin producția internă a extractelor, adoptăm practici ecologice ce reduc impactul asupra mediului și <strong>susțin un viitor sănătos pentru tine și cei dragi.</strong>',
                'herbal-therapy'
            ),
        ),
    );
}

/**
 * Cardurile afisate: randurile din ACF, altfel cele din design.
 *
 * Randurile fara titlu si fara text se sar. Un card fara iconita se afiseaza
 * fara ea - textul e argumentul, iconita doar il insoteste.
 *
 * @param array $args 'limit' - cate carduri, 0 inseamna toate.
 *
 * @return array Lista de carduri normalizate: 'icon', 'icon_alt', 'title', 'text'.
 */
function ht_home_benefits_data($args = array())
{
    $args = wp_parse_args($args, array(
        'limit' => 0,
    ));

    $rows = ht_home_benefits_field('ht_beneficii');
    $cards = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $title = isset($row['titlu']) ? trim((string)$row['titlu']) : '';
            $text = isset($row['text']) ? trim((string)$row['text']) : '';

            if ('' === $title && '' === $text) {
                continue;
            }

            $icon = isset($row['iconita']) && is_array($row['iconita']) ? $row['iconita'] : array();

            $cards[] = array(
                'icon'     => !empty($icon['url']) ? (string)$icon['url'] : '',
                'icon_alt' => !empty($icon['alt']) ? (string)$icon['alt'] : '',
                'title'    => $title,
                'text'     => $text,
            );
        }
    }

    if (!$cards) {
        foreach (ht_home_benefits_defaults() as $card) {
            $cards[] = array(
                'icon'     => $card['icon'],
                'icon_alt' => '',
                'title'    => $card['title'],
                'text'     => $card['text'],
            );
        }
    }

    if ($args['limit'] > 0) {
        $cards = array_slice($cards, 0, (int)$args['limit']);
    }

    return apply_filters('ht_home_benefits_data', $cards, $args);
}

/**
 * Titlul sectiunii: din ACF, altfel cel din design.
 *
 * @return string
 */
function ht_home_benefits_title()
{
    $title = (string)ht_home_benefits_field('ht_home_benefits_title');

    if ('' === $title) {
        $title = __('Puterea naturii, concentrată în fiecare produs', 'herbal-therapy');
    }

    return (string)apply_filters('ht_home_benefits_title', $title);
}

/**
 * Textul unui card, curatat si impartit in paragrafe.
 *
 * Campul ACF e un editor simplu (bold, italic, link), iar rezervele au deja
 * <strong>; wp_kses_post lasa exact acest gen de marcaj si nimic periculos.
 *
 * @param string $text Textul brut.
 *
 * @return string HTML sigur.
 */
function ht_home_benefits_text($text)
{
    return wpautop(wp_kses_post((string)$text));
}

/**
 * Randeaza un card.
 *
 * @param array $card Cardul, din ht_home_benefits_data().
 */
function ht_home_benefits_card($card)
{
    ?>
    <article class="ht-home-benefit">
        <?php if ('' !== $card['icon']) : ?>
            <span class="ht-home-benefit__icon">
                <img class="ht-home-benefit__img"
                     src="<?php echo esc_url($card['icon']); ?>"
                     width="56" height="56"
                     alt="<?php echo esc_attr($card['icon_alt']); ?>"
                     loading="lazy" decoding="async">
            </span>
        <?php endif; ?>

        <?php if ('' !== $card['title']) : ?>
            <h3 class="ht-home-benefit__title"><?php echo esc_html($card['title']); ?></h3>
        <?php endif; ?>

        <?php if ('' !== $card['text']) : ?>
            <div class="ht-home-benefit__text">
                <?php echo ht_home_benefits_text($card['text']); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_home_benefits_text(). ?>
            </div>
        <?php endif; ?>
    </article>
    <?php
}

/**
 * Randeaza sectiunea.
 *
 * Nu se afiseaza cand editorul a bifat "Ascunde sectiunea" in ACF.
 *
 * @param array $args 'title' (gol inseamna titlul din ACF / design) si 'limit'.
 */
function ht_home_benefits_section($args = array())
{
    $args = wp_parse_args($args, array(
        'title' => '',
        'limit' => 0,
    ));

    if (ht_home_benefits_field('ht_home_benefits_hide')) {
        return;
    }

    $cards = ht_home_benefits_data($args);

    if (!$cards) {
        return;
    }

    $title = '' !== (string)$args['title'] ? (string)$args['title'] : ht_home_benefits_title();
    ?>
    <section class="ht-home-benefits" data-ht-home-benefits>
        <div class="ht-wrapper">

            <?php if ('' !== $title) : ?>
                <div class="ht-home-benefits__head">
                    <h2 class="ht-home-benefits__title"><?php echo esc_html($title); ?></h2>
                </div>
            <?php endif; ?>

            <?php /* grila pe desktop; sub 900px assets/js/home-benefits.js o face carusel */ ?>
            <div class="swiper ht-home-benefits__swiper">
                <div class="swiper-wrapper ht-home-benefits__grid">
                    <?php foreach ($cards as $card) : ?>
                        <div class="swiper-slide ht-home-benefits__slide">
                            <?php ht_home_benefits_card($card); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="swiper-pagination ht-home-benefits__pagination"></div>
        </div>
    </section>
    <?php
}

/**
 * Shortcode: [ht_beneficii title="..." limit="4"]
 *
 * @param array $atts Atributele shortcode-ului.
 *
 * @return string
 */
function ht_home_benefits_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'title' => '',
        'limit' => 0,
    ), $atts, 'ht_beneficii');

    ob_start();
    ht_home_benefits_section(array(
        'title' => $atts['title'],
        'limit' => (int)$atts['limit'],
    ));

    return ob_get_clean();
}

add_shortcode('ht_beneficii', 'ht_home_benefits_shortcode');
add_shortcode('ht_benefits', 'ht_home_benefits_shortcode');
