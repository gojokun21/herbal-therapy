<?php
/**
 * Teste pentru inc/permalinks.php - functiile pure din spatele adreselor
 * scurte de magazin: scoaterea bazei din adrese, regulile de rescriere per
 * categorie si tinta redirectarilor 301.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class PermalinksTest extends TestCase
{
    /* -----------------------------------------------------------------------
     * ht_strip_url_base()
     * -------------------------------------------------------------------- */

    public function testScoateBazaDeCategorieDinAdresaRomaneasca()
    {
        $this->assertSame(
            'https://example.test/siropuri/',
            ht_strip_url_base('https://example.test/product-category/siropuri/', 'product-category')
        );
    }

    public function testScoateBazaPastrandPrefixulDeLimba()
    {
        /* defectul initial: Rank Math lipea bucatile in /rusiropy/ */
        $this->assertSame(
            'https://example.test/ru/siropy/',
            ht_strip_url_base('https://example.test/ru/product-category/siropy/', 'product-category')
        );
    }

    public function testScoateBazaDeProdus()
    {
        $this->assertSame(
            'https://example.test/ru/sirop-imunitate/',
            ht_strip_url_base('https://example.test/ru/product/sirop-imunitate/', 'product')
        );
    }

    public function testNuAtingeUnSlugCareDoarContineBaza()
    {
        $this->assertSame(
            'https://example.test/product-lovers/',
            ht_strip_url_base('https://example.test/product-lovers/', 'product')
        );
    }

    public function testScoateDoarPrimulSegmentPotrivit()
    {
        $this->assertSame(
            'https://example.test/product/x/',
            ht_strip_url_base('https://example.test/product/product/x/', 'product')
        );
    }

    public function testBazaGoalaLasaAdresaNeatinsa()
    {
        $this->assertSame(
            'https://example.test/product/x/',
            ht_strip_url_base('https://example.test/product/x/', '')
        );
    }

    public function testIgnoraSlashurileDeCapatAleBazei()
    {
        $this->assertSame(
            'https://example.test/x/',
            ht_strip_url_base('https://example.test/product/x/', '/product/')
        );
    }

    /* -----------------------------------------------------------------------
     * ht_permalink_base_is_plain()
     * -------------------------------------------------------------------- */

    public function testBazaCuPlaceholderNuEPlata()
    {
        $this->assertFalse(ht_permalink_base_is_plain('shop/%product_cat%'));
        $this->assertFalse(ht_permalink_base_is_plain(''));
        $this->assertTrue(ht_permalink_base_is_plain('product'));
    }

    /* -----------------------------------------------------------------------
     * ht_category_base_free_rules()
     * -------------------------------------------------------------------- */

    public function testGenereazaSetulCompletDeReguliPentruUnTermen()
    {
        $rules = ht_category_base_free_rules(array('siropy' => 'siropy'));

        $this->assertSame(array(
            'siropy/?$'                               => 'index.php?product_cat=siropy',
            'siropy/page/([0-9]{1,})/?$'              => 'index.php?product_cat=siropy&paged=$matches[1]',
            'siropy/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?product_cat=siropy&feed=$matches[1]',
            'siropy/(feed|rdf|rss|rss2|atom)/?$'      => 'index.php?product_cat=siropy&feed=$matches[1]',
        ), $rules);
    }

    public function testCaileAdanciIntraInainteaParintelui()
    {
        $rules = ht_category_base_free_rules(array(
            'ingrijire'       => 'ingrijire',
            'ingrijire/creme' => 'creme',
        ));

        $keys = array_keys($rules);

        $this->assertSame('ingrijire/creme/?$', $keys[0]);
        /* interogarea foloseste slug-ul frunzei, nu calea */
        $this->assertSame('index.php?product_cat=creme', $rules['ingrijire/creme/?$']);
    }

    public function testListaGoalaDaReguliGoale()
    {
        $this->assertSame(array(), ht_category_base_free_rules(array()));
    }

    /* -----------------------------------------------------------------------
     * ht_base_redirect_target()
     * -------------------------------------------------------------------- */

    public function testRedirectareaPastreazaLimbaSiPaginarea()
    {
        $this->assertSame(
            '/ru/siropy/page/2/',
            ht_base_redirect_target('/ru/product-category/siropy/page/2/', 'product-category')
        );
    }

    public function testRedirectareaPastreazaQueryString()
    {
        $this->assertSame(
            '/siropuri/?orderby=price&pret=10',
            ht_base_redirect_target('/product-category/siropuri/?orderby=price&pret=10', 'product-category')
        );
    }

    public function testNuRedirectioneazaCandBazaEDoarInQueryString()
    {
        $this->assertSame(
            '',
            ht_base_redirect_target('/siropuri/?ref=/product-category/x/', 'product-category')
        );
    }

    public function testNuRedirectioneazaOAdresaDejaScurta()
    {
        $this->assertSame('', ht_base_redirect_target('/ru/siropy/', 'product-category'));
        $this->assertSame('', ht_base_redirect_target('/sirop-imunitate/', 'product'));
    }
}
