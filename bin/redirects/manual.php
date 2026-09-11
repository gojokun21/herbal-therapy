<?php
/**
 * Potrivirile facute de mana pentru harta de redirecturi Shopify -> WooCommerce.
 *
 * Cheia e bucata de adresa veche (fara domeniu si fara prefixul de limba),
 * valoarea e tinta, in notatia inteleasa de bin/redirects/build-map.php:
 *
 *   product:{slug}        produsul romanesc cu slug-ul dat (ruseste prin Polylang)
 *   product_cat:{slug}    categoria de produse
 *   category:{slug}       categoria de articole
 *   page:{slug}           o pagina
 *   post:{slug}           un articol
 *   shop                  pagina magazinului (/produse/)
 *   shop?cat=a,b          magazinul filtrat pe categoriile date (slug-uri RO)
 *   shop?orderby=x        magazinul sortat
 *   home                  prima pagina
 *
 * Produsele care au un corespondent clar in WooCommerce nu apar aici: build-map
 * le gaseste singur dupa SKU / denumire (campurile wp_sku / wp_title din
 * shopify.json). Aici sunt doar cele fara corespondent (pachete promotionale,
 * cadouri, produse tehnice, produse care nu mai exista) si categoriile,
 * paginile si blogurile, care au alta structura pe site-ul nou.
 *
 * @package Herbal_Therapy
 */

