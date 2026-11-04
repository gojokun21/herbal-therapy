<?php
/**
 * Recenziile de produs.
 *
 * Referinta de design nu are bloc de recenzii, asa ca structura de aici e
 * scrisa in limbajul temei: rezumatul cu nota medie si distributia notelor,
 * lista de recenzii cu initiala autorului si formularul cu stele.
 *
 * Sabloanele pe care le foloseste stau in woocommerce/:
 *   single-product-reviews.php     - invelisul (rezumat + lista + formular)
 *   single-product/review.php      - o recenzie
 *   single-product/review-meta.php - autorul, eticheta de cumparator, data
 *
 * Se incarca din inc/woocommerce.php, deci doar cand pluginul e activ.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Stelele
 * ------------------------------------------------------------------------ */

/**
 * Conturul unei stele, pe acelasi viewBox de 20x20 ca restul iconitelor.
 *
 * Nu trece prin ht_icons(): randul de stele are nevoie de doua straturi (unul
 * gol, unul plin taiat pe latime), iar ht_get_icon() deseneaza o singura data.
 *
 * @return string
 */
function ht_star_svg()
{
    return '<svg class="ht-stars__icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">'
        . '<path d="m10 1.9 2.5 5 5.6.8-4 3.9.9 5.5-5-2.6-5 2.6.9-5.5-4-3.9 5.6-.8 2.5-5Z"/></svg>';
}

/**
 * Un rand de cinci stele, umplut proportional cu nota.
 *
 * Umplerea se face cu un al doilea rand, identic, taiat pe latime - asa ies si
 * notele fractionare (4,3 din 5), nu doar cele intregi.
 *
 * @param float $rating Nota, intre 0 si 5.
 * @param array $args   'label' - textul pentru cititoarele de ecran,
 *                      'size'  - modificatorul de marime ('sm', 'lg'),
 *                      'class' - clase in plus.
 *
 * @return string
 */
function ht_stars($rating, $args = array())
{
    $args = wp_parse_args($args, array(
        'label' => '',
        'size'  => '',
        'class' => '',
    ));

    $rating = max(0, min(5, (float)$rating));
    $row = str_repeat(ht_star_svg(), 5);

    $classes = 'ht-stars';

    if ('' !== $args['size']) {
        $classes .= ' ht-stars--' . $args['size'];
    }

    if ('' !== $args['class']) {
        $classes .= ' ' . $args['class'];
    }

    $label = $args['label'];

    if ('' === $label) {
        /* translators: %s: nota, cu o zecimala. */
        $label = sprintf(__('Nota %s din 5', 'herbal-therapy'), number_format_i18n($rating, 1));
    }

    return sprintf(
        '<span class="%1$s" role="img" aria-label="%2$s">'
        . '<span class="ht-stars__row">%3$s</span>'
        . '<span class="ht-stars__row ht-stars__row--fill" style="width:%4$s">%3$s</span>'
        . '</span>',
        esc_attr($classes),
        esc_attr($label),
        $row,
        esc_attr(round($rating / 5 * 100, 2) . '%')
    );
}

/**
 * Inlocuieste randul de stele al pluginului cu cel al temei.
 *
 * Filtrul prinde toate locurile unde WooCommerce scoate o nota - cardul de
 * produs, blocul de recenzii, widget-urile - deci stelele arata la fel peste tot.
 *
 * @param string $html   Markup-ul pluginului.
 * @param float  $rating Nota.
 * @param int    $count  Numarul de note.
 *
 * @return string
 */
function ht_rating_html($html, $rating, $count)
{
    if ((float)$rating <= 0) {
        return $html;
    }

    return ht_stars($rating, array('class' => 'star-rating'));
}

add_filter('woocommerce_product_get_rating_html', 'ht_rating_html', 10, 3);

/* ---------------------------------------------------------------------------
 * Datele rezumatului
 * ------------------------------------------------------------------------ */

/**
 * Distributia notelor, de la 5 la 1.
 *
 * @param WC_Product $product Produsul.
 *
 * @return array Randuri cu 'stars', 'count' si 'percent'.
 */
function ht_review_breakdown($product)
{
    $counts = (array)$product->get_rating_counts();
    $total = array_sum($counts);
    $rows = array();

    for ($stars = 5; $stars >= 1; $stars--) {
        $count = isset($counts[$stars]) ? (int)$counts[$stars] : 0;

        $rows[] = array(
            'stars'   => $stars,
            'count'   => $count,
            'percent' => $total > 0 ? round($count / $total * 100, 2) : 0,
        );
    }

    return $rows;
}

/* ---------------------------------------------------------------------------
 * O recenzie
 * ------------------------------------------------------------------------ */

