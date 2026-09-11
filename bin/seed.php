<?php
/**
 * Populeaza continutul real al site-ului.
 *
 * Scrie in baza de date datele care pana acum stateau doar in cod:
 *   - pagina "Despre noi" - toate campurile ACF, in romana si in rusa
 *     (bin/seed-about-content.php), cu fotografiile din tema urcate in
 *     biblioteca media; traducerea ruseasca se creeaza prin Polylang cand
 *     lipseste;
 *   - paginile "Contact" si "B2B" - campurile ACF, in romana si in rusa
 *     (bin/seed-pages-content.php), cu formularul CF7 al limbii legat;
 *   - prima pagina - sectiunile "Puterea naturii" si "Ce spun clientii", in
 *     romana si in rusa (bin/seed-home-content.php), cu iconitele din tema
 *     urcate in biblioteca media si recenziile legate de produsul din limba
 *     paginii;
 *   - meniul din subsol ('footer-menu'), construit din paginile si categoriile
 *     care exista cu adevarat.
 *
 * Rulare, din radacina temei:
 *   php bin/seed.php            - completeaza doar ce e gol
 *   php bin/seed.php --force    - suprascrie si ce e deja completat
 *   php bin/seed.php --dry-run  - arata ce ar scrie, fara sa scrie
 *
 * Cand PHP-ul din linia de comanda nu vede MySQL-ul (ex. Local pe Windows),
 * gazda se da prin mediu: HT_DB_HOST=127.0.0.1:10004 php bin/seed.php
 *
 * Scriptul e idempotent: rulat de doua ori nu dubleaza nimic. Fotografiile
 * urcate sunt insemnate cu meta '_ht_seed_source', asa ca la a doua rulare
 * sunt refolosite, nu urcate din nou.
 *
 * @package Herbal_Therapy
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/seed.php se ruleaza doar din linia de comanda.\n");
}

/* ---------------------------------------------------------------------------
 * Argumente
 * ------------------------------------------------------------------------ */

$ht_argv  = isset($argv) ? $argv : array();
$ht_force = in_array('--force', $ht_argv, true);
$ht_dry   = in_array('--dry-run', $ht_argv, true);

/* ---------------------------------------------------------------------------
 * WordPress
 * ------------------------------------------------------------------------ */

$ht_root = __DIR__;

while (!file_exists($ht_root . '/wp-load.php')) {
    $ht_parent = dirname($ht_root);

    if ($ht_parent === $ht_root) {
        exit('Nu am gasit wp-load.php pornind de la ' . __DIR__ . ".\n");
    }

    $ht_root = $ht_parent;
}

/* WordPress se asteapta la un context de cerere chiar si in linia de comanda */
$_SERVER['HTTP_HOST']      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_SERVER['SERVER_NAME']    = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/index.php';

/* Local expune MySQL pe TCP; din CLI se poate indica gazda: HT_DB_HOST=127.0.0.1:10004 */
if (!empty($_SERVER['HT_DB_HOST']) && !defined('DB_HOST')) {
    define('DB_HOST', (string)$_SERVER['HT_DB_HOST']);

    /* wp-config.php defineste DB_HOST din nou; avertismentul e asteptat */
    set_error_handler(function ($no, $str) {
        return false !== strpos($str, 'DB_HOST already defined');
    }, E_WARNING);
    require_once $ht_root . '/wp-load.php';
    restore_error_handler();
} else {
    require_once $ht_root . '/wp-load.php';
}
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

/* ---------------------------------------------------------------------------
 * Unelte
 * ------------------------------------------------------------------------ */

/**
 * Scrie un rand in consola.
 *
 * @param string $message Textul.
 * @param string $level   'ok', 'skip', 'warn' sau '' pentru randuri simple.
 */
function ht_seed_log($message, $level = '')
{
    $prefix = array(
        'ok'   => '  + ',
        'skip' => '  = ',
        'warn' => '  ! ',
    );

    echo (isset($prefix[$level]) ? $prefix[$level] : '') . $message . "\n";
}

/**
 * Pagina care foloseste un sablon anume.
 *
 * @param string $template Calea sablonului, ex. 'templates/about.php'.
 *
 * @return int ID-ul paginii sau 0.
 */
function ht_seed_page_by_template($template)
{
    $pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => array('publish', 'draft', 'private'),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'   => '_wp_page_template',
                'value' => $template,
            ),
        ),
    ));

    return $pages ? (int)$pages[0] : 0;
}

