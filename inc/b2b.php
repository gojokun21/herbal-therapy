<?php
/**
 * Pagina B2B.
 *
 * Se randeaza cu ht_b2b_page() dintr-un sablon de pagina. Masuratorile vin din
 * Figma (frame-ul "B2B", 1920x2101), luate pe un container de 1440px: coloana
 * din stanga - text si formular - 768px, spatiu 80px, fotografia din dreapta
 * 590x472. Latimile sunt scrise ca procent din container, ca sa tina aceleasi
 * proportii si pe wrapper-ul temei (1520px).
 *
 * Tot ce se vede - titlul mare, textul de sub el, fotografia, titlul
 * formularului si formularul insusi - se scrie din pagina, in grupul ACF
 * "Pagina B2B" (acf-json/group_ht_b2b.json). Nu exista date de rezerva in cod:
 * ce nu e completat acolo nu se afiseaza.
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
 * Sablonul de pagina care aduce sectiunea.
 */
const HT_B2B_TEMPLATE = 'templates/b2b.php';

/**
 * Pagina care foloseste sablonul B2B.
 *
 * O cautam ca sa putem citi campurile si din afara paginii - de exemplu cand
 * formularul se trimite prin admin-post.php si nu exista pagina interogata.
 *
 * @return int ID-ul paginii sau 0 cand niciuna nu foloseste sablonul.
 */
function ht_b2b_page_id()
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
                'value' => HT_B2B_TEMPLATE,
            ),
        ),
    ));

    $id = $pages ? (int)$pages[0] : 0;

    return (int)apply_filters('ht_b2b_page_id', $id);
}

/**
 * Citeste un camp ACF de pe pagina B2B.
 *
 * Pe pagina B2B citeste chiar pagina afisata; in rest cade pe pagina gasita de
 * ht_b2b_page_id().
 *
 * @param string $name Numele campului.
 *
 * @return mixed Null cand ACF lipseste sau campul nu are valoare.
 */
function ht_b2b_field_value($name)
{
    if (!function_exists('get_field')) {
        return null;
    }

    $id = (int)get_queried_object_id();

    if (!$id || HT_B2B_TEMPLATE !== get_page_template_slug($id)) {
        $id = ht_b2b_page_id();
    }

    if (!$id) {
        return null;
    }

    $value = get_field($name, $id);

    return ('' === $value || null === $value || array() === $value) ? null : $value;
}

/**
 * Titlul mare din capul coloanei din stanga.
 *
 * In design scrie "Vanzari B2B", pe cand titlul paginii - cel din firimituri si
 * din meniu - e doar "B2B", deci titlul afisat se scrie separat. Cat timp e
 * gol, se foloseste titlul paginii.
 *
 * @param string $fallback Titlul paginii.
 *
 * @return string
 */
function ht_b2b_title($fallback = '')
{
    $title = (string)ht_b2b_field_value('ht_b2b_title');

    if ('' === trim($title)) {
        $title = (string)$fallback;
    }

    return (string)apply_filters('ht_b2b_title', $title, $fallback);
}

/**
 * Textul de sub titlu, gata formatat.
 *
 * @return string HTML sau sir gol.
 */
function ht_b2b_intro()
{
    return (string)apply_filters('ht_b2b_intro', (string)ht_b2b_field_value('ht_b2b_intro'));
}

/**
 * Fotografia din dreapta.
 *
 * @return array 'id', 'url' si 'alt'. Cu 'url' gol, fotografia nu se afiseaza.
 */
function ht_b2b_image()
{
    $field = ht_b2b_field_value('ht_b2b_image');

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

    return apply_filters('ht_b2b_image', $image);
}

/**
 * Titlul de deasupra formularului.
 *
 * @return string
 */
