<?php
/**
 * Pagina de contact.
 *
 * Se randeaza cu ht_contact_page() dintr-un sablon de pagina. Masuratorile vin
 * din Figma (frame-ul "Contact", 1920x1987), luate pe un container de 1440px:
 * cartonasul din stanga 704px, spatiu 56px, formularul umple restul. Latimile
 * sunt scrise ca procent din container, ca sa tina aceleasi proportii si pe
 * wrapper-ul temei (1520px).
 *
 * Toate datele afisate - textul de sub titlu, fotografia, modalitatile de
 * contact, blocul cu retelele sociale, titlurile si formularul - se scriu din
 * pagina, in grupul ACF "Pagina de contact" (acf-json/group_ht_contact.json).
 * Nu exista date de rezerva in cod: ce nu e completat acolo nu se afiseaza.
 *
 * Formularul vine din Contact Form 7. Cand nu e ales niciunul, pagina cade pe
 * formularul propriu al temei, care trimite prin admin-post.php.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Datele afisate
 * ------------------------------------------------------------------------ */

/**
 * Pagina care foloseste sablonul de contact.
 *
 * O cautam ca sa putem citi datele companiei si din afara paginii - subsolul le
 * afiseaza pe toate paginile.
 *
 * @return int ID-ul paginii sau 0 cand niciuna nu foloseste sablonul.
 */
function ht_contact_page_id()
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
                'value' => 'templates/contact.php',
            ),
        ),
    ));

    $id = $pages ? (int)$pages[0] : 0;

    return (int)apply_filters('ht_contact_page_id', $id);
}

/**
 * Citeste un camp ACF de pe pagina de contact.
 *
 * Pe pagina de contact citeste chiar pagina afisata; in rest - subsol, alte
 * sabloane - cade pe pagina gasita de ht_contact_page_id().
 *
 * @param string $name Numele campului.
 *
 * @return mixed Null cand ACF lipseste sau campul nu are valoare.
 */
function ht_contact_field_value($name)
{
    if (!function_exists('get_field')) {
        return null;
    }

    $id = (int)get_queried_object_id();

    if (!$id || 'templates/contact.php' !== get_page_template_slug($id)) {
        $id = ht_contact_page_id();
    }

    if (!$id) {
        return null;
    }

    $value = get_field($name, $id);

    return ('' === $value || null === $value || array() === $value) ? null : $value;
}

/**
 * Textul de sub titlu, gata formatat.
 *
 * @return string HTML sau sir gol.
 */
function ht_contact_intro()
{
    return (string)apply_filters('ht_contact_intro', (string)ht_contact_field_value('ht_contact_intro'));
}

/**
 * Titlul cartonasului din stanga.
 *
 * @return string
 */
function ht_contact_card_title()
{
    return (string)apply_filters('ht_contact_card_title', (string)ht_contact_field_value('ht_contact_card_title'));
}

/**
 * Titlul de deasupra formularului.
 *
 * @return string
 */
function ht_contact_form_title()
{
    return (string)apply_filters('ht_contact_form_title', (string)ht_contact_field_value('ht_contact_form_title'));
}

/**
 * Fotografia din capul cartonasului cu datele de contact.
 *
 * @return array 'id', 'url' si 'alt'. Cu 'url' gol, fotografia nu se afiseaza.
 */
function ht_contact_image()
{
    $field = ht_contact_field_value('ht_contact_image');

    $image = array(
        'id'  => 0,
        'url' => '',
        'alt' => '',
    );

    if (is_array($field) && !empty($field['url'])) {
        $image = array(
            'id'  => isset($field['ID']) ? (int)$field['ID'] : 0,
            'url' => (string)$field['url'],
            'alt' => isset($field['alt']) ? (string)$field['alt'] : '',
        );
    }

    return apply_filters('ht_contact_image', $image);
}

/**
 * Randurile unui camp textarea, fara cele goale.
 *
 * Se imparte cu modificatorul /u: fara el, \R lucreaza pe octeti si se
 * potriveste si pe 0x85, al doilea octet al literei chirilice "х" (D1 85).
 * "выходной" se rupea in "вы" + un octet invalid (pe care esc_html il goleste)
 * si "одной" - vezi pagina de contact in rusa, 2026-09-08.
 *
 * @param string $text Textul, un rand pe linie.
 *
 * @return array Randurile ramase, reindexate.
 */
