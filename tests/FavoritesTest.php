<?php
/**
 * Testele listei de favorite (inc/favorites.php): curatarea identificatorilor,
 * fluxul de vizitator (cookie) si de utilizator autentificat (meta), limita
 * listei, contopirea la login, ruta REST, produsele paginii de favorite,
 * pagina /favorite/ si markup-ul butonului-inima.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class FavoritesTest extends TestCase
{
    protected function setUp(): void
    {
        HT_Test_Wp::reset();
        HT_Test_State::reset();
    }

    protected function tearDown(): void
    {
        HT_Test_Wp::reset();
        HT_Test_State::reset();
    }

    /**
     * Inregistreaza produse publicate.
     *
     * @param int ...$ids ID-urile.
     */
    private function products(...$ids)
    {
        foreach ($ids as $id) {
            HT_Test_State::product($id);
        }
    }

    /**
     * Apeleaza scrierea REST cu parametrii dati.
     *
     * @param array $params Parametrii cererii.
     *
     * @return array|WP_Error
     */
    private function rest(array $params)
    {
        return ht_favorites_rest_write(new WP_REST_Request($params));
    }

    /* ---- curatarea listei ---------------------------------------------- */

    public function test_sanitize_parses_cookie_string_and_drops_junk()
    {
        $this->assertSame(array(3, 1, 2, 5), ht_favorites_sanitize('3,1,2,abc,-5,0,1,,'));
    }

    public function test_sanitize_rejects_non_lists()
    {
        $this->assertSame(array(), ht_favorites_sanitize(null));
        $this->assertSame(array(), ht_favorites_sanitize(42));
        $this->assertSame(array(), ht_favorites_sanitize(''));
    }

    public function test_sanitize_applies_limit_filter()
    {
        add_filter('ht_favorites_limit', function () {
            return 3;
        });

        $this->assertSame(array(5, 6, 7), ht_favorites_sanitize(array(5, 6, 7, 8, 9)));
    }

    /* ---- fluxul de vizitator (cookie) ----------------------------------- */

    public function test_guest_starts_empty()
    {
        $this->assertSame(array(), ht_favorites_get());
        $this->assertSame(0, ht_favorites_count());
    }

    public function test_guest_reads_existing_cookie()
    {
        $_COOKIE[HT_FAVORITES_COOKIE] = '5,7';

        $this->assertSame(array(5, 7), ht_favorites_get());
        $this->assertTrue(ht_is_favorite(5));
        $this->assertFalse(ht_is_favorite(6));
    }

    public function test_guest_survives_malicious_cookie()
    {
        $_COOKIE[HT_FAVORITES_COOKIE] = '<script>alert(1)</script>,7,-3';

        /* absint() intoarce modulul, ca in WordPress: '-3' devine 3 */
        $this->assertSame(array(7, 3), ht_favorites_get());
    }

    public function test_guest_add_writes_cookie_newest_first()
    {
        $this->products(10, 11);

        ht_favorites_add(10);
        ht_favorites_add(11);

        $this->assertSame(array(11, 10), ht_favorites_get());
        $this->assertSame('11,10', $_COOKIE[HT_FAVORITES_COOKIE]);
    }

    public function test_add_ignores_duplicates_and_unknown_products()
    {
        $this->products(10);
        HT_Test_State::product(12, 'draft');

        ht_favorites_add(10);
        ht_favorites_add(10);   /* duplicat */
        ht_favorites_add(999);  /* inexistent */
        ht_favorites_add(12);   /* depublicat */

        $this->assertSame(array(10), ht_favorites_get());
    }

    public function test_remove_and_missing_remove()
    {
        $this->products(1, 2, 3);
        ht_favorites_set(array(1, 2, 3));

        $this->assertSame(array(1, 3), ht_favorites_remove(2));
        $this->assertSame(array(1, 3), ht_favorites_remove(2));
    }

    public function test_toggle_returns_resulting_state()
    {
        $this->products(10);

        $this->assertTrue(ht_favorites_toggle(10));
        $this->assertFalse(ht_favorites_toggle(10));
        $this->assertSame(array(), ht_favorites_get());
    }

    public function test_toggle_invalid_product_stays_off()
    {
        $this->assertFalse(ht_favorites_toggle(999));
        $this->assertSame(array(), ht_favorites_get());
    }

    public function test_clear_removes_cookie()
    {
        $this->products(10);
        ht_favorites_add(10);

        $this->assertSame(array(), ht_favorites_clear());
        $this->assertArrayNotHasKey(HT_FAVORITES_COOKIE, $_COOKIE);
    }

    public function test_add_over_limit_drops_oldest()
    {
        add_filter('ht_favorites_limit', function () {
            return 2;
        });
        $this->products(1, 2, 3);
        ht_favorites_set(array(1, 2));

        $this->assertTrue(ht_favorites_toggle(3));
        $this->assertSame(array(3, 1), ht_favorites_get());
    }

    /* ---- fluxul de utilizator autentificat ------------------------------ */

    public function test_logged_in_uses_user_meta_not_cookie()
    {
        HT_Test_State::$user_id = 42;
        $this->products(10);
        $_COOKIE[HT_FAVORITES_COOKIE] = '99';

        ht_favorites_add(10);

        $this->assertSame(array(10), ht_favorites_get());
        $this->assertSame(array(10), HT_Test_State::$user_meta[42][HT_FAVORITES_META]);
        /* cookie-ul vizitatorului ramane neatins */
        $this->assertSame('99', $_COOKIE[HT_FAVORITES_COOKIE]);
    }

    public function test_logged_in_clear_deletes_meta_row()
    {
        HT_Test_State::$user_id = 42;
        $this->products(10);
        ht_favorites_add(10);

        ht_favorites_clear();

        $this->assertFalse(isset(HT_Test_State::$user_meta[42][HT_FAVORITES_META]));
        $this->assertSame(array(), ht_favorites_get());
    }

    public function test_updated_action_fires_with_list_and_user()
    {
        HT_Test_State::$user_id = 7;
        $this->products(10);
        $seen = array();

        add_action('ht_favorites_updated', function ($ids, $user_id) use (&$seen) {
            $seen = array($ids, $user_id);
        }, 10, 2);

        ht_favorites_add(10);

        $this->assertSame(array(array(10), 7), $seen);
    }

    /* ---- contopirea la login -------------------------------------------- */

    public function test_login_merges_guest_cookie_into_account()
    {
        $this->products(1, 2, 3);
        $_COOKIE[HT_FAVORITES_COOKIE] = '1,2';
        HT_Test_State::$user_meta[42][HT_FAVORITES_META] = array(2, 3);

        ht_favorites_merge_on_login('user', new WP_User(42));

        $this->assertSame(array(1, 2, 3), HT_Test_State::$user_meta[42][HT_FAVORITES_META]);
        $this->assertArrayNotHasKey(HT_FAVORITES_COOKIE, $_COOKIE);
    }

    public function test_login_with_empty_cookie_keeps_account_list()
    {
        HT_Test_State::$user_meta[42][HT_FAVORITES_META] = array(5);

        ht_favorites_merge_on_login('user', new WP_User(42));

        $this->assertSame(array(5), HT_Test_State::$user_meta[42][HT_FAVORITES_META]);
        $this->assertArrayNotHasKey(HT_FAVORITES_COOKIE, $_COOKIE);
    }

    public function test_merge_validates_and_prepends_incoming()
    {
        $this->products(5, 7, 8);
        ht_favorites_set(array(5));

        $merged = ht_favorites_merge(array(7, 8, 999, 5));

        $this->assertSame(array(7, 8, 5), $merged);
    }

    /* ---- ruta REST ------------------------------------------------------ */

    public function test_rest_route_registers_expected_actions()
    {
        ht_favorites_register_rest();

        $route = HT_Test_State::$rest_routes['herbal-therapy/v1/favorites'];

        $this->assertSame('toggle', $route[1]['args']['action']['default']);
        $this->assertSame(array('toggle', 'add', 'remove', 'clear'), $route[1]['args']['action']['enum']);
    }

    public function test_rest_write_requires_id()
    {
        $result = $this->rest(array('action' => 'toggle'));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('ht_favorites_missing_id', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status']);
    }

    public function test_rest_rejects_unknown_product()
    {
        $result = $this->rest(array('action' => 'toggle', 'id' => 999));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('ht_favorites_invalid_product', $result->get_error_code());
        $this->assertSame(404, $result->get_error_data()['status']);
    }

    public function test_rest_toggle_on_and_off()
    {
        $this->products(10);

        $on = $this->rest(array('action' => 'toggle', 'id' => 10));
        $this->assertSame(array('ids' => array(10), 'count' => 1, 'active' => true), $on);

        $off = $this->rest(array('action' => 'toggle', 'id' => 10));
        $this->assertSame(array('ids' => array(), 'count' => 0, 'active' => false), $off);
    }

    public function test_rest_can_remove_a_product_unpublished_meanwhile()
    {
        /* produsul a fost salvat, apoi depublicat - trebuie sa poata iesi */
        HT_Test_State::product(10, 'draft');
        $_COOKIE[HT_FAVORITES_COOKIE] = '10';

        $result = $this->rest(array('action' => 'toggle', 'id' => 10));

        $this->assertSame(array('ids' => array(), 'count' => 0, 'active' => false), $result);
    }

    public function test_rest_add_with_ids_merges_legacy_list()
    {
        $this->products(5, 7, 8);
        ht_favorites_set(array(5));

        $result = $this->rest(array('action' => 'add', 'ids' => array(7, 8, 999)));

        $this->assertSame(array('ids' => array(7, 8, 5), 'count' => 3), $result);
    }

    public function test_rest_remove_and_clear()
    {
        $this->products(1, 2);
        ht_favorites_set(array(1, 2));

        $removed = $this->rest(array('action' => 'remove', 'id' => 2));
        $this->assertSame(array('ids' => array(1), 'count' => 1, 'active' => false), $removed);

        $cleared = $this->rest(array('action' => 'clear'));
        $this->assertSame(array('ids' => array(), 'count' => 0), $cleared);
    }

    /* ---- produsele paginii de favorite ---------------------------------- */

    public function test_products_keep_saved_order_and_drop_unpublished()
    {
        $this->products(9, 7, 8);
        HT_Test_State::product(8, 'draft');
        $_COOKIE[HT_FAVORITES_COOKIE] = '9,7,8';

        $ids = array_map(function ($product) {
            return $product->get_id();
        }, ht_favorites_products());

        $this->assertSame(array(9, 7), $ids);
    }

    /* ---- traducerile (Polylang) ------------------------------------------ */

    public function test_get_maps_ids_to_current_language()
    {
        $this->products(588, 587);
        HT_Test_State::$translations = array(588 => 587, 587 => 587);
        HT_Test_State::$user_id = 42;
        HT_Test_State::$user_meta[42][HT_FAVORITES_META] = array(588);

        $this->assertSame(array(587), ht_favorites_get());
        $this->assertTrue(ht_is_favorite(587));
        $this->assertSame(1, ht_favorites_count());
    }

    public function test_get_dedupes_saved_translation_pairs()
    {
        $this->products(588, 587);
        HT_Test_State::$translations = array(588 => 587, 587 => 587);
        $_COOKIE[HT_FAVORITES_COOKIE] = '588,587';

        $this->assertSame(array(587), ht_favorites_get());
        $this->assertSame(1, ht_favorites_count());
    }

    public function test_remove_works_through_translated_id()
    {
        $this->products(588, 587);
        HT_Test_State::$translations = array(588 => 587, 587 => 587);
        HT_Test_State::$user_id = 42;
        HT_Test_State::$user_meta[42][HT_FAVORITES_META] = array(588);

        $result = $this->rest(array('action' => 'remove', 'id' => 587));

        $this->assertSame(array('ids' => array(), 'count' => 0, 'active' => false), $result);
        $this->assertFalse(isset(HT_Test_State::$user_meta[42][HT_FAVORITES_META]));
    }

    public function test_untranslated_foreign_product_stays_visible()
    {
        $this->products(588);
        $_COOKIE[HT_FAVORITES_COOKIE] = '588';

        $this->assertSame(array(588), ht_favorites_get());

        $ids = array_map(function ($product) {
            return $product->get_id();
        }, ht_favorites_products());

        $this->assertSame(array(588), $ids);
    }

    /* ---- grupul de traduceri, fara limba curenta (ca in REST) ------------ */

    /**
     * Perechea 587 (ro) / 588 (ru), publicata, cu grupul de traduceri legat.
     */
    private function pairRoRu()
    {
        $this->products(587, 588);
        HT_Test_State::$translation_groups = array(
            587 => array('ro' => 587, 'ru' => 588),
            588 => array('ro' => 587, 'ru' => 588),
        );
    }

    public function test_is_favorite_matches_any_translation()
    {
        $this->pairRoRu();
        $_COOKIE[HT_FAVORITES_COOKIE] = '588';

        $this->assertTrue(ht_is_favorite(587));
        $this->assertTrue(ht_is_favorite(588));
        $this->assertFalse(ht_is_favorite(600));
    }

    public function test_rest_remove_translated_id_without_language_context()
    {
        $this->pairRoRu();
        HT_Test_State::$user_id = 42;
        HT_Test_State::$user_meta[42][HT_FAVORITES_META] = array(588);

        $result = $this->rest(array('action' => 'remove', 'id' => 587));

        $this->assertSame(array('ids' => array(), 'count' => 0, 'active' => false), $result);
        $this->assertFalse(isset(HT_Test_State::$user_meta[42][HT_FAVORITES_META]));
    }

    public function test_toggle_translated_id_removes_instead_of_duplicating()
    {
        $this->pairRoRu();
        $_COOKIE[HT_FAVORITES_COOKIE] = '588';

        $result = $this->rest(array('action' => 'toggle', 'id' => 587));

        $this->assertSame(array('ids' => array(), 'count' => 0, 'active' => false), $result);
    }

    public function test_add_skips_already_saved_translation()
    {
        $this->pairRoRu();
        $_COOKIE[HT_FAVORITES_COOKIE] = '588';

        $this->assertSame(array(588), ht_favorites_add(587));
    }

    public function test_rest_payload_localizes_with_lang_param()
    {
        $this->pairRoRu();
        $_COOKIE[HT_FAVORITES_COOKIE] = '588';

        $result = ht_favorites_rest_read(new WP_REST_Request(array('lang' => 'ro')));

        $this->assertSame(array('ids' => array(587), 'count' => 1), $result);
    }

    /* ---- pagina /favorite/ ---------------------------------------------- */

    public function test_install_creates_page_once_with_template()
    {
        ht_favorites_install_page();

        $page_id = HT_Test_State::$pages['favorite'];
        $this->assertSame('publish', get_post_status($page_id));
        $this->assertSame(
            'templates/favorites.php',
            HT_Test_State::$post_meta[$page_id]['_wp_page_template']
        );
        $this->assertSame($page_id, ht_favorites_page_id());

        /* a doua rulare nu creeaza inca o pagina */
        $before = HT_Test_State::$next_post_id;
        ht_favorites_install_page();
        $this->assertSame($before, HT_Test_State::$next_post_id);
    }

    public function test_page_id_finds_page_by_path_and_caches_option()
    {
        $page_id = wp_insert_post(array('post_name' => 'favorite', 'post_status' => 'publish'));

        $this->assertSame($page_id, ht_favorites_page_id());
        $this->assertSame($page_id, get_option('ht_favorites_page_id'));
    }

    public function test_page_id_ignores_unpublished_page()
    {
        $page_id = wp_insert_post(array('post_name' => 'favorite', 'post_status' => 'publish'));
        update_option('ht_favorites_page_id', $page_id);
        HT_Test_State::$post_status[$page_id] = 'trash';

        $this->assertSame(0, ht_favorites_page_id());
    }

    /* ---- markup --------------------------------------------------------- */

    public function test_button_renders_inactive_state()
    {
        $this->products(10);

        ob_start();
        ht_favorite_button(10);
        $html = ob_get_clean();

        $this->assertStringContainsString('class="ht-card__fav"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('data-product-id="10"', $html);
        $this->assertStringContainsString('data-ht-favorite', $html);
        $this->assertStringContainsString('class="ht-card__fav-icon"', $html);
    }

    public function test_button_renders_active_state()
    {
        $this->products(10);
        ht_favorites_add(10);

        ob_start();
        ht_favorite_button(10, 'ht-product__fav');
        $html = ob_get_clean();

        $this->assertStringContainsString('class="ht-product__fav is-active"', $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);
        $this->assertStringContainsString('aria-label="Șterge din favorite"', $html);
    }

    public function test_count_badge_hides_zero()
    {
        ob_start();
        ht_favorites_count_badge();
        $empty = ob_get_clean();

        $this->assertStringContainsString('></span>', $empty);

        $this->products(1, 2);
        ht_favorites_set(array(1, 2));

        ob_start();
        ht_favorites_count_badge();
        $two = ob_get_clean();

        $this->assertStringContainsString('>2</span>', $two);
    }
}
