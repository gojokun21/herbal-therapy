<?php
/**
 * Continutul primei pagini, pe limbi, pentru bin/seed.php.
 *
 * Sectiunea "Puterea naturii" (inc/home-benefits.php): textele romanesti sunt
 * cele de pe site-ul Shopify, cele rusesti traducerea lor. Iconitele ('file')
 * sunt cele din assets/img/home/benefits/; seed-ul le urca o singura data in
 * biblioteca media si le refoloseste in toate limbile.
 *
 * Sectiunea "Ce spun clientii" (inc/home-reviews.php): fiecare recenzie
 * vorbeste despre produsul la care e legata - textul numeste produsul asa cum
 * apare in catalog, ca sa nu apara o recenzie despre unguent langa o sticla de
 * apa oxigenata. Produsul e dat prin SKU (EAN); seed-ul il cauta si il
 * inlocuieste cu traducerea din limba paginii (Polylang).
 *
 * Fisierul intoarce un tablou indexat dupa codul limbii:
 *   - 'page'     - titlul si slug-ul, folosite doar cand traducerea se creeaza;
 *   - 'texts'    - campurile ACF de text, dupa nume;
 *   - 'benefits' - cardurile: 'file', 'title', 'text' (HTML simplu: <strong>, <a>);
 *   - 'reviews'  - recenziile: 'author', 'rating' (1-5), 'text', 'sku'.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(

    'ro' => array(
        'page' => array('title' => 'Home', 'slug' => 'home'),
        'texts' => array(
            'ht_home_benefits_title' => 'Puterea naturii, concentrată în fiecare produs',
        ),
        'benefits' => array(
            array(
                'file'  => 'formule-unice.png',
                'title' => 'Formule Unice',
                'text'  => 'Produsele noastre conțin <strong>extracte personalizate</strong>, dezvoltate special <strong>pentru a aborda nevoile tale specifice</strong>.',
            ),
            array(
                'file'  => 'eficienta-maxima.png',
                'title' => 'Eficiență Maximă',
                'text'  => 'Realizarea internă a extractelor ne permite să <strong>maximizăm concentrația compușilor activi</strong>, oferind <strong>produse mai eficiente</strong> și <strong>rezultate mai rapide</strong>.',
            ),
            array(
                'file'  => 'calitate-garantata.png',
                'title' => 'Calitate Garantată',
                'text'  => 'Ne monitorizăm direct procesul de producție, asigurându-ne că <strong>plantele sunt cultivate organic</strong>, <strong>fără pesticide sau chimicale</strong> dăunătoare.',
            ),
            array(
                'file'  => 'grija-pentru-mediu.png',
                'title' => 'Grijă pentru Mediu',
                'text'  => 'Prin producția internă a extractelor, adoptăm practici ecologice ce reduc impactul asupra mediului și <strong>susțin un viitor sănătos pentru tine și cei dragi.</strong>',
            ),
        ),
        'reviews' => array(
            array(
                'author' => 'Maria C.',
                'rating' => 5,
                'sku'    => '6427321003055', /* Unguent cu Ulei de Cătină 50 ml */
                'text'   => 'Unguentul cu ulei de cătină mi-a calmat pielea după doar câteva zile de folosire. Miroase natural și se absoarbe repede, îl recomand oricui are pielea sensibilă.',
            ),
            array(
                'author' => 'Ion Rusu',
                'rating' => 5,
                'sku'    => '4840257008956', /* Sirop IMUNO Pătlagină + Vitamina C 200 ml */
                'text'   => 'Comand de aici de peste un an. Siropul IMUNO cu pătlagină și vitamina C e nelipsit iarna la noi în casă, copiii îl iau fără mofturi. Livrarea a ajuns a doua zi.',
            ),
            array(
                'author' => 'Ana-Maria D.',
                'rating' => 4,
                'sku'    => '6427321000795', /* Șampon cu extracte de Urzică și Immortelle 300 ml */
                'text'   => 'Șamponul cu urzică și immortelle chiar face diferența, părul e mai puțin gras și are volum. Aș vrea și o variantă de un litru, cel de 300 ml se termină repede.',
            ),
            array(
                'author' => 'Vasile P.',
                'rating' => 5,
                'sku'    => '6427321002904', /* REUMIX Herbal, Cremă Emulgel 100 ml */
                'text'   => 'REUMIX Herbal m-a ajutat la durerile de spate mai mult decât multe geluri scumpe din farmacie. Produs local, preț corect, ce să mai ceri.',
            ),
            array(
                'author' => 'Elena S.',
                'rating' => 5,
                'sku'    => '6427321002263', /* Cremă de corp hidratantă cu D-panthenol, pepene galben 250 ml */
                'text'   => 'Crema de corp hidratantă cu D-panthenol e superbă, textură fină și un parfum discret de pepene galben. Se vede că e făcută de o fabrică serioasă, nu de un brand de internet.',
            ),
            array(
                'author' => 'Cristina M.',
                'rating' => 4,
                'sku'    => '4840257008703', /* Multivitamine + Minerale Adulți N30 */
                'text'   => 'Multivitaminele cu minerale pentru adulți au ambalaj îngrijit și prospect clar în română. După o lună de cură mă simt vizibil mai energică dimineața.',
            ),
            array(
                'author' => 'Dumitru B.',
                'rating' => 5,
                'sku'    => '4840257007294', /* Pudră mentolată 1% 50 g */
                'text'   => 'Pudra mentolată e exact ca pe vremuri, răcorește imediat și calitatea e constantă. Am luat și apă oxigenată, comanda a venit bine împachetată, nimic vărsat.',
            ),
            array(
                'author' => 'Natalia G.',
                'rating' => 5,
                'sku'    => '6427321000894', /* Gel de duș cu Gălbenele și Coada Șoricelului 500 ml */
                'text'   => 'Gelul de duș cu gălbenele și coada șoricelului lasă pielea catifelată și miroase discret. Am luat trei la ofertă și nu regret nimic.',
            ),
        ),
    ),

    'ru' => array(
        'page' => array('title' => 'Главная', 'slug' => 'home'),
        'texts' => array(
            'ht_home_benefits_title' => 'Сила природы, сконцентрированная в каждом продукте',
        ),
        'benefits' => array(
            array(
                'file'  => 'formule-unice.png',
                'title' => 'Уникальные формулы',
                'text'  => 'Наши продукты содержат <strong>индивидуально разработанные экстракты</strong>, созданные специально <strong>для решения ваших конкретных потребностей</strong>.',
            ),
            array(
                'file'  => 'eficienta-maxima.png',
                'title' => 'Максимальная эффективность',
                'text'  => 'Собственное производство экстрактов позволяет нам <strong>максимально повысить концентрацию активных веществ</strong>, предлагая <strong>более эффективные продукты</strong> и <strong>более быстрые результаты</strong>.',
            ),
            array(
                'file'  => 'calitate-garantata.png',
                'title' => 'Гарантированное качество',
                'text'  => 'Мы напрямую контролируем процесс производства, следя за тем, чтобы <strong>растения выращивались органически</strong>, <strong>без пестицидов и вредных химикатов</strong>.',
            ),
            array(
                'file'  => 'grija-pentru-mediu.png',
                'title' => 'Забота об окружающей среде',
                'text'  => 'Благодаря собственному производству экстрактов мы применяем экологичные практики, которые снижают воздействие на окружающую среду и <strong>поддерживают здоровое будущее для вас и ваших близких.</strong>',
            ),
        ),
        'reviews' => array(
            array(
                'author' => 'Мария К.',
                'rating' => 5,
                'sku'    => '6427321003055', /* Мазь с облепиховым маслом 50 мл */
                'text'   => 'Мазь с облепиховым маслом успокоила мою кожу всего за несколько дней. Пахнет натурально и быстро впитывается — рекомендую всем, у кого чувствительная кожа.',
            ),
            array(
                'author' => 'Ион Русу',
                'rating' => 5,
                'sku'    => '4840257008956', /* Сироп IMUNO Подорожник + Витамин C 200 мл */
                'text'   => 'Заказываю здесь уже больше года. Сироп IMUNO с подорожником и витамином C зимой у нас дома незаменим, дети пьют его без капризов. Доставка пришла на следующий день.',
            ),
            array(
                'author' => 'Анна-Мария Д.',
                'rating' => 4,
                'sku'    => '6427321000795', /* Шампунь с экстрактами крапивы и бессмертника 300 мл */
                'text'   => 'Шампунь с крапивой и бессмертником действительно работает: волосы меньше жирнятся, появился объём. Хотелось бы литровую упаковку — 300 мл быстро заканчиваются.',
            ),
            array(
                'author' => 'Василе П.',
                'rating' => 5,
                'sku'    => '6427321002904', /* REUMIX Herbal, крем-эмульгель 100 мл */
                'text'   => 'REUMIX Herbal помог мне при болях в спине лучше многих дорогих гелей из аптеки. Местный продукт, честная цена — что ещё нужно.',
            ),
            array(
                'author' => 'Елена С.',
                'rating' => 5,
                'sku'    => '6427321002263', /* Увлажняющий крем для тела с D-пантенолом, дыня 250 мл */
                'text'   => 'Увлажняющий крем для тела с D-пантенолом превосходный: нежная текстура и лёгкий аромат дыни. Видно, что его делает серьёзная фабрика, а не интернет-бренд.',
            ),
            array(
                'author' => 'Кристина М.',
                'rating' => 4,
                'sku'    => '4840257008703', /* Мультивитамины + Минералы для взрослых N30 */
                'text'   => 'У мультивитаминов с минералами для взрослых аккуратная упаковка и понятная инструкция. После месяца курса по утрам чувствую заметно больше энергии.',
            ),
            array(
                'author' => 'Думитру Б.',
                'rating' => 5,
                'sku'    => '4840257007294', /* Ментоловая пудра 1% 50 г */
                'text'   => 'Ментоловая пудра — как в старые добрые времена: сразу освежает, качество стабильное. Взял ещё перекись водорода, заказ пришёл хорошо упакованным, ничего не разлилось.',
            ),
            array(
                'author' => 'Наталия Г.',
                'rating' => 5,
                'sku'    => '6427321000894', /* Гель для душа с календулой и тысячелистником 500 мл */
                'text'   => 'Гель для душа с календулой и тысячелистником оставляет кожу бархатистой и пахнет ненавязчиво. Взяла три по акции и ничуть не жалею.',
            ),
        ),
    ),
);
