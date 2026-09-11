<?php
/**
 * Pagina "Despre noi".
 *
 * Se randeaza cu ht_about_page() dintr-un sablon de pagina. Masuratorile vin
 * din Figma (frame-ul "Despre noi", 1920x6584), luate pe un container de
 * 1440px; latimile si distantele orizontale sunt scrise in CSS ca procent din
 * container, ca sa tina aceleasi proportii pe wrapper-ul temei (1520px).
 * Sectiunile sunt despartite peste tot de 120px.
 *
 * Continutul vine exclusiv din pagina, din grupul ACF "Pagina despre noi"
 * (acf-json/group_ht_about.json). In cod nu exista date de rezerva: un camp
 * gol nu afiseaza nimic, iar o sectiune fara titlu, text si fotografie nu se
 * randeaza deloc. Textele din design se scriu in pagina cu bin/seed.php
 * (continutul, pe limbi, e in bin/seed-about-content.php).
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Citirea datelor
 * ------------------------------------------------------------------------ */

/**
 * Citeste un camp ACF de pe pagina curenta.
 *
 * @param string $name Numele campului.
 *
 * @return mixed Null cand ACF lipseste sau campul nu are valoare.
 */
function ht_about_field($name)
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
 * Valoarea unui camp de text; sir gol cand nu e completat.
 *
 * @param string $name Numele campului ACF.
 *
 * @return string
 */
function ht_about_text($name)
{
    $value = ht_about_field($name);
    $value = (null === $value) ? '' : trim((string)$value);

    return (string)apply_filters('ht_about_text', $value, $name);
}

/**
 * O fotografie a paginii, din campul ACF.
 *
 * @param string $name Numele campului ACF.
 *
 * @return array|null 'url' si 'alt', sau null cand nu e aleasa.
 */
function ht_about_image($name)
{
    $field = ht_about_field($name);
    $image = null;

    if (is_array($field) && !empty($field['url'])) {
        $image = array(
            'url' => (string)$field['url'],
            'alt' => isset($field['alt']) ? (string)$field['alt'] : '',
        );
    }

    return apply_filters('ht_about_image', $image, $name);
}

/**
 * Afiseaza o fotografie a paginii; nimic cand nu e aleasa.
 *
 * @param string $name    Numele campului ACF.
 * @param string $classes Clasele imaginii.
 * @param string $loading 'lazy' sau 'eager'.
 */
function ht_about_the_image($name, $classes = '', $loading = 'lazy')
{
    $image = ht_about_image($name);

    if (!$image) {
        return;
    }

    printf(
        '<img class="%1$s" src="%2$s" alt="%3$s" loading="%4$s" decoding="async" />',
        esc_attr($classes),
        esc_url($image['url']),
        esc_attr($image['alt']),
        esc_attr($loading)
    );
}

/**
 * Transforma un text pe randuri libere in paragrafe.
 *
 * Randurile goale despart paragrafele - asa arata si textele din design, unde
 * intre blocuri exista cate un rand liber.
 *
 * @param string $text Textul brut sau deja formatat.
 *
 * @return string HTML gata de afisat.
 */
function ht_about_paragraphs($text)
{
    $text = trim((string)$text);

    if ('' === $text) {
        return '';
    }

    /* textul scris cu editorul vine deja pe paragrafe */
    if (false !== stripos($text, '<p')) {
        return wp_kses_post($text);
    }

    $blocks = preg_split('/\R{2,}/u', $text);
    $out = '';

    foreach ((array)$blocks as $block) {
        $block = trim((string)$block);

        if ('' === $block) {
            continue;
        }

        $out .= '<p>' . wp_kses_post($block) . '</p>';
    }

    return $out;
}

/* ---------------------------------------------------------------------------
 * Randurile repetabile
 * ------------------------------------------------------------------------ */

/**
 * Cele trei cartonase cu valorile brandului.
 *
 * @return array Fiecare rand are 'title', 'text' si 'image'.
 */