/**
 * Urca in biblioteca media o fotografie din tema.
 *
 * Fisierele deja urcate de script sunt recunoscute dupa meta '_ht_seed_source'
 * si refolosite, ca sa nu se adune copii la fiecare rulare.
 *
 * @param string $relative Calea in tema, ex. '/assets/img/about/care.webp'.
 * @param string $title    Titlul din biblioteca media.
 *
 * @return int ID-ul atasamentului sau 0 cand fisierul lipseste.
 */
function ht_seed_attachment($relative, $title = '')
{
    global $ht_dry;

    $existing = get_posts(array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'   => '_ht_seed_source',
                'value' => $relative,
            ),
        ),
    ));

    if ($existing) {
        return (int)$existing[0];
    }

    $source = get_template_directory() . $relative;

    if (!file_exists($source)) {
        ht_seed_log('lipseste fisierul ' . $relative, 'warn');

        return 0;
    }

    if ($ht_dry) {
        ht_seed_log('ar urca ' . basename($relative), 'ok');

        return 0;
    }

    $uploads = wp_upload_dir();

    if (!empty($uploads['error'])) {
        ht_seed_log('biblioteca media indisponibila: ' . $uploads['error'], 'warn');

        return 0;
    }

    $name = wp_unique_filename($uploads['path'], basename($relative));
    $dest = trailingslashit($uploads['path']) . $name;

    if (!copy($source, $dest)) {
        ht_seed_log('nu am putut copia ' . $relative, 'warn');

        return 0;
    }

    $type = wp_check_filetype($dest, null);

    $id = wp_insert_attachment(
        array(
            'post_mime_type' => $type['type'] ? $type['type'] : 'image/webp',
            'post_title'     => '' !== $title ? $title : pathinfo($name, PATHINFO_FILENAME),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ),
        $dest,
        0,
        true
    );

    if (is_wp_error($id)) {
        ht_seed_log('nu am putut inregistra ' . $name . ': ' . $id->get_error_message(), 'warn');

        return 0;
    }

    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $dest));
    update_post_meta($id, '_ht_seed_source', $relative);

    ht_seed_log('urcat ' . $name . ' (#' . $id . ')', 'ok');

    return (int)$id;
}

/**
 * Daca o valoare inseamna "camp gol".
 *
 * ACF raspunde diferit dupa tipul campului: sir gol la text, false la un
 * repeater fara randuri, 0 la o fotografie nealeasa. Toate inseamna acelasi
 * lucru, deci le tratam la fel.
 *
 * @param mixed $value Valoarea citita sau pregatita.
 *
 * @return bool
 */
function ht_seed_is_empty($value)
{
    return (null === $value || false === $value || '' === $value
        || array() === $value || 0 === $value || '0' === $value);
}

/**
 * Scrie un camp ACF, respectand --force si --dry-run.
 *
 * @param string $selector Numele sau cheia campului.
 * @param mixed  $value    Valoarea.
 * @param int    $post_id  Postarea.
 * @param string $label    Numele afisat in raport.
 *
 * @return bool Daca a scris.
 */
function ht_seed_field($selector, $value, $post_id, $label = '')
{
    global $ht_force, $ht_dry;

    $label = ('' !== $label) ? $label : $selector;

    if (ht_seed_is_empty($value)) {
        ht_seed_log($label . ' - nimic de scris', 'skip');

        return false;
    }

    $filled = !ht_seed_is_empty(get_field($selector, $post_id, false));

    if ($filled && !$ht_force) {
        ht_seed_log($label . ' - deja completat', 'skip');

        return false;
    }

    if ($ht_dry) {
        ht_seed_log($label . ($filled ? ' - ar suprascrie' : ' - ar scrie'), 'ok');

        return false;
    }

    update_field($selector, $value, $post_id);
    ht_seed_log($label . ($filled ? ' - suprascris' : ' - scris'), 'ok');

    return true;
}

/* ---------------------------------------------------------------------------
 * Verificari
 * ------------------------------------------------------------------------ */

echo "\nHerbal Therapy - populare continut\n";
echo str_repeat('-', 52) . "\n";

if ($ht_dry) {
    ht_seed_log('--dry-run: nu se scrie nimic in baza de date', 'warn');
}

if (!function_exists('update_field')) {
    exit("ACF nu e activ - campurile paginilor nu pot fi scrise.\n");
}

if (!function_exists('ht_about_page')) {
    exit("Tema Herbal Therapy nu e activa.\n");
}

