<?php
/**
 * Pornirea testelor unitare.
 *
 * Testele merg fara WordPress si fara baza de date: fisierele testate
 * (inc/shop-facets.php e pur; inc/shop-filters.php atinge WordPress doar prin
 * cateva functii) primesc aici inlocuitori minimali. Ce e legat de baza de
 * date (randurile de produse) se injecteaza din teste prin filtrul
 * 'ht_shop_facet_rows_override' - vezi tests/Support/Wp.php.
 *
 * Rulare: composer install && composer test
 *
 * @package Herbal_Therapy
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/Wp.php';
require __DIR__ . '/Support/Fixtures.php';

define('ABSPATH', dirname(__DIR__) . '/');
define('DAY_IN_SECONDS', 86400);

require dirname(__DIR__) . '/inc/shop-facets.php';
require dirname(__DIR__) . '/inc/shop-filters.php';

define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);
define('COOKIEPATH', '/');
define('COOKIE_DOMAIN', '');

require __DIR__ . '/Support/FavoritesWp.php';
require dirname(__DIR__) . '/inc/favorites.php';

require dirname(__DIR__) . '/inc/permalinks.php';
require dirname(__DIR__) . '/inc/redirects.php';
require dirname(__DIR__) . '/inc/whatsapp.php';

/* inc/contact.php - impartirea pe randuri a campurilor textarea */
require dirname(__DIR__) . '/inc/contact.php';

/* inc/checkout.php - campurile de finalizare (telefon obligatoriu) */
require dirname(__DIR__) . '/inc/checkout.php';

/* inc/shipping-progress.php - bara spre livrarea gratuita (calculul pur) */
require dirname(__DIR__) . '/inc/shipping-progress.php';