function ht_contact_split_lines($text)
{
    $lines = preg_split('/\R/u', (string)$text);

    return array_values(array_filter(array_map('trim', (array)$lines), 'strlen'));
}

/**
 * Modalitatile alternative de contact, in ordinea din pagina.
 *
 * Fiecare linie are: 'icon' (din ht_icons()), 'label' - textul mic, verde -,
 * 'lines' - unul sau mai multe randuri de text - si, optional, 'href'.
 *
 * @return array
 */
function ht_contact_methods()
{
    $rows = ht_contact_field_value('ht_contact_methods');
    $methods = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $lines = ht_contact_split_lines(isset($row['lines']) ? $row['lines'] : '');

            if (!$lines) {
                continue;
            }

            $methods[] = array(
                'icon'  => !empty($row['icon']) ? (string)$row['icon'] : 'location',
                'label' => isset($row['label']) ? (string)$row['label'] : '',
                'lines' => $lines,
                'href'  => isset($row['href']) ? trim((string)$row['href']) : '',
            );
        }
    }

    return apply_filters('ht_contact_methods', $methods);
}

/**
 * Blocul de la baza cartonasului, cu retelele sociale.
 *
 * Textul poarta legaturi si vine pe paragrafe, deci pleaca gata construit; se
 * curata la afisare cu wp_kses_post().
 *
 * @return array 'label' si 'text'.
 */
function ht_contact_social()
{
    return apply_filters('ht_contact_social', array(
        'label' => (string)ht_contact_field_value('ht_contact_social_label'),
        'text'  => (string)ht_contact_field_value('ht_contact_social_text'),
    ));
}

/**
 * Conturile oficiale de retele sociale.
 *
 * Sunt datele companiei, nu ale paginii: le foloseste si subsolul, pe toate
 * paginile. Randurile fara adresa sunt sarite.
 *
 * @return array Fiecare rand are 'icon' (din ht_icons()), 'label' si 'url'.
 */
function ht_contact_socials()
{
    $labels = array(
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'telegram'  => 'Telegram',
        'tiktok'    => 'TikTok',
        'youtube'   => 'YouTube',
    );

    $rows = ht_contact_field_value('ht_contact_socials');
    $socials = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $url = isset($row['url']) ? trim((string)$row['url']) : '';
            $icon = !empty($row['icon']) ? (string)$row['icon'] : '';

            if ('' === $url || '' === $icon) {
                continue;
            }

            $socials[] = array(
                'icon'  => $icon,
                'label' => isset($labels[$icon]) ? $labels[$icon] : ucfirst($icon),
                'url'   => $url,
            );
        }
    }

    return apply_filters('ht_contact_socials', $socials);
}

/**
 * Adresa politicii de confidentialitate, pentru bifa din formular.
 *
 * @return string Sir gol cand pagina nu e setata in WordPress.
 */
function ht_contact_privacy_url()
{
    $url = (string)get_privacy_policy_url();

    return (string)apply_filters('ht_contact_privacy_url', $url);
}

/**
 * Legatura catre politica de confidentialitate, din textul bifei.
 *
 * Cat timp pagina nu e setata in WordPress, textul ramane pe loc, dar fara
 * legatura - asa bifa isi pastreaza intelesul.
 *
 * @return string HTML gata escapat.
 */
function ht_contact_privacy_link()
{
    $label = esc_html__('politicii de confidențialitate', 'herbal-therapy');
    $url = ht_contact_privacy_url();

    if ('' === $url) {
        return '<span class="ht-contact__consent-link">' . $label . '</span>';
    }

    return sprintf(
        '<a class="ht-contact__consent-link" href="%s">%s</a>',
        esc_url($url),
        $label
    );
}

/* ---------------------------------------------------------------------------
 * Formularul din Contact Form 7
 * ------------------------------------------------------------------------ */

