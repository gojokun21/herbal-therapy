<?php
/**
 * Teste pentru inc/redirects.php - functiile pure din spatele redirecturilor
 * 301 de pe adresele vechi Shopify: normalizarea caii, alegerea limbii,
 * tintele de rezerva si pastrarea parametrilor de urmarire.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class RedirectsTest extends TestCase
{
    /**
     * O harta mica, in forma scrisa de bin/redirects/build-map.php.
     */
    private function map()
    {
        return array(
            'fallback' => array(
                'shop'    => array('ro' => '/produse/', 'ru' => '/ru/produkty/'),
                'home'    => array('ro' => '/', 'ru' => '/ru/'),
                'blog'    => array('ro' => '/blog/', 'ru' => '/ru/blog/'),
                'cart'    => array('ro' => '/cos/', 'ru' => '/ru/cart/'),
                'account' => array('ro' => '/contul-meu/', 'ru' => '/ru/my-account/'),
                'terms'   => array('ro' => '/termenii-si-conditiile/'),
            ),
            'paths' => array(
                '/products/sirop-imuno-tusin-herbal-therapy-200-ml' => array('ro' => '/sirop-imuno-tusin-200-ml/', 'ru' => '/ru/sirop-imuno-tusin-200-ml/'),
                '/products/șampon-cu-extract-de-urzica-500-ml'      => array('ro' => '/sampon-urzica-300-ml/'),
                '/collections/siropuri-1'                          => array('ro' => '/siropuri/', 'ru' => '/ru/siropy/'),
                '/collections/șampoane-geluri-de-duș-sapunuri'     => array('ro' => '/produse/?cat=sampoane,geluri-de-dus', 'ru' => '/ru/produkty/?cat=shampuni,geli-dlya-dusha'),
                '/pages/contact'                                   => array('ro' => '/contact/', 'ru' => '/ru/kontakty/'),
                '/policies/privacy-policy'                         => array('ro' => '/politica-de-confidentialitate/'),
                '/blogs/vitamine-si-suplimente'                    => array('ro' => '/category/nutritie-si-suplimente/', 'ru' => '/ru/category/pitanie-i-dobavki/'),
                '/blogs/vitamine-si-suplimente/astenia-de-primavara' => array('ro' => '/cum-facem-fata-asteniei-de-primavara/', 'ru' => '/ru/kak-spravitsya-s-vesenney-asteniey/'),
            ),
        );
    }

    /* -----------------------------------------------------------------------
     * ht_legacy_normalize_path()
     * -------------------------------------------------------------------- */

    public function testNormalizareaDecodeazaSiScoateSlashulFinal()
    {
        $this->assertSame(
            '/products/șampon-cu-extract-de-urzica-500-ml',
            ht_legacy_normalize_path('/products/%C8%99ampon-cu-extract-de-urzica-500-ml/')
        );
    }

    public function testNormalizareaFaceLitereMiciSiVirgulaDinSedila()
    {
        /* ş (U+015F) si Ţ (U+0162) devin ș si ț */
        $this->assertSame(
            '/products/șampon-țara',
            ht_legacy_normalize_path("/Products/\xC5\x9Fampon-\xC5\xA2ara")
        );
    }

    public function testNormalizareaScoateSufixeleShopify()
    {
        $this->assertSame('/products/x', ht_legacy_normalize_path('/products/x.json'));
        $this->assertSame('/collections/x', ht_legacy_normalize_path('/collections/x.atom'));
        $this->assertSame('/products/x', ht_legacy_normalize_path('//products///x/'));
    }

    public function testRadacinaRamaneSlash()
    {
        $this->assertSame('/', ht_legacy_normalize_path('/'));
        $this->assertSame('/', ht_legacy_normalize_path(''));
    }

    /* -----------------------------------------------------------------------
     * ht_legacy_redirect_target(): produse
     * -------------------------------------------------------------------- */

    public function testProdusCunoscutInRomana()
    {
        $this->assertSame(
            '/sirop-imuno-tusin-200-ml/',
            ht_legacy_redirect_target('/products/sirop-imuno-tusin-herbal-therapy-200-ml', $this->map())
        );
    }

    public function testProdusCunoscutInRusa()
    {
        $this->assertSame(
            '/ru/sirop-imuno-tusin-200-ml/',
            ht_legacy_redirect_target('/ru/products/sirop-imuno-tusin-herbal-therapy-200-ml/', $this->map())
        );
    }

    public function testEnglezaMergePeRomana()
    {
        $this->assertSame(
            '/sirop-imuno-tusin-200-ml/',
            ht_legacy_redirect_target('/en/products/sirop-imuno-tusin-herbal-therapy-200-ml', $this->map())
        );
    }

    public function testProdusCuDiacriticeCodateInAdresa()
    {
        $this->assertSame(
            '/sampon-urzica-300-ml/',
            ht_legacy_redirect_target('/products/%C8%99ampon-cu-extract-de-urzica-500-ml?variant=123', $this->map())
        );
    }

    public function testRusaFaraTraducereCadePeRomana()
    {
        $this->assertSame(
            '/sampon-urzica-300-ml/',
            ht_legacy_redirect_target('/ru/products/șampon-cu-extract-de-urzica-500-ml', $this->map())
        );
    }

    public function testProdusNecunoscutMergeLaMagazin()
    {
        $this->assertSame('/produse/', ht_legacy_redirect_target('/products/nu-exista', $this->map()));
        $this->assertSame('/ru/produkty/', ht_legacy_redirect_target('/ru/products/nu-exista', $this->map()));
    }

    public function testProdusDinColectieMergeLaProdus()
    {
        $this->assertSame(
            '/sirop-imuno-tusin-200-ml/',
            ht_legacy_redirect_target('/collections/siropuri-1/products/sirop-imuno-tusin-herbal-therapy-200-ml', $this->map())
        );
    }

    public function testProdusNecunoscutDinColectieCunoscutaMergeLaColectie()
    {
        $this->assertSame(
            '/siropuri/',
            ht_legacy_redirect_target('/collections/siropuri-1/products/nu-exista', $this->map())
        );
    }

    /* -----------------------------------------------------------------------
     * colectii, pagini, politici, blog
     * -------------------------------------------------------------------- */

    public function testColectieCunoscuta()
    {
        $this->assertSame('/ru/siropy/', ht_legacy_redirect_target('/ru/collections/siropuri-1', $this->map()));
        $this->assertSame('/siropuri/', ht_legacy_redirect_target('/collections/siropuri-1/tag-x', $this->map()));
    }

    public function testColectieCuFiltruDeCategoriiPastreazaQueryulTintei()
    {
        $this->assertSame(
            '/produse/?cat=sampoane,geluri-de-dus',
            ht_legacy_redirect_target('/collections/%C8%99ampoane-geluri-de-du%C8%99-sapunuri?page=2', $this->map())
        );
        $this->assertSame(
            '/ru/produkty/?cat=shampuni,geli-dlya-dusha&utm_source=x',
            ht_legacy_redirect_target('/ru/collections/șampoane-geluri-de-duș-sapunuri?utm_source=x', $this->map())
        );
    }

    public function testIndexulDeColectiiSiColectieNecunoscutaMergLaMagazin()
    {
        $this->assertSame('/produse/', ht_legacy_redirect_target('/collections', $this->map()));
        $this->assertSame('/produse/', ht_legacy_redirect_target('/collections/nu-exista', $this->map()));
    }

    public function testPaginiSiPolitici()
    {
        $this->assertSame('/ru/kontakty/', ht_legacy_redirect_target('/ru/pages/contact', $this->map()));
        $this->assertSame('/', ht_legacy_redirect_target('/pages/nu-exista', $this->map()));
        $this->assertSame('/politica-de-confidentialitate/', ht_legacy_redirect_target('/policies/privacy-policy', $this->map()));
        $this->assertSame('/termenii-si-conditiile/', ht_legacy_redirect_target('/ru/policies/shipping-policy', $this->map()));
    }

    public function testBlog()
    {
        $this->assertSame(
            '/ru/kak-spravitsya-s-vesenney-asteniey/',
            ht_legacy_redirect_target('/ru/blogs/vitamine-si-suplimente/astenia-de-primavara', $this->map())
        );
        /* articol necunoscut sau /tagged/ -> indexul blogului respectiv */
        $this->assertSame(
            '/category/nutritie-si-suplimente/',
            ht_legacy_redirect_target('/blogs/vitamine-si-suplimente/tagged/vitamine', $this->map())
        );
        $this->assertSame('/blog/', ht_legacy_redirect_target('/blogs/news', $this->map()));
        $this->assertSame('/ru/blog/', ht_legacy_redirect_target('/ru/blogs', $this->map()));
    }

    /* -----------------------------------------------------------------------
     * cos, cont, cautare, radacina
     * -------------------------------------------------------------------- */

    public function testCosContSiCheckoutShopify()
    {
        $this->assertSame('/cos/', ht_legacy_redirect_target('/cart', $this->map()));
        $this->assertSame('/cos/', ht_legacy_redirect_target('/checkouts/cn/abc123', $this->map()));
        $this->assertSame('/ru/my-account/', ht_legacy_redirect_target('/ru/account/login', $this->map()));
        $this->assertSame('/contul-meu/', ht_legacy_redirect_target('/account/orders/1', $this->map()));
    }

    public function testNuSeRedirecteazaCatreSineInsusi()
    {
        /* /ru/cart/ e chiar pagina cosului pe rusa */
        $this->assertSame('', ht_legacy_redirect_target('/ru/cart/', $this->map()));
        $this->assertSame('', ht_legacy_redirect_target('/ru/cart', $this->map()));
    }

    public function testCautareaTreceInParametrulS()
    {
        $this->assertSame('/?s=sirop%20tuse', ht_legacy_redirect_target('/search?q=sirop+tuse&type=product', $this->map()));
        $this->assertSame('/ru/?s=sirop', ht_legacy_redirect_target('/ru/search?q=sirop', $this->map()));
        $this->assertSame('/', ht_legacy_redirect_target('/search', $this->map()));
    }

    public function testRadacinaEnglezaMergeAcasaIarRestulRadacinilorNu()
    {
        $this->assertSame('/', ht_legacy_redirect_target('/en', $this->map()));
        $this->assertSame('/', ht_legacy_redirect_target('/en/', $this->map()));
        $this->assertSame('', ht_legacy_redirect_target('/', $this->map()));
        $this->assertSame('', ht_legacy_redirect_target('/ru/', $this->map()));
    }

    public function testAdreseleNoiNuSuntAtinse()
    {
        $this->assertSame('', ht_legacy_redirect_target('/siropuri/', $this->map()));
        $this->assertSame('', ht_legacy_redirect_target('/ru/siropy/', $this->map()));
        $this->assertSame('', ht_legacy_redirect_target('/produse/?cat=siropuri', $this->map()));
        $this->assertSame('', ht_legacy_redirect_target('/checkout/', $this->map()));
        $this->assertSame('', ht_legacy_redirect_target('/productsx/y', $this->map()));
    }

    /* -----------------------------------------------------------------------
     * parametrii de urmarire
     * -------------------------------------------------------------------- */

    public function testSePastreazaDoarParametriiDeUrmarire()
    {
        $this->assertSame(
            '/sirop-imuno-tusin-200-ml/?utm_source=fb&utm_campaign=toamna&fbclid=abc',
            ht_legacy_redirect_target(
                '/products/sirop-imuno-tusin-herbal-therapy-200-ml?variant=1&utm_source=fb&_pos=2&utm_campaign=toamna&fbclid=abc',
                $this->map()
            )
        );
    }

    public function testKeepQueryCodeazaValorile()
    {
        $this->assertSame('utm_source=a%20b', ht_legacy_keep_query('utm_source=a+b&variant=3'));
        $this->assertSame('', ht_legacy_keep_query('variant=3'));
        $this->assertSame('s=x', ht_legacy_keep_query('', array('s' => 'x')));
    }

    /* -----------------------------------------------------------------------
     * ht_legacy_is_candidate()
     * -------------------------------------------------------------------- */

    public function testCandidatii()
    {
        $this->assertTrue(ht_legacy_is_candidate('/products/x'));
        $this->assertTrue(ht_legacy_is_candidate('/ru/collections/x?page=2'));
        $this->assertTrue(ht_legacy_is_candidate('/en/'));
        $this->assertTrue(ht_legacy_is_candidate('/en'));
        $this->assertTrue(ht_legacy_is_candidate('/collections'));
        $this->assertTrue(ht_legacy_is_candidate('/products/x.json'));
        $this->assertTrue(ht_legacy_is_candidate('/Products/X'));

        $this->assertFalse(ht_legacy_is_candidate('/'));
        $this->assertFalse(ht_legacy_is_candidate('/ru/'));
        $this->assertFalse(ht_legacy_is_candidate('/siropuri/'));
        $this->assertFalse(ht_legacy_is_candidate('/checkout/'));
        $this->assertFalse(ht_legacy_is_candidate('/productsx/y'));
        $this->assertFalse(ht_legacy_is_candidate('/energie/'));
    }
}