function ht_b2b_form_title()
{
    return (string)apply_filters('ht_b2b_form_title', (string)ht_b2b_field_value('ht_b2b_form_title'));
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
function ht_b2b_form_id()
{
    $id = (int)ht_b2b_field_value('ht_b2b_form');

    if ($id && 'wpcf7_contact_form' !== get_post_type($id)) {
        $id = 0;
    }

    return (int)apply_filters('ht_b2b_form_id', $id);
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
function ht_b2b_cf7_autop($autop, $options = array())
{
    if (isset($options['for']) && 'form' !== $options['for']) {
        return $autop;
    }

    if (!function_exists('wpcf7_get_current_contact_form')) {
        return $autop;
    }

    $form = wpcf7_get_current_contact_form();

    if ($form && (int)$form->id() === ht_b2b_form_id()) {
        return false;
    }

    return $autop;
}

add_filter('wpcf7_autop_or_not', 'ht_b2b_cf7_autop', 10, 2);

/* ---------------------------------------------------------------------------
 * Campurile formularului propriu (rezerva, cand CF7 nu e la indemana)
 * ------------------------------------------------------------------------ */

/**
 * Campurile, in ordinea si pe latimile din design.
 *
 * 'width' spune cat ocupa campul pe rand: 'full' - tot randul; 'half' - jumate,
 * iar doua campuri 'half' consecutive stau alaturi.
 *
 * La liste, 'placeholder' e textul cenusiu de dinaintea alegerii, iar 'options'
 * sunt optiunile propriu-zise. Cand campul e obligatoriu, textul cenusiu nu
 * poate fi trimis.
 *
 * @return array
 */
function ht_b2b_fields()
{
    return apply_filters('ht_b2b_fields', array(
        'company' => array(
            'label'        => __('Nume companie', 'herbal-therapy'),
            'placeholder'  => __('Introduceți denumirea companiei', 'herbal-therapy'),
            'type'         => 'text',
            'width'        => 'full',
            'required'     => true,
            'autocomplete' => 'organization',
        ),
        'domain' => array(
            'label'       => __('Domeniul de activitate', 'herbal-therapy'),
            'placeholder' => __('Alege domeniul de activitate', 'herbal-therapy'),
            'type'        => 'select',
            'width'       => 'full',
            'required'    => true,
            'options'     => array(
                __('Farmacie', 'herbal-therapy'),
                __('Magazin naturist', 'herbal-therapy'),
                __('Magazin online', 'herbal-therapy'),
                __('Distribuitor', 'herbal-therapy'),
                __('Clinică sau cabinet medical', 'herbal-therapy'),
                __('Salon sau centru SPA', 'herbal-therapy'),
                __('Altele', 'herbal-therapy'),
            ),
        ),
        'name' => array(
            'label'        => __('Nume și prenume', 'herbal-therapy'),
            'placeholder'  => __('Persoana de contact', 'herbal-therapy'),
            'type'         => 'text',
            'width'        => 'half',
            'required'     => true,
            'autocomplete' => 'name',
        ),
        'role' => array(
            'label'       => __('Funcția în companie', 'herbal-therapy'),
            'placeholder' => __('Alege funcția în companie', 'herbal-therapy'),
            'type'        => 'select',
            'width'       => 'half',
            'required'    => true,
            'options'     => array(
                __('Administrator', 'herbal-therapy'),
                __('Director', 'herbal-therapy'),
                __('Manager achiziții', 'herbal-therapy'),
                __('Farmacist', 'herbal-therapy'),
                __('Reprezentant vânzări', 'herbal-therapy'),
                __('Altele', 'herbal-therapy'),
            ),
        ),
        'phone' => array(
            'label'        => __('Telefon', 'herbal-therapy'),
            'placeholder'  => __('Introduceți număr de telefon', 'herbal-therapy'),
            'type'         => 'tel',
            'width'        => 'half',
            'required'     => true,
            'autocomplete' => 'tel',
        ),
        'email' => array(
            'label'        => __('Email', 'herbal-therapy'),
            'placeholder'  => __('Introduceți email', 'herbal-therapy'),
            'type'         => 'email',
            'width'        => 'half',
            'required'     => true,
            'autocomplete' => 'email',
        ),
        'partner' => array(
            'label'       => __('Tipul de partener', 'herbal-therapy'),
            'placeholder' => __('Alege tipul de partener', 'herbal-therapy'),
            'type'        => 'select',
            'width'       => 'full',
            'required'    => false,
            'options'     => array(
                __('Revânzare', 'herbal-therapy'),
                __('Distribuție', 'herbal-therapy'),
                __('Marcă proprie', 'herbal-therapy'),
                __('Colaborare pe termen lung', 'herbal-therapy'),
                __('Altele', 'herbal-therapy'),
            ),
        ),
        'message' => array(
            'label'       => __('Comentarii', 'herbal-therapy'),
            'placeholder' => __('Spune-ne mai multe despre compania ta sau despre tipul de colaborare dorit.', 'herbal-therapy'),
            'type'        => 'textarea',
            'width'       => 'full',
            'required'    => false,
        ),
    ));
}

/**
 * Imparte campurile pe randuri, dupa 'width'.
 *
 * @return array Fiecare rand e o lista de perechi [cheie, descriere].
 */
function ht_b2b_rows()
{
    $rows = array();
    $pair = array();

    foreach (ht_b2b_fields() as $key => $field) {
        $half = isset($field['width']) && 'half' === $field['width'];

        if (!$half) {
            if ($pair) {
                $rows[] = $pair;
                $pair = array();
            }

            $rows[] = array(array($key, $field));

            continue;
        }

        $pair[] = array($key, $field);

        if (2 === count($pair)) {
            $rows[] = $pair;
            $pair = array();
        }
    }

    if ($pair) {
        $rows[] = $pair;
    }

    return $rows;
}

/* ---------------------------------------------------------------------------
 * Trimiterea solicitarii
 * ------------------------------------------------------------------------ */

/**
 * Numele actiunii din admin-post.php.
 *
 * @return string
 */
function ht_b2b_action()
{
    return 'ht_b2b';
}

/**
 * Adresa pe care ajung solicitarile.
 *
 * @return string
 */
function ht_b2b_recipient()
{
    return (string)apply_filters('ht_b2b_recipient', get_option('admin_email'));
}

/**
 * Cheia sub care se tine raspunsul intre trimitere si redirectionare.
 *
 * @param string $token Jetonul din adresa.
 *
 * @return string
 */
function ht_b2b_result_key($token)
{
    return 'ht_b2b_' . $token;
}

/**
 * Raspunsul cererii precedente, cand exista.
 *
 * Se citeste o singura data: dupa afisare, transientul se sterge, ca mesajul sa
 * nu ramana lipit de adresa.
 *
 * @return array 'status' ('ok' sau 'error'), 'errors' si 'values'.
 */
function ht_b2b_result()
{
    static $result = null;

    if (null !== $result) {
        return $result;
    }

    $result = array('status' => '', 'errors' => array(), 'values' => array());

    $token = isset($_GET['ht-b2b']) ? sanitize_key(wp_unslash($_GET['ht-b2b'])) : '';

    if ('' === $token) {
        return $result;
    }

    $stored = get_transient(ht_b2b_result_key($token));

    if (is_array($stored)) {
        $result = wp_parse_args($stored, $result);
        delete_transient(ht_b2b_result_key($token));
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
function ht_b2b_value($key)
{
    $result = ht_b2b_result();

    return isset($result['values'][$key]) ? (string)$result['values'][$key] : '';
}

/**
 * Mesajul de eroare al unui camp.
 *
 * @param string $key Numele campului.
 *
 * @return string
 */
function ht_b2b_error($key)
{
    $result = ht_b2b_result();

    return isset($result['errors'][$key]) ? (string)$result['errors'][$key] : '';
}

/**
 * Preia formularul, verifica datele si trimite solicitarea.
 */
function ht_b2b_submit()
{
    $referer = wp_get_referer();
    $back = $referer ? $referer : home_url('/');

    /* jetonul leaga raspunsul de cererea curenta, nu de vizitator */
    $token = strtolower(wp_generate_password(12, false, false));

    $nonce = isset($_POST['ht_b2b_nonce']) ? sanitize_text_field(wp_unslash($_POST['ht_b2b_nonce'])) : '';

    if (!wp_verify_nonce($nonce, ht_b2b_action())) {
        ht_b2b_redirect($back, $token, array(
            'status' => 'error',
            'errors' => array('form' => __('Sesiunea a expirat. Încearcă din nou.', 'herbal-therapy')),
            'values' => array(),
        ));
    }

    /* campul-capcana e ascuns; daca e completat, cererea vine de la un robot */
    $trap = isset($_POST['ht_b2b_website']) ? trim((string)wp_unslash($_POST['ht_b2b_website'])) : '';

    if ('' !== $trap) {
        ht_b2b_redirect($back, $token, array(
            'status' => 'ok',
            'errors' => array(),
            'values' => array(),
        ));
    }

    $fields = ht_b2b_fields();
    $values = array();
    $errors = array();

    foreach ($fields as $key => $field) {
        $raw = isset($_POST['ht_b2b_' . $key]) ? wp_unslash($_POST['ht_b2b_' . $key]) : '';

        if ('email' === $field['type']) {
            $value = sanitize_email((string)$raw);
        } elseif ('textarea' === $field['type']) {
            $value = sanitize_textarea_field((string)$raw);
        } else {
            $value = sanitize_text_field((string)$raw);
        }

        /* la liste se accepta doar optiunile propuse */
        if ('select' === $field['type'] && !in_array($value, $field['options'], true)) {
            $value = '';
        }

        $values[$key] = $value;

        if (!empty($field['required']) && '' === $value) {
            $errors[$key] = __('Completează acest câmp.', 'herbal-therapy');

            continue;
        }

        if ('email' === $field['type'] && '' !== $value && !is_email($value)) {
            $errors[$key] = __('Emailul nu pare valid.', 'herbal-therapy');
        }
    }

    if ($errors) {
        ht_b2b_redirect($back, $token, array(
            'status' => 'error',
            'errors' => $errors,
            'values' => $values,
        ));
    }

    $subject = sprintf(
        /* translators: %s: numele site-ului. */
        __('[%s] Solicitare nouă de colaborare B2B', 'herbal-therapy'),
        wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
    );

    $lines = array();

    foreach ($fields as $key => $field) {
        $lines[] = $field['label'] . ': ' . ('' !== $values[$key] ? $values[$key] : '-');
    }

    $headers = array('Reply-To: ' . $values['name'] . ' <' . $values['email'] . '>');

    $sent = wp_mail(
        ht_b2b_recipient(),
        $subject,
        implode("\n", $lines),
        apply_filters('ht_b2b_mail_headers', $headers, $values)
    );

    do_action('ht_b2b_submitted', $values, $sent);

    if (!$sent) {
        ht_b2b_redirect($back, $token, array(
            'status' => 'error',
            'errors' => array('form' => __('Solicitarea nu a putut fi trimisă. Încearcă mai târziu sau scrie-ne pe email.', 'herbal-therapy')),
            'values' => $values,
        ));
    }

    ht_b2b_redirect($back, $token, array(
        'status' => 'ok',
        'errors' => array(),
        'values' => array(),
    ));
}

add_action('admin_post_nopriv_ht_b2b', 'ht_b2b_submit');
add_action('admin_post_ht_b2b', 'ht_b2b_submit');

/**
 * Pune raspunsul deoparte si intoarce vizitatorul in pagina.
 *
 * @param string $url    Adresa paginii B2B.
 * @param string $token  Jetonul care leaga raspunsul de redirectionare.
 * @param array  $result 'status', 'errors', 'values'.
 */
function ht_b2b_redirect($url, $token, $result)
{
    set_transient(ht_b2b_result_key($token), $result, 5 * MINUTE_IN_SECONDS);

    $url = remove_query_arg('ht-b2b', $url);
    $url = add_query_arg('ht-b2b', $token, $url);

    wp_safe_redirect($url . '#ht-b2b-form');
    exit;
}

/* ---------------------------------------------------------------------------
 * Randarea
 * ------------------------------------------------------------------------ */

/**
 * Firimiturile de deasupra continutului.
 *
 * @param string $current Numele paginii curente.
 */
function ht_b2b_crumbs($current)
{
    ?>
    <nav class="ht-b2b__crumbs" aria-label="<?php esc_attr_e('Firimituri', 'herbal-therapy'); ?>">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Acasă', 'herbal-therapy'); ?></a>
        <span class="ht-b2b__crumb-sep" aria-hidden="true">/</span>
        <span class="ht-b2b__crumb-current" aria-current="page"><?php echo esc_html($current); ?></span>
    </nav>
    <?php
}

/**
 * Un camp din formularul propriu.
 *
 * @param string $key   Numele campului, fara prefix.
 * @param array  $field Descrierea din ht_b2b_fields().
 */
function ht_b2b_field($key, $field)
{
    $id = 'ht-b2b-' . $key;
    $name = 'ht_b2b_' . $key;
    $error = ht_b2b_error($key);
    $value = ht_b2b_value($key);
    $describe = $error ? ' aria-describedby="' . esc_attr($id) . '-error"' : '';
    ?>
    <p class="ht-b2b__field<?php echo $error ? ' ht-b2b__field--error' : ''; ?>">
        <label class="ht-b2b__label" for="<?php echo esc_attr($id); ?>">
            <?php echo esc_html($field['label']); ?>
            <?php if (!empty($field['required'])) : ?>
                <span class="ht-b2b__required" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>

        <?php if ('textarea' === $field['type']) : ?>
            <textarea class="ht-b2b__input ht-b2b__input--area"
                      id="<?php echo esc_attr($id); ?>"
                      name="<?php echo esc_attr($name); ?>"
                      rows="3"
                      placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                      <?php echo !empty($field['required']) ? 'required' : ''; ?>
                      <?php echo $describe; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php
                echo esc_textarea($value);
            ?></textarea>

        <?php elseif ('select' === $field['type']) : ?>
            <?php /* clasa --empty tine textul cenusiu cat timp nu s-a ales nimic */ ?>
            <select class="ht-b2b__input ht-b2b__input--select<?php echo '' === $value ? ' ht-b2b__input--empty' : ''; ?>"
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr($name); ?>"
                    <?php echo !empty($field['required']) ? 'required' : ''; ?>
                    <?php echo $describe; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
                <option value=""<?php echo !empty($field['required']) ? ' disabled' : ''; ?><?php selected('', $value); ?>>
                    <?php echo esc_html($field['placeholder']); ?>
                </option>

                <?php foreach ($field['options'] as $option) : ?>
                    <option value="<?php echo esc_attr($option); ?>" <?php selected($option, $value); ?>>
                        <?php echo esc_html($option); ?>
                    </option>
                <?php endforeach; ?>
            </select>

        <?php else : ?>
            <input class="ht-b2b__input"
                   type="<?php echo esc_attr($field['type']); ?>"
                   id="<?php echo esc_attr($id); ?>"
                   name="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                   <?php echo !empty($field['autocomplete']) ? 'autocomplete="' . esc_attr($field['autocomplete']) . '"' : ''; ?>
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php echo $describe; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
        <?php endif; ?>

        <?php if ($error) : ?>
            <span class="ht-b2b__error" id="<?php echo esc_attr($id); ?>-error"><?php echo esc_html($error); ?></span>
        <?php endif; ?>
    </p>
    <?php
}

/**
 * Titlul si formularul.
 *
 * Formularul vine din Contact Form 7 cand exista unul legat de pagina; altfel
 * se afiseaza cel propriu al temei.
 */
function ht_b2b_form()
{
    $id = ht_b2b_form_id();
    $title = ht_b2b_form_title();
    ?>
    <div class="ht-b2b__form-col" id="ht-b2b-form">
        <?php if ('' !== trim($title)) : ?>
            <h2 class="ht-b2b__form-title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php
        if ($id) {
            echo do_shortcode(sprintf(
                '[contact-form-7 id="%d" html_class="ht-b2b__form ht-b2b__form--cf7"]',
                $id
            ));
        } else {
            ht_b2b_form_fallback();
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
function ht_b2b_form_fallback()
{
    $result = ht_b2b_result();
    ?>
    <?php if ('ok' === $result['status']) : ?>
        <p class="ht-b2b__notice ht-b2b__notice--ok" role="status">
            <?php esc_html_e('Mulțumim! Am primit solicitarea și revenim cu un răspuns cât de repede putem.', 'herbal-therapy'); ?>
        </p>
    <?php elseif (!empty($result['errors']['form'])) : ?>
        <p class="ht-b2b__notice ht-b2b__notice--error" role="alert">
            <?php echo esc_html($result['errors']['form']); ?>
        </p>
    <?php endif; ?>

    <form class="ht-b2b__form"
          method="post"
          action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
          novalidate>

        <input type="hidden" name="action" value="<?php echo esc_attr(ht_b2b_action()); ?>">
        <?php wp_nonce_field(ht_b2b_action(), 'ht_b2b_nonce'); ?>

        <?php /* capcana pentru roboti: ascunsa la afisare, dar completata automat de ei */ ?>
        <p class="ht-b2b__trap" aria-hidden="true">
            <label for="ht-b2b-website"><?php esc_html_e('Lasă câmpul gol', 'herbal-therapy'); ?></label>
            <input type="text" id="ht-b2b-website" name="ht_b2b_website" value="" tabindex="-1" autocomplete="off">
        </p>

        <?php foreach (ht_b2b_rows() as $row) : ?>
            <?php if (1 === count($row)) : ?>
                <?php ht_b2b_field($row[0][0], $row[0][1]); ?>
            <?php else : ?>
                <div class="ht-b2b__row">
                    <?php foreach ($row as $item) : ?>
                        <?php ht_b2b_field($item[0], $item[1]); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <span class="ht-b2b__submit">
            <button class="ht-b2b__button" type="submit">
                <?php esc_html_e('Trimite solicitarea', 'herbal-therapy'); ?>
            </button>
        </span>
    </form>
    <?php
}

/**
 * Pagina intreaga: firimituri, titlu, text, formular si fotografie.
 *
 * @param array $args 'title' - titlul paginii, folosit in firimituri; 'intro' -
 *                    textul de sub titlu, gata formatat. Cat timp lipseste, se
 *                    ia din campul ACF.
 */
function ht_b2b_page($args = array())
{
    $args = wp_parse_args($args, array(
        'title' => __('B2B', 'herbal-therapy'),
        'intro' => '',
    ));

    $heading = ht_b2b_title($args['title']);
    $intro = '' !== trim((string)$args['intro']) ? $args['intro'] : ht_b2b_intro();
    $image = ht_b2b_image();
    ?>
    <section class="ht-b2b">
        <div class="ht-wrapper">

            <?php ht_b2b_crumbs($args['title']); ?>

            <div class="ht-b2b__body">
                <div class="ht-b2b__main">
                    <header class="ht-b2b__head">
                        <h1 class="ht-b2b__title"><?php echo esc_html($heading); ?></h1>

                        <?php if ('' !== trim($intro)) : ?>
                            <div class="ht-b2b__intro"><?php echo wp_kses_post($intro); ?></div>
                        <?php endif; ?>
                    </header>

                    <?php ht_b2b_form(); ?>
                </div>

                <?php if ('' !== $image['url']) : ?>
                    <div class="ht-b2b__media">
                        <img src="<?php echo esc_url($image['url']); ?>"
                             alt="<?php echo esc_attr($image['alt']); ?>"
                             width="590" height="472" loading="lazy" decoding="async">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}