/**
 * Formularul CF7 afisat in pagina.
 *
 * Se alege din pagina, in campul ACF. Cand nu e ales niciunul - sau pluginul e
 * oprit - pagina cade pe formularul propriu al temei.
 *
 * @return int ID-ul formularului sau 0.
 */
function ht_contact_form_id()
{
    $id = (int)ht_contact_field_value('ht_contact_form');

    if ($id && 'wpcf7_contact_form' !== get_post_type($id)) {
        $id = 0;
    }

    return (int)apply_filters('ht_contact_form_id', $id);
}

/**
 * Sablonul formularului e scris ca HTML, deci CF7 nu trebuie sa mai adauge
 * <p> si <br> peste el. Se opreste doar pentru formularul paginii.
 *
 * @param bool  $autop   Daca CF7 aplica wpcf7_autop().
 * @param array $options Contextul: 'form' sau 'mail'.
 *
 * @return bool
 */
function ht_contact_cf7_autop($autop, $options = array())
{
    if (isset($options['for']) && 'form' !== $options['for']) {
        return $autop;
    }

    if (!function_exists('wpcf7_get_current_contact_form')) {
        return $autop;
    }

    $form = wpcf7_get_current_contact_form();

    if ($form && (int)$form->id() === ht_contact_form_id()) {
        return false;
    }

    return $autop;
}

add_filter('wpcf7_autop_or_not', 'ht_contact_cf7_autop', 10, 2);

/**
 * Adresa politicii nu se poate scrie in sablonul CF7, care e text fix. In locul
 * ei se pune semnul %%politica%%, inlocuit aici cu legatura din WordPress.
 *
 * @param string $html Markup-ul formularului.
 *
 * @return string
 */
function ht_contact_cf7_tokens($html)
{
    if (false === strpos($html, '%%politica%%')) {
        return $html;
    }

    return str_replace('%%politica%%', ht_contact_privacy_link(), $html);
}

add_filter('wpcf7_form_elements', 'ht_contact_cf7_tokens');

/* ---------------------------------------------------------------------------
 * Campurile formularului propriu (rezerva, cand CF7 nu e la indemana)
 * ------------------------------------------------------------------------ */

/**
 * Campurile, in ordinea din design.
 *
 * @return array
 */
function ht_contact_fields()
{
    return apply_filters('ht_contact_fields', array(
        'name'    => array(
            'label'       => __('Nume și prenume', 'herbal-therapy'),
            'placeholder' => __('Numele tău', 'herbal-therapy'),
            'type'        => 'text',
            'required'    => true,
            'autocomplete' => 'name',
        ),
        'email'   => array(
            'label'       => __('Email', 'herbal-therapy'),
            'placeholder' => __('exemplu@email.com', 'herbal-therapy'),
            'type'        => 'email',
            'required'    => true,
            'autocomplete' => 'email',
        ),
        'phone'   => array(
            'label'       => __('Telefon', 'herbal-therapy'),
            'placeholder' => __('07xx xxx xxx', 'herbal-therapy'),
            'type'        => 'tel',
            'required'    => false,
            'autocomplete' => 'tel',
        ),
        'message' => array(
            'label'       => __('Mesaj', 'herbal-therapy'),
            'placeholder' => __('Scrie mesajul tău aici...', 'herbal-therapy'),
            'type'        => 'textarea',
            'required'    => true,
            'autocomplete' => '',
        ),
    ));
}

/* ---------------------------------------------------------------------------
 * Trimiterea mesajului
 * ------------------------------------------------------------------------ */

/**
 * Numele actiunii din admin-post.php.
 *
 * @return string
 */
function ht_contact_action()
{
    return 'ht_contact';
}

/**
 * Adresa pe care ajung mesajele.
 *
 * @return string
 */
function ht_contact_recipient()
{
    return (string)apply_filters('ht_contact_recipient', get_option('admin_email'));
}

/**
 * Cheia sub care se tine raspunsul intre trimitere si redirectionare.
 *
 * @param string $token Jetonul din adresa.
 *
 * @return string
 */
function ht_contact_result_key($token)
{
    return 'ht_contact_' . $token;
}