/* ---------------------------------------------------------------------------
 * 1. Pagina "Despre noi", in fiecare limba
 *
 * Continutul, pe limbi, e in bin/seed-about-content.php. Pagina romaneasca e
 * cea care foloseste sablonul templates/about.php; traducerile se cauta prin
 * Polylang si, cand lipsesc, se creeaza si se leaga de ea. Fara Polylang se
 * scrie doar limba romana.
 * ------------------------------------------------------------------------ */

echo "\n[1] Pagina \"Despre noi\"\n";

$ht_about_content = require __DIR__ . '/seed-about-content.php';
$ht_polylang = function_exists('pll_get_post') && function_exists('pll_set_post_language');

/**
 * Traducerea unei pagini intr-o limba: cea gasita sau, la nevoie, una noua.
 *
 * @param string $lang     Codul limbii Polylang.
 * @param int    $source   Pagina de pornire (limba implicita).
 * @param array  $page     'title' si 'slug' pentru pagina noua.
 * @param string $template Sablonul paginii, ex. 'templates/about.php'.
 *
 * @return int ID-ul paginii sau 0.
 */
function ht_seed_translation($lang, $source, $page, $template)
{
    global $ht_dry, $ht_polylang;

    if (!$ht_polylang) {
        return 0;
    }

    $id = (int)pll_get_post($source, $lang);

    if ($id) {
        return $id;
    }

    if ($ht_dry) {
        ht_seed_log('ar crea pagina "' . $page['title'] . '" (' . $lang . ') ca traducere a #' . $source, 'ok');

        return 0;
    }

    $id = wp_insert_post(array(
        'post_type'    => 'page',
        'post_status'  => get_post_status($source) ? get_post_status($source) : 'publish',
        'post_title'   => $page['title'],
        'post_name'    => $page['slug'],
        'post_content' => '',
    ), true);

    if (is_wp_error($id)) {
        ht_seed_log('nu am putut crea pagina (' . $lang . '): ' . $id->get_error_message(), 'warn');

        return 0;
    }

    update_post_meta($id, '_wp_page_template', $template);
    pll_set_post_language($id, $lang);

    $translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($source) : array();
    $translations[pll_get_post_language($source)] = $source;
    $translations[$lang] = $id;
    pll_save_post_translations($translations);

    ht_seed_log('pagina "' . $page['title'] . '" (' . $lang . ') creata (#' . $id . ')', 'ok');

    return (int)$id;
}

/**
 * Pagina in limba implicita care foloseste un sablon, si limba ei.
 *
 * @param string $template Sablonul.
 * @param array  $langs    Limbile cunoscute (cheile din fisierul de continut).
 *
 * @return array [ID sau 0, codul limbii].
 */
function ht_seed_source_page($template, $langs)
{
    global $ht_polylang;

    $id = ht_seed_page_by_template($template);
    $lang = 'ro';

    if ($id && $ht_polylang) {
        $default = function_exists('pll_default_language') ? (string)pll_default_language() : 'ro';
        $in_default = (int)pll_get_post($id, $default);
        $id = $in_default ? $in_default : $id;
        $lang = (string)pll_get_post_language($id);
        $lang = in_array($lang, $langs, true) ? $lang : 'ro';
    }

    return array($id, $lang);
}

/**
 * Parcurge limbile unei pagini si apeleaza $fill pentru fiecare.
 *
 * @param string   $template Sablonul paginii.
 * @param array    $content  Continutul pe limbi.
 * @param callable $fill     function ($post_id, $lang_content, $source_id, $lang).
 */
function ht_seed_each_language($template, $content, $fill)
{
    global $ht_polylang;

    list($source_id, $source_lang) = ht_seed_source_page($template, array_keys($content));

    if (!$source_id) {
        ht_seed_log('nicio pagina nu foloseste sablonul ' . $template . ' - sar peste', 'warn');

        return;
    }

    foreach ($content as $lang => $lang_content) {
        echo "\n  [" . $lang . "]\n";

        if ($lang === $source_lang) {
            $page_id = $source_id;
        } elseif (!$ht_polylang) {
            ht_seed_log('Polylang nu e activ - limba "' . $lang . '" e sarita', 'warn');

            continue;
        } else {
            $page_id = ht_seed_translation($lang, $source_id, $lang_content['page'], $template);
        }

        if (!$page_id) {
            continue;
        }

        ht_seed_log('pagina #' . $page_id . ' - ' . get_the_title($page_id));
        call_user_func($fill, $page_id, $lang_content, $source_id, $lang);
    }
}

