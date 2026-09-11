<?php
/**
 * Continutul paginilor "Contact" si "B2B", pe limbi, pentru bin/seed.php.
 *
 * Textele romanesti sunt cele scrise deja in pagini din administrare; cele
 * rusesti sunt traducerea lor. Fotografiile nu sunt aici: seed-ul le copiaza
 * din pagina in limba implicita. Formularele Contact Form 7 se cauta dupa
 * titlu ('form'), in ordinea data, si se leaga primul gasit.
 *
 * Fisierul intoarce un tablou indexat dupa sablon, apoi dupa codul limbii:
 *   - 'page'  - titlul si slug-ul, folosite doar cand traducerea se creeaza;
 *   - 'texts' - campurile ACF de text, dupa nume;
 *   - 'rows'  - campurile repetabile, dupa cheia campului;
 *   - 'form'  - titlurile posibile ale formularului CF7.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(

    /* -----------------------------------------------------------------------
     * Contact
     * -------------------------------------------------------------------- */
    'templates/contact.php' => array(
        'ro' => array(
            'page' => array('title' => 'Contact', 'slug' => 'contact'),
            'texts' => array(
                'ht_contact_intro'        => '<p>Dacă ai întrebări legate de produsele noastre, comenzi, recomandări personalizate sau pur și simplu vrei să afli mai multe despre cum transformăm darurile naturii în soluții pentru starea ta de bine, contactează-ne prin formularul de mai jos.</p>',
                'ht_contact_card_title'   => 'Modalități alternative de a ne contacta',
                'ht_contact_social_label' => 'Hai să rămânem conectați!',
                'ht_contact_social_text'  => '<p>Urmărește-ne pe <a href="https://www.facebook.com/herbaltherapy.romania" target="_blank" rel="noopener">Facebook</a>, <a href="https://www.instagram.com/herbaltherapy.ro" target="_blank" rel="noopener"><strong>Instagram</strong></a> pentru a fi la curent cu ultimele noutăți, oferte speciale și sfaturi despre cum poți îmbrățișa un stil de viață sănătos și natural.</p>',
                'ht_contact_form_title'   => 'Îți promitem că vom răspunde cât mai repede posibil!',
            ),
            'rows' => array(
                'field_ht_contact_methods' => array(
                    array('field_ht_contact_method_icon' => 'location', 'field_ht_contact_method_label' => 'La sediu comercial', 'field_ht_contact_method_href' => '', 'field_ht_contact_method_lines' => 'Strada Fantanii Nr. 17A, Brașov, România'),
                    array('field_ht_contact_method_icon' => 'phone', 'field_ht_contact_method_label' => 'Prin telefon', 'field_ht_contact_method_href' => 'tel:+37378884061', 'field_ht_contact_method_lines' => '+373 78 88 40 61'),
                    array('field_ht_contact_method_icon' => 'mail', 'field_ht_contact_method_label' => 'Email', 'field_ht_contact_method_href' => 'mailto:info@herbal-therapy.ro', 'field_ht_contact_method_lines' => 'info@herbal-therapy.ro'),
                    array('field_ht_contact_method_icon' => 'clock', 'field_ht_contact_method_label' => 'Program', 'field_ht_contact_method_href' => '', 'field_ht_contact_method_lines' => "Luni - Vineri: 08:00 - 17:00\nSâmbătă - Duminică: Închis"),
                ),
                'field_ht_contact_socials' => array(
                    array('field_ht_contact_social_icon' => 'facebook', 'field_ht_contact_social_url' => 'https://www.facebook.com/herbaltherapy.romania'),
                    array('field_ht_contact_social_icon' => 'instagram', 'field_ht_contact_social_url' => 'https://www.instagram.com/herbaltherapy.ro'),
                ),
            ),
            'form' => array('Contact'),
        ),

        'ru' => array(
            'page' => array('title' => 'Контакты', 'slug' => 'kontakty'),
            'texts' => array(
                'ht_contact_intro'        => '<p>Если у вас есть вопросы о нашей продукции, заказах, персональных рекомендациях или вы просто хотите узнать больше о том, как мы превращаем дары природы в решения для вашего здоровья, свяжитесь с нами через форму ниже.</p>',
                'ht_contact_card_title'   => 'Другие способы связаться с нами',
                'ht_contact_social_label' => 'Давайте оставаться на связи!',
                'ht_contact_social_text'  => '<p>Подписывайтесь на нас в <a href="https://www.facebook.com/herbaltherapy.romania" target="_blank" rel="noopener">Facebook</a> и <a href="https://www.instagram.com/herbaltherapy.ro" target="_blank" rel="noopener"><strong>Instagram</strong></a>, чтобы первыми узнавать о новинках, специальных предложениях и советах о том, как вести здоровый и естественный образ жизни.</p>',
                'ht_contact_form_title'   => 'Обещаем ответить как можно скорее!',
            ),
            'rows' => array(
                'field_ht_contact_methods' => array(
                    array('field_ht_contact_method_icon' => 'location', 'field_ht_contact_method_label' => 'В коммерческом офисе', 'field_ht_contact_method_href' => '', 'field_ht_contact_method_lines' => 'ул. Фынтыний 17A, Брашов, Румыния'),
                    array('field_ht_contact_method_icon' => 'phone', 'field_ht_contact_method_label' => 'По телефону', 'field_ht_contact_method_href' => 'tel:+37378884061', 'field_ht_contact_method_lines' => '+373 78 88 40 61'),
                    array('field_ht_contact_method_icon' => 'mail', 'field_ht_contact_method_label' => 'Эл. почта', 'field_ht_contact_method_href' => 'mailto:info@herbal-therapy.ro', 'field_ht_contact_method_lines' => 'info@herbal-therapy.ro'),
                    array('field_ht_contact_method_icon' => 'clock', 'field_ht_contact_method_label' => 'График работы', 'field_ht_contact_method_href' => '', 'field_ht_contact_method_lines' => "Понедельник – Пятница: 08:00 – 17:00\nСуббота – Воскресенье: выходной"),
                ),
                'field_ht_contact_socials' => array(
                    array('field_ht_contact_social_icon' => 'facebook', 'field_ht_contact_social_url' => 'https://www.facebook.com/herbaltherapy.romania'),
                    array('field_ht_contact_social_icon' => 'instagram', 'field_ht_contact_social_url' => 'https://www.instagram.com/herbaltherapy.ro'),
                ),
            ),
            'form' => array('Contact RU'),
        ),
    ),

    /* -----------------------------------------------------------------------
     * B2B
     * -------------------------------------------------------------------- */
    'templates/b2b.php' => array(
        'ro' => array(
            'page' => array('title' => 'B2B', 'slug' => 'b2b'),
            'texts' => array(
                'ht_b2b_title'      => 'Vânzări B2B',
                'ht_b2b_intro'      => '<p>Herbal Therapy este deschisă colaborărilor cu farmacii, magazine naturiste, magazine online, distribuitori și alte companii interesate de comercializarea produselor noastre.</p><p>Punem la dispoziția partenerilor o gamă variată de vitamine și suplimente, siropuri, produse cosmetice, produse de îngrijire personală și produse pentru copii, dezvoltate pentru sănătatea și îngrijirea întregii familii.</p><p>Prin colaborarea B2B cu Herbal Therapy poți beneficia de condiții comerciale dedicate partenerilor, acces la gama noastră de produse și suport din partea echipei noastre pentru dezvoltarea unei colaborări pe termen lung.</p>',
                'ht_b2b_form_title' => 'Formular de colaborare B2B',
            ),
            'rows' => array(),
            'form' => array('B2b Form'),
        ),

        'ru' => array(
            'page' => array('title' => 'B2B', 'slug' => 'b2b'),
            'texts' => array(
                'ht_b2b_title'      => 'Продажи B2B',
                'ht_b2b_intro'      => '<p>Herbal Therapy открыта к сотрудничеству с аптеками, магазинами натуральных продуктов, интернет-магазинами, дистрибьюторами и другими компаниями, заинтересованными в реализации нашей продукции.</p><p>Мы предлагаем партнёрам широкий ассортимент витаминов и пищевых добавок, сиропов, косметики, средств личной гигиены и товаров для детей, разработанных для здоровья и заботы обо всей семье.</p><p>Сотрудничество B2B с Herbal Therapy даёт вам специальные коммерческие условия для партнёров, доступ к нашему ассортименту и поддержку нашей команды в развитии долгосрочного сотрудничества.</p>',
                'ht_b2b_form_title' => 'Форма сотрудничества B2B',
            ),
            'rows' => array(),
            'form' => array('B2b RU'),
        ),
    ),
);