/**
 * Raspunsul cererii precedente, cand exista.
 *
 * Se citeste o singura data: dupa afisare, transientul se sterge, ca mesajul sa
 * nu ramana lipit de adresa.
 *
 * @return array 'status' ('ok' sau 'error'), 'errors' si 'values'.
 */
function ht_contact_result()
{
    static $result = null;

    if (null !== $result) {
        return $result;
    }

    $result = array('status' => '', 'errors' => array(), 'values' => array());

    $token = isset($_GET['ht-contact']) ? sanitize_key(wp_unslash($_GET['ht-contact'])) : '';

    if ('' === $token) {
        return $result;
    }

    $stored = get_transient(ht_contact_result_key($token));

    if (is_array($stored)) {
        $result = wp_parse_args($stored, $result);
        delete_transient(ht_contact_result_key($token));
    }

    return $result;
}

/**
 * Valoarea ramasa intr-un camp dupa o trimitere respinsa.
 *
 * @param string $key Numele campului.
 *
 * @return string
 */
function ht_contact_value($key)
{
    $result = ht_contact_result();

    return isset($result['values'][$key]) ? (string)$result['values'][$key] : '';
}

/**
 * Mesajul de eroare al unui camp.
 *
 * @param string $key Numele campului.
 *
 * @return string
 */
function ht_contact_error($key)
{
    $result = ht_contact_result();

    return isset($result['errors'][$key]) ? (string)$result['errors'][$key] : '';
}

/**
 * Preia formularul, verifica datele si trimite mesajul.
 */
function ht_contact_submit()
{
    $referer = wp_get_referer();
    $back = $referer ? $referer : home_url('/');

    /* jetonul leaga raspunsul de cererea curenta, nu de vizitator */
    $token = wp_generate_password(12, false, false);
    $token = strtolower($token);

    $nonce = isset($_POST['ht_contact_nonce']) ? sanitize_text_field(wp_unslash($_POST['ht_contact_nonce'])) : '';

    if (!wp_verify_nonce($nonce, ht_contact_action())) {
        ht_contact_redirect($back, $token, array(
            'status' => 'error',
            'errors' => array('form' => __('Sesiunea a expirat. Încearcă din nou.', 'herbal-therapy')),
            'values' => array(),
        ));
    }

    $values = array(
        'name'    => isset($_POST['ht_name']) ? sanitize_text_field(wp_unslash($_POST['ht_name'])) : '',
        'email'   => isset($_POST['ht_email']) ? sanitize_email(wp_unslash($_POST['ht_email'])) : '',
        'phone'   => isset($_POST['ht_phone']) ? sanitize_text_field(wp_unslash($_POST['ht_phone'])) : '',
        'message' => isset($_POST['ht_message']) ? sanitize_textarea_field(wp_unslash($_POST['ht_message'])) : '',
        'consent' => empty($_POST['ht_consent']) ? '' : '1',
    );

    /* campul-capcana e ascuns; daca e completat, cererea vine de la un robot */
    $trap = isset($_POST['ht_website']) ? trim((string)wp_unslash($_POST['ht_website'])) : '';

    if ('' !== $trap) {
        ht_contact_redirect($back, $token, array(
            'status' => 'ok',
            'errors' => array(),
            'values' => array(),
        ));
    }

    $errors = array();

    if ('' === $values['name']) {
        $errors['name'] = __('Spune-ne cum te cheamă.', 'herbal-therapy');
    }

    if ('' === $values['email']) {
        $errors['email'] = __('Avem nevoie de un email ca să îți răspundem.', 'herbal-therapy');
    } elseif (!is_email($values['email'])) {
        $errors['email'] = __('Emailul nu pare valid.', 'herbal-therapy');
    }

    if ('' === $values['message']) {
        $errors['message'] = __('Scrie-ne câteva rânduri.', 'herbal-therapy');
    }

    if ('' === $values['consent']) {
        $errors['consent'] = __('Bifează acordul pentru prelucrarea datelor.', 'herbal-therapy');
    }

    if ($errors) {
        ht_contact_redirect($back, $token, array(
            'status' => 'error',
            'errors' => $errors,
            'values' => $values,
        ));
    }

    $subject = sprintf(
        /* translators: %s: numele site-ului. */
        __('[%s] Mesaj nou din formularul de contact', 'herbal-therapy'),
        wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
    );

    $body = implode("\n", array(
        /* translators: %s: numele expeditorului. */
        sprintf(__('Nume: %s', 'herbal-therapy'), $values['name']),
        /* translators: %s: adresa de e-mail a expeditorului. */
        sprintf(__('Email: %s', 'herbal-therapy'), $values['email']),
        /* translators: %s: numarul de telefon al expeditorului. */
        sprintf(__('Telefon: %s', 'herbal-therapy'), '' !== $values['phone'] ? $values['phone'] : '-'),
        '',
        __('Mesaj:', 'herbal-therapy'),
        $values['message'],
    ));

    $headers = array('Reply-To: ' . $values['name'] . ' <' . $values['email'] . '>');

    $sent = wp_mail(
        ht_contact_recipient(),
        $subject,
        $body,
        apply_filters('ht_contact_mail_headers', $headers, $values)
    );

    do_action('ht_contact_submitted', $values, $sent);

    if (!$sent) {
        ht_contact_redirect($back, $token, array(
            'status' => 'error',
            'errors' => array('form' => __('Mesajul nu a putut fi trimis. Încearcă mai târziu sau scrie-ne pe email.', 'herbal-therapy')),
            'values' => $values,
        ));
    }

    ht_contact_redirect($back, $token, array(
        'status' => 'ok',
        'errors' => array(),
        'values' => array(),
    ));
}