/**
 * Scrie in pagina toate campurile ACF ale unei limbi.
 *
 * @param int   $post_id Pagina.
 * @param array $content Intrarea limbii din seed-about-content.php.
 */
function ht_seed_about_fill($post_id, $content)
{
    /* textele */
    foreach ($content['texts'] as $name => $value) {
        ht_seed_field($name, $value, $post_id);
    }

    /* fotografiile simple - aceleasi in toate limbile */
    $images = array(
        'ht_about_hero_image'      => array('hero-bg.webp', 'Despre noi - fundal'),
        'ht_about_hero_products'   => array('hero-products.webp', 'Despre noi - gama de produse'),
        'ht_about_care_image'      => array('care.webp', 'Cu grija pentru corpul dvs si mediu'),
        'ht_about_cosmetics_image' => array('cosmetics.webp', 'Despre cosmeticile noastre'),
        'ht_about_mission_image'   => array('mission.webp', 'Misiunea noastra'),
        'ht_about_vision_image'    => array('vision.webp', 'Viziunea brandului'),
        'ht_about_vision_thumb'    => array('vision-thumb.webp', 'Viziunea brandului - detaliu'),
        'ht_about_location_image'  => array('location.webp', 'Unde ne gasesti'),
    );

    foreach ($images as $name => $image) {
        $id = ht_seed_attachment('/assets/img/about/' . $image[0], $image[1]);

        if ($id) {
            ht_seed_field($name, $id, $post_id);
        }
    }

    /* valorile brandului */
    $rows = array();

    foreach ($content['values'] as $value) {
        $rows[] = array(
            'field_ht_about_value_title' => $value['title'],
            'field_ht_about_value_text'  => $value['text'],
            'field_ht_about_value_image' => ht_seed_attachment(
                '/assets/img/about/' . $value['file'],
                'Valoare - ' . $value['title']
            ),
        );
    }

    ht_seed_field('field_ht_about_values', $rows, $post_id, 'ht_about_values');

    /* banda "Fabrica si Depozit" */
    $rows = array();

    foreach (array('factory-1.webp', 'factory-2.webp', 'factory-3.webp', 'factory-4.webp') as $index => $file) {
        $id = ht_seed_attachment('/assets/img/about/' . $file, 'Fabrica si depozit ' . ($index + 1));

        if ($id) {
            $rows[] = array('field_ht_about_factory_image' => $id);
        }
    }

    ht_seed_field('field_ht_about_factory_images', $rows, $post_id, 'ht_about_factory_images');

    /* punctele de lucru */
    $rows = array();

    foreach ($content['locations'] as $place) {
        $rows[] = array(
            'field_ht_about_location_label'   => $place['label'],
            'field_ht_about_location_address' => $place['address'],
            'field_ht_about_location_href'    => $place['href'],
            'field_ht_about_location_map'     => ('' !== $place['file'])
                ? ht_seed_attachment('/assets/img/about/' . $place['file'], 'Harta - ' . $place['address'])
                : 0,
        );
    }

    ht_seed_field('field_ht_about_locations', $rows, $post_id, 'ht_about_locations');
}

ht_seed_each_language('templates/about.php', $ht_about_content, function ($page_id, $content) {
    ht_seed_about_fill($page_id, $content);
});

/* ---------------------------------------------------------------------------
 * 2. Paginile "Contact" si "B2B", in fiecare limba
 *
 * Continutul e in bin/seed-pages-content.php. Fotografiile se copiaza din
 * pagina in limba implicita, iar formularul Contact Form 7 se cauta dupa
 * titlu.
 * ------------------------------------------------------------------------ */

echo "\n[2] Paginile \"Contact\" si \"B2B\"\n";

/**
 * Formularul Contact Form 7 cu unul dintre titlurile date.
 *
 * @param array $titles Titluri posibile, in ordinea preferintei.
 *
 * @return int ID-ul sau 0.
 */
function ht_seed_cf7_by_title($titles)
{
    foreach ((array)$titles as $title) {
        $found = get_posts(array(
            'post_type'      => 'wpcf7_contact_form',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'title'          => $title,
        ));

        if ($found) {
            return (int)$found[0];
        }
    }

    return 0;
}

/**
 * Scrie in pagina campurile unei limbi (texte, randuri, formular, fotografii).
 *
 * @param int    $post_id Pagina.
 * @param array  $content Intrarea limbii din seed-pages-content.php.
 * @param int    $source  Pagina in limba implicita, de unde se copiaza pozele.
 * @param string $prefix  Prefixul campurilor: 'ht_contact' sau 'ht_b2b'.
 */
