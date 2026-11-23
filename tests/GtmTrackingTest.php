<?php
/**
 * Teste pentru inc/gtm-tracking.php - datele produsului pentru add_to_cart,
 * luate din atributul pe care GTM4WP il pune prin 'woocommerce_loop_add_to_cart_link'.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class GtmTrackingTest extends TestCase
{
    /** Cate apeluri a primit filtrul-imitatie GTM4WP. */
    private $calls = 0;

    protected function setUp(): void
    {
        HT_Test_Wp::reset();
        $this->calls = 0;
    }

    protected function tearDown(): void
    {
        HT_Test_Wp::reset();
    }

    /**
     * Imita ListTracking::add_to_cart_link_filter() din GTM4WP 2.1.1.
     */
    private function fakeGtm4wp(array $item)
    {
        add_filter('woocommerce_loop_add_to_cart_link', function ($link, $product = null) use ($item) {
            $this->calls++;
            $pos = strpos($link, '<a ');

            return substr_replace(
                $link,
                '<a data-gtm4wp_product_data="' . esc_attr(json_encode($item)) . '" ',
                $pos,
                3
            );
        }, 10, 2);
    }

    private function product($id)
    {
        return new class($id) {
            private $id;

            public function __construct($id)
            {
                $this->id = $id;
            }

            public function get_id()
            {
                return $this->id;
            }
        };
    }

    /* -----------------------------------------------------------------------
     * ht_gtm_item_from_link()
     * -------------------------------------------------------------------- */

    public function testScoateJsonulDinAtributulGtm4wp()
    {
        $item = array('item_id' => '1847', 'item_name' => 'Bom-Benghe cremă "30 ml"', 'sku' => '6427321002461', 'price' => 49.3);
        $link = '<a data-gtm4wp_product_data="' . esc_attr(json_encode($item)) . '" href="#">';

        $this->assertSame($item, json_decode(ht_gtm_item_from_link($link), true));
    }

    public function testFaraAtributIntoarceSirGol()
    {
        $this->assertSame('', ht_gtm_item_from_link('<a href="#">'));
        $this->assertSame('', ht_gtm_item_from_link(null));
    }

    public function testRespingeJsonInvalidSauFaraItemId()
    {
        $this->assertSame('', ht_gtm_item_from_link('<a data-gtm4wp_product_data="{nu e json" href="#">'));
        $this->assertSame('', ht_gtm_item_from_link('<a data-gtm4wp_product_data="' . esc_attr('{"item_name":"x"}') . '" href="#">'));
        $this->assertSame('', ht_gtm_item_from_link('<a data-gtm4wp_product_data="' . esc_attr('{"item_id":""}') . '" href="#">'));
    }

    public function testNuConfundaAtributeCuNumeAsemanator()
    {
        $this->assertSame('', ht_gtm_item_from_link('<a data-x-gtm4wp_product_data="' . esc_attr('{"item_id":"1"}') . '" href="#">'));
    }

    /* -----------------------------------------------------------------------
     * ht_gtm_item_json() / ht_gtm_item_attr()
     * -------------------------------------------------------------------- */

    public function testIaDateleDeLaGtm4wpPrinFiltru()
    {
        $this->fakeGtm4wp(array('item_id' => '4840257007188', 'price' => 12.5));

        $json = ht_gtm_item_json($this->product(900001));

        $this->assertSame(array('item_id' => '4840257007188', 'price' => 12.5), json_decode($json, true));
    }

    public function testFiltrulRuleazaOSinguraDataPeProdus()
    {
        $this->fakeGtm4wp(array('item_id' => '7'));

        ht_gtm_item_json($this->product(900002));
        ht_gtm_item_json($this->product(900002));

        $this->assertSame(1, $this->calls);
    }

    public function testFaraDateDeLaGtm4wpNuPuneAtribut()
    {
        /* produs variabil/pachet: GTM4WP lasa link-ul neatins */
        $this->assertSame('', ht_gtm_item_attr($this->product(900003)));
        $this->assertSame('', ht_gtm_item_json(null));
    }

    public function testAtributulEsteEscapat()
    {
        $attr = ht_gtm_item_attr('{"item_id":"1","item_name":"A \"B\" & C"}');

        $this->assertSame(' data-ht-gtm-item="{&quot;item_id&quot;:&quot;1&quot;,&quot;item_name&quot;:&quot;A \&quot;B\&quot; &amp; C&quot;}"', $attr);
        $this->assertSame('', ht_gtm_item_attr(''));
    }
}
