<?php
/**
 * Rutele REST folosite de scripturile de import din /bin/import.
 *
 * Doua lucruri se fac aici, si nu din exterior, pentru ca amandoua depind de
 * cod care stie sa-si aseze singur datele:
 *
 *  1. Campurile ACF. Repeaterele 'beneficii' si 'ingrediente' se pastreaza
 *     desfacute pe randuri, fiecare cu o cheie-oglinda catre definitia
 *     campului (beneficii_0_text alaturi de _beneficii_0_text). Scrise de mana
 *     prin meta_data, e usor sa scapi o cheie: campul apare gol in
 *     administrare si se pierde la prima salvare. update_field() le aseaza pe
 *     toate corect.
 *
 *  2. Perechea in rusa. Legatura dintre traduceri e un termen din taxonomia
 *     'post_translations' care tine un tablou serializat {ro: id, ru: id}, plus
 *     cate un termen de limba pe fiecare produs. Polylang are functii pentru
 *     asta; refacute din afara, prin scriere directa in tabele, raman usor
 *     nesincronizate. In plus ordinea conteaza - vezi ht_import_twin_callback().
 *
 * Rutele cer aceleasi drepturi ca editarea unui produs. Dupa ce importul s-a
 * terminat, poti scoate linia care incarca fisierul din functions.php.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cheile campurilor, luate din acf-json/group_ht_product.json.
 *
 * update_field() primeste cheia, nu numele: numele se poate schimba din
 * interfata, cheia nu.
 *
 * @return array<string, string>
 */
function ht_import_acf_keys()
{
    return array(
        'beneficii'        => 'field_ht_product_benefits',
        'ingrediente'      => 'field_ht_product_ingredients',
        'mod_de_utilizare' => 'field_ht_product_usage',
        'atentionari'      => 'field_ht_product_warnings',
    );
}

/**
 * Inregistreaza rutele.
 */
function ht_import_register_routes()
{
    $args_id = array(
        'id' => array(
            'validate_callback' => function ($value) {
                return 'product' === get_post_type((int)$value);
            },
        ),
    );

    register_rest_route('ht-import/v1', '/acf/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'ht_import_acf_callback',
        'permission_callback' => 'ht_import_permission',
        'args'                => $args_id,
    ));

    register_rest_route('ht-import/v1', '/twin/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'ht_import_twin_callback',
        'permission_callback' => 'ht_import_permission',
        'args'                => $args_id,
    ));
}

add_action('rest_api_init', 'ht_import_register_routes');

/**
 * Aceleasi drepturi ca la editarea produsului.
 *
 * @param WP_REST_Request $request Cererea.
 *
 * @return bool|WP_Error
 */
function ht_import_permission($request)
{
    if (!function_exists('get_field')) {
        return new WP_Error('ht_import_no_acf', 'ACF nu este activ.', array('status' => 501));
    }

    if (!current_user_can('edit_post', (int)$request['id'])) {
        return new WP_Error('ht_import_forbidden', 'Drepturi insuficiente.', array('status' => 403));
    }

    return true;
}

/* ---------------------------------------------------------------------------
 * Campurile ACF
 * ------------------------------------------------------------------------ */

/**
 * Scrie pe produs campurile primite, prin update_field().
 *
 * Cheile lipsa din tablou se lasa neatinse; cele prezente si goale golesc
 * campul.
 *
 * @param int   $id      ID-ul produsului.
 * @param array $campuri Valorile: beneficii, ingrediente, mod_de_utilizare, atentionari.
 *
 * @return array Ce s-a scris, pentru raportul importului.
 */