function ht_seed_page_fill($post_id, $content, $source, $prefix)
{
    foreach ($content['texts'] as $name => $value) {
        ht_seed_field($name, $value, $post_id);
    }

    foreach ($content['rows'] as $key => $rows) {
        ht_seed_field($key, $rows, $post_id, str_replace('field_', '', $key));
    }

    $form = ht_seed_cf7_by_title($content['form']);

    if ($form) {
        ht_seed_field($prefix . '_form', $form, $post_id);
    } else {
        ht_seed_log($prefix . '_form - nu am gasit formularul "' . implode('" / "', (array)$content['form']) . '"', 'warn');
    }

    if ($post_id !== $source) {
        $image = (int)get_field($prefix . '_image', $source, false);

        if ($image) {
            ht_seed_field($prefix . '_image', $image, $post_id);
        }
    }
}

$ht_pages_content = require __DIR__ . '/seed-pages-content.php';

foreach ($ht_pages_content as $ht_template => $ht_languages) {
    echo "\n " . $ht_template . "\n";

    $ht_prefix = ('templates/contact.php' === $ht_template) ? 'ht_contact' : 'ht_b2b';

    ht_seed_each_language($ht_template, $ht_languages, function ($page_id, $content, $source_id) use ($ht_prefix) {
        ht_seed_page_fill($page_id, $content, $source_id, $ht_prefix);
    });
}

/* ---------------------------------------------------------------------------
 * 3. Prima pagina - sectiunile "Puterea naturii" si "Ce spun clientii"
 *
 * Continutul e in bin/seed-home-content.php. Pagina romaneasca e cea care
 * foloseste sablonul templates/home-template.php; iconitele se urca o singura
 * data si se refolosesc in toate limbile. Recenziile se leaga de produsul din
 * limba paginii, gasit dupa SKU si tradus prin Polylang.
 * ------------------------------------------------------------------------ */

echo "\n[3] Prima pagina - \"Puterea naturii\" si \"Ce spun clientii\"\n";

/**
 * Produsul cu SKU-ul dat, in limba ceruta.
 *
 * SKU-ul e acelasi pe produs si pe traducerea lui, deci cautarea poate
 * nimeri oricare dintre ele; Polylang il duce la limba paginii.
 *
 * @param string $sku  SKU-ul (EAN).
 * @param string $lang Codul limbii paginii.
 *
 * @return int ID-ul produsului, 0 cand nu exista.
 */
function ht_seed_product_by_sku($sku, $lang)
{
    $id = function_exists('wc_get_product_id_by_sku') ? (int)wc_get_product_id_by_sku($sku) : 0;

    if (!$id) {
        return 0;
    }

    if (function_exists('pll_get_post')) {
        $translated = (int)pll_get_post($id, $lang);

        if ($translated) {
            $id = $translated;
        }
    }

    return 'publish' === get_post_status($id) ? $id : 0;
}

/**
 * Scrie in prima pagina sectiunile "Puterea naturii" si "Ce spun clientii".
 *
 * @param int    $post_id Pagina.
 * @param array  $content Intrarea limbii din seed-home-content.php.
 * @param string $lang    Codul limbii paginii.
 */
function ht_seed_home_fill($post_id, $content, $lang)
{
    foreach ($content['texts'] as $name => $value) {
        ht_seed_field($name, $value, $post_id);
    }

    $rows = array();

    foreach ($content['benefits'] as $benefit) {
        $rows[] = array(
            'field_ht_home_benefit_iconita' => ht_seed_attachment(
                '/assets/img/home/benefits/' . $benefit['file'],
                'Beneficiu - ' . $benefit['title']
            ),
            'field_ht_home_benefit_titlu'   => $benefit['title'],
            'field_ht_home_benefit_text'    => $benefit['text'],
        );
    }

    ht_seed_field('field_ht_home_benefits', $rows, $post_id, 'ht_beneficii');

    if (empty($content['reviews'])) {
        return;
    }

    $rows = array();

    foreach ($content['reviews'] as $review) {
        $product_id = ht_seed_product_by_sku($review['sku'], $lang);

        if (!$product_id) {
            ht_seed_log('recenzia "' . $review['author'] . '": produsul cu SKU ' . $review['sku'] . ' lipseste - ramane fara produs', 'warn');
        }

        $rows[] = array(
            'field_ht_home_review_autor'  => $review['author'],
            'field_ht_home_review_nota'   => (int)$review['rating'],
            'field_ht_home_review_text'   => $review['text'],
            'field_ht_home_review_produs' => $product_id,
        );
    }

    ht_seed_field('field_ht_home_reviews', $rows, $post_id, 'ht_recenzii');
}

