<?php
/**
 * Stiva de butoane plutitoare din coltul din dreapta-jos.
 *
 * Un singur container fix, in care modulele isi pun butoanele prin filtrul
 * 'ht_floating_buttons' (inc/call.php - apelul telefonic, inc/whatsapp.php -
 * conversatia pe WhatsApp). Asa butoanele se aseaza unul peste altul cu o
 * singura regula de pozitionare, iar subsolul rezerva loc sub ele pe telefon
 * indiferent cate sunt.
 *
 * Fiecare buton e un <a> cu clasa "ht-fab" si o clasa de varianta
 * ("ht-fab--call", "ht-fab--whatsapp"); stilul e in assets/css/floating.css
 * si se incarca doar cand exista cel putin un buton.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Randul cu telefon din cartonasul paginii de contact.
 *
 * Sursa de rezerva pentru ambele butoane, ca numarul sa se schimbe dintr-un
 * singur loc cand campurile din Setari generale sunt goale.
 *
 * @return array{href: string, text: string} Legatura (tel:...) si textul
 *                                            afisat; ambele goale cand nu
 *                                            exista niciun rand cu telefon.
 */
function ht_floating_contact_phone()
{
    $phone = array('href' => '', 'text' => '');

    if (!function_exists('ht_contact_methods')) {
        return $phone;
    }

    foreach (ht_contact_methods() as $method) {
        if ('phone' !== $method['icon']) {
            continue;
        }

        $phone['href'] = (string)$method['href'];
        $phone['text'] = (string)$method['lines'][0];

        break;
    }

    return $phone;
}

/**
 * Markup-ul unui buton din stiva.
 *
 * @param string $variant Sufixul clasei de varianta ('call', 'whatsapp').
 * @param string $href    Adresa, deja construita; se escapeaza aici.
 * @param string $icon    Numele iconitei din ht_icons().
 * @param string $label   Textul ascuns vizual, citit de cititoarele de ecran.
 * @param string $aria    Eticheta citita de cititoarele de ecran.
 * @param array  $attrs   Atribute suplimentare (ex. target, rel), fara escapare.
 *
 * @return string
 */
function ht_floating_button($variant, $href, $icon, $label, $aria, array $attrs = array())
{
    $extra = '';

    foreach ($attrs as $name => $value) {
        $extra .= sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
    }

    return sprintf(
        '<a class="ht-fab ht-fab--%1$s" href="%2$s" aria-label="%3$s"%4$s>%5$s<span class="ht-fab__label">%6$s</span></a>',
        esc_attr($variant),
        esc_url($href),
        esc_attr($aria),
        $extra,
        function_exists('ht_get_icon') ? ht_get_icon($icon, 'ht-fab__icon') : '',
        esc_html($label)
    );
}

/**
 * Butoanele de afisat, de sus in jos, ca markup gata escapat.
 *
 * Modulele le adauga prin filtrul 'ht_floating_buttons', indexate dupa nume
 * ('call', 'whatsapp'), ca sa poata fi scoase sau reordonate dintr-un singur
 * filtru. Intrarile goale se ignora.
 *
 * @return array<string, string>
 */
function ht_floating_buttons()
{
    if (is_admin()) {
        return array();
    }

    $buttons = (array)apply_filters('ht_floating_buttons', array());

    return array_filter(array_map('strval', $buttons), 'strlen');
}

/**
 * Pune stiva in pagina, inaintea scripturilor din subsol.
 */
function ht_floating_render()
{
    $buttons = ht_floating_buttons();

    if (!$buttons) {
        return;
    }

    echo '<div class="ht-floating">' . implode('', $buttons) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup escapat de ht_floating_button().
}

add_action('wp_footer', 'ht_floating_render', 7);

/**
 * Stilul stivei; se incarca doar cand exista cel putin un buton.
 */
function ht_floating_assets()
{
    if (!ht_floating_buttons()) {
        return;
    }

    wp_enqueue_style(
        'ht-floating',
        ht_asset_uri('/assets/css/floating.css'),
        array('ht-style'),
        ht_asset_version('/assets/css/floating.css')
    );
}

add_action('wp_enqueue_scripts', 'ht_floating_assets', 20);