function ht_import_apply_acf($id, $campuri)
{
    $keys = ht_import_acf_keys();
    $scris = array();

    /* Beneficii - repeater cu un singur subcamp, maximum 6 randuri. */
    if (isset($campuri['beneficii'])) {
        $valoare = array();

        foreach ((array)$campuri['beneficii'] as $rand) {
            $text = is_array($rand) ? (isset($rand['text']) ? $rand['text'] : '') : $rand;
            $text = trim(wp_strip_all_tags((string)$text));

            if ('' !== $text) {
                $valoare[] = array('text' => $text);
            }
        }

        $valoare = array_slice($valoare, 0, 6);
        update_field($keys['beneficii'], $valoare, $id);
        $scris['beneficii'] = count($valoare);
    }

    /* Ingrediente - repeater cu text plus comutatorul de ingrosare. */
    if (isset($campuri['ingrediente'])) {
        $valoare = array();

        foreach ((array)$campuri['ingrediente'] as $rand) {
            $text = is_array($rand) ? (isset($rand['text']) ? $rand['text'] : '') : $rand;
            $text = trim(wp_strip_all_tags((string)$text));

            if ('' === $text) {
                continue;
            }

            $valoare[] = array(
                'text'   => $text,
                'strong' => (is_array($rand) && !empty($rand['strong'])) ? 1 : 0,
            );
        }

        update_field($keys['ingrediente'], $valoare, $id);
        $scris['ingrediente'] = count($valoare);
    }

    /* Cele doua taburi - wysiwyg, deci pastram HTML-ul permis in continut. */
    foreach (array('mod_de_utilizare', 'atentionari') as $nume) {
        if (!isset($campuri[$nume])) {
            continue;
        }

        $html = trim((string)$campuri[$nume]);
        $html = '' === $html ? '' : wp_kses_post($html);

        update_field($keys[$nume], $html, $id);
        $scris[$nume] = strlen($html);
    }

    return $scris;
}

/**
 * POST /ht-import/v1/acf/<id>
 *
 * @param WP_REST_Request $request Cererea.
 *
 * @return WP_REST_Response
 */
function ht_import_acf_callback($request)
{
    $id = (int)$request['id'];

    $campuri = array();
    foreach (array_keys(ht_import_acf_keys()) as $nume) {
        if (null !== $request->get_param($nume)) {
            $campuri[$nume] = $request->get_param($nume);
        }
    }

    return rest_ensure_response(array(
        'id'    => $id,
        'scris' => ht_import_apply_acf($id, $campuri),
    ));
}

/* ---------------------------------------------------------------------------
 * Perechea in alta limba
 * ------------------------------------------------------------------------ */

/**
 * Categoria corespunzatoare intr-o alta limba, creata daca lipseste.
 *
 * @param int    $term_id ID-ul categoriei din limba sursa.
 * @param string $lang    Codul limbii tinta.
 * @param string $nume    Denumirea tradusa, folosita doar la creare.
 *
 * @return int ID-ul categoriei traduse, 0 la esec.
 */
function ht_import_translated_category($term_id, $lang, $nume)
{
    $existent = function_exists('pll_get_term') ? (int)pll_get_term($term_id, $lang) : 0;

    if ($existent) {
        return $existent;
    }

    if ('' === trim((string)$nume)) {
        return 0;
    }

    /* Denumirile se pot repeta intre limbi, deci cautam intai un termen liber. */
    $termen = get_term_by('name', $nume, 'product_cat');

    if ($termen && function_exists('pll_get_term_language')
        && pll_get_term_language($termen->term_id) === $lang) {
        $nou_id = (int)$termen->term_id;
    } else {
        $creat = wp_insert_term($nume, 'product_cat');

        if (is_wp_error($creat)) {
            /* termenul exista deja cu acelasi slug - il refolosim */
            $date = $creat->get_error_data();
            $nou_id = is_array($date) && isset($date['term_id']) ? (int)$date['term_id'] : 0;

            if (!$nou_id) {
                return 0;
            }
        } else {
            $nou_id = (int)$creat['term_id'];
        }
    }

    if (function_exists('pll_set_term_language')) {
        pll_set_term_language($nou_id, $lang);
    }

    if (function_exists('pll_save_term_translations') && function_exists('pll_get_term_translations')) {
        $traduceri = pll_get_term_translations($term_id);
        $traduceri[$lang] = $nou_id;
        pll_save_term_translations($traduceri);
    }

    return $nou_id;
}

/**
 * Opreste copierea de metadate a Polylang for WooCommerce.
 *
 * Cand se salveaza legatura dintre traduceri, pluginul isi sincronizeaza lista
 * proprie de campuri de produs - pret, stoc, taxe - intre cele doua produse, si
 * le scrie ca randuri noi peste cele existente. Rezultatul sunt meta duplicate
 * pe produsul sursa, care se inmultesc la fiecare rulare a importului.
 *
 * Importul nu are nevoie de copiere: aseaza el insusi fiecare proprietate pe
 * traducere, cu valorile luate din sursa. Vezi ht_import_twin_callback().
 *
 * @return array Lista goala de campuri de copiat.
 */