$ht_home_content = require __DIR__ . '/seed-home-content.php';

ht_seed_each_language('templates/home-template.php', $ht_home_content, function ($page_id, $content, $source_id, $lang) {
    ht_seed_home_fill($page_id, $content, $lang);
});

/* ---------------------------------------------------------------------------
 * 4. Meniul din subsol
 *
 * Fiecare legatura arata spre o pagina sau o categorie care exista in site;
 * ce lipseste e sarit, nu inventat.
 * ------------------------------------------------------------------------ */

echo "\n[4] Meniul din subsol\n";

$ht_footer_columns = array(
    'Clientului' => array(
        array('type' => 'page', 'slug' => 'blog'),
        array('type' => 'page', 'slug' => 'contact'),
        array('type' => 'page', 'slug' => 'contul-meu'),
        array('type' => 'page', 'slug' => 'favorite'),
    ),
    'Produse' => array(
        array('type' => 'page', 'slug' => 'produse', 'label' => 'Toate produsele'),
        array('type' => 'product_cat', 'slug' => 'vitamine-si-suplimente-nutritive'),
        array('type' => 'product_cat', 'slug' => 'cosmetica-medicala'),
        array('type' => 'product_cat', 'slug' => 'siropuri'),
    ),
    'Companie' => array(
        array('type' => 'page', 'slug' => 'despre-noi'),
        array('type' => 'page', 'slug' => 'contact'),
    ),
);

$ht_menu_name = 'Subsol';
$ht_menu = wp_get_nav_menu_object($ht_menu_name);

if ($ht_menu && !$ht_force) {
    ht_seed_log('meniul "' . $ht_menu_name . '" exista deja - il pastrez (--force il reconstruieste)', 'skip');
} elseif ($ht_dry) {
    ht_seed_log('ar construi meniul "' . $ht_menu_name . '" cu ' . count($ht_footer_columns) . ' coloane', 'ok');
} else {
    if ($ht_menu) {
        foreach ((array)wp_get_nav_menu_items($ht_menu->term_id) as $ht_item) {
            wp_delete_post($ht_item->ID, true);
        }

        $ht_menu_id = (int)$ht_menu->term_id;
        ht_seed_log('meniul "' . $ht_menu_name . '" golit', 'ok');
    } else {
        $ht_menu_id = wp_create_nav_menu($ht_menu_name);

        if (is_wp_error($ht_menu_id)) {
            exit('Nu am putut crea meniul: ' . $ht_menu_id->get_error_message() . "\n");
        }

        ht_seed_log('meniul "' . $ht_menu_name . '" creat (#' . $ht_menu_id . ')', 'ok');
    }

    $ht_order = 0;

    foreach ($ht_footer_columns as $ht_title => $ht_links) {
        $ht_resolved = array();

        foreach ($ht_links as $ht_link) {
            if ('page' === $ht_link['type']) {
                $ht_page = get_page_by_path($ht_link['slug']);

                if (!$ht_page || 'publish' !== $ht_page->post_status) {
                    ht_seed_log('pagina "' . $ht_link['slug'] . '" lipseste sau nu e publicata - sarita', 'warn');

                    continue;
                }

                $ht_resolved[] = array(
                    'object'    => 'page',
                    'object_id' => (int)$ht_page->ID,
                    'type'      => 'post_type',
                    'label'     => isset($ht_link['label']) ? $ht_link['label'] : $ht_page->post_title,
                );

                continue;
            }

            $ht_term = get_term_by('slug', $ht_link['slug'], $ht_link['type']);

            if (!$ht_term) {
                ht_seed_log('categoria "' . $ht_link['slug'] . '" lipseste - sarita', 'warn');

                continue;
            }

            $ht_resolved[] = array(
                'object'    => $ht_link['type'],
                'object_id' => (int)$ht_term->term_id,
                'type'      => 'taxonomy',
                'label'     => isset($ht_link['label']) ? $ht_link['label'] : $ht_term->name,
            );
        }

        if (!$ht_resolved) {
            ht_seed_log('coloana "' . $ht_title . '" ar ramane goala - sarita', 'warn');

            continue;
        }

        $ht_order++;

        /* capul de coloana nu e clicabil in subsol; conteaza doar titlul */
        $ht_parent = wp_update_nav_menu_item($ht_menu_id, 0, array(
            'menu-item-title'    => $ht_title,
            'menu-item-url'      => '#',
            'menu-item-type'     => 'custom',
            'menu-item-status'   => 'publish',
            'menu-item-position' => $ht_order,
        ));

        foreach ($ht_resolved as $ht_link) {
            $ht_order++;

            wp_update_nav_menu_item($ht_menu_id, 0, array(
                'menu-item-title'     => $ht_link['label'],
                'menu-item-object'    => $ht_link['object'],
                'menu-item-object-id' => $ht_link['object_id'],
                'menu-item-type'      => $ht_link['type'],
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $ht_parent,
                'menu-item-position'  => $ht_order,
            ));
        }

        ht_seed_log('coloana "' . $ht_title . '" - ' . count($ht_resolved) . ' legaturi', 'ok');
    }

    $ht_locations = (array)get_theme_mod('nav_menu_locations', array());

    if (empty($ht_locations['footer-menu']) || (int)$ht_locations['footer-menu'] !== (int)$ht_menu_id) {
        $ht_locations['footer-menu'] = (int)$ht_menu_id;
        set_theme_mod('nav_menu_locations', $ht_locations);
        ht_seed_log('meniul legat de zona "Meniu footer"', 'ok');
    } else {
        ht_seed_log('meniul era deja legat de zona "Meniu footer"', 'skip');
    }
}

