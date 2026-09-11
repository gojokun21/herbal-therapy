<?php
/**
 * Template Name: Contact
 *
 * Titlul vine din pagina; restul - textul de sub el, cartonasul cu datele de
 * contact si formularul - din grupul ACF "Pagina de contact", cu ce scrie in
 * design pe post de rezerva. Vezi inc/contact.php.
 *
 * @package Herbal_Therapy
 */

get_header();
?>

<main id="primary" class="site-main site-main--contact">
    <?php
    while (have_posts()) :
        the_post();

        ht_contact_page(array(
            'title' => get_the_title(),
        ));
    endwhile;
    ?>
</main>

<?php
get_footer();
