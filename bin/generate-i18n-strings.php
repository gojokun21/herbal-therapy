<?php
/**
 * Genereaza inc/i18n-strings.php - inventarul sirurilor temei, grupate pe
 * sectiuni, pentru inregistrarea in Polylang.
 *
 *   php bin/generate-i18n-strings.php
 *
 * Foloseste aceeasi extragere ca bin/make-pot.php (bin/lib-i18n-extract.php),
 * deci inventarul si sablonul .pot arata mereu aceleasi siruri.
 *
 * Sectiunea unui sir se ia dupa fisierul in care apare prima oara. Un sir
 * ajunge intr-o singura sectiune, pentru ca Polylang indexeaza sirurile dupa
 * md5(string) - vezi PLL_Admin_Strings::register_string(): daca l-am inregistra
 * de doua ori, a doua inregistrare ar suprascrie contextul primeia.
 *
 * @package Herbal_Therapy
 */

if (PHP_SAPI !== 'cli') {
    exit("Se ruleaza doar din linia de comanda.\n");
}

require_once __DIR__ . '/lib-i18n-extract.php';

$ht_theme_dir = dirname(__DIR__);
$ht_domain    = 'herbal-therapy';
$ht_target    = $ht_theme_dir . '/inc/i18n-strings.php';

/**
 * Sectiunea in care intra un fisier.
 *
 * Regulile se verifica in ordine, prima care se potriveste castiga; ce nu se
 * potriveste nicaieri ajunge in "General".
 *
 * @return array Perechi [expresie regulata, nume de sectiune].
 */
function ht_i18n_sections()
{
    return array(
        array('#^404\.php$#',                                    '404'),
        array('#^header\.php$#',                                 'Header'),
        array('#^(footer|inc/footer)\.php$#',                    'Footer'),
        array('#^inc/(navigation|language|class-ht-nav-walker)#', 'Header'),
        array('#^(search|searchform)\.php$|^inc/search\.php$#',   'Cautare'),
        array('#^inc/(hero-slider|home-about|home-benefits)\.php$|^templates/home#', 'Prima pagina'),
        array('#^inc/about\.php$|^templates/about\.php$#',        'Despre noi'),
        array('#^inc/contact\.php$|^templates/contact\.php$#',    'Contact'),
        array('#^inc/b2b\.php$|^templates/b2b\.php$#',            'B2B'),
        array('#^inc/(floating|call|whatsapp)\.php$#',           'Contact rapid'),
        array('#^inc/favorites\.php$|^templates/favorites\.php$#', 'Favorite'),
        array('#^inc/(account)\.php$|^templates/account\.php$|^woocommerce/myaccount/#', 'Cont'),
        array('#^inc/checkout\.php$|^templates/checkout\.php$|^woocommerce/checkout/#', 'Checkout'),
        array('#^inc/(minicart|shipping-progress)\.php$|^woocommerce/cart/#', 'Coș'),
        array('#^inc/reviews\.php$|^woocommerce/single-product#', 'Recenzii'),
        array('#^inc/single-product\.php$|^woocommerce/content-single-product\.php$#', 'Produs'),
        array('#^inc/(catalog|categories|products|shop-filters|shop-ajax)\.php$|^woocommerce/(content-product\.php|loop/)#', 'Catalog'),
        array('#^inc/(blog|blog-archive)\.php$|^(single|archive)\.php$|^templates/blog\.php$|^template-parts/#', 'Blog'),
        array('#^inc/toast\.php$#',                               'Notificari'),
        array('#^inc/woocommerce\.php$#',                         'WooCommerce'),
        array('#^inc/admin\.php$#',                               'Administrare'),
        array('#^page\.php$#',                                    'Pagini'),
    );
}

/**
 * Sectiunea unui fisier.
 *
 * @param string $rel Calea relativa a fisierului.
 *
 * @return string
 */
function ht_i18n_section_for($rel)
{
    foreach (ht_i18n_sections() as $rule) {
        list($pattern, $section) = $rule;

        if (preg_match($pattern, $rel)) {
            return $section;
        }
    }

    return 'General';
}

/* ---------------------------------------------------------------------------
 * Rularea
 * ------------------------------------------------------------------------ */

$ht_warnings = array();
$ht_entries  = ht_i18n_scan_theme($ht_theme_dir, $ht_domain, $ht_warnings);

/* un sir -> o singura sectiune, data de prima lui aparitie */
$ht_by_section = array();
$ht_seen       = array();

foreach ($ht_entries as $ht_entry) {
    $ht_refs = $ht_entry['references'];
    sort($ht_refs);

    $ht_file    = preg_replace('/:\d+$/', '', $ht_refs[0]);
    $ht_section = ht_i18n_section_for($ht_file);

    /* formele de plural se inregistreaza separat: Polylang traduce siruri
     * simple, nu perechi singular/plural */
    foreach (array($ht_entry['singular'], $ht_entry['plural']) as $ht_string) {
        if (null === $ht_string || '' === $ht_string || isset($ht_seen[$ht_string])) {
            continue;
        }

        $ht_seen[$ht_string]              = true;
        $ht_by_section[$ht_section][]     = $ht_string;
    }
}

ksort($ht_by_section);

foreach ($ht_by_section as &$ht_list) {
    sort($ht_list, SORT_NATURAL | SORT_FLAG_CASE);
}

unset($ht_list);

/* ---------------------------------------------------------------------------
 * Scrierea
 * ------------------------------------------------------------------------ */

$ht_total = count($ht_seen);
$ht_out   = <<<'HEAD'
<?php
/**
 * Inventarul sirurilor traductibile ale temei, grupate pe sectiuni.
 *
 * FISIER GENERAT - nu il edita manual.
 * Regenerare:  php bin/generate-i18n-strings.php
 *
 * Fiecare sir este inregistrat in Polylang de ht_i18n_register_strings() si
 * apare in Limbi -> Traduceri siruri, sub grupul "Herbal Therapy: <sectiune>".
 *
 * Sirurile raman in acelasi timp gettext obisnuit in cod (__(), _e(), ...),
 * deci traducerile din .po/.mo continua sa functioneze; vezi inc/i18n.php.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(

HEAD;

foreach ($ht_by_section as $ht_section => $ht_list) {
    $ht_out .= sprintf("\n    /* %s (%d) */\n", $ht_section, count($ht_list));
    $ht_out .= sprintf("    %s => array(\n", var_export((string)$ht_section, true));

    foreach ($ht_list as $ht_string) {
        $ht_out .= sprintf("        %s,\n", var_export($ht_string, true));
    }

    $ht_out .= "    ),\n";
}

$ht_out .= ");\n";

file_put_contents($ht_target, $ht_out);

printf("Scris %s\n", str_replace('\\', '/', substr($ht_target, strlen($ht_theme_dir) + 1)));
printf("%d siruri in %d sectiuni.\n", $ht_total, count($ht_by_section));

foreach ($ht_by_section as $ht_section => $ht_list) {
    printf("  %-16s %d\n", $ht_section, count($ht_list));
}

if ($ht_warnings) {
    printf("\n%d de verificat:\n", count($ht_warnings));

    foreach (array_unique($ht_warnings) as $ht_warning) {
        printf("  %s\n", $ht_warning);
    }
}
