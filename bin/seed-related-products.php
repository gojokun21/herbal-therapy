<?php
/**
 * Populeaza produsele legate (upsell si cross-sell) din WooCommerce.
 *
 * Harta de mai jos e rezultatul analizei de marketing a catalogului
 * (01.09.2026, 167 produse) si urmeaza felul in care tema le foloseste:
 *
 *   - UPSELL  = ambalajul mai mare al aceluiasi produs (N30 -> N60,
 *               20 ml -> 50 ml, 100 ml -> 300 ml). Tema le arata in cardul
 *               "Alege varianta mai avantajoasa" (ht_product_upgrades()),
 *               cel mult 2, cu pretul pe bucata cand marimea e in nume;
 *   - CROSS   = produsele cumparate impreuna, dupa rutina de folosire
 *               (aceeasi problema: articulatii, raceala, imunitate, ten
 *               acneic, ingrijirea bebelusului, ritualul de baie cu acelasi
 *               extract etc.). Tema le arata in caruselul "Oamenii cumpara
 *               acest produs impreuna cu" (ht_product_bought_together()),
 *               cel mult 8.
 *
 * Produsele sunt identificate prin SKU (EAN); cele fara SKU prin titlul RO.
 * Relatiile se scriu pe produsul romanesc si pe perechea lui ruseasca
 * (Polylang), cu ID-urile traduse.
 *
 * Rulare, din radacina temei:
 *   php bin/seed-related-products.php            - completeaza doar produsele fara relatii
 *   php bin/seed-related-products.php --force    - suprascrie si relatiile existente
 *   php bin/seed-related-products.php --dry-run  - arata ce ar scrie, fara sa scrie
 *
 * Cand PHP-ul din linia de comanda nu vede MySQL-ul (Local pe Windows):
 *   HT_DB_HOST=127.0.0.1:10004 php bin/seed-related-products.php
 *
 * @package Herbal_Therapy
 */

if ('cli' !== PHP_SAPI) {
    http_response_code(403);
    exit("bin/seed-related-products.php se ruleaza doar din linia de comanda.\n");
}

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

$_SERVER['HTTP_HOST']      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_SERVER['SERVER_NAME']    = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/index.php';

if (!empty($_SERVER['HT_DB_HOST']) && !defined('DB_HOST')) {
    define('DB_HOST', (string)$_SERVER['HT_DB_HOST']);

    set_error_handler(function ($no, $str) {
        return false !== strpos($str, 'DB_HOST already defined');
    }, E_WARNING);
    require_once $ht_root . '/wp-load.php';
    restore_error_handler();
} else {
    require_once $ht_root . '/wp-load.php';
}

if (!function_exists('wc_get_product')) {
    exit("WooCommerce nu e activ.\n");
}

/* ---------------------------------------------------------------------------
 * Catalogul: alias scurt => SKU sau 'name:<titlul RO>' (pentru cele fara SKU)
 * ------------------------------------------------------------------------ */

