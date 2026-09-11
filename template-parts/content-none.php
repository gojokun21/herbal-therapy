<?php
/**
 * Mesajul afisat cand nu exista rezultate.
 *
 * @package Herbal_Therapy
 */
?>

<section class="no-results not-found">
    <?php if (is_search()) : ?>
        <p><?php esc_html_e('Nu am găsit nimic pentru această căutare. Încearcă alți termeni.', 'herbal-therapy'); ?></p>
        <?php get_search_form(); ?>
    <?php else : ?>
        <p><?php esc_html_e('Nu există conținut de afișat deocamdată.', 'herbal-therapy'); ?></p>
    <?php endif; ?>
</section>
