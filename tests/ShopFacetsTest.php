<?php
/**
 * Testele nucleului pur de calcul al contoarelor (inc/shop-facets.php).
 *
 * Regula testata: numarul de langa o optiune spune cate produse ar ramane
 * daca ai bifa-o, tinand cont de celelalte grupuri, dar nu de grupul ei.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class ShopFacetsTest extends TestCase
{
    /**
     * @param array $selection Filtrele active.
     * @param array|null $rows Randurile; implicit tot catalogul.
     *
     * @return array
     */
    private function facets(array $selection = array(), array $rows = null)
    {
        return ht_shop_facet_counts(
            null === $rows ? HT_Test_Fixtures::rows() : $rows,
            $selection,
            HT_Test_Fixtures::config()
        );
    }

    /* ---- fara filtre ---------------------------------------------------- */

    public function test_no_filters_counts_every_group_over_the_whole_listing()
    {
        $facets = $this->facets();

        $this->assertSame(11, $facets['total']);
        $this->assertSame(600.0, $facets['max']);

        $this->assertSame(array('0-200' => 9, '200-500' => 3, '500-1000' => 1, '1000-2000' => 0), $facets['price']);
        $this->assertSame(array('instock' => 8, 'outofstock' => 3, 'new' => 5), $facets['stock']);
        $this->assertSame(array(
            HT_Test_Fixtures::CAT_SIROPURI      => 4,
            HT_Test_Fixtures::CAT_UNGUENTE      => 4,
            HT_Test_Fixtures::CAT_VITAMINE      => 3,
            HT_Test_Fixtures::CAT_VITAMINE_COPII => 1,
        ), $facets['cat']);
    }

    public function test_empty_listing_yields_zeroes_not_errors()
    {
        $facets = $this->facets(array(), array());

        $this->assertSame(0, $facets['total']);
        $this->assertSame(0.0, $facets['max']);
        $this->assertSame(array(), $facets['cat']);
        $this->assertSame(array('0-200' => 0, '200-500' => 0, '500-1000' => 0, '1000-2000' => 0), $facets['price']);
        $this->assertSame(array('instock' => 0, 'outofstock' => 0, 'new' => 0), $facets['stock']);
    }

    public function test_missing_config_groups_are_tolerated()
    {
        $facets = ht_shop_facet_counts(HT_Test_Fixtures::rows(), array(), array());

        $this->assertSame(array(), $facets['price']);
        $this->assertSame(array(), $facets['stock']);
        $this->assertSame(11, $facets['total']);
    }

    /* ---- categoria bifata ----------------------------------------------- */

    public function test_selected_category_narrows_price_and_stock_but_not_other_categories()
    {
        $facets = $this->facets(array('cat' => array(HT_Test_Fixtures::CAT_SIROPURI)));

        $this->assertSame(4, $facets['total']);

        /* grupul propriu ramane neschimbat: bifarea alteia se aduna, nu se intersecteaza */
        $this->assertSame(4, $facets['cat'][HT_Test_Fixtures::CAT_UNGUENTE]);
        $this->assertSame(3, $facets['cat'][HT_Test_Fixtures::CAT_VITAMINE]);

        /* celelalte grupuri se restrang la siropuri */
        $this->assertSame(array('0-200' => 3, '200-500' => 1, '500-1000' => 0, '1000-2000' => 0), $facets['price']);
        $this->assertSame(array('instock' => 3, 'outofstock' => 1, 'new' => 1), $facets['stock']);
    }

    public function test_two_selected_categories_are_a_union()
    {
        $facets = $this->facets(array('cat' => array(HT_Test_Fixtures::CAT_SIROPURI, HT_Test_Fixtures::CAT_UNGUENTE)));

        /* 1,2,3,7 + 4,5,6 (7 e in ambele, numarat o data) */
        $this->assertSame(7, $facets['total']);
    }

    public function test_category_ids_are_compared_as_integers()
    {
        $facets = $this->facets(array('cat' => array((string)HT_Test_Fixtures::CAT_SIROPURI)));

        $this->assertSame(4, $facets['total']);
    }

    /* ---- pretul ---------------------------------------------------------- */

    public function test_selected_bucket_narrows_categories_and_stock_but_not_other_buckets()
    {
        $facets = $this->facets(array('price' => array('200-500')));

        $this->assertSame(3, $facets['total']);
        $this->assertSame(array('0-200' => 9, '200-500' => 3, '500-1000' => 1, '1000-2000' => 0), $facets['price']);
        $this->assertSame(array('instock' => 1, 'outofstock' => 2, 'new' => 2), $facets['stock']);
        $this->assertEquals(array(
            HT_Test_Fixtures::CAT_SIROPURI      => 1,
            HT_Test_Fixtures::CAT_UNGUENTE      => 1,
            HT_Test_Fixtures::CAT_VITAMINE      => 1,
            HT_Test_Fixtures::CAT_VITAMINE_COPII => 1,
        ), $facets['cat']);
    }

    public function test_variable_product_spanning_two_buckets_counts_in_both()
    {
        $row = HT_Test_Fixtures::row(6, 180, 320, 'instock', HT_Test_Fixtures::NOW, array());
        $facets = $this->facets(array(), array($row));

        $this->assertSame(1, $facets['price']['0-200']);
        $this->assertSame(1, $facets['price']['200-500']);
        $this->assertSame(0, $facets['price']['500-1000']);
    }

    public function test_bucket_edges_are_inclusive_like_woocommerce()
    {
        $row = HT_Test_Fixtures::row(1, 200, 200, 'instock', HT_Test_Fixtures::NOW, array());
        $facets = $this->facets(array(), array($row));

        $this->assertSame(1, $facets['price']['0-200']);
        $this->assertSame(1, $facets['price']['200-500']);
    }

    public function test_open_ended_bucket_has_no_ceiling()
    {
        $row = HT_Test_Fixtures::row(1, 99999, 99999, 'instock', HT_Test_Fixtures::NOW, array());
        $config = HT_Test_Fixtures::config();
        $config['buckets'][] = array('key' => '2000+', 'min' => 2000, 'max' => null);

        $facets = ht_shop_facet_counts(array($row), array('price' => array('2000+')), $config);

        $this->assertSame(1, $facets['price']['2000+']);
        $this->assertSame(1, $facets['total']);
    }

    public function test_unknown_bucket_key_matches_nothing()
    {
        $facets = $this->facets(array('price' => array('nu-exista')));

        $this->assertSame(0, $facets['total']);
    }

    public function test_free_range_uses_overlap_semantics()
    {
        $facets = $this->facets(array('min_price' => 100, 'max_price' => 200));

        /* 2 (150), 5 (199.99), 6 (180-320), 7 (120), 10 (200) */
        $this->assertSame(5, $facets['total']);
        $this->assertSame(2, $facets['cat'][HT_Test_Fixtures::CAT_SIROPURI]);
        $this->assertSame(3, $facets['cat'][HT_Test_Fixtures::CAT_UNGUENTE]);

        /* intervalul liber face parte din grupul de pret: contoarele lui nu il vad */
        $this->assertSame(9, $facets['price']['0-200']);
    }

    public function test_free_range_with_one_end_only()
    {
        $this->assertSame(3, $this->facets(array('min_price' => 250))['total']);   // 3, 6, 9
        $this->assertSame(3, $this->facets(array('max_price' => 80))['total']);    // 1, 4, 11
    }

    public function test_bucket_and_free_range_intersect()
    {
        $facets = $this->facets(array('price' => array('0-200'), 'min_price' => 250, 'max_price' => 300));

        /* doar produsul variabil 180-320 e si sub 200 si peste 250 */
        $this->assertSame(1, $facets['total']);
    }

    /* ---- disponibilitatea ------------------------------------------------ */

    public function test_selected_stock_narrows_categories_and_price_but_not_other_stock()
    {
        $facets = $this->facets(array('stock' => array('new')));

        $this->assertSame(5, $facets['total']);
        $this->assertSame(array('instock' => 8, 'outofstock' => 3, 'new' => 5), $facets['stock']);
        $this->assertSame(array('0-200' => 5, '200-500' => 2, '500-1000' => 0, '1000-2000' => 0), $facets['price']);
        $this->assertSame(2, $facets['cat'][HT_Test_Fixtures::CAT_UNGUENTE]);
    }

    public function test_out_of_stock_includes_backorders()
    {
        $facets = $this->facets(array('stock' => array('outofstock')));

        $this->assertSame(3, $facets['total']); // 3, 5 (precomanda), 10
    }

    public function test_stock_options_are_a_union()
    {
        $this->assertSame(11, $this->facets(array('stock' => array('instock', 'outofstock')))['total']);
        $this->assertSame(7, $this->facets(array('stock' => array('new', 'outofstock')))['total']);
    }

    public function test_new_is_decided_by_publish_date_against_threshold()
    {
        $fresh = HT_Test_Fixtures::FRESH;

        $this->assertTrue(ht_shop_row_is_new(HT_Test_Fixtures::row(1, 1, 1, 'instock', $fresh, array()), $fresh));
        $this->assertFalse(ht_shop_row_is_new(HT_Test_Fixtures::row(1, 1, 1, 'instock', '2026-07-31 11:59:59', array()), $fresh));
    }

    /* ---- combinatii ------------------------------------------------------ */

    public function test_all_three_groups_combined()
    {
        $facets = $this->facets(array(
            'cat'   => array(HT_Test_Fixtures::CAT_SIROPURI),
            'price' => array('200-500'),
            'stock' => array('instock'),
        ));

        $this->assertSame(0, $facets['total']);

        /* fiecare grup vede doar celelalte doua */
        $this->assertSame(array(HT_Test_Fixtures::CAT_UNGUENTE => 1), $facets['cat']);
        $this->assertSame(3, $facets['price']['0-200']);
        $this->assertSame(array('instock' => 0, 'outofstock' => 1, 'new' => 0), $facets['stock']);
    }

    public function test_max_price_ignores_filters()
    {
        $facets = $this->facets(array('cat' => array(HT_Test_Fixtures::CAT_SIROPURI)));

        $this->assertSame(600.0, $facets['max']);
    }

    /**
     * Proprietatea care defineste fatetele: numarul de langa o optiune e
     * exact totalul pe care l-ai obtine cu grupul ei inlocuit de acea optiune.
     *
     * @dataProvider selections
     */
    public function test_each_count_equals_the_total_you_would_get_by_picking_that_option(array $selection)
    {
        $facets = $this->facets($selection);

        foreach ($facets['cat'] as $term_id => $count) {
            $probe = array_merge($selection, array('cat' => array($term_id)));
            $this->assertSame($this->facets($probe)['total'], $count, "categoria {$term_id}");
        }

        foreach ($facets['price'] as $key => $count) {
            $probe = array_merge($selection, array('price' => array($key), 'min_price' => null, 'max_price' => null));
            $this->assertSame($this->facets($probe)['total'], $count, "interval {$key}");
        }

        foreach ($facets['stock'] as $key => $count) {
            $probe = array_merge($selection, array('stock' => array($key)));
            $this->assertSame($this->facets($probe)['total'], $count, "disponibilitate {$key}");
        }
    }

    public function selections()
    {
        return array(
            'nimic'            => array(array()),
            'categorie'        => array(array('cat' => array(HT_Test_Fixtures::CAT_UNGUENTE))),
            'doua categorii'   => array(array('cat' => array(HT_Test_Fixtures::CAT_UNGUENTE, HT_Test_Fixtures::CAT_VITAMINE))),
            'interval'         => array(array('price' => array('0-200'))),
            'interval liber'   => array(array('min_price' => 100, 'max_price' => 250)),
            'disponibilitate'  => array(array('stock' => array('new', 'outofstock'))),
            'toate'            => array(array(
                'cat'       => array(HT_Test_Fixtures::CAT_UNGUENTE),
                'price'     => array('0-200', '200-500'),
                'min_price' => 100,
                'max_price' => null,
                'stock'     => array('instock'),
            )),
        );
    }

    /* ---- pe o pagina de categorie --------------------------------------- */

    public function test_category_page_counts_only_its_own_products()
    {
        $rows = HT_Test_Fixtures::rows_in(array(HT_Test_Fixtures::CAT_VITAMINE, HT_Test_Fixtures::CAT_VITAMINE_COPII));
        $facets = $this->facets(array(), $rows);

        $this->assertSame(3, $facets['total']);
        $this->assertSame(array('0-200' => 2, '200-500' => 1, '500-1000' => 1, '1000-2000' => 0), $facets['price']);
        $this->assertSame(array('instock' => 2, 'outofstock' => 1, 'new' => 2), $facets['stock']);
        $this->assertArrayNotHasKey(HT_Test_Fixtures::CAT_SIROPURI, $facets['cat']);
        $this->assertSame(1, $facets['cat'][HT_Test_Fixtures::CAT_VITAMINE_COPII]);
    }
}
