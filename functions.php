<?php
/**
 * Herbal Therapy - punctul de intrare al temei.
 *
 * Fisierul incarca doar modulele din /inc. Logica noua se adauga acolo, nu aici.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Constante
 * ------------------------------------------------------------------------ */

if (!defined('HT_DIR')) {
    define('HT_DIR', get_template_directory());
}

if (!defined('HT_URI')) {
    define('HT_URI', get_template_directory_uri());
}

if (!defined('HT_VERSION')) {
    $ht_theme = wp_get_theme(get_template());
    define('HT_VERSION', $ht_theme->get('Version') ? $ht_theme->get('Version') : '1.0.0');
    unset($ht_theme);
}

/* ---------------------------------------------------------------------------
 * Module
 * ------------------------------------------------------------------------ */

$ht_modules = array(
    'setup',              // suporturi, meniuri, zone de widget-uri
    'i18n',               // inventarul de siruri + puntea catre Polylang
    'icons',              // iconite SVG
    'template-tags',      // functii apelate din template-uri
    'template-functions', // filtre asupra comportamentului WP
    'favorites',          // lista de produse salvate
    'assets',             // CSS / JS
    'navigation',         // meniuri + walker-ul de mega-meniu
    'catalog',            // butonul "Catalog" si panoul lui cu categorii
    'language',           // comutatorul de limba
    'search',             // panoul de cautare si pagina de rezultate
    'hero-slider',        // caruselul de pe prima pagina
    'products',           // cardul de produs si caruselul de produse
    'categories',         // caruselul cu categorii de produse
    'top-sales',          // sectiunea "Top vanzari" - taburi de categorii + best sellers
    'blog',               // sectiunea cu articole de pe prima pagina
    'home-reviews',       // sectiunea cu recenzii de pe prima pagina
    'home-benefits',      // sectiunea "Puterea naturii" de pe prima pagina
    'blog-archive',       // pagina de listare a articolelor
    'contact',            // pagina de contact si formularul ei
    'b2b',                // pagina B2B si formularul de colaborare
    'about',              // pagina "Despre noi"
    'home-about',         // blocul "Despre noi" de pe prima pagina
    'footer',             // coloanele, contactele si platile din subsol
    'whatsapp',           // butonul plutitor de WhatsApp
    'redirects',          // 301 de pe adresele vechi (Shopify) catre cele noi
    'admin',              // ajustari in zona de administrare
);

foreach ($ht_modules as $ht_module) {
    require_once HT_DIR . '/inc/' . $ht_module . '.php';
}

unset($ht_modules, $ht_module);

/* WooCommerce, doar daca pluginul e activ */
if (class_exists('WooCommerce')) {
    require_once HT_DIR . '/inc/woocommerce.php';
    require_once HT_DIR . '/inc/permalinks.php'; // adrese fara /product/ si /product-category/
}

/*
 * Rutele REST folosite de scripturile din /bin/import: campurile ACF si
 * perechea in rusa. Se pot scoate dupa ce importul s-a terminat.
 */
require_once HT_DIR . '/inc/import-endpoint.php';