/* ---------------------------------------------------------------------------
 * 5. Meniul din subsol in celelalte limbi
 *
 * Polylang tine cate un meniu pe limba pentru fiecare zona; fara meniu pentru
 * o limba, subsolul ramane fara coloane acolo. Pentru fiecare limba in afara
 * celei implicite, meniul se construieste dupa cel implicit: capetele de
 * coloana se traduc din tabelul de mai jos, iar legaturile se inlocuiesc cu
 * traducerile paginilor / categoriilor din Polylang. Ce nu are traducere e
 * sarit.
 * ------------------------------------------------------------------------ */

echo "\n[5] Meniul din subsol in celelalte limbi\n";

/* titlurile coloanelor si etichetele scrise de mana, pe limba */
$ht_footer_menu_i18n = array(
    'ru' => array(
        'Clientului'      => 'Покупателю',
        'Produse'         => 'Продукты',
        'Companie'        => 'Компания',
        'Toate produsele' => 'Все продукты',
    ),
);

$ht_source_menu = wp_get_nav_menu_object($ht_menu_name);
$ht_source_menu = $ht_source_menu ? (int)$ht_source_menu->term_id : 0;

if (!function_exists('pll_languages_list') || !$ht_source_menu) {
    ht_seed_log('Polylang lipseste sau meniul "' . $ht_menu_name . '" nu exista - pasul e sarit', 'warn');
} else {
    $ht_pll_options = (array)get_option('polylang', array());
    $ht_theme       = get_option('stylesheet');

    foreach (pll_languages_list() as $ht_lang) {
        if ($ht_lang === pll_default_language()) {
            continue;
        }

        $ht_lang_menu_name = $ht_menu_name . ' (' . strtoupper($ht_lang) . ')';
        $ht_lang_menu      = wp_get_nav_menu_object($ht_lang_menu_name);
        $ht_labels         = isset($ht_footer_menu_i18n[$ht_lang]) ? $ht_footer_menu_i18n[$ht_lang] : array();

        if ($ht_lang_menu && !$ht_force) {
            ht_seed_log('meniul "' . $ht_lang_menu_name . '" exista deja - il pastrez (--force il reconstruieste)', 'skip');

            continue;
        }

        if ($ht_dry) {
            ht_seed_log('ar construi meniul "' . $ht_lang_menu_name . '" dupa meniul #' . $ht_source_menu, 'ok');

            continue;
        }

        if ($ht_lang_menu) {
            foreach ((array)wp_get_nav_menu_items($ht_lang_menu->term_id) as $ht_item) {
                wp_delete_post($ht_item->ID, true);
            }

            $ht_lang_menu_id = (int)$ht_lang_menu->term_id;
            ht_seed_log('meniul "' . $ht_lang_menu_name . '" golit', 'ok');
        } else {
            $ht_lang_menu_id = wp_create_nav_menu($ht_lang_menu_name);

            if (is_wp_error($ht_lang_menu_id)) {
                exit('Nu am putut crea meniul: ' . $ht_lang_menu_id->get_error_message() . "\n");
            }

            ht_seed_log('meniul "' . $ht_lang_menu_name . '" creat (#' . $ht_lang_menu_id . ')', 'ok');
        }

        /* copiii grupati pe parinte, ca la pasul 4 */
        $ht_items    = (array)wp_get_nav_menu_items($ht_source_menu);
        $ht_children = array();

        foreach ($ht_items as $ht_item) {
            if ($ht_item->menu_item_parent) {
                $ht_children[(int)$ht_item->menu_item_parent][] = $ht_item;
            }
        }

        $ht_order = 0;

        foreach ($ht_items as $ht_item) {
            if ($ht_item->menu_item_parent || empty($ht_children[$ht_item->ID])) {
                continue;
            }

            $ht_resolved = array();

            foreach ($ht_children[$ht_item->ID] as $ht_child) {
                if ('post_type' === $ht_child->type) {
                    $ht_translated = (int)pll_get_post((int)$ht_child->object_id, $ht_lang);
                    $ht_default    = $ht_translated ? get_the_title($ht_translated) : '';
                } elseif ('taxonomy' === $ht_child->type) {
                    $ht_translated = (int)pll_get_term((int)$ht_child->object_id, $ht_lang);
                    $ht_term       = $ht_translated ? get_term($ht_translated, $ht_child->object) : null;
                    $ht_default    = ($ht_term && !is_wp_error($ht_term)) ? $ht_term->name : '';
                } else {
                    $ht_translated = 0;
                    $ht_default    = '';
                }

                if (!$ht_translated || '' === $ht_default) {
                    ht_seed_log('"' . $ht_child->title . '" nu are traducere in ' . $ht_lang . ' - sarita', 'warn');

                    continue;
                }

                $ht_resolved[] = array(
                    'object'    => $ht_child->object,
                    'object_id' => $ht_translated,
                    'type'      => $ht_child->type,
                    /* eticheta scrisa de mana se traduce din tabel; altfel ia titlul traducerii */
                    'label'     => isset($ht_labels[$ht_child->title]) ? $ht_labels[$ht_child->title] : $ht_default,
                );
            }

            if (!$ht_resolved) {
                ht_seed_log('coloana "' . $ht_item->title . '" ar ramane goala in ' . $ht_lang . ' - sarita', 'warn');

                continue;
            }

            $ht_title = isset($ht_labels[$ht_item->title]) ? $ht_labels[$ht_item->title] : $ht_item->title;
            $ht_order++;

            $ht_parent = wp_update_nav_menu_item($ht_lang_menu_id, 0, array(
                'menu-item-title'    => $ht_title,
                'menu-item-url'      => '#',
                'menu-item-type'     => 'custom',
                'menu-item-status'   => 'publish',
                'menu-item-position' => $ht_order,
            ));

            foreach ($ht_resolved as $ht_link) {
                $ht_order++;

                wp_update_nav_menu_item($ht_lang_menu_id, 0, array(
                    'menu-item-title'     => $ht_link['label'],
                    'menu-item-object'    => $ht_link['object'],
                    'menu-item-object-id' => $ht_link['object_id'],
                    'menu-item-type'      => $ht_link['type'],
                    'menu-item-status'    => 'publish',
                    'menu-item-parent-id' => $ht_parent,
                    'menu-item-position'  => $ht_order,
                ));
            }

            ht_seed_log('coloana "' . $ht_title . '" - ' . count($ht_resolved) . ' legaturi', 'ok');
        }

        /* legatura de zona, pe limba, sta in optiunile Polylang */
        $ht_current = isset($ht_pll_options['nav_menus'][$ht_theme]['footer-menu'][$ht_lang])
            ? (int)$ht_pll_options['nav_menus'][$ht_theme]['footer-menu'][$ht_lang]
            : 0;

        if ($ht_current !== (int)$ht_lang_menu_id) {
            $ht_pll_options['nav_menus'][$ht_theme]['footer-menu'][$ht_lang] = (int)$ht_lang_menu_id;
            update_option('polylang', $ht_pll_options);
            ht_seed_log('meniul legat de zona "Meniu footer" pentru ' . $ht_lang, 'ok');
        } else {
            ht_seed_log('meniul era deja legat de zona "Meniu footer" pentru ' . $ht_lang, 'skip');
        }
    }
}

echo "\n" . str_repeat('-', 52) . "\n";
echo "Gata.\n\n";