function ht_about_values()
{
    $rows = ht_about_field('ht_about_values');
    $values = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $title = isset($row['title']) ? trim((string)$row['title']) : '';
            $text = isset($row['text']) ? trim((string)$row['text']) : '';

            if ('' === $title && '' === $text) {
                continue;
            }

            $image = (isset($row['image']) && is_array($row['image']) && !empty($row['image']['url']))
                ? array(
                    'url' => (string)$row['image']['url'],
                    'alt' => isset($row['image']['alt']) ? (string)$row['image']['alt'] : '',
                )
                : array('url' => '', 'alt' => '');

            $values[] = array(
                'title' => $title,
                'text'  => $text,
                'image' => $image,
            );
        }
    }

    return apply_filters('ht_about_values', $values);
}

/**
 * Fotografiile din banda "Fabrica si Depozit".
 *
 * @return array Fiecare rand are 'url' si 'alt'.
 */
function ht_about_factory_images()
{
    $rows = ht_about_field('ht_about_factory_images');
    $images = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $image = isset($row['image']) ? $row['image'] : $row;

            if (is_array($image) && !empty($image['url'])) {
                $images[] = array(
                    'url' => (string)$image['url'],
                    'alt' => isset($image['alt']) ? (string)$image['alt'] : '',
                );
            }
        }
    }

    return apply_filters('ht_about_factory_images', $images);
}

/**
 * Daca o sectiune are ceva de aratat: titlu, text sau fotografie.
 *
 * @param string $title Titlul.
 * @param string $text  Textul.
 * @param string $image Numele campului cu fotografia.
 *
 * @return bool
 */
function ht_about_has($title, $text, $image)
{
    return '' !== trim((string)$title) || '' !== trim((string)$text) || null !== ht_about_image($image);
}

/* ---------------------------------------------------------------------------
 * Randarea
 * ------------------------------------------------------------------------ */

/**
 * Firimiturile de deasupra titlului.
 *
 * @param string $current Numele paginii curente.
 */
function ht_about_crumbs($current)
{
    ?>
    <nav class="ht-about__crumbs" aria-label="<?php esc_attr_e('Firimituri', 'herbal-therapy'); ?>">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Acasă', 'herbal-therapy'); ?></a>
        <span class="ht-about__crumb-sep" aria-hidden="true">/</span>
        <span class="ht-about__crumb-current" aria-current="page"><?php echo esc_html($current); ?></span>
    </nav>
    <?php
}

/**
 * Capul paginii: titlul peste fotografia cu iarba si gama de produse.
 *
 * @param string $title Titlul afisat.
 */
function ht_about_hero($title)
{
    $intro = ht_about_text('ht_about_hero_intro');
    ?>
    <section class="ht-about__hero">
        <div class="ht-about__hero-stage">
            <div class="ht-about__hero-head">
                <h1 class="ht-about__hero-title"><?php echo esc_html($title); ?></h1>

                <?php if ('' !== trim($intro)) : ?>
                    <div class="ht-about__hero-intro"><?php echo ht_about_paragraphs($intro); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_about_paragraphs(). ?></div>
                <?php endif; ?>
            </div>

            <?php ht_about_the_image('ht_about_hero_image', 'ht-about__hero-bg', 'eager'); ?>
            <?php ht_about_the_image('ht_about_hero_products', 'ht-about__hero-products', 'eager'); ?>
        </div>
    </section>
    <?php
}

/**
 * "Cu grija pentru corpul dvs si mediu": fotografie in stanga, text in dreapta.
 */
