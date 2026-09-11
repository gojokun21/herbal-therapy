<?php
/**
 * Redirecturi 301 de pe adresele site-ului vechi (Shopify) catre cele noi.
 *
 * Domeniul ramane acelasi, deci dupa mutarea DNS-ului toate adresele vechi
 * ajung aici. Shopify avea structura /products/{handle}, /collections/{handle},
 * /pages/{handle}, /blogs/{blog}/{articol}, /policies/{x}, plus prefixele de
 * limba /ru/ si /en/. Site-ul nou nu are engleza: /en/... merge pe romana.
 *
 * Cum functioneaza:
 *
 * - Harta cale veche -> cale noua (pe limbi) sta in inc/redirect-map.php,
 *   generata de bin/redirects/build-map.php din datele Shopify si din baza WP.
 * - Calea ceruta se normalizeaza (decodare, litere mici, diacritice cu
 *   virgula, fara slash final, fara sufixele .json/.atom ale Shopify), i se
 *   scoate prefixul de limba si se cauta in harta.
 * - Ce nu e in harta, dar sta intr-un spatiu de adrese Shopify, primeste o
 *   tinta de rezerva: produs necunoscut -> magazin, pagina -> prima pagina,
 *   articol -> blog, /account -> contul meu, /cart -> cos, /search?q= -> cautare.
 * - Parametrii de urmarire (utm_*, gclid, fbclid) se pastreaza; restul
 *   (variant=, page=, _pos=) se arunca.
 *
 * Toate functiile de aici, in afara de ht_legacy_redirect(), sunt pure si au
 * teste in tests/RedirectsTest.php.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Primul segment al adreselor Shopify pe care le preluam. Nu exista in WP
 * (nu avem pagini cu aceste slug-uri), deci nu putem lua fata unui continut
 * real. /checkout lipseste intentionat: pe site-ul nou exista pagina reala.
 *
 * @return string[]
 */
function ht_legacy_namespaces()
{
    return array('products', 'collections', 'pages', 'policies', 'blogs', 'cart', 'checkouts', 'account', 'search', 'apps');
}

/**
 * Normalizeaza o cale ca sa poata fi cautata in harta.
 *
 * @param string $path Calea, fara query string.
 *
 * @return string Calea decodata, cu litere mici, fara slash final.
 */
function ht_legacy_normalize_path($path)
{
    $path = rawurldecode((string)$path);
    $path = function_exists('mb_strtolower') ? mb_strtolower($path, 'UTF-8') : strtolower($path);

    /* diacriticele cu sedila (ş ţ) devin cele cu virgula (ș ț), cum le are harta */
    $path = str_replace(array("\xC5\x9F", "\xC5\xA3"), array("\xC8\x99", "\xC8\x9B"), $path);

    $path = preg_replace('#/+#', '/', $path);
    $path = preg_replace('#\.(json|atom|js|oembed)$#', '', $path);
    $path = rtrim($path, '/');

    return '' === $path ? '/' : $path;
}

/**
 * Query string-ul de pastrat la redirect: doar parametrii de urmarire.
 *
 * @param string $query Query string-ul brut.
 * @param array  $extra Parametri de adaugat (ex. cautarea).
 *
 * @return string Query string-ul nou, fara '?', sau sir gol.
 */
function ht_legacy_keep_query($query, array $extra = array())
{
    $params = array();
    parse_str((string)$query, $params);

    $keep = array();
    foreach ($params as $key => $value) {
        if (is_string($value) && preg_match('/^(utm_[a-z_]+|gclid|fbclid|msclkid|ttclid)$/i', (string)$key)) {
            $keep[$key] = $value;
        }
    }

    $keep = $extra + $keep;

    return $keep ? http_build_query($keep, '', '&', PHP_QUERY_RFC3986) : '';
}

/**
 * Alege varianta de limba a unei intrari din harta.
 *
 * @param array  $entry Ex. array('ro' => '/x/', 'ru' => '/ru/y/').
 * @param string $lang  'ro' sau 'ru'.
 *
 * @return string Calea sau sir gol.
 */
function ht_legacy_pick(array $entry, $lang)
{
    if (isset($entry[$lang])) {
        return (string)$entry[$lang];
    }

    return isset($entry['ro']) ? (string)$entry['ro'] : '';
}

/**
 * Tinta redirectarii pentru o cerere veche (functie pura).
 *
 * @param string $request_uri Calea ceruta, cu query string cu tot.
 * @param array  $map         Harta din inc/redirect-map.php ('paths', 'fallback').
 *
 * @return string Calea noua (cu prefix de limba, incepe cu '/') sau sir gol
 *                daca cererea nu e o adresa veche de redirectat.
 */