$ht_catalog = array(
    /* Cosmetica medicala */
    'artix100'        => '6427321002898',
    'artix300'        => '6427321003093',
    'reumix100'       => '6427321002904',
    'reumix300'       => '6427321003116',
    'varix100'        => '6427321002805',
    'varix300'        => '6427321003109',
    'balsam-camfor'   => '6427321000665',
    'bombenghe30'     => '6427321002461',
    'bombenghe75'     => '6427321001624',
    'geucamen30'      => '6427321002454',
    'geucamen75'      => '6427321001617',
    'reliefix'        => '6427321003062',
    'ulei-revulsiv'   => '6427190000025',

    /* Balsam de buze */
    'buze-capsuna'    => '6427321002232',
    'buze-pepene'     => '6427321002249',

    /* Geluri de dus */
    'gel-catina'      => '6427321000917',
    'gel-galbenele'   => '6427321000894',
    'gel-immortelle'  => '6427321000863',
    'gel-musetel'     => '6427321000887',
    'gel-salvie'      => '6427321000900',
    'gel-herbal'      => '6427321000870',

    /* Hidratare pentru maini si corp */
    'corp-pepene'     => '6427321002263',
    'corp-floral'     => '6427321002256',
    'maini-aloe'      => '6427321003161',
    'maini-catina'    => '6427321003185',
    'maini-galbenele' => 'name:Cremă de mâini cu Extract de Gălbenele și Ulei de Măsline 50 ml',
    'maini-immortelle' => 'name:Cremă de mâini cu Extract de Immortelle și Ulei de Cânepă 50 ml',
    'maini-musetel'   => '6427321003178',
    'maini-galbenele-immortelle100' => '6427321002409',
    'calcaie'         => '6427321004199',
    'dexpanthen100'   => '6427321002447',
    'dexpanthen30'    => '6427321002744',
    'melkfett'        => '6427321002430',

    /* Pudre */
    'pudra-copii'     => '4840257007492',
    'pudra-antitransp' => '4840257007300',
    'pudra-mentol'    => '4840257007294',

    /* Siropuri */
    'sirop-cimbrisor' => '4840257007508',
    'sirop-detox'     => '4840257008727',
    'sirop-echinacea-zinc' => '4840257008901',
    'sirop-echinacea-patlagina' => 'name:Sirop IMUNO Echinaceea, Pătlagină, Ghimbir și Vitamina C 200 ml',
    'sirop-hepatic'   => '4840257008093',
    'sirop-lax'       => '4840257008949',
    'sirop-iedera'    => '4840257008963',
    'sirop-patlagina-c' => '4840257008956',
    'sirop-resveratrol' => '4840257008871',
    'sirop-tusin'     => '4840257008772',
    'sirop-vitamine-copii' => '4840257008789',
    'sirop-phytocalm-copii' => '4840257008734',
    'sirop-phytocalm-somn' => '4840257008758',
    'sirop-macese'    => 'name:Sirop de Măceșe 200 ml',

    /* Solutii */
    'alcool-mentolat' => '4840257007188',
    'apa-oxigenata200' => '4840257006662',
    'apa-oxigenata500' => '4840257006945',
    'apa-oxigenata1000' => '4840257006754',

    /* Spumant de baie */
    'spumant-detox'   => '6427321004182',
    'spumant-relax'   => '6427321004151',
    'spumant-revital' => '6427321004175',

    /* Sapun lichid */
    'sapun-aloe'      => '6427321000597',
    'sapun-ceai-verde' => '6427321000603',
    'sapun-galbenele' => '6427321000580',
    'sapun-musetel'   => '6427321000634',
    'sapun-herbal'    => '6427321000658',

    /* Uleiuri */
    'ulei-corp'       => '6427321002065',
    'ulei-magneziu'   => '6427321004144',
    'ricin100'        => '6423063014731',
    'ricin55'         => '6423063014762',
    'ulei-vaselina'   => 'name:Ulei de Vaselină 25 g',

    /* Unguente */
    'ung-aloe20'      => '6427321000726',
    'ung-aloe50'      => '6427321003024',
    'ung-arnica20'    => '6427321003079',
    'ung-arnica50'    => '6427321003192',
    'ung-capsicum20'  => '6427321002959',
    'ung-capsicum50'  => '6427321003246',
    'ung-echinacea50' => '6427321000689',
    'ung-gheara20'    => '6427321002980',
    'ung-gheara50'    => '6427321003222',
    'ung-galbenele20' => '6427321000559',
    'ung-galbenele40' => '6427321000450',
    'ung-galbenele50' => '6427321003031',
    'ung-galbenele-propolis20' => '6427321000542',
    'ung-galbenele-propolis40' => 'name:Unguent cu Gălbenele și Propolis 40 ml',
    'ung-galbenele-propolis50' => '6427321003000',
    'ung-galbenele-siminoc40' => '6427321000481',
    'ung-melissa50'   => '6427321000702',
    'ung-musetel20'   => '6427321000733',
    'ung-musetel50'   => '6427321003017',
    'ung-musetel-aloe40' => '6427321000474',
    'ung-rostopasca20' => 'name:Unguent cu Rostopască 20 ml',
    'ung-rostopasca40' => '6427321000467',
    'ung-rostopasca50' => '6427321002997',
    'ung-brusture20'  => '6427321002942',
    'ung-brusture50'  => '6427321003239',
    'ung-spanz20'     => '6427321002966',
    'ung-tataneasa20' => '6427321002935',
    'ung-salvie50'    => '6427321000696',
    'ung-siminoc20'   => '6427321000740',
    'ung-siminoc50'   => '6427321003048',
    'ung-canepa50'    => '6427321000719',
    'ung-catina20'    => '6427321000757',
    'ung-catina50'    => '6427321003055',

    /* Vaseline */
    'vaselina-cosmetica' => '6423063016803',
    'vaselina-floral' => '6423063016827',
    'vaselina-rose'   => '6423063016810',

    /* Vitamine si suplimente */
    'bcomplex'        => '4840257009304',
    'camgznd3-30'     => '4840257008666',
    'camgznd3-60'     => 'name:Calciu + Magneziu + Zinc + Vitamina D3 N60',
    'camgzn30'        => '4840257008673',
    'camgzn60'        => '4840257009038',
    'calciu1000-30'   => '4840257008055',
    'calciu1000-60'   => '4840257009076',
    'calciu-d3-forte' => '4840257009052',
    'calciu-d3-caise' => '4840257007201',
    'calciu-farmaco10' => '4840257006365',
    'calciu-farmaco60' => 'name:Calciu-Farmaco(Gluconat) 500mg N60',
    'flusept-propolis' => '4840257009359',
    'flusept-salvie'  => '4840257009342',
    'ginkgo'          => '4840257009069',
    'hepatoliv'       => '4840257009311',
    'mgb6'            => '4840257009328',
    'melatonina'      => '4840257008918',
    'multi-teen30'    => '4840257008697',
    'multi-teen60'    => '4840257009014',
    'multi-adulti30'  => '4840257008703',
    'multi-adulti60'  => '4840257009007',
    'seleniu'         => '4840257009250',
    'vitc-propolis-echinacea' => 'name:Vitamina C 100 mg Propolis și Echinacea N60',
    'vitc-propolis-polen' => '4840257007348',
    'vitc-propolis-echinacea-zinc' => '4840257007362',
    'vitc-propolis'   => '4840257007379',
    'vitc-propolis-miere' => '4840257007331',
    'vitc-gluc10'     => '4840257006372',
    'vitc-gluc120'    => '4840257007058',
    'vitc-lamaie10'   => 'name:Vitamina C 100 mg cu glucoză și aromă de lămâie N10',
    'vitc-lamaie120'  => '4840257007027',
    'vitc-portocala10' => '4840257006822',
    'vitc-portocala120' => '4840257007034',
    'vitc-struguri10' => 'name:Vitamina C 100 mg cu glucoză și aromă de struguri N10',
    'vitc-struguri120' => '4840257007041',
    'vitc-zmeura10'   => '4840257006846',
    'vitc-zmeura120'  => '4840257007010',
    'vitc1000-soc'    => '4840257009335',
    'vitc180-20'      => '4840257007461',
    'vitc180-portocala20' => '4840257007669',
    'vitc180-struguri20' => '4840257007652',
    'vitc300zn30'     => '4840257008635',
    'vitc300zn60'     => '4840257009021',
    'vitc500-30'      => '4840257008642',
    'vitc500-60'      => '6427321000573',
    'd3-2000'         => '4840257008208',
    'd3-4000'         => '4840257008215',

    /* Ingrijire faciala */
    'acid-salicilic'  => '6427321000627',
    'micelara-immortelle' => '6427321000948',
    'micelara-lavanda' => '6427321000931',
    'lotiune-antiacneica' => '6427321002072',
    'demachianta'     => '6427321000962',
    'spray-corp'      => '6427321000986',
    'toner'           => '6427321000979',

    /* Sampoane */
    'sampon-catina300' => '6427321000849',
    'sampon-herbal300' => '6427321000801',
    'sampon-galbenele100' => '6427321002379',
    'sampon-galbenele300' => '6427321000825',
    'sampon-musetel300' => 'name:Șampon cu extracte de Mușețel și Aloe Vera + Ulei de Cânepă 300 ml',
    'sampon-salvie100' => '6427321002362',
    'sampon-salvie300' => '6427321000832',
    'sampon-salvie1000' => '6427321003376',
    'sampon-urzica100' => '6427321002355',
    'sampon-urzica300' => '6427321000795',
);

/* ---------------------------------------------------------------------------
 * UPSELL: ambalajul mai mare al aceluiasi produs (cel mult 2, in ordinea
 * in care le propunem: intai urmatoarea marime, apoi cea mai mare)
 * ------------------------------------------------------------------------ */

