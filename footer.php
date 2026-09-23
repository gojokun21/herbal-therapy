<?php
/**
 * Subsolul paginii.
 *
 * Datele vin din /inc/footer.php: coloanele din meniul 'footer-menu', restul din filtre.
 *
 * @package Herbal_Therapy
 */

$ht_columns = ht_footer_columns();
$ht_contacts = ht_footer_contacts();
$ht_socials = array_filter(ht_footer_socials(), function ($social) {
    return !empty($social['url']);
});
$ht_payments = ht_footer_payments();
$ht_entity = ht_footer_legal_entity();
?>

<footer class="ht-footer">
    <div class="ht-wrapper ht-footer__content">

        <?php foreach ($ht_columns as $ht_column) : ?>
            <div class="ht-footer__col">
                <h3 class="ht-footer__col-title"><?php echo esc_html($ht_column['title']); ?></h3>
                <ul class="ht-footer__links">
                    <?php foreach ($ht_column['links'] as $ht_link) : ?>
                        <li class="ht-footer__links-item">
                            <a href="<?php echo esc_url($ht_link['url']); ?>"
                                <?php if (!empty($ht_link['title'])) : ?>
                                    title="<?php echo esc_attr($ht_link['title']); ?>"
                                <?php endif; ?>
                                <?php if (!empty($ht_link['target'])) : ?>
                                    target="<?php echo esc_attr($ht_link['target']); ?>"
                                <?php endif; ?>
                                <?php if ('_blank' === $ht_link['target']) : ?>
                                    rel="<?php echo esc_attr(trim($ht_link['rel'] . ' noopener')); ?>"
                                <?php elseif (!empty($ht_link['rel'])) : ?>
                                    rel="<?php echo esc_attr($ht_link['rel']); ?>"
                                <?php endif; ?>>
                                <?php echo esc_html($ht_link['label']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <?php if ($ht_contacts || $ht_socials) : ?>
            <div class="ht-footer__contacts-socials">

                <?php if ($ht_contacts) : ?>
                    <div class="ht-footer__contacts">
                        <?php foreach ($ht_contacts as $ht_contact) : ?>
                            <div class="ht-footer__contacts-item">
                                <?php if (!empty($ht_contact['href'])) : ?>
                                    <a href="<?php echo esc_url($ht_contact['href'], array('http', 'https', 'tel', 'mailto')); ?>">
                                        <?php echo esc_html($ht_contact['label']); ?>
                                    </a>
                                <?php else : ?>
                                    <span><?php echo esc_html($ht_contact['label']); ?></span>
                                <?php endif; ?>

                                <?php if (!empty($ht_contact['schedule'])) : ?>
                                    <span class="ht-footer__contacts-schedule">
                                        <?php echo esc_html($ht_contact['schedule']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($ht_socials) : ?>
                    <ul class="ht-footer__socials">
                        <?php foreach ($ht_socials as $ht_social) : ?>
                            <li class="ht-footer__socials-item">
                                <a href="<?php echo esc_url($ht_social['url']); ?>"
                                   target="_blank" rel="noopener"
                                   aria-label="<?php echo esc_attr($ht_social['label']); ?>">
                                    <?php ht_icon($ht_social['icon']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <?php if ($ht_payments) : ?>
            <div class="ht-footer__payments">
                <ul class="ht-footer__payments-list">
                    <?php foreach ($ht_payments as $ht_payment) : ?>
                        <li class="ht-footer__payments-item ht-footer__payments-item--<?php echo esc_attr($ht_payment['slug']); ?>">
                            <img src="<?php echo esc_url($ht_payment['src']); ?>"
                                 alt="<?php echo esc_attr($ht_payment['label']); ?>"
                                 loading="lazy" decoding="async"/>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>

    <div class="ht-footer__bottom">
        <?php if ($ht_entity) : ?>
            <p class="ht-footer__legal">
                <span><?php echo esc_html($ht_entity['name']); ?></span>
                <?php if ('' !== $ht_entity['idno']) : ?>
                    <span>
                        <?php
                        /* translators: %s: codul fiscal (IDNO) al companiei. */
                        echo esc_html(sprintf(__('IDNO %s', 'herbal-therapy'), $ht_entity['idno']));
                        ?>
                    </span>
                <?php endif; ?>
                <span>
                    <?php
                    /* translators: %s: adresa juridica a companiei. */
                    echo esc_html(sprintf(__('Sediul: %s', 'herbal-therapy'), $ht_entity['address']));
                    ?>
                </span>
                <?php if (is_email($ht_entity['email'])) : ?>
                    <a href="<?php echo esc_url('mailto:' . antispambot($ht_entity['email'])); ?>">
                        <?php echo esc_html(antispambot($ht_entity['email'])); ?>
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php echo wp_kses_post(ht_footer_copyright()); ?>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