function ht_about_care()
{
    $title = ht_about_text('ht_about_care_title');
    $text = ht_about_text('ht_about_care_text');

    if (!ht_about_has($title, $text, 'ht_about_care_image')) {
        return;
    }
    ?>
    <section class="ht-about__section ht-about__care">
        <?php ht_about_leaves(); ?>

        <div class="ht-wrapper ht-about__care-inner">
            <div class="ht-about__care-media">
                <?php ht_about_the_image('ht_about_care_image'); ?>
            </div>

            <div class="ht-about__care-text">
                <?php if ('' !== $title) : ?><h2 class="ht-about__title"><?php echo esc_html($title); ?></h2><?php endif; ?>
                <div class="ht-about__body"><?php echo ht_about_paragraphs($text); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_about_paragraphs(). ?></div>
            </div>
        </div>
    </section>
    <?php
}

/**
 * "Despre cosmeticile noastre": banda bej pe toata latimea, cu fotografia in
 * dreapta.
 */
function ht_about_cosmetics()
{
    $title = ht_about_text('ht_about_cosmetics_title');
    $text = ht_about_text('ht_about_cosmetics_text');

    if (!ht_about_has($title, $text, 'ht_about_cosmetics_image')) {
        return;
    }
    ?>
    <section class="ht-about__section ht-about__cosmetics">
        <span class="ht-about__cosmetics-panel" aria-hidden="true"></span>

        <div class="ht-wrapper ht-about__cosmetics-inner">
            <div class="ht-about__cosmetics-text">
                <?php if ('' !== $title) : ?><h2 class="ht-about__title"><?php echo esc_html($title); ?></h2><?php endif; ?>
                <div class="ht-about__body"><?php echo ht_about_paragraphs($text); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_about_paragraphs(). ?></div>
            </div>

            <div class="ht-about__cosmetics-media">
                <?php ht_about_the_image('ht_about_cosmetics_image'); ?>
            </div>
        </div>
    </section>
    <?php
}

/**
 * "Misiunea noastra": eprubetele in stanga, cartonasul verde in dreapta.
 */