/*
 * In design recenzia e un card fara imagine de autor, deci Gravatar-ul iese de
 * pe hook. Hook-ul ramane, ca extensiile sa aiba unde scrie; ca sa se intoarca
 * imaginea:
 *
 *   add_action('woocommerce_review_before', 'woocommerce_review_display_gravatar', 10);
 */
remove_action('woocommerce_review_before', 'woocommerce_review_display_gravatar', 10);

/**
 * Numarul de raspunsuri la o recenzie.
 *
 * @param WP_Comment $comment Recenzia.
 *
 * @return int
 */
function ht_review_replies($comment)
{
    $replies = get_comments(array(
        'parent' => (int)$comment->comment_ID,
        'status' => 'approve',
        'count'  => true,
    ));

    return (int)$replies;
}

/**
 * Imaginile atasate unei recenzii.
 *
 * WooCommerce nu are camp de imagini pe recenzie; sablonul e pregatit pentru
 * ele, iar sursa se leaga aici - dintr-un camp ACF pe comentariu sau dintr-un
 * plugin de recenzii cu fotografii:
 *
 *   add_filter('ht_review_images', function ($images, $comment) { ... }, 10, 2);
 *
 * @param WP_Comment $comment Recenzia.
 *
 * @return array Lista de URL-uri.
 */
function ht_review_images($comment)
{
    $images = array();

    if (function_exists('get_field')) {
        $field = get_field('imagini', 'comment_' . (int)$comment->comment_ID);

        foreach ((array)$field as $item) {
            if (is_array($item) && isset($item['url'])) {
                $images[] = $item['url'];
            } elseif (is_string($item) && '' !== $item) {
                $images[] = $item;
            }
        }
    }

    return array_filter((array)apply_filters('ht_review_images', $images, $comment));
}

/**
 * Recenziile noi primele.
 *
 * WordPress listeaza comentariile de la cel mai vechi; la un magazin conteaza
 * insa ce s-a scris ultima data. Filtrul atinge doar lista de recenzii, nu si
 * comentariile de pe articole.
 *
 * @param array $args Argumentele lui wp_list_comments().
 *
 * @return array
 */
function ht_review_list_args($args)
{
    $args['reverse_top_level'] = true;

    return $args;
}

add_filter('woocommerce_product_review_list_args', 'ht_review_list_args');

/* ---------------------------------------------------------------------------
 * Raspunsurile magazinului
 *
 * Sub o recenzie raspunde doar magazinul. Din pagina produsului, butonul
 * "Raspunde" il vad numai cei care modereaza comentariile (administratorul,
 * managerul de magazin); din administrare merge si nativ, din
 * Produse -> Recenzii -> Raspunde. Raspunsul e un comentariu cu parinte, deci
 * WooCommerce nu-l pune nici la numarul de recenzii, nici la nota medie.
 * ------------------------------------------------------------------------ */

/**
 * Poate utilizatorul curent sa raspunda la recenzii din pagina produsului?
 *
 * @return bool
 */
function ht_review_can_reply()
{
    return is_user_logged_in() && current_user_can('moderate_comments');
}

/**
 * E un raspuns al magazinului?
 *
 * Se recunoaste dupa autor - un cont care modereaza comentariile -, deci merge
 * la fel pentru raspunsurile scrise din pagina si pentru cele din administrare.
 *
 * @param WP_Comment $comment Comentariul.
 *
 * @return bool
 */
function ht_review_is_staff_reply($comment)
{
    if (!$comment instanceof WP_Comment) {
        return false;
    }

    $user_id = (int)$comment->user_id;

    return (int)$comment->comment_parent > 0
        && $user_id > 0
        && user_can($user_id, 'moderate_comments');
}

/**
 * Recenzia la care se raspunde, cand pagina e deschisa cu ?replytocom=.
 *
 * Asa ajunge pagina doar fara JavaScript - altfel comment-reply.js muta
 * formularul pe loc. Atunci formularul vine direct in modul de raspuns.
 *
 * @return int ID-ul recenziei sau 0.
 */
function ht_review_reply_target()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- doar alege modul formularului.
    if (empty($_GET['replytocom']) || !ht_review_can_reply()) {
        return 0;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- vezi mai sus.
    $comment = get_comment(absint(wp_unslash($_GET['replytocom'])));

    if (!$comment || (int)$comment->comment_post_ID !== (int)get_the_ID() || '1' !== $comment->comment_approved) {
        return 0;
    }

    return (int)$comment->comment_ID;
}

/**
 * Raspunsul magazinului trece de regula "doar cumparatorii".
 *
 * Cand WooCommerce cere ca recenziile sa vina doar de la cei care au cumparat
 * produsul, el opreste orice comentariu pe produs al altcuiva - si deci si
 * raspunsul magazinului. Pentru un raspuns scris de cineva care modereaza
 * comentariile, verificarea se scoate pe cererea curenta.
 *
 * @param int $post_id Produsul.
 */