function ht_import_skip_meta_copy()
{
    return array();
}

/**
 * Cauta un produs netradus care descrie acelasi articol in limba ceruta.
 *
 * Polylang stie doar de traducerile pe care le-a legat el. Daca perechea a fost
 * creata de mana si nimeni nu a apasat butonul de legare, pll_get_post() spune
 * ca nu exista - iar importul ar crea un al doilea produs identic. S-a intamplat
 * deja cu o pereche din magazin.
 *
 * Cautam deci si dupa SKU, care e acelasi pe toate traducerile unui produs, si
 * in lipsa lui dupa titlul exact. Un produs deja legat de altceva nu se atinge.
 *
 * @param WC_Product $sursa Produsul din limba sursa.
 * @param string     $lang  Limba ceruta.
 * @param string     $titlu Titlul tradus, folosit cand nu exista SKU.
 *
 * @return int ID-ul produsului gasit, 0 daca nu e niciunul.
 */
function ht_import_find_orphan($sursa, $lang, $titlu)
{
    $comun = array(
        'post_type'        => 'product',
        'post_status'      => 'any',
        'posts_per_page'   => 5,
        'fields'           => 'ids',
        'lang'             => $lang,
        'suppress_filters' => false,
    );

    $sku = $sursa->get_sku();
    $candidati = array();

    if ('' !== $sku) {
        $intrebare = new WP_Query($comun + array(
            'meta_query' => array(
                array('key' => '_sku', 'value' => $sku),
            ),
        ));
        $candidati = $intrebare->posts;
    }

    if (!$candidati && '' !== $titlu) {
        $intrebare = new WP_Query($comun + array('title' => $titlu));
        $candidati = $intrebare->posts;
    }

    foreach ($candidati as $id) {
        $id = (int)$id;

        if ($id === $sursa->get_id()) {
            continue;
        }

        /* daca e deja traducerea altui produs, il lasam in pace */
        $traduceri = function_exists('pll_get_post_translations')
            ? pll_get_post_translations($id)
            : array();

        if (count($traduceri) > 1) {
            continue;
        }

        return $id;
    }

    return 0;
}

/**
 * POST /ht-import/v1/twin/<id>
 *
 * Creeaza sau actualizeaza perechea intr-o alta limba a produsului dat.
 *
 * Ordinea din functie nu e intamplatoare. Codul SKU e acelasi pe ambele
 * traduceri, iar WooCommerce refuza din principiu doua produse cu acelasi SKU;
 * Polylang for WooCommerce ridica interdictia doar cand cele doua produse sunt
 * deja legate ca traduceri. De aceea produsul se creeaza intai fara SKU, apoi i
 * se pune limba si legatura, si abia la final primeste codul.
 *
 * Datele de magazin - pret, stoc, greutate, imagini - se copiaza explicit,
 * fiindca in setarile Polylang de pe acest site sincronizarea e dezactivata.
 *
 * @param WP_REST_Request $request Cererea.
 *
 * @return WP_REST_Response|WP_Error
 */