add_action('admin_post_nopriv_ht_contact', 'ht_contact_submit');
add_action('admin_post_ht_contact', 'ht_contact_submit');

/**
 * Pune raspunsul deoparte si intoarce vizitatorul in pagina.
 *
 * @param string $url    Adresa paginii de contact.
 * @param string $token  Jetonul care leaga raspunsul de redirectionare.
 * @param array  $result 'status', 'errors', 'values'.
 */
function ht_contact_redirect($url, $token, $result)
{
    set_transient(ht_contact_result_key($token), $result, 5 * MINUTE_IN_SECONDS);

    $url = remove_query_arg('ht-contact', $url);
    $url = add_query_arg('ht-contact', $token, $url);

    wp_safe_redirect($url . '#ht-contact-form');
    exit;
}

/* ---------------------------------------------------------------------------
 * Randarea
 * ------------------------------------------------------------------------ */

/**
 * Firimiturile de deasupra titlului.
 *
 * @param string $current Numele paginii curente.
 */
function ht_contact_crumbs($current)
{
    ?>
    <nav class="ht-contact__crumbs" aria-label="<?php esc_attr_e('Firimituri', 'herbal-therapy'); ?>">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Acasă', 'herbal-therapy'); ?></a>
        <span class="ht-contact__crumb-sep" aria-hidden="true">/</span>
        <span class="ht-contact__crumb-current" aria-current="page"><?php echo esc_html($current); ?></span>
    </nav>
    <?php
}

/**
 * Cartonasul din stanga: fotografia si modalitatile alternative de contact.
 */