function ht_review_allow_staff_reply($post_id)
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- formularul de comentarii nu are nonce; valoarea doar se compara.
    $parent = isset($_POST['comment_parent']) ? absint(wp_unslash($_POST['comment_parent'])) : 0;

    if ($parent > 0 && ht_review_can_reply() && 'product' === get_post_type($post_id)) {
        remove_action('pre_comment_on_post', array('WC_Comments', 'validate_product_review_verified_owners'));
    }
}

/* inaintea verificarii pluginului, care sta pe prioritatea implicita (10) */
add_action('pre_comment_on_post', 'ht_review_allow_staff_reply', 5);

/* ---------------------------------------------------------------------------
 * Formularul
 * ------------------------------------------------------------------------ */

/**
 * Un camp de text al formularului.
 *
 * @param string $key          Numele campului.
 * @param string $label        Eticheta.
 * @param string $type         Tipul input-ului.
 * @param string $value        Valoarea completata dinainte.
 * @param bool   $required     Camp obligatoriu.
 * @param string $autocomplete Valoarea atributului 'autocomplete'.
 *
 * @return string
 */
function ht_review_form_field($key, $label, $type, $value, $required, $autocomplete)
{
    return sprintf(
        '<p class="ht-rev__field comment-form-%1$s">'
        . '<label class="ht-rev__label" for="%1$s">%2$s%3$s</label>'
        . '<input class="ht-rev__input" id="%1$s" name="%1$s" type="%4$s" autocomplete="%5$s" value="%6$s"%7$s></p>',
        esc_attr($key),
        esc_html($label),
        $required ? '&nbsp;<span class="required">*</span>' : '',
        esc_attr($type),
        esc_attr($autocomplete),
        esc_attr($value),
        $required ? ' required' : ''
    );
}

/**
 * Alegerea notei, ca cinci stele.
 *
 * Sunt butoane radio, nu lista derulanta: se aleg si de la tastatura, se trimit
 * fara JavaScript si nu mai au nevoie de scriptul pluginului, care oricum
 * porneste doar cand gaseste un '#rating' de tip <select>.
 *
 * Stelele stau in DOM de la 5 la 1 si se intorc din CSS ('row-reverse'), ca
 * regula 'input:checked ~ .ht-rev__star' sa poata colora si stelele dinainte.
 *
 * @return string
 */
function ht_review_rating_field()
{
    if (!wc_review_ratings_enabled()) {
        return '';
    }

    $required = wc_review_ratings_required();

    $labels = array(
        5 => __('Perfect', 'herbal-therapy'),
        4 => __('Bun', 'herbal-therapy'),
        3 => __('Acceptabil', 'herbal-therapy'),
        2 => __('Slab', 'herbal-therapy'),
        1 => __('Foarte slab', 'herbal-therapy'),
    );

    $stars = '';

    foreach ($labels as $value => $label) {
        $stars .= sprintf(
            '<input class="ht-rev__star-input ht-visually-hidden" type="radio" name="rating" '
            . 'id="ht-rating-%1$d" value="%1$d"%2$s>'
            . '<label class="ht-rev__star" for="ht-rating-%1$d">%3$s'
            . '<span class="ht-visually-hidden">%4$s</span></label>',
            (int)$value,
            $required ? ' required' : '',
            ht_star_svg(),
            esc_html(sprintf(
                /* translators: 1: nota aleasa, 2: denumirea notei. */
                __('%1$d din 5 - %2$s', 'herbal-therapy'),
                $value,
                $label
            ))
        );
    }

    return sprintf(
        '<fieldset class="ht-rev__field ht-rev__field--rating" data-ht-rev-rating>'
        . '<legend class="ht-rev__label">%1$s%2$s</legend>'
        . '<div class="ht-rev__picker">%3$s</div>'
        . '</fieldset>',
        esc_html__('Evaluarea ta', 'herbal-therapy'),
        $required ? '&nbsp;<span class="required">*</span>' : '',
        $stars
    );
}

/**
 * Atributele unui text care difera intre recenzie si raspuns.
 *
 * Ambele variante stau pe element, iar single-product.js o alege pe cea
 * potrivita cand formularul trece pe raspuns si inapoi.
 *
 * @param string $review Textul pentru recenzie.
 * @param string $reply  Textul pentru raspuns.
 *
 * @return string
 */
function ht_review_text_attrs($review, $reply)
{
    return sprintf(
        ' data-ht-rev-review="%1$s" data-ht-rev-reply="%2$s"',
        esc_attr($review),
        esc_attr($reply)
    );
}

/**
 * Formularul de recenzie, in stilul temei.
 *
 * Acelasi formular scrie si raspunsul magazinului: cand se apasa "Raspunde",
 * scriptul ascunde stelele si schimba textele (vezi ht_review_text_attrs()).
 *
 * @param array $args Argumentele construite de sablonul pluginului.
 *
 * @return array
 */