function ht_legacy_redirect_target($request_uri, array $map)
{
    $parts = explode('?', (string)$request_uri, 2);
    $query = isset($parts[1]) ? $parts[1] : '';
    $path = ht_legacy_normalize_path($parts[0]);
    $original = $path;

    $paths = isset($map['paths']) ? $map['paths'] : array();
    $fallback = isset($map['fallback']) ? $map['fallback'] : array();

    /* prefixul de limba: /ru/ ramane rusa, /en/ (disparuta) merge pe romana */
    $lang = 'ro';
    $old_lang = '';
    if (preg_match('#^/(ru|en)(?=/|$)#', $path, $m)) {
        $old_lang = $m[1];
        $lang = 'ru' === $old_lang ? 'ru' : 'ro';
        $path = substr($path, strlen($m[0]));
        if ('' === $path) {
            $path = '/';
        }
    }

    $extra = array();

    if ('/' === $path) {
        /* /en si /en/ -> prima pagina romaneasca; /ru/ si / sunt pagini reale */
        if ('en' !== $old_lang || !isset($fallback['home'])) {
            return '';
        }
        $entry = $fallback['home'];
    } else {
        $seg = explode('/', ltrim($path, '/'));
        $ns = $seg[0];

        if (!in_array($ns, ht_legacy_namespaces(), true)) {
            return '';
        }

        $entry = null;

        switch ($ns) {
            case 'products':
                $entry = isset($seg[1], $paths['/products/' . $seg[1]]) ? $paths['/products/' . $seg[1]] : null;
                $fb = 'shop';
                break;

            case 'collections':
                if (isset($seg[3]) && 'products' === $seg[2] && isset($paths['/products/' . $seg[3]])) {
                    /* /collections/{c}/products/{p}: produsul, nu colectia */
                    $entry = $paths['/products/' . $seg[3]];
                } elseif (isset($seg[1], $paths['/collections/' . $seg[1]])) {
                    $entry = $paths['/collections/' . $seg[1]];
                }
                $fb = 'shop';
                break;

            case 'pages':
                $entry = isset($seg[1], $paths['/pages/' . $seg[1]]) ? $paths['/pages/' . $seg[1]] : null;
                $fb = 'home';
                break;

            case 'policies':
                $entry = isset($seg[1], $paths['/policies/' . $seg[1]]) ? $paths['/policies/' . $seg[1]] : null;
                $fb = 'terms';
                break;

            case 'blogs':
                if (isset($seg[2], $paths['/blogs/' . $seg[1] . '/' . $seg[2]])) {
                    $entry = $paths['/blogs/' . $seg[1] . '/' . $seg[2]];
                } elseif (isset($seg[1], $paths['/blogs/' . $seg[1]])) {
                    /* articol necunoscut sau /tagged/: indexul blogului */
                    $entry = $paths['/blogs/' . $seg[1]];
                }
                $fb = 'blog';
                break;

            case 'cart':
            case 'checkouts':
                $fb = 'cart';
                break;

            case 'account':
                $fb = 'account';
                break;

            case 'search':
                $params = array();
                parse_str($query, $params);
                if (isset($params['q']) && is_string($params['q']) && '' !== trim($params['q'])) {
                    $extra['s'] = trim($params['q']);
                }
                $fb = 'home';
                break;

            default:
                $fb = 'home';
        }

        if (null === $entry) {
            if (!isset($fallback[$fb])) {
                return '';
            }
            $entry = $fallback[$fb];
        }
    }

    $target = ht_legacy_pick($entry, $lang);
    if ('' === $target) {
        return '';
    }

    /* nu ne redirectam catre noi insine (ex. /ru/cart e chiar pagina cosului) */
    if (rtrim(ht_legacy_normalize_path(strtok($target, '?')), '/') === rtrim($original, '/')) {
        return '';
    }

    $keep = ht_legacy_keep_query($query, $extra);
    if ('' !== $keep) {
        $target .= (false === strpos($target, '?') ? '?' : '&') . $keep;
    }

    return $target;
}

/**
 * Cererea curenta seamana cu o adresa veche? Test ieftin, inainte de a
 * incarca harta.
 *
 * @param string $request_uri Calea ceruta.
 *
 * @return bool
 */
function ht_legacy_is_candidate($request_uri)
{
    $path = strtok((string)$request_uri, '?');
    $path = rawurldecode($path);

    if (preg_match('#^/en(?:/|$)#i', $path)) {
        return true;
    }

    $ns = implode('|', array_map('preg_quote', ht_legacy_namespaces()));

    return (bool)preg_match('#^/(?:ru/|en/)?(?:' . $ns . ')(?:[/.]|$)#i', $path);
}

/**
 * Harta din inc/redirect-map.php, incarcata o singura data.
 *
 * @return array
 */
function ht_legacy_redirect_map()
{
    static $map = null;

    if (null === $map) {
        $file = get_template_directory() . '/inc/redirect-map.php';
        $map = file_exists($file) ? (array)include $file : array();
    }

    return $map;
}

/**
 * Redirectarea propriu-zisa, inaintea redirecturilor canonice ale WP si Polylang.
 */
function ht_legacy_redirect()
{
    if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
        return;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string)wp_unslash($_SERVER['REQUEST_URI']) : '';

    if ('' === $uri || !ht_legacy_is_candidate($uri)) {
        return;
    }

    $target = ht_legacy_redirect_target($uri, ht_legacy_redirect_map());

    if ('' === $target) {
        return;
    }

    /* domeniul din setari, nefiltrat de Polylang (tinta are deja prefixul de limba) */
    $home = untrailingslashit((string)get_option('home'));

    header('X-Redirect-By: herbal-legacy');
    wp_safe_redirect($home . $target, 301);
    exit;
}

add_action('template_redirect', 'ht_legacy_redirect', 0);
