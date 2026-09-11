<?php
/**
 * Testele stratului WordPress al filtrelor (inc/shop-filters.php): citirea
 * adresei, lista de categorii pe magazin vs. pe o pagina de categorie,
 * contoarele afisate, legaturile de comutare / golire, etichetele active,
 * conditiile din interogare si markup-ul barei laterale.
 *
 * Randurile de produse vin din HT_Test_Fixtures prin filtrul
 * 'ht_shop_pre_facet_rows'; pe o pagina de categorie se restrang la familia
 * termenului, ca in SQL-ul real.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class ShopFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        HT_Test_Wp::reset();
        HT_Test_Fixtures::terms();

        add_filter('ht_shop_pre_facet_rows', function () {
            $scope = ht_shop_scope_term();

            return $scope ? HT_Test_Fixtures::rows_in(ht_shop_term_family($scope)) : HT_Test_Fixtures::rows();
        });
    }

    protected function tearDown(): void
    {
        HT_Test_Wp::reset();
    }

    /**
     * Pune testul pe o pagina de categorie.
     *
     * @param int $term_id ID-ul categoriei.
     */
    private function on_category($term_id)
    {
        HT_Test_Wp::$queried = HT_Test_Wp::$terms[$term_id];
    }

    /**
     * Parametrii dintr-o adresa.
     *
     * @param string $url Adresa.
     *
     * @return array
     */
    private function query($url)
    {
        $query = array();
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function slugs(array $terms)
    {
        return array_map(function ($term) {
            return $term->slug;
        }, $terms);
    }

    /* ---- citirea adresei ------------------------------------------------ */

    public function test_values_accept_comma_list_and_array_form()
    {
        $_GET['cat'] = 'siropuri,unguente';
        $this->assertSame(array('siropuri', 'unguente'), ht_shop_filter_values('cat'));

        ht_shop_memo_flush();
        $_GET['cat'] = array('siropuri', 'unguente');
        $this->assertSame(array('siropuri', 'unguente'), ht_shop_filter_values('cat'));
    }

    public function test_values_are_sanitized_deduplicated_and_never_empty()
    {
        $_GET['cat'] = 'Siropuri, ,siropuri,<b>x</b>,';

        $this->assertSame(array('siropuri', 'b-x-b'), ht_shop_filter_values('cat'));
        $this->assertSame(array(), ht_shop_filter_values('price'));
        $this->assertTrue(ht_shop_filter_checked('cat', 'siropuri'));
        $this->assertFalse(ht_shop_filter_checked('cat', 'unguente'));
    }

    public function test_price_input_keeps_digits_only()
    {
        $_GET['min_price'] = '1,200';
        $_GET['max_price'] = 'abc';

        $this->assertSame(1200.0, ht_shop_price_input('min_price'));
        $this->assertNull(ht_shop_price_input('max_price'));
        $this->assertNull(ht_shop_price_input('lipsa'));
    }

    public function test_has_filters()
    {
        $this->assertFalse(ht_shop_has_filters());

        $_GET['orderby'] = 'price';
        $this->assertFalse(ht_shop_has_filters(), 'sortarea nu e filtru');

        $_GET['max_price'] = '100';
        $this->assertTrue(ht_shop_has_filters());
    }

    public function test_unknown_category_slug_is_ignored_in_selection()
    {
        $_GET['cat'] = 'siropuri,nu-exista';

        $selection = ht_shop_facet_selection_from_request();

        $this->assertSame(array(HT_Test_Fixtures::CAT_SIROPURI), $selection['cat']);
    }

    /* ---- lista de categorii --------------------------------------------- */

    public function test_shop_lists_categories_that_have_products_sorted_by_name()
    {
        $this->assertSame(
            array('siropuri', 'unguente', 'vitamine-copii', 'vitamine-si-suplimente'),
            $this->slugs(ht_shop_filter_categories()),
            'ceaiuri nu are produse, deci lipseste'
        );
    }

    public function test_category_page_lists_only_its_subcategories()
    {
        $this->on_category(HT_Test_Fixtures::CAT_VITAMINE);

        $this->assertSame(array('vitamine-copii'), $this->slugs(ht_shop_filter_categories()));
    }

    public function test_category_page_without_subcategories_lists_nothing_but_shared_ones()
    {
        $this->on_category(HT_Test_Fixtures::CAT_UNGUENTE);

        /* produsul 7 e si in siropuri, deci siropuri ramane o restrangere valida */
        $this->assertSame(array('siropuri'), $this->slugs(ht_shop_filter_categories()));

        HT_Test_Wp::reset();
        HT_Test_Fixtures::terms();
        add_filter('ht_shop_pre_facet_rows', function () {
            return array(HT_Test_Fixtures::row(1, 10, 10, 'instock', HT_Test_Fixtures::NOW, array(HT_Test_Fixtures::CAT_SIROPURI)));
        });
        $this->on_category(HT_Test_Fixtures::CAT_SIROPURI);

        $this->assertSame(array(), ht_shop_filter_categories());
    }

    /* ---- contoarele ------------------------------------------------------ */

    public function test_shop_payload_carries_every_visible_option()
    {
        $payload = ht_shop_facet_payload();

        $this->assertSame(
            array('siropuri' => 4, 'unguente' => 4, 'vitamine-copii' => 1, 'vitamine-si-suplimente' => 3),
            $payload['cat']
        );
        $this->assertSame(array('0-200' => 9, '200-500' => 3, '500-1000' => 1, '1000-2000' => 0), $payload['price']);
        $this->assertSame(3, $payload['stock']['outofstock']);
    }

    public function test_category_page_counts_are_scoped_to_that_category()
    {
        $this->on_category(HT_Test_Fixtures::CAT_VITAMINE);

        $payload = ht_shop_facet_payload();

        $this->assertSame(array('vitamine-copii' => 1), $payload['cat']);
        $this->assertSame(array('0-200' => 2, '200-500' => 1, '500-1000' => 1, '1000-2000' => 0), $payload['price']);
        $this->assertSame(array('instock' => 2, 'outofstock' => 1, 'new' => 2), $payload['stock']);
    }

    public function test_counts_react_to_the_other_groups()
    {
        $_GET['price'] = '200-500';

        $payload = ht_shop_facet_payload();

        $this->assertSame(array('siropuri' => 1, 'unguente' => 1, 'vitamine-copii' => 1, 'vitamine-si-suplimente' => 1), $payload['cat']);
        $this->assertSame(array('instock' => 1, 'outofstock' => 2, 'new' => 2), $payload['stock']);
        $this->assertSame(9, $payload['price']['0-200'], 'grupul propriu nu se restrange');
    }

    public function test_price_range_ceiling_rounds_up_to_hundreds_and_follows_the_listing()
    {
        $this->assertSame(array('min' => 1, 'max' => 600), ht_shop_price_range());

        HT_Test_Wp::reset();
        HT_Test_Fixtures::terms();
        add_filter('ht_shop_pre_facet_rows', function () {
            return array(HT_Test_Fixtures::row(1, 10, 149, 'instock', HT_Test_Fixtures::NOW, array()));
        });
        $this->assertSame(200, ht_shop_price_range()['max']);

        HT_Test_Wp::reset();
        add_filter('ht_shop_pre_facet_rows', function () {
            return array();
        });
        $this->assertSame(2000, ht_shop_price_range()['max'], 'fara produse ramane plafonul implicit');
    }

    /* ---- legaturile ------------------------------------------------------ */

    public function test_toggle_adds_then_removes_a_value()
    {
        $url = ht_shop_toggle_url('cat', 'siropuri');
        $this->assertSame(array('cat' => 'siropuri'), $this->query($url));
        $this->assertStringStartsWith(HT_Test_Wp::$shop_url, $url);

        $_GET['cat'] = 'siropuri,unguente';
        ht_shop_memo_flush();
        $this->assertSame(array('cat' => 'unguente'), $this->query(ht_shop_toggle_url('cat', 'siropuri')));

        $_GET['cat'] = 'siropuri';
        ht_shop_memo_flush();
        $this->assertSame(HT_Test_Wp::$shop_url, ht_shop_toggle_url('cat', 'siropuri'), 'ultima valoare scoasa lasa adresa curata');
    }

    public function test_toggle_keeps_the_other_groups_sorting_and_free_range()
    {
        $_GET = array('cat' => 'siropuri', 'min_price' => '10', 'max_price' => '99.5', 'orderby' => 'price', 'paged' => '3');

        $query = $this->query(ht_shop_toggle_url('stock', 'new'));

        $this->assertEquals(
            array('cat' => 'siropuri', 'stock' => 'new', 'min_price' => '10', 'max_price' => '99.5', 'orderby' => 'price'),
            $query,
            'paginarea nu se pastreaza: alt filtru inseamna alt set de rezultate'
        );
    }

    public function test_category_page_links_stay_on_the_category()
    {
        $this->on_category(HT_Test_Fixtures::CAT_VITAMINE);

        $this->assertStringStartsWith('https://example.test/vitamine-si-suplimente/', ht_shop_toggle_url('cat', 'vitamine-copii'));
        $this->assertSame('https://example.test/vitamine-si-suplimente/', ht_shop_clear_url());
    }

    public function test_clear_one_group_or_everything()
    {
        $_GET = array('cat' => 'siropuri', 'price' => '0-200', 'min_price' => '10', 'max_price' => '50', 'stock' => 'new', 'orderby' => 'price');

        $this->assertSame(
            array('cat' => 'siropuri', 'stock' => 'new', 'orderby' => 'price'),
            $this->query(ht_shop_clear_url('price')),
            'golirea pretului scoate si intervalul liber'
        );
        $this->assertSame(array('orderby' => 'price'), $this->query(ht_shop_clear_url()));
    }

    /* ---- etichetele active ---------------------------------------------- */

    public function test_active_chips_in_display_order_with_removal_links()
    {
        $_GET = array('cat' => 'unguente,nu-exista', 'price' => '0-200', 'min_price' => '10', 'max_price' => '50', 'stock' => 'new');

        $chips = ht_shop_active_filters();
        $labels = array_column($chips, 'label');

        $this->assertSame(array('Unguente', 'Sub 200', '10 - 50', 'Nou'), $labels, 'slug-ul necunoscut nu produce eticheta');
        /* eticheta scoate doar valoarea ei; ce mai era in adresa (si slug-ul necunoscut) ramane */
        $this->assertEquals(array('cat' => 'nu-exista', 'price' => '0-200', 'min_price' => '10', 'max_price' => '50', 'stock' => 'new'), $this->query($chips[0]['url']));
        $this->assertEquals(array('cat' => 'unguente,nu-exista', 'price' => '0-200', 'stock' => 'new'), $this->query($chips[2]['url']));
    }

    public function test_chip_for_a_category_from_another_listing_still_shows()
    {
        $this->on_category(HT_Test_Fixtures::CAT_VITAMINE);
        $_GET['cat'] = 'siropuri';

        $labels = array_column(ht_shop_active_filters(), 'label');

        $this->assertSame(array('Siropuri'), $labels, 'vizitatorul trebuie sa poata scoate un filtru care nu mai e in lista');
    }

    /* ---- interogarea ----------------------------------------------------- */

    public function test_query_gets_a_category_tax_clause_only_when_needed()
    {
        $query = new WP_Query();
        ht_shop_filter_categories_query($query);
        $this->assertSame('', $query->get('tax_query'));

        $_GET['cat'] = 'siropuri,unguente';
        ht_shop_memo_flush();
        $query->set('tax_query', array(array('taxonomy' => 'product_visibility')));
        ht_shop_filter_categories_query($query);

        $tax = $query->get('tax_query');
        $this->assertCount(2, $tax, 'conditia existenta ramane');
        $this->assertSame(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array('siropuri', 'unguente'), 'operator' => 'IN'), $tax[1]);
    }

    public function test_sql_clauses_untouched_without_price_or_stock_filters()
    {
        $_GET['cat'] = 'siropuri';
        $clauses = array('join' => '', 'where' => '');

        $this->assertSame($clauses, ht_shop_filter_clauses($clauses, new WP_Query()));
    }

    public function test_sql_clauses_join_lookup_table_once_and_union_within_groups()
    {
        $_GET = array('price' => '0-200,200-500', 'stock' => 'instock,outofstock', 'min_price' => '5');
        $clauses = array('join' => ' INNER JOIN wp_wc_product_meta_lookup AS ht_pf ON x', 'where' => '');

        $out = ht_shop_filter_clauses($clauses, new WP_Query());

        $this->assertSame(1, substr_count($out['join'], 'ht_pf'), 'jonctiunea nu se dubleaza');
        $this->assertStringContainsString("(ht_pf.max_price >= 0.000000 AND ht_pf.min_price <= 200.000000) OR (ht_pf.max_price >= 200.000000 AND ht_pf.min_price <= 500.000000)", $out['where']);
        $this->assertStringContainsString("(ht_pf.stock_status = 'instock' OR ht_pf.stock_status IN ('outofstock', 'onbackorder'))", $out['where']);
        $this->assertStringNotContainsString('min_price >= 5', $out['where'], 'intervalul liber ramane in seama WooCommerce');
    }

    public function test_sql_clauses_are_skipped_outside_listings()
    {
        HT_Test_Wp::$is_search = true;
        $_GET['stock'] = 'new';
        $clauses = array('join' => '', 'where' => '');

        $this->assertSame($clauses, ht_shop_filter_clauses($clauses, new WP_Query()));
    }

    /* ---- markup ---------------------------------------------------------- */

    private function render()
    {
        ob_start();
        ht_shop_filters_markup();

        return (string)ob_get_clean();
    }

    public function test_shop_markup_shows_scoped_counts_and_checked_state()
    {
        $_GET['price'] = '200-500';

        $html = $this->render();

        $this->assertMatchesRegularExpression('~name="cat\[\]"\s+value="unguente"[^>]*>.*?ht-check__count">1<~s', $html);
        $this->assertMatchesRegularExpression('~value="200-500"\s+checked=~', $html);
        $this->assertMatchesRegularExpression('~value="0-200"\s*>~', $html);
        $this->assertSame(4, substr_count($html, 'name="cat[]"'));
    }

    public function test_category_page_markup_hides_the_category_group_when_empty()
    {
        $this->on_category(HT_Test_Fixtures::CAT_SIROPURI);
        HT_Test_Wp::$hooks = array();
        add_filter('ht_shop_pre_facet_rows', function () {
            return HT_Test_Fixtures::rows_in(array(HT_Test_Fixtures::CAT_SIROPURI));
        });

        /* produsul 7 e si in unguente: grupul ramane, cu o singura optiune */
        $this->assertSame(1, substr_count($this->render(), 'name="cat[]"'));

        HT_Test_Wp::$hooks = array();
        ht_shop_memo_flush();
        add_filter('ht_shop_pre_facet_rows', function () {
            return array(HT_Test_Fixtures::row(1, 10, 10, 'instock', HT_Test_Fixtures::NOW, array(HT_Test_Fixtures::CAT_SIROPURI)));
        });

        $html = $this->render();

        $this->assertStringNotContainsString('name="cat[]"', $html);
        $this->assertStringNotContainsString('data-ht-filters-searchable', $html);
        $this->assertStringContainsString('name="price[]"', $html, 'celelalte grupuri raman');
    }

    public function test_markup_slider_follows_the_listing_ceiling()
    {
        $this->on_category(HT_Test_Fixtures::CAT_SIROPURI);

        $this->assertStringContainsString('data-ceil="300"', $this->render());
    }
}
