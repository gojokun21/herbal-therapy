<?php
/**
 * Comutatorul de limba din header.
 *
 * Datele vin de la Polylang; daca site-ul e mutat pe WPML, functia
 * icl_get_languages() acopera acelasi lucru. Fara plugin de traducere sau cu o
 * singura limba activa, comutatorul nu se randeaza deloc.
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
 * Limbile site-ului, normalizate.
 *
 * @return array Lista de array-uri cu cheile 'slug', 'locale', 'name', 'url',
 *               'flag' si 'current'.
 */
function ht_languages()
{
    static $languages = null;

    if (null !== $languages) {
        return $languages;
    }

    $languages = array();

    if (function_exists('pll_the_languages')) {
        $raw = pll_the_languages(array('raw' => 1, 'hide_if_empty' => 0));

        foreach ((array)$raw as $slug => $lang) {
            $languages[] = array(
                'slug'    => isset($lang['slug']) ? $lang['slug'] : $slug,
                'locale'  => isset($lang['locale']) ? $lang['locale'] : '',
                'name'    => isset($lang['name']) ? $lang['name'] : $slug,
                'url'     => isset($lang['url']) ? $lang['url'] : home_url('/'),
                'flag'    => isset($lang['flag']) ? $lang['flag'] : '',
                'current' => !empty($lang['current_lang']),
            );
        }
    } elseif (function_exists('icl_get_languages')) {
        $raw = icl_get_languages('skip_missing=0');

        foreach ((array)$raw as $lang) {
            $languages[] = array(
                'slug'    => isset($lang['language_code']) ? $lang['language_code'] : '',
                'locale'  => isset($lang['default_locale']) ? $lang['default_locale'] : '',
                'name'    => isset($lang['native_name']) ? $lang['native_name'] : '',
                'url'     => isset($lang['url']) ? $lang['url'] : home_url('/'),
                'flag'    => isset($lang['country_flag_url']) ? $lang['country_flag_url'] : '',
                'current' => !empty($lang['active']),
            );
        }
    }

    $languages = apply_filters('ht_languages', $languages);

    return $languages;
}

/**
 * Limba activa.
 *
 * @return array|null
 */
function ht_current_language()
{
    foreach (ht_languages() as $language) {
        if ($language['current']) {
            return $language;
        }
    }

    return null;
}

/**
 * Steagul unei limbi, ca <img>.
 *
 * Polylang livreaza steaguri de 16x11; in Figma stau la 18px latime, deci le
 * dam latimea din design si lasam inaltimea sa se calculeze.
 *
 * @param array $language Limba.
 *
 * @return string HTML gata escapat sau sir gol.
 */
function ht_language_flag($language)
{
    if ('' === $language['flag']) {
        return '';
    }

    return sprintf(
        '<img class="ht-lang__flag" src="%1$s" alt="" width="18" height="12" loading="lazy" decoding="async"/>',
        esc_url($language['flag'])
    );
}

/* ---------------------------------------------------------------------------
 * Markup
 * ------------------------------------------------------------------------ */

/**
 * Comutatorul din randul de meniu: limba activa, iar la clic restul limbilor.
 */
function ht_language_switcher()
{
    $languages = ht_languages();
    $current = ht_current_language();

    /* cu o singura limba nu are ce comuta */
    if (count($languages) < 2 || !$current) {
        return;
    }
    ?>
    <div class="ht-lang">
        <button class="ht-lang__toggle" type="button" data-ht-lang-toggle
                aria-expanded="false" aria-controls="htLangList">
            <span class="ht-visually-hidden"><?php esc_html_e('Limba site-ului:', 'herbal-therapy'); ?></span>
            <span class="ht-lang__name"><?php echo esc_html($current['name']); ?></span>
            <?php echo ht_language_flag($current); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern. ?>
        </button>

        <ul class="ht-lang__list" id="htLangList" data-ht-lang-list>
            <?php foreach ($languages as $language) : ?>
                <li>
                    <a class="ht-lang__link<?php echo $language['current'] ? ' is-current' : ''; ?>"
                       href="<?php echo esc_url($language['url']); ?>"
                       lang="<?php echo esc_attr($language['slug']); ?>"
                       hreflang="<?php echo esc_attr($language['slug']); ?>"
                        <?php echo $language['current'] ? ' aria-current="true"' : ''; ?>>
                        <span class="ht-lang__name"><?php echo esc_html($language['name']); ?></span>
                        <?php echo ht_language_flag($language); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern. ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Limbile din drawer-ul de pe mobil, unde randul de meniu e ascuns.
 */
function ht_language_drawer()
{
    $languages = ht_languages();

    if (count($languages) < 2) {
        return;
    }
    ?>
    <div class="ht-drawer__lang">
        <span class="ht-drawer__section-title"><?php esc_html_e('Limba', 'herbal-therapy'); ?></span>

        <ul class="ht-drawer__langs">
            <?php foreach ($languages as $language) : ?>
                <li>
                    <a class="ht-drawer__lang-btn<?php echo $language['current'] ? ' is-active' : ''; ?>"
                       href="<?php echo esc_url($language['url']); ?>"
                       lang="<?php echo esc_attr($language['slug']); ?>"
                       hreflang="<?php echo esc_attr($language['slug']); ?>"
                        <?php echo $language['current'] ? ' aria-current="true"' : ''; ?>>
                        <?php echo ht_language_flag($language); // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit intern. ?>
                        <span><?php echo esc_html($language['name']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}