function ht_import_twin_callback($request)
{
    if (!function_exists('pll_set_post_language')) {
        return new WP_Error('ht_import_no_polylang', 'Polylang nu este activ.', array('status' => 501));
    }

    $sursa_id = (int)$request['id'];
    $lang = sanitize_key((string)$request->get_param('lang'));

    if ('' === $lang || !in_array($lang, pll_languages_list(), true)) {
        return new WP_Error('ht_import_bad_lang', 'Limba ceruta nu e configurata.', array('status' => 400));
    }

    $sursa = wc_get_product($sursa_id);

    if (!$sursa) {
        return new WP_Error('ht_import_no_product', 'Produsul sursa nu exista.', array('status' => 404));
    }

    $titlu = trim((string)$request->get_param('titlu'));

    if ('' === $titlu) {
        return new WP_Error('ht_import_no_title', 'Lipseste titlul tradus.', array('status' => 400));
    }

    $exista = (int)pll_get_post($sursa_id, $lang);

    /* pereche existenta, dar nelegata de Polylang - o adoptam in loc sa dublam */
    if (!$exista) {
        $exista = ht_import_find_orphan($sursa, $lang, $titlu);
    }

    $creat_acum = false;

    /* vezi ht_import_skip_meta_copy() - altfel raman meta duplicate pe sursa */
    add_filter('pllwc_copy_post_metas', 'ht_import_skip_meta_copy', 999);

    /* --- 1. postul, deocamdata fara SKU --- */
    $date = array(
        'post_type'    => 'product',
        'post_title'   => $titlu,
        'post_content' => (string)$request->get_param('descriere'),
        'post_excerpt' => (string)$request->get_param('descriere_scurta'),
        'post_status'  => get_post_status($sursa_id),
    );

    $slug = sanitize_title((string)$request->get_param('slug'));

    if ('' !== $slug) {
        $date['post_name'] = $slug;
    }

    if ($exista) {
        $date['ID'] = $exista;
        $tinta_id = wp_update_post($date, true);
    } else {
        $tinta_id = wp_insert_post($date, true);
        $creat_acum = true;
    }

    if (is_wp_error($tinta_id)) {
        remove_filter('pllwc_copy_post_metas', 'ht_import_skip_meta_copy', 999);
        return $tinta_id;
    }

    $tinta_id = (int)$tinta_id;

    /* --- 2. limba si legatura, inainte de orice atinge SKU-ul --- */
    pll_set_post_language($tinta_id, $lang);

    $traduceri = function_exists('pll_get_post_translations')
        ? pll_get_post_translations($sursa_id)
        : array();
    $traduceri[pll_get_post_language($sursa_id)] = $sursa_id;
    $traduceri[$lang] = $tinta_id;
    pll_save_post_translations($traduceri);

    /* --- 3. datele de magazin, copiate din sursa --- */
    $tinta = wc_get_product($tinta_id);

    if (!$tinta) {
        remove_filter('pllwc_copy_post_metas', 'ht_import_skip_meta_copy', 999);
        return new WP_Error('ht_import_twin_failed', 'Produsul tradus nu s-a putut incarca.', array('status' => 500));
    }

    $tinta->set_sku($sursa->get_sku());
    $tinta->set_regular_price($sursa->get_regular_price());
    $tinta->set_sale_price($sursa->get_sale_price());
    $tinta->set_stock_status($sursa->get_stock_status());
    $tinta->set_weight($sursa->get_weight());
    $tinta->set_catalog_visibility($sursa->get_catalog_visibility());

    /*
     * Imaginile raman aceleasi fisiere: produsul e acelasi, doar textul difera.
     * Asa nu se descarca de doua ori cele cateva sute de fotografii si
     * biblioteca media ramane curata.
     */
    $tinta->set_image_id($sursa->get_image_id());
    $tinta->set_gallery_image_ids($sursa->get_gallery_image_ids());

    /* --- 4. categoriile, in varianta lor tradusa --- */
    $categorii = array();

    foreach ($sursa->get_category_ids() as $cat_id) {
        $nume = (string)$request->get_param('categorie');
        $tradus = ht_import_translated_category($cat_id, $lang, $nume);

        if ($tradus) {
            $categorii[] = $tradus;
        }
    }

    if ($categorii) {
        $tinta->set_category_ids($categorii);
    }

    $tinta->save();

    /* --- 5. campurile ACF traduse --- */
    $campuri = array();

    foreach (array_keys(ht_import_acf_keys()) as $nume) {
        if (null !== $request->get_param($nume)) {
            $campuri[$nume] = $request->get_param($nume);
        }
    }

    $acf = $campuri ? ht_import_apply_acf($tinta_id, $campuri) : array();

    remove_filter('pllwc_copy_post_metas', 'ht_import_skip_meta_copy', 999);

    return rest_ensure_response(array(
        'sursa'      => $sursa_id,
        'id'         => $tinta_id,
        'lang'       => $lang,
        'creat'      => $creat_acum,
        'categorii'  => $categorii,
        'acf'        => $acf,
    ));
}