function ht_about_mission()
{
    $title = ht_about_text('ht_about_mission_title');
    $text = ht_about_text('ht_about_mission_text');

    if (!ht_about_has($title, $text, 'ht_about_mission_image')) {
        return;
    }
    ?>
    <section class="ht-about__section ht-about__mission">
        <div class="ht-wrapper">
            <div class="ht-about__mission-box">
                <div class="ht-about__mission-media">
                    <?php /* umbrele de sub eprubete, desenate sub fotografie */ ?>
                    <span class="ht-about__mission-shadow" aria-hidden="true"></span>
                    <span class="ht-about__mission-shadow" aria-hidden="true"></span>
                    <span class="ht-about__mission-shadow" aria-hidden="true"></span>
                    <span class="ht-about__mission-shadow" aria-hidden="true"></span>

                    <?php ht_about_the_image('ht_about_mission_image', 'ht-about__mission-img'); ?>
                </div>

                <div class="ht-about__mission-panel">
                    <?php if ('' !== $title) : ?><h2 class="ht-about__title"><?php echo esc_html($title); ?></h2><?php endif; ?>
                    <div class="ht-about__body ht-about__body--green"><?php echo ht_about_paragraphs($text); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_about_paragraphs(). ?></div>
                </div>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Cele trei cartonase: siguranta, eficacitate, calitate.
 */
function ht_about_values_grid()
{
    $values = ht_about_values();

    if (!$values) {
        return;
    }
    ?>
    <section class="ht-about__section ht-about__values">
        <div class="ht-wrapper ht-about__values-grid">
            <?php foreach ($values as $i => $value) : ?>
                <article class="ht-about__value ht-about__value--<?php echo (int)($i % 3) + 1; ?>">
                    <?php if ('' !== $value['image']['url']) : ?>
                        <img
                            class="ht-about__value-icon"
                            src="<?php echo esc_url($value['image']['url']); ?>"
                            alt="<?php echo esc_attr($value['image']['alt']); ?>"
                            loading="lazy"
                            decoding="async"
                        />
                    <?php endif; ?>

                    <?php if ('' !== $value['title']) : ?>
                        <h3 class="ht-about__value-title"><?php echo esc_html($value['title']); ?></h3>
                    <?php endif; ?>

                    <div class="ht-about__body"><?php echo ht_about_paragraphs($value['text']); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_about_paragraphs(). ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

/**
 * "Viziunea brandului nostru": doua blocuri de text in stanga, fotografia mare
 * in dreapta.
 */
function ht_about_vision()
{
    $title = ht_about_text('ht_about_vision_title');
    $lead = ht_about_text('ht_about_vision_lead');
    $note = ht_about_text('ht_about_vision_note');

    if (!ht_about_has($title, $lead . $note, 'ht_about_vision_image')) {
        return;
    }
    ?>
    <section class="ht-about__section ht-about__vision">
        <div class="ht-wrapper ht-about__vision-grid">
            <div class="ht-about__vision-text">
                <?php if ('' !== $title) : ?><h2 class="ht-about__title"><?php echo esc_html($title); ?></h2><?php endif; ?>
                <div class="ht-about__body"><?php echo ht_about_paragraphs($lead); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_about_paragraphs(). ?></div>

                <?php if ('' !== $note || ht_about_image('ht_about_vision_thumb')) : ?>
                    <div class="ht-about__vision-note">
                        <?php if (ht_about_image('ht_about_vision_thumb')) : ?>
                            <div class="ht-about__vision-thumb">
                                <?php ht_about_the_image('ht_about_vision_thumb'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="ht-about__body ht-about__vision-note-text"><?php echo ht_about_paragraphs($note); // phpcs:ignore WordPress.Security.EscapeOutput -- curatat in ht_about_paragraphs(). ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ht-about__vision-media">
                <?php ht_about_the_image('ht_about_vision_image'); ?>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Banda cu cele patru fotografii din fabrica si depozit.
 */
function ht_about_factory()
{
    $title = ht_about_text('ht_about_factory_title');
    $images = ht_about_factory_images();

    if (!$images) {
        return;
    }
    ?>
    <section class="ht-about__section ht-about__factory">
        <div class="ht-wrapper">
            <?php if ('' !== $title) : ?><h2 class="ht-about__title ht-about__title--center"><?php echo esc_html($title); ?></h2><?php endif; ?>

            <div class="ht-about__factory-grid">
                <?php foreach ($images as $image) : ?>
                    <div class="ht-about__factory-item">
                        <img
                            src="<?php echo esc_url($image['url']); ?>"
                            alt="<?php echo esc_attr($image['alt']); ?>"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Frunzele care plutesc in jurul sectiunii "Cu grija pentru corpul dvs".
 *
 * Sunt pur decorative: stau in afara fluxului, ancorate de sectiune, si nu se
 * afiseaza pe ecrane mici, unde nu mai au loc.
 */
function ht_about_leaves()
{
    ?>
    <div class="ht-about__leaves" aria-hidden="true">
        <?php for ($i = 1; $i <= 5; $i++) : ?>
            <img
                class="ht-about__leaf ht-about__leaf--<?php echo (int)$i; ?>"
                src="<?php echo esc_url(ht_asset_uri('/assets/img/about/leaf-' . $i . '.webp')); ?>"
                alt=""
                loading="lazy"
                decoding="async"
            />
        <?php endfor; ?>
    </div>
    <?php
}

/**
 * Pagina intreaga.
 *
 * @param array $args 'title' - titlul afisat in firimituri si in cap.
 */
function ht_about_page($args = array())
{
    $args = wp_parse_args($args, array(
        'title' => __('Despre noi', 'herbal-therapy'),
    ));

    $heading = ht_about_text('ht_about_hero_title');
    $heading = ('' !== $heading) ? $heading : (string)$args['title'];
    ?>
    <div class="ht-about">
        <div class="ht-wrapper">
            <?php ht_about_crumbs($args['title']); ?>
        </div>

        <?php
        ht_about_hero($heading);
        ht_about_care();
        ht_about_cosmetics();
        ht_about_mission();
        ht_about_values_grid();
        ht_about_vision();
        ht_about_factory();
        ?>
    </div>
    <?php
}
