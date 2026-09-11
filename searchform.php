<?php
/**
 * Formularul de cautare.
 *
 * Acelasi formular pe pagina de rezultate, la 404 si acolo unde o cautare nu a
 * intors nimic. Panoul din header are markup propriu (inc/search.php).
 *
 * @package Herbal_Therapy
 */

$ht_search_id = 'search-' . wp_unique_id();
?>
<form role="search" method="get" class="ht-searchform" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="ht-visually-hidden" for="<?php echo esc_attr($ht_search_id); ?>">
        <?php esc_html_e('Caută pe site', 'herbal-therapy'); ?>
    </label>

    <?php ht_icon('search'); ?>

    <input type="search"
           id="<?php echo esc_attr($ht_search_id); ?>"
           name="s"
           value="<?php echo esc_attr(get_search_query()); ?>"
           placeholder="<?php esc_attr_e('Caută un produs sau o categorie', 'herbal-therapy'); ?>"
           autocomplete="off"/>

    <button type="submit"><?php esc_html_e('Caută', 'herbal-therapy'); ?></button>
</form>