$ht_upsells = array(
    'artix100'        => array('artix300'),
    'reumix100'       => array('reumix300'),
    'varix100'        => array('varix300'),
    'bombenghe30'     => array('bombenghe75'),
    'geucamen30'      => array('geucamen75'),
    'dexpanthen30'    => array('dexpanthen100'),
    'maini-galbenele' => array('maini-galbenele-immortelle100'),
    'maini-immortelle' => array('maini-galbenele-immortelle100'),
    'apa-oxigenata200' => array('apa-oxigenata500', 'apa-oxigenata1000'),
    'apa-oxigenata500' => array('apa-oxigenata1000'),
    'ricin55'         => array('ricin100'),
    'vaselina-rose'   => array('vaselina-cosmetica'),
    'vaselina-floral' => array('vaselina-cosmetica'),

    'ung-aloe20'      => array('ung-aloe50'),
    'ung-arnica20'    => array('ung-arnica50'),
    'ung-capsicum20'  => array('ung-capsicum50'),
    'ung-gheara20'    => array('ung-gheara50'),
    'ung-galbenele20' => array('ung-galbenele40', 'ung-galbenele50'),
    'ung-galbenele40' => array('ung-galbenele50'),
    'ung-galbenele-propolis20' => array('ung-galbenele-propolis40', 'ung-galbenele-propolis50'),
    'ung-galbenele-propolis40' => array('ung-galbenele-propolis50'),
    'ung-musetel20'   => array('ung-musetel50'),
    'ung-rostopasca20' => array('ung-rostopasca40', 'ung-rostopasca50'),
    'ung-rostopasca40' => array('ung-rostopasca50'),
    'ung-brusture20'  => array('ung-brusture50'),
    'ung-siminoc20'   => array('ung-siminoc50'),
    'ung-catina20'    => array('ung-catina50'),

    'sampon-galbenele100' => array('sampon-galbenele300'),
    'sampon-salvie100' => array('sampon-salvie300', 'sampon-salvie1000'),
    'sampon-salvie300' => array('sampon-salvie1000'),
    'sampon-urzica100' => array('sampon-urzica300'),

    'camgznd3-30'     => array('camgznd3-60'),
    'camgzn30'        => array('camgzn60'),
    'calciu1000-30'   => array('calciu1000-60'),
    /* calciu-farmaco10 -> 60 lipseste intentionat: N60 (69,60) e mai scump
       pe tableta decat N10 (10,15); de reluat cand clientul ajusteaza pretul */
    'multi-teen30'    => array('multi-teen60'),
    'multi-adulti30'  => array('multi-adulti60'),
    'vitc300zn30'     => array('vitc300zn60'),
    'vitc500-30'      => array('vitc500-60'),
    'd3-2000'         => array('d3-4000'),
    'vitc-gluc10'     => array('vitc-gluc120'),
    'vitc-lamaie10'   => array('vitc-lamaie120'),
    'vitc-portocala10' => array('vitc-portocala120'),
    'vitc-struguri10' => array('vitc-struguri120'),
    'vitc-zmeura10'   => array('vitc-zmeura120'),
    'vitc180-portocala20' => array('vitc-portocala120'),
    'vitc180-struguri20' => array('vitc-struguri120'),
    'vitc180-20'      => array('vitc-gluc120'),
);

/* ---------------------------------------------------------------------------
 * CROSS-SELL: "cumparate impreuna", dupa rutina (cel mult 8, primele sunt
 * cele mai probabile)
 * ------------------------------------------------------------------------ */

