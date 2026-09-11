<?php
/**
 * Filtrarea catalogului fara reincarcarea paginii.
 *
 * Cererea pleaca pe adresa reala a listarii, cu parametrul 'ht_ajax' adaugat:
 * interogarea principala, sortarea, paginarea si toate hook-urile raman exact
 * cele de la o incarcare obisnuita, doar ca in loc de pagina intreaga
 * raspundem cu coloana din dreapta filtrelor - bara de deasupra grilei, grila
 * si paginarea.
 *
 * Asa nu se dubleaza nicaieri logica de filtrare: ce vede vizitatorul dupa o
 * bifa e acelasi markup pe care l-ar fi primit si dupa o reincarcare.
 *
 * Fara JavaScript nu se schimba nimic - formularul de filtre merge prin GET
 * catre aceeasi adresa.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parametrul din adresa care cere doar fragmentul de listare.
 *
 * @return string
 */
function ht_shop_ajax_param()
{
    return 'ht_ajax';
}

/**
 * Cererea curenta cere doar fragmentul?
 *
 * @return bool
 */
function ht_shop_is_ajax_request()
{
    static $is_ajax = null;

    if (null === $is_ajax) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- listare publica, fara efecte.
        $is_ajax = !empty($_GET[ht_shop_ajax_param()]);
    }

    return $is_ajax;
}

/**
 * Scoate parametrul de AJAX dintr-o adresa.
 *
 * @param string $url Adresa curatata.
 *
 * @return string
 */
function ht_shop_ajax_strip($url)
{
    $url = preg_replace('/([?&])' . preg_quote(ht_shop_ajax_param(), '/') . '=[^&#]*&?/', '$1', (string)$url);

    return preg_replace('/[?&]$/', '', $url);
}

/**
 * Uita parametrul de AJAX pentru tot restul cererii.
 *
 * Legaturile de paginare le construieste paginate_links() din adresa ceruta -
 * $_SERVER['REQUEST_URI'] - nu din argumentele primite, deci parametrul ar
 * ajunge in fiecare dintre ele si ar intoarce JSON in loc de pagina, la o
 * deschidere in fila noua. Il scoatem inainte de randare; raspundem si iesim
 * imediat dupa, deci nu apuca nimeni sa se sprijine pe adresa modificata.
 *
 * Din $_GET pleaca din acelasi motiv: formularul de sortare isi pastreaza
 * ceilalti parametri ai adresei in campuri ascunse, copiate de acolo.
 *
 * Raspunsul la "e o cerere AJAX?" ramane cel dinainte de curatare - functia il
 * retine la prima citire, iar aici o chemam inainte de a schimba ceva.
 */
function ht_shop_ajax_clean_request_uri()
{
    ht_shop_is_ajax_request();

    if (!empty($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = ht_shop_ajax_strip(wp_unslash($_SERVER['REQUEST_URI']));
    }

    unset($_GET[ht_shop_ajax_param()], $_REQUEST[ht_shop_ajax_param()]);
}

/**
 * Continutul coloanei din dreapta.
 *
 * Aceeasi succesiune de hook-uri ca in archive-product.php, ca fragmentul
 * intors sa fie identic cu cel randat la o incarcare intreaga: etichetele
 * filtrelor active, sortarea, grila si paginarea.
 *
 * @return string
 */
function ht_shop_loop_html()
{
    ob_start();

    if (woocommerce_product_loop()) {
        do_action('woocommerce_before_shop_loop');

        woocommerce_product_loop_start();

        if (wc_get_loop_prop('total')) {
            while (have_posts()) {
                the_post();

                do_action('woocommerce_shop_loop');

                wc_get_template_part('content', 'product');
            }
        }

        woocommerce_product_loop_end();

        do_action('woocommerce_after_shop_loop');
    } else {
        do_action('woocommerce_no_products_found');
    }

    wp_reset_postdata();

    return (string)ob_get_clean();
}

/**
 * Raspunde cu fragmentul si opreste randarea paginii.
 *
 * Se aseaza dupa redirectionarile canonice si dupa cele ale WooCommerce, ambele
 * pe prioritatea 10, ca o adresa gresita sa se corecteze inainte de a raspunde.
 */
function ht_shop_ajax_response()
{
    if (!ht_shop_is_ajax_request() || !ht_is_shop_archive()) {
        return;
    }

    global $wp_query;

    nocache_headers();
    ht_shop_ajax_clean_request_uri();

    wp_send_json_success(array(
        'html'   => ht_shop_loop_html(),
        'total'  => (int)$wp_query->found_posts,
        'pages'  => (int)$wp_query->max_num_pages,
        /* contoarele din bara laterala, recalculate pentru noua selectie */
        'facets' => ht_shop_facet_payload(),
    ));
}

add_action('template_redirect', 'ht_shop_ajax_response', 100);