return array(

    /* --- produse Shopify fara corespondent 1:1 in WooCommerce ------------ */
    '/products/unguent-cu-radacina-de-tataneasa-regenerare-rapida-vanatai-și-entorse-50-ml' => 'product:unguent-cu-radacina-de-tataneasa-20-ml',
    '/products/unguent-cu-radacina-de-spanz-ameliorarea-durerilor-de-spate-și-tensiunii-musculare-50-ml' => 'product:unguent-cu-radacina-de-spanz-20-ml',
    '/products/șampon-cu-extract-de-urzica-500-ml' => 'product:sampon-cu-extracte-de-urzica-si-immortelle-ulei-de-canepa-300-ml',
    '/products/șampon-cu-extract-de-aloe-vera-si-ulei-de-eucalipt-500-ml' => 'product_cat:sampoane',
    '/products/calciu-cu-vitamina-d3-aroma-de-caise-kids-x-30-cpr-calciu-250mg-sub-forma-de-carbonat-de-calciu-625-mg-vit-d3-cholecalciferol-5ug' => 'product:calciu-d3-cu-aroma-de-caise-n60',
    '/products/calciu-vitamina-d3-forte-aroma-portocala-x30-cpr-calciu-elementar-500mg-62-5-nr-vitamina-d3-colecalciferol-5ug-100-vnr' => 'product:calciu-d3-forte-cu-aroma-de-portocala-n60',
    '/products/crom-picolinat-200-µg-30-capsule' => 'product_cat:vitamine-si-suplimente',
    '/products/zinc-picolinat-25-mg-30-capsule' => 'product_cat:vitamine-si-suplimente',
    '/products/vitamina-a-10-000-ui-30-capsule' => 'product_cat:vitamine-si-suplimente',
    '/products/sirop-imuno-lemn-dulce-și-menta-200-ml-herbal-therapy' => 'product_cat:siropuri',
    '/products/ulei-camforat-herbal-therapy-200-ml' => 'product_cat:uleiuri',
    '/products/ulei-de-parafina-herbal-therapy-100-ml' => 'product_cat:uleiuri',
    '/products/🎁-cadou-gel-de-dus-catifelare-și-hidratare-extract-de-immortelle-și-ulei-de-canepa-500-ml' => 'product_cat:geluri-de-dus',
    '/products/🎁-cadou-sapun-lichid-catifelare-și-hidratare-extract-de-mușețel-și-uleiuri-naturale-500-ml' => 'product_cat:sapun-lichid',
    '/products/asigura-comanda-impotriva-furtului-sau-pierderii' => 'shop',
    '/products/pickup-in-store' => 'shop',

    /* pachetele promotionale nu exista pe site-ul nou -> pagina de reduceri */
    '/products/pachet-pentru-bunici-imunitate-si-sanatate-pentru-sarbatori-linistite' => 'page:reduceri',
    '/products/pachet-pentru-bunici-ingrijire-pentru-picioare-grele' => 'page:reduceri',
    '/products/pachet-pentru-bunici-pentru-mușchi-și-articulații' => 'page:reduceri',
    '/products/pachet-pentru-bunici-sarbatori-fara-dureri-cu-remedii-naturale' => 'page:reduceri',
    '/products/pachet-pentru-ea-cadoul-perfect-pentru-piele-sanatoasa' => 'page:reduceri',
    '/products/pachet-pentru-ea-pentru-un-ten-sanatos' => 'page:reduceri',
    '/products/pachet-pentru-mamici-sarbatori-linistite-cu-ingrijire-completa-pentru-cei-mici' => 'page:reduceri',
    '/products/pachet-promotionaltoner-facial-si-balsam-de-buze-cu-musetel' => 'page:reduceri',
    '/products/pachet-promoțional-8-martie' => 'page:reduceri',
    '/products/pachet-unisex-ingrijire-completa-pentru-ten-acneic' => 'page:reduceri',
    '/products/pachet-unisex-pentru-ingrijire-completa-curațare-hidratare' => 'page:reduceri',
    '/products/pachet-unisex-pentru-sanatate-bunastare-energie-claritate-relaxare' => 'page:reduceri',
    '/products/pentru-ea-pachet-hidratant-regenerant' => 'page:reduceri',

    /* --- colectii -> categorii ------------------------------------------- */
    '/collections/all' => 'shop',
    '/collections/colectie' => 'shop',
    '/collections/toate-produsele' => 'shop',
    '/collections/produse-copii' => 'shop',
    '/collections/best-sellers' => 'shop?orderby=popularity',
    '/collections/creme-unguente-și-loțiuni-1' => 'product_cat:unguente',
    '/collections/oferte-speciale' => 'page:reduceri',
    '/collections/promotii' => 'page:reduceri',
    '/collections/produse-cosmetice' => 'product_cat:cosmetica-medicala',
    '/collections/siropuri-1' => 'product_cat:siropuri',
    '/collections/uleiuri-cosmetice-1' => 'product_cat:uleiuri',
    '/collections/vitamine-și-minerale' => 'product_cat:vitamine-si-suplimente',
    '/collections/șampoane-geluri-de-duș-sapunuri' => 'shop?cat=sampoane,geluri-de-dus,sapun-lichid',

    /* --- pagini ---------------------------------------------------------- */
    '/pages/blog' => 'page:blog',
    '/pages/contact' => 'page:contact',
    '/pages/despre-noi' => 'page:despre-noi',
    '/pages/termeni-și-condiții' => 'page:termenii-si-conditiile',
    '/pages/politica-de-confidentialitate' => 'page:politica-de-confidentialitate',
    '/pages/prelucrarea-datelor-cu-caracter-personal' => 'page:politica-de-confidentialitate',
    '/pages/politica-cookie' => 'page:politica-de-confidentialitate',
    '/pages/politica-de-livrare-și-retur' => 'page:termenii-si-conditiile',
    '/pages/formular-returnare-produs' => 'page:termenii-si-conditiile',
    '/pages/modalitați-de-plata' => 'page:termenii-si-conditiile',
    '/pages/intrebari-frecvente' => 'page:contact',
    '/pages/program-livrare-sarbatori' => 'page:contact',
    '/pages/program-de-fidelizare' => 'page:despre-noi',
    '/pages/produse-copii' => 'shop',
    '/pages/catalog-pdf' => 'shop',
    '/pages/collection-bundles' => 'page:reduceri',
    '/pages/reviews' => 'home',
    '/pages/wishlist' => 'page:favorite',
    '/pages/shared-wishlists' => 'page:favorite',

    /* --- politicile Shopify --------------------------------------------- */
    '/policies/contact-information' => 'page:contact',
    '/policies/privacy-policy' => 'page:politica-de-confidentialitate',
    '/policies/refund-policy' => 'page:termenii-si-conditiile',
    '/policies/shipping-policy' => 'page:termenii-si-conditiile',
    '/policies/terms-of-service' => 'page:termenii-si-conditiile',

    /* --- blog: indexurile de blog -> categorii de articole --------------- */
    '/blogs/news' => 'page:blog',
    '/blogs/dureri-reumatice-și-musculare' => 'page:blog',
    '/blogs/ingrijirea-tenului' => 'category:ingrijirea-tenului',
    '/blogs/sfaturi-si-solutii-pentru-varice' => 'category:sfaturi-pentru-un-stil-de-viata-sanatos',
    '/blogs/vitamine-si-suplimente' => 'category:nutritie-si-suplimente',

    /* articolele se potrivesc singure dupa slug; doar acesta are alt slug */
    '/blogs/vitamine-si-suplimente/astenia-de-primavara' => 'post:cum-facem-fata-asteniei-de-primavara',
);