$ht_cross = array(
    /* Articulatii, muschi, dureri (uz extern) + sustinere interna */
    'artix100'        => array('camgznd3-30', 'ung-gheara50', 'reumix100', 'ulei-magneziu', 'ung-arnica50', 'spumant-relax'),
    'artix300'        => array('camgznd3-60', 'ung-gheara50', 'reumix300', 'ulei-magneziu', 'ung-arnica50', 'ulei-revulsiv'),
    'reumix100'       => array('artix100', 'ung-capsicum50', 'bombenghe75', 'ung-brusture50', 'ulei-revulsiv', 'mgb6'),
    'reumix300'       => array('artix300', 'ung-capsicum50', 'bombenghe75', 'ung-brusture50', 'ulei-revulsiv', 'mgb6'),
    'varix100'        => array('ginkgo', 'ung-arnica50', 'vitc1000-soc', 'spumant-revital', 'ung-galbenele-siminoc40', 'corp-floral'),
    'varix300'        => array('ginkgo', 'ung-arnica50', 'vitc1000-soc', 'spumant-revital', 'ung-galbenele-siminoc40', 'corp-floral'),
    'bombenghe30'     => array('geucamen30', 'balsam-camfor', 'reliefix', 'ung-capsicum20', 'mgb6', 'pudra-mentol'),
    'bombenghe75'     => array('geucamen75', 'balsam-camfor', 'reliefix', 'ung-capsicum50', 'mgb6', 'pudra-mentol'),
    'geucamen30'      => array('bombenghe30', 'balsam-camfor', 'reliefix', 'ung-arnica20', 'ulei-revulsiv', 'pudra-mentol'),
    'geucamen75'      => array('bombenghe75', 'balsam-camfor', 'reliefix', 'ung-arnica50', 'ulei-revulsiv', 'pudra-mentol'),
    'balsam-camfor'   => array('sirop-tusin', 'ung-salvie50', 'bombenghe75', 'geucamen75', 'flusept-salvie', 'reliefix'),
    'reliefix'        => array('bombenghe75', 'geucamen75', 'balsam-camfor', 'pudra-mentol', 'ung-arnica50', 'ulei-magneziu'),
    'ulei-revulsiv'   => array('reumix300', 'artix300', 'spumant-relax', 'ulei-magneziu', 'ung-capsicum50', 'balsam-camfor'),
    'ung-arnica20'    => array('ung-gheara20', 'ung-galbenele20', 'varix100', 'bombenghe30', 'camgznd3-30', 'reliefix'),
    'ung-arnica50'    => array('ung-gheara50', 'ung-galbenele50', 'varix100', 'bombenghe75', 'camgznd3-30', 'reliefix'),
    'ung-capsicum20'  => array('reumix100', 'bombenghe30', 'ung-gheara20', 'ung-brusture20', 'balsam-camfor', 'mgb6'),
    'ung-capsicum50'  => array('reumix100', 'bombenghe75', 'ung-gheara50', 'ung-brusture50', 'balsam-camfor', 'mgb6'),
    'ung-gheara20'    => array('artix100', 'ung-arnica20', 'ung-brusture20', 'camgznd3-30', 'ulei-magneziu', 'ung-spanz20'),
    'ung-gheara50'    => array('artix100', 'ung-arnica50', 'ung-brusture50', 'camgznd3-60', 'ulei-magneziu', 'ung-spanz20'),
    'ung-brusture20'  => array('reumix100', 'ung-gheara20', 'ung-spanz20', 'ung-tataneasa20', 'artix100', 'ung-capsicum20'),
    'ung-brusture50'  => array('reumix100', 'ung-gheara50', 'ung-spanz20', 'ung-tataneasa20', 'artix100', 'ung-capsicum50'),
    'ung-spanz20'     => array('ung-brusture20', 'ung-tataneasa20', 'reumix100', 'ung-capsicum20', 'artix100', 'ung-gheara20'),
    'ung-tataneasa20' => array('ung-arnica20', 'ung-spanz20', 'ung-brusture20', 'calciu1000-30', 'artix100', 'camgznd3-30'),
    'ulei-magneziu'   => array('mgb6', 'spumant-relax', 'artix100', 'melatonina', 'sirop-phytocalm-somn', 'ung-gheara50'),

    /* Piele: unguente calmante, regenerare, ingrijirea bebelusului */
    'ung-galbenele20' => array('ung-musetel20', 'ung-galbenele-propolis20', 'sapun-galbenele', 'maini-galbenele', 'pudra-copii', 'dexpanthen30'),
    'ung-galbenele40' => array('ung-musetel50', 'ung-galbenele-propolis40', 'sapun-galbenele', 'maini-galbenele', 'pudra-copii', 'dexpanthen30'),
    'ung-galbenele50' => array('ung-musetel50', 'ung-galbenele-propolis50', 'sapun-galbenele', 'maini-galbenele-immortelle100', 'pudra-copii', 'dexpanthen100'),
    'ung-galbenele-propolis20' => array('ung-galbenele20', 'ung-echinacea50', 'buze-capsuna', 'sapun-galbenele', 'vitc-propolis', 'apa-oxigenata200'),
    'ung-galbenele-propolis40' => array('ung-galbenele40', 'ung-echinacea50', 'buze-capsuna', 'sapun-galbenele', 'vitc-propolis', 'apa-oxigenata200'),
    'ung-galbenele-propolis50' => array('ung-galbenele50', 'ung-echinacea50', 'buze-capsuna', 'sapun-galbenele', 'vitc-propolis', 'maini-galbenele-immortelle100'),
    'ung-galbenele-siminoc40' => array('ung-siminoc50', 'ung-galbenele40', 'ung-melissa50', 'gel-galbenele', 'maini-immortelle', 'varix100'),
    'ung-musetel20'   => array('ung-galbenele20', 'ung-musetel-aloe40', 'sapun-musetel', 'pudra-copii', 'buze-pepene', 'gel-musetel'),
    'ung-musetel50'   => array('ung-galbenele50', 'ung-musetel-aloe40', 'sapun-musetel', 'pudra-copii', 'gel-musetel', 'maini-musetel'),
    'ung-musetel-aloe40' => array('ung-aloe50', 'ung-musetel50', 'gel-musetel', 'maini-aloe', 'sapun-aloe', 'pudra-copii'),
    'ung-aloe20'      => array('ung-musetel-aloe40', 'maini-aloe', 'sapun-aloe', 'dexpanthen30', 'spray-corp', 'apa-oxigenata200'),
    'ung-aloe50'      => array('ung-musetel-aloe40', 'maini-aloe', 'sapun-aloe', 'dexpanthen100', 'gel-musetel', 'spray-corp'),
    'ung-siminoc20'   => array('ung-galbenele-siminoc40', 'ung-canepa50', 'micelara-immortelle', 'maini-immortelle', 'gel-immortelle', 'ung-rostopasca20'),
    'ung-siminoc50'   => array('ung-galbenele-siminoc40', 'ung-canepa50', 'ung-melissa50', 'micelara-immortelle', 'maini-immortelle', 'gel-immortelle'),
    'ung-catina20'    => array('ung-galbenele20', 'maini-catina', 'sampon-catina300', 'gel-catina', 'dexpanthen30', 'sirop-macese'),
    'ung-catina50'    => array('ung-galbenele50', 'maini-catina', 'sampon-catina300', 'gel-catina', 'dexpanthen100', 'sirop-macese'),
    'ung-rostopasca20' => array('ung-siminoc20', 'ung-galbenele20', 'ung-echinacea50', 'sapun-ceai-verde', 'ung-canepa50', 'sirop-detox'),
    'ung-rostopasca40' => array('ung-siminoc50', 'ung-galbenele40', 'ung-echinacea50', 'sapun-ceai-verde', 'ung-canepa50', 'sirop-detox'),
    'ung-rostopasca50' => array('ung-siminoc50', 'ung-galbenele50', 'ung-echinacea50', 'sapun-ceai-verde', 'ung-canepa50', 'sirop-detox'),
    'ung-echinacea50' => array('lotiune-antiacneica', 'acid-salicilic', 'sapun-ceai-verde', 'toner', 'ung-galbenele-propolis50', 'ung-rostopasca50'),
    'ung-melissa50'   => array('toner', 'micelara-immortelle', 'demachianta', 'ung-siminoc50', 'vitc1000-soc', 'sirop-resveratrol'),
    'ung-salvie50'    => array('balsam-camfor', 'sirop-tusin', 'sirop-iedera', 'flusept-salvie', 'sampon-salvie300', 'gel-salvie'),
    'ung-canepa50'    => array('ung-siminoc50', 'gel-immortelle', 'maini-immortelle', 'sampon-urzica300', 'micelara-immortelle', 'ung-rostopasca50'),
    'dexpanthen30'    => array('ung-galbenele20', 'ung-aloe20', 'pudra-copii', 'melkfett', 'calcaie', 'maini-galbenele'),
    'dexpanthen100'   => array('ung-galbenele50', 'ung-aloe50', 'melkfett', 'calcaie', 'pudra-copii', 'corp-pepene'),
    'melkfett'        => array('dexpanthen100', 'calcaie', 'maini-galbenele-immortelle100', 'vaselina-cosmetica', 'corp-pepene', 'ung-galbenele50'),
    'calcaie'         => array('melkfett', 'dexpanthen100', 'corp-pepene', 'vaselina-cosmetica', 'spumant-revital', 'maini-galbenele-immortelle100'),
    'pudra-copii'     => array('ung-galbenele20', 'ung-musetel20', 'dexpanthen30', 'sapun-musetel', 'sampon-musetel300', 'sirop-vitamine-copii'),
    'pudra-antitransp' => array('pudra-mentol', 'gel-salvie', 'calcaie', 'sapun-ceai-verde', 'spray-corp', 'sampon-salvie300'),
    'pudra-mentol'    => array('pudra-antitransp', 'alcool-mentolat', 'reliefix', 'bombenghe75', 'gel-salvie', 'spumant-revital'),
    'vaselina-cosmetica' => array('vaselina-rose', 'vaselina-floral', 'calcaie', 'melkfett', 'buze-capsuna', 'ulei-vaselina'),
    'vaselina-rose'   => array('vaselina-cosmetica', 'vaselina-floral', 'buze-capsuna', 'maini-galbenele', 'corp-floral', 'spray-corp'),
    'vaselina-floral' => array('vaselina-cosmetica', 'vaselina-rose', 'buze-pepene', 'maini-musetel', 'corp-floral', 'spray-corp'),
    'ulei-vaselina'   => array('vaselina-cosmetica', 'ricin55', 'sirop-lax', 'apa-oxigenata200', 'alcool-mentolat', 'melkfett'),
    'ricin55'         => array('sampon-urzica300', 'sampon-catina300', 'buze-capsuna', 'ulei-corp', 'ulei-vaselina', 'maini-catina'),
    'ricin100'        => array('sampon-urzica300', 'sampon-catina300', 'bcomplex', 'ulei-corp', 'ulei-vaselina', 'maini-catina'),
    'ulei-corp'       => array('corp-pepene', 'spray-corp', 'gel-herbal', 'spumant-relax', 'maini-catina', 'ricin55'),
    'apa-oxigenata200' => array('ung-galbenele20', 'ung-galbenele-propolis20', 'alcool-mentolat', 'ung-aloe20', 'vaselina-cosmetica', 'ung-arnica20'),
    'apa-oxigenata500' => array('ung-galbenele20', 'ung-galbenele-propolis20', 'alcool-mentolat', 'ung-aloe20', 'vaselina-cosmetica', 'ung-arnica20'),
    'apa-oxigenata1000' => array('ung-galbenele50', 'ung-galbenele-propolis50', 'alcool-mentolat', 'ung-aloe50', 'vaselina-cosmetica', 'ung-arnica50'),
    'alcool-mentolat' => array('pudra-mentol', 'apa-oxigenata200', 'bombenghe75', 'balsam-camfor', 'reliefix', 'ulei-vaselina'),

    /* Ingrijire faciala */
    'acid-salicilic'  => array('lotiune-antiacneica', 'toner', 'micelara-lavanda', 'ung-echinacea50', 'sapun-ceai-verde', 'vitc1000-soc'),
    'lotiune-antiacneica' => array('acid-salicilic', 'toner', 'micelara-immortelle', 'sapun-ceai-verde', 'ung-echinacea50', 'gel-salvie'),
    'micelara-immortelle' => array('toner', 'demachianta', 'micelara-lavanda', 'maini-immortelle', 'buze-pepene', 'spray-corp'),
    'micelara-lavanda' => array('toner', 'demachianta', 'micelara-immortelle', 'acid-salicilic', 'buze-capsuna', 'spray-corp'),
    'demachianta'     => array('toner', 'micelara-immortelle', 'micelara-lavanda', 'spray-corp', 'ung-aloe20', 'buze-capsuna'),
    'toner'           => array('demachianta', 'micelara-immortelle', 'acid-salicilic', 'lotiune-antiacneica', 'spray-corp', 'ung-melissa50'),
    'spray-corp'      => array('corp-floral', 'demachianta', 'toner', 'ulei-corp', 'gel-herbal', 'maini-aloe'),
    'buze-capsuna'    => array('buze-pepene', 'maini-galbenele', 'ung-galbenele-propolis20', 'vaselina-rose', 'micelara-lavanda', 'maini-catina'),
    'buze-pepene'     => array('buze-capsuna', 'maini-musetel', 'corp-pepene', 'ung-musetel20', 'vaselina-floral', 'micelara-immortelle'),

    /* Corp si maini */
    'corp-pepene'     => array('buze-pepene', 'maini-catina', 'gel-herbal', 'spray-corp', 'ulei-corp', 'corp-floral'),
    'corp-floral'     => array('spray-corp', 'maini-aloe', 'gel-immortelle', 'corp-pepene', 'ulei-corp', 'vaselina-floral'),
    'maini-aloe'      => array('maini-catina', 'sapun-aloe', 'ung-aloe20', 'corp-floral', 'buze-pepene', 'gel-musetel'),
    'maini-catina'    => array('sapun-herbal', 'ung-catina20', 'maini-aloe', 'corp-pepene', 'buze-capsuna', 'gel-catina'),
    'maini-galbenele' => array('sapun-galbenele', 'ung-galbenele20', 'buze-capsuna', 'melkfett', 'gel-galbenele', 'maini-musetel'),
    'maini-immortelle' => array('gel-immortelle', 'ung-canepa50', 'sapun-herbal', 'buze-pepene', 'micelara-immortelle', 'maini-galbenele'),
    'maini-musetel'   => array('sapun-musetel', 'ung-musetel20', 'buze-pepene', 'maini-aloe', 'gel-musetel', 'corp-pepene'),
    'maini-galbenele-immortelle100' => array('sapun-galbenele', 'melkfett', 'calcaie', 'ung-galbenele-siminoc40', 'buze-capsuna', 'gel-galbenele'),

    /* Igiena: ritualul de baie cu acelasi extract */
    'gel-catina'      => array('sampon-catina300', 'sapun-herbal', 'maini-catina', 'ung-catina50', 'spumant-revital', 'corp-pepene'),
    'gel-galbenele'   => array('sampon-galbenele300', 'sapun-galbenele', 'maini-galbenele', 'ung-galbenele50', 'spumant-relax', 'corp-pepene'),
    'gel-immortelle'  => array('sampon-urzica300', 'sapun-herbal', 'maini-immortelle', 'ung-canepa50', 'spumant-detox', 'corp-floral'),
    'gel-musetel'     => array('sampon-musetel300', 'sapun-musetel', 'maini-musetel', 'ung-musetel-aloe40', 'pudra-copii', 'spumant-relax'),
    'gel-salvie'      => array('sampon-salvie300', 'sapun-ceai-verde', 'pudra-antitransp', 'ung-salvie50', 'spumant-revital', 'calcaie'),
    'gel-herbal'      => array('sampon-herbal300', 'sapun-herbal', 'corp-pepene', 'spumant-detox', 'maini-catina', 'ulei-corp'),
    'sapun-aloe'      => array('gel-musetel', 'maini-aloe', 'sapun-musetel', 'sampon-musetel300', 'ung-aloe20', 'corp-floral'),
    'sapun-ceai-verde' => array('gel-salvie', 'lotiune-antiacneica', 'ung-echinacea50', 'sampon-salvie300', 'pudra-antitransp', 'maini-aloe'),
    'sapun-galbenele' => array('gel-galbenele', 'maini-galbenele', 'ung-galbenele20', 'sampon-galbenele300', 'sapun-musetel', 'maini-galbenele-immortelle100'),
    'sapun-musetel'   => array('gel-musetel', 'maini-musetel', 'pudra-copii', 'sampon-musetel300', 'sapun-aloe', 'ung-musetel20'),
    'sapun-herbal'    => array('gel-herbal', 'sampon-herbal300', 'maini-catina', 'sapun-galbenele', 'corp-floral', 'maini-immortelle'),
    'sampon-catina300' => array('gel-catina', 'ricin100', 'sampon-urzica300', 'sapun-herbal', 'maini-catina', 'bcomplex'),
    'sampon-herbal300' => array('gel-herbal', 'sapun-herbal', 'ricin55', 'sampon-catina300', 'spumant-revital', 'corp-pepene'),
    'sampon-galbenele100' => array('gel-galbenele', 'sapun-galbenele', 'ricin55', 'sampon-urzica100', 'ung-galbenele20', 'maini-galbenele'),
    'sampon-galbenele300' => array('gel-galbenele', 'sapun-galbenele', 'ricin100', 'sampon-urzica300', 'ung-galbenele50', 'maini-galbenele'),
    'sampon-musetel300' => array('gel-musetel', 'sapun-musetel', 'pudra-copii', 'sampon-galbenele300', 'maini-musetel', 'ung-musetel20'),
    'sampon-salvie100' => array('gel-salvie', 'sapun-ceai-verde', 'sampon-urzica100', 'ricin55', 'ung-salvie50', 'pudra-antitransp'),
    'sampon-salvie300' => array('gel-salvie', 'sapun-ceai-verde', 'sampon-urzica300', 'ricin100', 'ung-salvie50', 'pudra-antitransp'),
    'sampon-salvie1000' => array('gel-salvie', 'sapun-ceai-verde', 'sampon-urzica300', 'ricin100', 'ung-salvie50', 'spumant-revital'),
    'sampon-urzica100' => array('ricin55', 'gel-immortelle', 'sampon-catina300', 'sapun-herbal', 'bcomplex', 'maini-immortelle'),
    'sampon-urzica300' => array('ricin100', 'gel-immortelle', 'sampon-catina300', 'sapun-herbal', 'bcomplex', 'seleniu'),
    'spumant-detox'   => array('spumant-relax', 'spumant-revital', 'ulei-magneziu', 'gel-immortelle', 'sirop-detox', 'corp-floral'),
    'spumant-relax'   => array('spumant-detox', 'spumant-revital', 'ulei-magneziu', 'mgb6', 'sirop-phytocalm-somn', 'melatonina'),
    'spumant-revital' => array('spumant-relax', 'spumant-detox', 'ulei-corp', 'gel-salvie', 'calcaie', 'ulei-magneziu'),

    /* Siropuri */
    'sirop-cimbrisor' => array('sirop-tusin', 'sirop-iedera', 'flusept-salvie', 'ung-salvie50', 'balsam-camfor', 'vitc500-30'),
    'sirop-detox'     => array('sirop-hepatic', 'sirop-lax', 'hepatoliv', 'spumant-detox', 'vitc1000-soc', 'seleniu'),
    'sirop-echinacea-zinc' => array('vitc-propolis-echinacea-zinc', 'flusept-propolis', 'd3-2000', 'sirop-echinacea-patlagina', 'sirop-patlagina-c', 'vitc300zn30'),
    'sirop-echinacea-patlagina' => array('sirop-echinacea-zinc', 'sirop-tusin', 'vitc-propolis-echinacea', 'flusept-propolis', 'd3-2000', 'balsam-camfor'),
    'sirop-hepatic'   => array('hepatoliv', 'sirop-detox', 'sirop-lax', 'seleniu', 'bcomplex', 'sirop-macese'),
    'sirop-lax'       => array('sirop-detox', 'sirop-hepatic', 'ulei-vaselina', 'ricin55', 'mgb6', 'hepatoliv'),
    'sirop-iedera'    => array('sirop-tusin', 'sirop-cimbrisor', 'sirop-patlagina-c', 'balsam-camfor', 'flusept-salvie', 'ung-salvie50'),
    'sirop-patlagina-c' => array('sirop-tusin', 'sirop-iedera', 'sirop-cimbrisor', 'sirop-echinacea-patlagina', 'flusept-propolis', 'vitc-gluc120'),
    'sirop-resveratrol' => array('ginkgo', 'seleniu', 'vitc1000-soc', 'multi-adulti60', 'hepatoliv', 'd3-4000'),
    'sirop-tusin'     => array('sirop-cimbrisor', 'sirop-iedera', 'sirop-patlagina-c', 'flusept-salvie', 'balsam-camfor', 'ung-salvie50'),
    'sirop-vitamine-copii' => array('sirop-phytocalm-copii', 'vitc-zmeura120', 'calciu-d3-caise', 'multi-teen30', 'sirop-macese', 'pudra-copii'),
    'sirop-phytocalm-copii' => array('sirop-vitamine-copii', 'sirop-phytocalm-somn', 'mgb6', 'spumant-relax', 'vitc-zmeura120', 'pudra-copii'),
    'sirop-phytocalm-somn' => array('melatonina', 'mgb6', 'spumant-relax', 'sirop-phytocalm-copii', 'ulei-magneziu', 'bcomplex'),
    'sirop-macese'    => array('sirop-patlagina-c', 'vitc500-30', 'sirop-vitamine-copii', 'sirop-hepatic', 'sirop-echinacea-zinc', 'd3-2000'),

    /* Vitamine si suplimente */
    'bcomplex'        => array('mgb6', 'multi-adulti30', 'seleniu', 'sampon-urzica300', 'sirop-phytocalm-somn', 'd3-2000'),
    'camgznd3-30'     => array('multi-adulti30', 'vitc500-30', 'artix100', 'ulei-magneziu', 'seleniu', 'bcomplex'),
    'camgznd3-60'     => array('multi-adulti60', 'vitc500-60', 'artix100', 'ulei-magneziu', 'seleniu', 'bcomplex'),
    'camgzn30'        => array('d3-2000', 'vitc500-30', 'multi-adulti30', 'artix100', 'bcomplex', 'ulei-magneziu'),
    'camgzn60'        => array('d3-2000', 'vitc500-60', 'multi-adulti60', 'artix100', 'bcomplex', 'ulei-magneziu'),
    'calciu1000-30'   => array('d3-2000', 'd3-4000', 'mgb6', 'multi-adulti30', 'ung-tataneasa20', 'artix100'),
    'calciu1000-60'   => array('d3-4000', 'd3-2000', 'mgb6', 'multi-adulti60', 'ung-tataneasa20', 'artix100'),
    'calciu-d3-forte' => array('mgb6', 'vitc500-60', 'multi-adulti60', 'artix100', 'bcomplex', 'vitc-portocala120'),
    'calciu-d3-caise' => array('sirop-vitamine-copii', 'vitc-zmeura120', 'multi-teen30', 'mgb6', 'sirop-phytocalm-copii', 'vitc-portocala120'),
    'calciu-farmaco10' => array('d3-2000', 'vitc-gluc10', 'mgb6', 'vitc-portocala10', 'multi-adulti30', 'calciu-d3-caise'),
    'calciu-farmaco60' => array('d3-2000', 'vitc-gluc120', 'mgb6', 'vitc-portocala120', 'multi-adulti30', 'calciu-d3-caise'),
    'flusept-propolis' => array('flusept-salvie', 'vitc-propolis-miere', 'sirop-patlagina-c', 'sirop-echinacea-zinc', 'sirop-tusin', 'balsam-camfor'),
    'flusept-salvie'  => array('flusept-propolis', 'sirop-tusin', 'sirop-iedera', 'ung-salvie50', 'vitc500-30', 'balsam-camfor'),
    'ginkgo'          => array('sirop-resveratrol', 'varix100', 'bcomplex', 'seleniu', 'mgb6', 'multi-adulti60'),
    'hepatoliv'       => array('sirop-hepatic', 'sirop-detox', 'seleniu', 'bcomplex', 'sirop-lax', 'vitc1000-soc'),
    'mgb6'            => array('bcomplex', 'sirop-phytocalm-somn', 'melatonina', 'ulei-magneziu', 'calciu1000-30', 'spumant-relax'),
    'melatonina'      => array('sirop-phytocalm-somn', 'mgb6', 'spumant-relax', 'bcomplex', 'ulei-magneziu', 'ginkgo'),
    'multi-teen30'    => array('vitc300zn30', 'calciu-d3-caise', 'd3-2000', 'sirop-phytocalm-copii', 'vitc-zmeura120', 'mgb6'),
    'multi-teen60'    => array('vitc300zn60', 'calciu-d3-caise', 'd3-2000', 'sirop-phytocalm-copii', 'vitc-zmeura120', 'mgb6'),
    'multi-adulti30'  => array('d3-2000', 'mgb6', 'vitc500-30', 'seleniu', 'camgzn30', 'bcomplex'),
    'multi-adulti60'  => array('d3-2000', 'mgb6', 'vitc500-60', 'seleniu', 'camgzn60', 'bcomplex'),
    'seleniu'         => array('vitc1000-soc', 'multi-adulti30', 'ginkgo', 'sirop-resveratrol', 'bcomplex', 'hepatoliv'),
    'vitc-propolis-echinacea' => array('sirop-echinacea-zinc', 'flusept-propolis', 'd3-2000', 'multi-adulti30', 'sirop-echinacea-patlagina', 'seleniu'),
    'vitc-propolis-polen' => array('flusept-propolis', 'sirop-macese', 'd3-2000', 'bcomplex', 'multi-adulti30', 'sirop-echinacea-zinc'),
    'vitc-propolis-echinacea-zinc' => array('sirop-echinacea-zinc', 'flusept-propolis', 'd3-4000', 'sirop-echinacea-patlagina', 'multi-adulti30', 'seleniu'),
    'vitc-propolis'   => array('flusept-propolis', 'sirop-patlagina-c', 'd3-2000', 'ung-galbenele-propolis20', 'multi-adulti30', 'sirop-echinacea-zinc'),
    'vitc-propolis-miere' => array('flusept-propolis', 'sirop-tusin', 'sirop-patlagina-c', 'd3-2000', 'multi-adulti30', 'sirop-echinacea-zinc'),
    'vitc-gluc10'     => array('vitc-portocala10', 'vitc-zmeura10', 'vitc-lamaie10', 'd3-2000', 'sirop-vitamine-copii', 'calciu-farmaco10'),
    'vitc-gluc120'    => array('vitc-portocala120', 'vitc-zmeura120', 'vitc-lamaie120', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise'),
    'vitc-lamaie10'   => array('vitc-portocala10', 'vitc-zmeura10', 'vitc-struguri10', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise'),
    'vitc-lamaie120'  => array('vitc-portocala120', 'vitc-zmeura120', 'vitc-struguri120', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise'),
    'vitc-portocala10' => array('vitc-zmeura10', 'vitc-lamaie10', 'vitc-struguri10', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-forte'),
    'vitc-portocala120' => array('vitc-zmeura120', 'vitc-lamaie120', 'vitc-struguri120', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-forte'),
    'vitc-struguri10' => array('vitc-zmeura10', 'vitc-portocala10', 'vitc-lamaie10', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise'),
    'vitc-struguri120' => array('vitc-zmeura120', 'vitc-portocala120', 'vitc-lamaie120', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise'),
    'vitc-zmeura10'   => array('vitc-portocala10', 'vitc-struguri10', 'vitc-lamaie10', 'sirop-vitamine-copii', 'd3-2000', 'calciu-d3-caise'),
    'vitc-zmeura120'  => array('vitc-portocala120', 'vitc-struguri120', 'vitc-lamaie120', 'sirop-vitamine-copii', 'd3-2000', 'calciu-d3-caise'),
    'vitc180-20'      => array('vitc180-portocala20', 'vitc180-struguri20', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise', 'multi-teen30'),
    'vitc180-portocala20' => array('vitc180-struguri20', 'vitc180-20', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise', 'multi-teen30'),
    'vitc180-struguri20' => array('vitc180-portocala20', 'vitc180-20', 'd3-2000', 'sirop-vitamine-copii', 'calciu-d3-caise', 'multi-teen30'),
    'vitc1000-soc'    => array('d3-4000', 'seleniu', 'multi-adulti30', 'sirop-echinacea-zinc', 'sirop-resveratrol', 'ginkgo'),
    'vitc300zn30'     => array('d3-2000', 'sirop-echinacea-zinc', 'flusept-propolis', 'multi-adulti30', 'seleniu', 'sirop-macese'),
    'vitc300zn60'     => array('d3-2000', 'sirop-echinacea-zinc', 'flusept-propolis', 'multi-adulti60', 'seleniu', 'sirop-macese'),
    'vitc500-30'      => array('d3-2000', 'multi-adulti30', 'sirop-echinacea-zinc', 'flusept-salvie', 'seleniu', 'camgzn30'),
    'vitc500-60'      => array('d3-2000', 'multi-adulti60', 'sirop-echinacea-zinc', 'flusept-salvie', 'seleniu', 'camgzn60'),
    'd3-2000'         => array('calciu1000-30', 'camgzn30', 'vitc500-30', 'multi-adulti30', 'mgb6', 'seleniu'),
    'd3-4000'         => array('calciu1000-60', 'camgzn60', 'vitc500-60', 'multi-adulti60', 'mgb6', 'seleniu'),
);

/* ---------------------------------------------------------------------------
 * Unelte
 * ------------------------------------------------------------------------ */

function ht_rel_log($message, $level = '')
{
    $prefix = array('ok' => '  + ', 'skip' => '  = ', 'warn' => '  ! ');
    echo (isset($prefix[$level]) ? $prefix[$level] : '') . $message . "\n";
}

/**
 * Titlu normalizat pentru comparatie (fara diferente de diacritice vechi/noi).
 */
function ht_rel_norm($title)
{
    $title = strtr($title, array('ş' => 'ș', 'ţ' => 'ț', 'Ş' => 'Ș', 'Ţ' => 'Ț'));
    $title = preg_replace('/\s+/u', ' ', trim($title));

    return mb_strtolower($title);
}

/** Toate produsele RO (publicate si ciorne), pe SKU si pe titlu. */
function ht_rel_index()
{
    $by_sku = array();
    $by_name = array();
    $query = new WP_Query(array(
        'post_type'      => 'product',
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'lang'           => 'ro',
    ));

    foreach ($query->posts as $id) {
        $product = wc_get_product($id);

        if (!$product) {
            continue;
        }

        if ($product->get_sku()) {
            $by_sku[$product->get_sku()] = (int)$id;
        }

        $by_name[ht_rel_norm($product->get_name())] = (int)$id;
    }

    return array($by_sku, $by_name);
}

/** ID-ul traducerii in limba data, sau 0. */
function ht_rel_translation($id, $lang)
{
    if (!function_exists('pll_get_post_translations')) {
        return 0;
    }

    $translations = pll_get_post_translations($id);

    return isset($translations[$lang]) ? (int)$translations[$lang] : 0;
}

/* ---------------------------------------------------------------------------
 * Rezolvarea aliasurilor
 * ------------------------------------------------------------------------ */

list($ht_by_sku, $ht_by_name) = ht_rel_index();
$ht_ids = array();
$ht_missing = array();

foreach ($ht_catalog as $alias => $key) {
    if (0 === strpos($key, 'name:')) {
        $norm = ht_rel_norm(substr($key, 5));
        $id = isset($ht_by_name[$norm]) ? $ht_by_name[$norm] : 0;
    } else {
        $id = isset($ht_by_sku[$key]) ? $ht_by_sku[$key] : 0;
    }

    if ($id) {
        $ht_ids[$alias] = $id;
    } else {
        $ht_missing[] = $alias . ' (' . $key . ')';
    }
}

if ($ht_missing) {
    ht_rel_log('Produse negasite in catalog:', 'warn');

    foreach ($ht_missing as $row) {
        ht_rel_log('    ' . $row);
    }
}

/* Validare: toate aliasurile din relatii sunt cunoscute, fara auto-referinte */
$ht_errors = array();

foreach (array('upsell' => $ht_upsells, 'cross' => $ht_cross) as $kind => $map) {
    foreach ($map as $alias => $targets) {
        if (!isset($ht_catalog[$alias])) {
            $ht_errors[] = "$kind: alias necunoscut '$alias'";
        }

        foreach ($targets as $target) {
            if (!isset($ht_catalog[$target])) {
                $ht_errors[] = "$kind $alias: tinta necunoscuta '$target'";
            } elseif ($target === $alias) {
                $ht_errors[] = "$kind $alias: se refera la el insusi";
            }
        }

        if (count(array_unique($targets)) !== count($targets)) {
            $ht_errors[] = "$kind $alias: tinte duplicate";
        }

        $limit = ('upsell' === $kind) ? 2 : 8;

        if (count($targets) > $limit) {
            $ht_errors[] = "$kind $alias: peste $limit tinte";
        }
    }
}

if ($ht_errors) {
    ht_rel_log('Harta are erori:', 'warn');

    foreach ($ht_errors as $row) {
        ht_rel_log('    ' . $row);
    }

    exit(1);
}

$ht_without = array_diff(array_keys($ht_catalog), array_keys($ht_cross));

if ($ht_without) {
    ht_rel_log('Fara cross-sell: ' . implode(', ', $ht_without), 'warn');
}

/* ---------------------------------------------------------------------------
 * Scrierea
 * ------------------------------------------------------------------------ */

/**
 * Scrie relatiile pe un produs, intr-o limba.
 *
 * @param int    $id    Produsul.
 * @param int[]  $up    ID-urile upsell (in aceeasi limba).
 * @param int[]  $cross ID-urile cross-sell (in aceeasi limba).
 * @param string $label Etichete pentru consola.
 *
 * @return string 'ok', 'skip' sau 'same'.
 */
function ht_rel_write($id, $up, $cross, $label)
{
    global $ht_force, $ht_dry;

    $product = wc_get_product($id);

    if (!$product) {
        ht_rel_log("$label: produsul #$id nu exista", 'warn');

        return 'skip';
    }

    $old_up = array_map('intval', $product->get_upsell_ids());
    $old_cross = array_map('intval', $product->get_cross_sell_ids());

    if ($old_up === $up && $old_cross === $cross) {
        return 'same';
    }

    if (!$ht_force && ($old_up || $old_cross)) {
        ht_rel_log("$label: are deja relatii (--force ca sa le suprascrii)", 'skip');

        return 'skip';
    }

    if (!$ht_dry) {
        $product->set_upsell_ids($up);
        $product->set_cross_sell_ids($cross);
        $product->save();
    }

    return 'ok';
}

$ht_stats = array('ok' => 0, 'skip' => 0, 'same' => 0);

echo ($ht_dry ? '[dry-run] ' : '') . 'Produse legate: ' . count($ht_ids) . " produse gasite\n";

foreach ($ht_ids as $alias => $ro_id) {
    $up_aliases = isset($ht_upsells[$alias]) ? $ht_upsells[$alias] : array();
    $cross_aliases = isset($ht_cross[$alias]) ? $ht_cross[$alias] : array();

    /* Doar tintele gasite; ordinea din harta se pastreaza */
    $up_ro = array();
    $cross_ro = array();

    foreach ($up_aliases as $target) {
        if (isset($ht_ids[$target])) {
            $up_ro[] = $ht_ids[$target];
        }
    }

    foreach ($cross_aliases as $target) {
        if (isset($ht_ids[$target])) {
            $cross_ro[] = $ht_ids[$target];
        }
    }

    $langs = array('ro' => $ro_id);
    $ru_id = ht_rel_translation($ro_id, 'ru');

    if ($ru_id) {
        $langs['ru'] = $ru_id;
    }

    foreach ($langs as $lang => $id) {
        if ('ro' === $lang) {
            $up = $up_ro;
            $cross = $cross_ro;
        } else {
            $up = array_values(array_filter(array_map(function ($rid) use ($lang) {
                return ht_rel_translation($rid, $lang);
            }, $up_ro)));
            $cross = array_values(array_filter(array_map(function ($rid) use ($lang) {
                return ht_rel_translation($rid, $lang);
            }, $cross_ro)));
        }

        $result = ht_rel_write($id, $up, $cross, "$alias [$lang #$id]");
        $ht_stats[$result]++;

        if ('ok' === $result) {
            ht_rel_log(sprintf(
                '%s [%s #%d]: upsell %s | cross %s',
                $alias,
                $lang,
                $id,
                $up ? implode(',', $up) : '-',
                $cross ? implode(',', $cross) : '-'
            ), 'ok');
        }
    }
}

printf(
    "\n%s scrise: %d, neschimbate: %d, sarite: %d\n",
    $ht_dry ? '[dry-run]' : 'Gata.',
    $ht_stats['ok'],
    $ht_stats['same'],
    $ht_stats['skip']
);