function ht_review_form_args($args)
{
    $commenter = wp_get_current_commenter();
    $required = (bool)get_option('require_name_email', 1);

    /* fara JavaScript, pagina cu ?replytocom= vine direct cu formularul de raspuns */
    $is_reply = ht_review_reply_target() > 0;

    $submit_review = __('Trimite recenzia', 'herbal-therapy');
    $submit_reply = __('Trimite răspunsul', 'herbal-therapy');
    $label_review = __('Recenzia ta', 'herbal-therapy');
    $label_reply = __('Răspunsul tău', 'herbal-therapy');
    $hint_review = __('Spune-ne cum ți s-a părut produsul: textura, mirosul, rezultatul după câteva utilizări.', 'herbal-therapy');
    $hint_reply = __('Răspunsul se publică sub recenzie, în numele magazinului.', 'herbal-therapy');

    $args['title_reply'] = __('Scrie o recenzie', 'herbal-therapy');
    $args['title_reply_before'] = '<span id="reply-title" class="ht-rev__form-title" role="heading" aria-level="3">';
    $args['title_reply_after'] = '</span>';
    $args['cancel_reply_link'] = __('Anulează', 'herbal-therapy');
    $args['class_container'] = 'comment-respond ht-rev__respond';
    $args['class_form'] = 'comment-form ht-rev__form';
    $args['class_submit'] = 'ht-rev__submit';
    $args['label_submit'] = $is_reply ? $submit_reply : $submit_review;
    $args['logged_in_as'] = '';
    $args['comment_notes_before'] = '';
    $args['comment_notes_after'] = '';
    $args['submit_field'] = '<p class="form-submit ht-rev__actions">%1$s %2$s</p>';

    /* submit_button trece prin sprintf(), deci un '%' din traducere se dubleaza */
    $args['submit_button'] = '<button name="%1$s" type="submit" id="%2$s" class="%3$s"'
        . str_replace('%', '%%', ht_review_text_attrs($submit_review, $submit_reply))
        . '>%4$s</button>';

    $args['fields'] = array(
        'author' => ht_review_form_field(
            'author',
            __('Numele tău', 'herbal-therapy'),
            'text',
            $commenter['comment_author'],
            $required,
            'name'
        ),
        'email'  => ht_review_form_field(
            'email',
            __('Adresa de email', 'herbal-therapy'),
            'email',
            $commenter['comment_author_email'],
            $required,
            'email'
        ),
    );

    $account = wc_get_page_permalink('myaccount');

    if ($account) {
        $args['must_log_in'] = sprintf(
            '<p class="ht-rev__notice">%s</p>',
            sprintf(
                /* translators: 1: eticheta de deschidere a legaturii, 2: cea de inchidere. */
                esc_html__('Trebuie să fii %1$sautentificat%2$s ca să lași o recenzie.', 'herbal-therapy'),
                '<a href="' . esc_url($account) . '">',
                '</a>'
            )
        );
    }

    /* un raspuns nu da nota, deci fara JavaScript stelele nici nu se scriu */
    $args['comment_field'] = ($is_reply ? '' : ht_review_rating_field())
        . sprintf(
            '<p class="ht-rev__field comment-form-comment">'
            . '<label class="ht-rev__label" for="comment"><span%1$s>%2$s</span>&nbsp;<span class="required">*</span></label>'
            . '<textarea class="ht-rev__textarea" id="comment" name="comment" cols="45" rows="8" '
            . 'placeholder="%3$s"%4$s required></textarea></p>',
            ht_review_text_attrs($label_review, $label_reply),
            esc_html($is_reply ? $label_reply : $label_review),
            esc_attr($is_reply ? $hint_reply : $hint_review),
            ht_review_text_attrs($hint_review, $hint_reply)
        );

    return $args;
}

add_filter('woocommerce_product_review_comment_form_args', 'ht_review_form_args');

/* ---------------------------------------------------------------------------
 * Fisierele blocului
 * ------------------------------------------------------------------------ */

/**
 * CSS-ul recenziilor.
 *
 * Nu are JavaScript separat: stelele din formular sunt butoane radio, butonul
 * "Scrie o recenzie" e o ancora catre formular, iar sortarea si modul de
 * raspuns al formularului stau in assets/js/single-product.js.
 */
function ht_enqueue_reviews_assets()
{
    if (!is_product()) {
        return;
    }

    wp_enqueue_style(
        'ht-reviews',
        ht_asset_uri('/assets/css/reviews.css'),
        array('ht-style', 'ht-single-product'),
        ht_asset_version('/assets/css/reviews.css')
    );
}

/* dupa ht_enqueue_single_product_assets (21), ca dependenta sa fie inregistrata */
add_action('wp_enqueue_scripts', 'ht_enqueue_reviews_assets', 22);
