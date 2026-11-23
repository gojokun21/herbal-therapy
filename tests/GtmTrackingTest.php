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
        unset($GLOBALS['wp_filter'], $GLOBALS['product']);
    }

    /**
     * Imita ListTracking::single_add_to_cart_tracking() inregistrat pe
     * 'woocommerce_after_add_to_cart_button' (WP_Hook->callbacks).
     */
    private function fakeGtm4wpSingle(array $extra = array())
    {
        $tracker = new GTM4WP_Fake_ListTracking();
        $callbacks = array(10 => $extra + array(
            'gtm4wp' => array('function' => array($tracker, 'single_add_to_cart_tracking'), 'accepted_args' => 1),
        ));
        $GLOBALS['wp_filter'] = array(
            'woocommerce_after_add_to_cart_button' => (object)array('callbacks' => $callbacks),
        );

        return $tracker;
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

    public function testPachetulIaDateleDinCallbackulGtm4wpDePePaginaProdusului()
    {
        /* filtrul de bucla nu raspunde (produs ne-simplu), callback-ul single da */
        $this->fakeGtm4wpSingle();

        $json = ht_gtm_item_json($this->product(900004));

        $this->assertSame(array('item_id' => 'PACHET-900004', 'price' => 260.8), json_decode($json, true));
    }

    public function testCallbackulSingleNuDeclanseazaAlteFunctiiDePeHook()
    {
        $other = 0;
        $this->fakeGtm4wpSingle(array(
            'other' => array('function' => function () use (&$other) {
                $other++;
                echo '<p>alt plugin</p>';
            }, 'accepted_args' => 1),
        ));

        ht_gtm_item_json($this->product(900005));

        $this->assertSame(0, $other);
    }

    public function testRestaureazaGlobalProduct()
    {
        $this->fakeGtm4wpSingle();
        $GLOBALS['product'] = 'pagina-curenta';

        ht_gtm_item_json($this->product(900006));

        $this->assertSame('pagina-curenta', $GLOBALS['product']);
    }

    public function testFaraGtm4wpPeHookNuIntoarceNimic()
    {
        $this->assertSame('', ht_gtm4wp_single_markup($this->product(900007)));
    }

    public function testAtributulEsteEscapat()
    {
        $attr = ht_gtm_item_attr('{"item_id":"1","item_name":"A \"B\" & C"}');

        $this->assertSame(' data-ht-gtm-item="{&quot;item_id&quot;:&quot;1&quot;,&quot;item_name&quot;:&quot;A \&quot;B\&quot; &amp; C&quot;}"', $attr);
        $this->assertSame('', ht_gtm_item_attr(''));
    }
}

/**
 * Imitatia ListTracking din GTM4WP: scrie span-ul pentru global $product.
 */
final class GTM4WP_Fake_ListTracking
{
    public function single_add_to_cart_tracking()
    {
        global $product;

        $item = array('item_id' => 'PACHET-' . $product->get_id(), 'price' => 260.8);

        echo '<span class="gtm4wp_single_productdata" style="display:none; visibility:hidden;" data-gtm4wp_product_data="' . esc_attr(json_encode($item)) . '"></span>' . "\n";
    }
}