function ht_contact_card()
{
    $image = ht_contact_image();
    $methods = ht_contact_methods();
    $social = ht_contact_social();
    $title = ht_contact_card_title();
    $has_social = '' !== trim($social['label']) || '' !== trim($social['text']);

    /* fara nimic scris in ACF, cartonasul nu are ce arata */
    if ('' === $image['url'] && !$methods && !$has_social && '' === trim($title)) {
        return;
    }
    ?>
    <aside class="ht-contact__card">
        <?php if (!empty($image['url'])) : ?>
            <div class="ht-contact__photo">
                <img src="<?php echo esc_url($image['url']); ?>"
                     alt="<?php echo esc_attr($image['alt']); ?>"
                     width="704" height="260" loading="lazy" decoding="async">
            </div>
        <?php endif; ?>

        <div class="ht-contact__card-body">
            <?php if ('' !== trim($title)) : ?>
                <h2 class="ht-contact__card-title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>

            <div class="ht-contact__details">
                <?php if ($methods) : ?>
                <ul class="ht-contact__methods">
                    <?php
                    foreach ($methods as $method) :
                        /* liniile pe mai multe randuri stau lipite de marginea de sus a bulinei */
                        $stack = count($method['lines']) > 1;
                        ?>
                        <li class="ht-contact__method<?php echo $stack ? ' ht-contact__method--stack' : ''; ?>">
                            <span class="ht-contact__method-icon">
                                <?php ht_icon($method['icon'], 'ht-contact__method-glyph'); ?>
                            </span>

                            <span class="ht-contact__method-text">
                                <span class="ht-contact__method-label"><?php echo esc_html($method['label']); ?></span>

                                <?php if (!empty($method['href'])) : ?>
                                    <a class="ht-contact__method-value" href="<?php echo esc_url($method['href']); ?>">
                                        <?php echo esc_html(implode(' ', $method['lines'])); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="ht-contact__method-value">
                                        <?php foreach ($method['lines'] as $line) : ?>
                                            <span class="ht-contact__method-line"><?php echo esc_html($line); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ($has_social) : ?>
                    <div class="ht-contact__social">
                        <?php if ('' !== trim($social['label'])) : ?>
                            <p class="ht-contact__method-label"><?php echo esc_html($social['label']); ?></p>
                        <?php endif; ?>

                        <?php if ('' !== trim($social['text'])) : ?>
                            <div class="ht-contact__social-text"><?php echo wp_kses_post($social['text']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
    <?php
}

/**
 * Un camp din formular.
 *
 * @param string $key   Numele campului, fara prefix.
 * @param array  $field Descrierea din ht_contact_fields().
 */
function ht_contact_field($key, $field)
{
    $id = 'ht-contact-' . $key;
    $name = 'ht_' . $key;
    $error = ht_contact_error($key);
    $value = ht_contact_value($key);
    ?>
    <p class="ht-contact__field<?php echo $error ? ' ht-contact__field--error' : ''; ?>">
        <label class="ht-contact__label" for="<?php echo esc_attr($id); ?>">
            <?php echo esc_html($field['label']); ?>
            <?php if (!empty($field['required'])) : ?>
                <span class="ht-contact__required" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>

        <?php if ('textarea' === $field['type']) : ?>
            <textarea class="ht-contact__input ht-contact__input--area"
                      id="<?php echo esc_attr($id); ?>"
                      name="<?php echo esc_attr($name); ?>"
                      rows="4"
                      placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                      <?php echo !empty($field['required']) ? 'required' : ''; ?>
                      <?php echo $error ? 'aria-describedby="' . esc_attr($id) . '-error"' : ''; ?>><?php
                echo esc_textarea($value);
            ?></textarea>
        <?php else : ?>
            <input class="ht-contact__input"
                   type="<?php echo esc_attr($field['type']); ?>"
                   id="<?php echo esc_attr($id); ?>"
                   name="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                   <?php echo !empty($field['autocomplete']) ? 'autocomplete="' . esc_attr($field['autocomplete']) . '"' : ''; ?>
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php echo $error ? 'aria-describedby="' . esc_attr($id) . '-error"' : ''; ?>>
        <?php endif; ?>

        <?php if ($error) : ?>
            <span class="ht-contact__error" id="<?php echo esc_attr($id); ?>-error"><?php echo esc_html($error); ?></span>
        <?php endif; ?>
    </p>
    <?php
}

/**
 * Coloana din dreapta: titlul si formularul.
 *
 * Formularul vine din Contact Form 7 cand exista unul legat de pagina; altfel
 * se afiseaza cel propriu al temei.
 */
function ht_contact_form()
{
    $id = ht_contact_form_id();
    $title = ht_contact_form_title();
    ?>
    <div class="ht-contact__form-col" id="ht-contact-form">
        <?php if ('' !== trim($title)) : ?>
            <h2 class="ht-contact__form-title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php
        if ($id) {
            echo do_shortcode(sprintf(
                '[contact-form-7 id="%d" html_class="ht-contact__form ht-contact__form--cf7"]',
                $id
            ));
        } else {
            ht_contact_form_fallback();
        }
        ?>
    </div>
    <?php
}

/**
 * Formularul propriu al temei: acelasi desen, dar trimis prin admin-post.php.
 *
 * Ramane pentru cazul in care Contact Form 7 e oprit sau formularul paginii nu
 * e facut inca, ca pagina sa nu iasa fara formular.
 */
function ht_contact_form_fallback()
{
    $result = ht_contact_result();
    $consent_error = ht_contact_error('consent');

    $consent = sprintf(
        /* translators: %s: legatura catre politica de confidentialitate. */
        __('Sunt de acord cu prelucrarea datelor conform %s.', 'herbal-therapy'),
        ht_contact_privacy_link()
    );
    ?>
        <?php if ('ok' === $result['status']) : ?>
            <p class="ht-contact__notice ht-contact__notice--ok" role="status">
                <?php esc_html_e('Mulțumim! Mesajul a plecat spre noi și îți răspundem cât de repede putem.', 'herbal-therapy'); ?>
            </p>
        <?php elseif (!empty($result['errors']['form'])) : ?>
            <p class="ht-contact__notice ht-contact__notice--error" role="alert">
                <?php echo esc_html($result['errors']['form']); ?>
            </p>
        <?php endif; ?>

        <form class="ht-contact__form"
              method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              novalidate>

            <input type="hidden" name="action" value="<?php echo esc_attr(ht_contact_action()); ?>">
            <?php wp_nonce_field(ht_contact_action(), 'ht_contact_nonce'); ?>

            <?php /* capcana pentru roboti: ascunsa la afisare, dar completata automat de ei */ ?>
            <p class="ht-contact__trap" aria-hidden="true">
                <label for="ht-contact-website"><?php esc_html_e('Lasă câmpul gol', 'herbal-therapy'); ?></label>
                <input type="text" id="ht-contact-website" name="ht_website" value="" tabindex="-1" autocomplete="off">
            </p>

            <?php foreach (ht_contact_fields() as $key => $field) : ?>
                <?php ht_contact_field($key, $field); ?>
            <?php endforeach; ?>

            <div class="ht-contact__submit">
                <p class="ht-contact__consent<?php echo $consent_error ? ' ht-contact__consent--error' : ''; ?>">
                    <input class="ht-contact__checkbox"
                           type="checkbox"
                           id="ht-contact-consent"
                           name="ht_consent"
                           value="1"
                           <?php echo $consent_error ? 'aria-describedby="ht-contact-consent-error"' : ''; ?>>
                    <label class="ht-contact__consent-text" for="ht-contact-consent">
                        <?php echo wp_kses_post($consent); ?>
                    </label>

                    <?php if ($consent_error) : ?>
                        <span class="ht-contact__error" id="ht-contact-consent-error"><?php echo esc_html($consent_error); ?></span>
                    <?php endif; ?>
                </p>

                <span class="ht-contact__submit-btn">
                    <button class="ht-contact__button" type="submit">
                        <?php esc_html_e('Trimite mesajul', 'herbal-therapy'); ?>
                    </button>
                </span>
            </div>
        </form>
    <?php
}

/**
 * Pagina intreaga: firimituri, titlu, cartonas si formular.
 *
 * @param array $args 'title' - titlul afisat; 'intro' - textul de sub el, gata
 *                    formatat. Cat timp lipseste, se ia din campul ACF.
 */
function ht_contact_page($args = array())
{
    $args = wp_parse_args($args, array(
        'title' => __('Contact', 'herbal-therapy'),
        'intro' => '',
    ));

    $intro = '' !== trim((string)$args['intro']) ? $args['intro'] : ht_contact_intro();
    ?>
    <section class="ht-contact">
        <div class="ht-wrapper">

            <?php ht_contact_crumbs($args['title']); ?>

            <header class="ht-contact__head">
                <h1 class="ht-contact__title"><?php echo esc_html($args['title']); ?></h1>

                <?php if ('' !== trim((string)$intro)) : ?>
                    <div class="ht-contact__intro"><?php echo wp_kses_post($intro); ?></div>
                <?php endif; ?>
            </header>

            <div class="ht-contact__body">
                <?php ht_contact_card(); ?>
                <?php ht_contact_form(); ?>
            </div>

        </div>
    </section>
    <?php
}
