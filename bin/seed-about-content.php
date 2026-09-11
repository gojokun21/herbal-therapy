<?php
/**
 * Continutul paginii "Despre noi", pe limbi, pentru bin/seed.php.
 *
 * Textele romanesti sunt cele din design (Figma, frame-ul "Despre noi");
 * cele rusesti sunt traducerea lor. Fotografiile sunt aceleasi in toate
 * limbile si vin din /assets/img/about.
 *
 * Fisierul intoarce un tablou indexat dupa codul limbii Polylang ('ro', 'ru').
 * Fiecare limba are:
 *   - 'page'      - titlul si slug-ul paginii, folosite doar cand traducerea
 *                   trebuie creata;
 *   - 'texts'     - campurile ACF de text, dupa nume;
 *   - 'values'    - cele trei cartonase (title, text, file);
 *   - 'locations' - punctele de lucru (label, address, file, href).
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(

    /* -----------------------------------------------------------------------
     * Romana
     * -------------------------------------------------------------------- */
    'ro' => array(
        'page' => array(
            'title' => 'Despre noi',
            'slug'  => 'despre-noi',
        ),

        'texts' => array(
            'ht_about_hero_title' => 'Despre Herbal Therapy',
            'ht_about_hero_intro' => '<strong>Herbal Therapy</strong> este un furnizor lider <strong>B2C</strong> și <strong>B2B</strong> de suplimente alimentare și produse cosmetice premium, dedicate valorificării puterii naturii. Suntem specializați în crearea de produse de înaltă calitate formulate cu extracte de plante puternice și ingrediente locale din surse durabile.',

            'ht_about_care_title' => 'Cu grijă pentru corpul dvs și mediu',
            'ht_about_care_text'  => "Misiunea noastră este să promovăm sănătatea și bunăstarea oferind soluții naturale care sunt atât eficiente, cât și ecologice. Fiecare produs este realizat cu atenție pentru a asigura puritatea, potența și responsabilitatea față de mediu, făcându-vă mai ușor să vă hrăniți corpul și pielea cu tot ce are natura de oferit.\n\nCu trei unități de producție de ultimă generație situate în România și una în Republica Moldova și un site suplimentar la nivel internațional, compania noastră și-a stabilit o prezență solidă pe piețele cheie din Europa, Turcia și Asia Centrală. Această acoperire globală este o dovadă a cererii în creștere pentru produsele noastre, care sunt recunoscute pe scară largă pentru calitatea și eficacitatea lor în aceste regiuni.",

            'ht_about_cosmetics_title' => 'Despre cosmeticile noastre” SPECTRU',
            'ht_about_cosmetics_text'  => 'La Herbal Therapy, credem că natura deține cheia către adevărata frumusețe și bunăstare. Dedicați fabricării de produse cosmetice premium, valorificăm puterea ingredientelor naturale, uleiurilor din plante și extractelor de plante pentru a crea produse pe cât de blânde cu pielea dumneavoastră, pe atât de eficiente.',

            'ht_about_mission_title' => 'Misiunea noastră',
            'ht_about_mission_text'  => "Misiunea noastră este de a combina tradițiile pe bază de plante onorate de timp cu știința modernă, oferind soluții de îngrijire a pielii care hrănesc, protejează și îmbunătățesc frumusețea naturală. Fiecare formulă pe care o dezvoltăm este concepută atent pentru a oferi rezultate vizibile, acordând în același timp prioritate durabilității și practicilor conștiente de mediu.\n\n<strong>Știați că producem propriile noastre extracte din plante?!</strong>",

            'ht_about_vision_title' => 'Viziunea brandului nostru',
            'ht_about_vision_lead'  => "Brandul nostru s-a născut cu o viziune ambițioasă: aceea de a inova piața produselor cosmetice și a suplimentelor alimentare, atât prin activitățile noastre aspiraționale de cercetare și dezvoltare, cât și prin crearea de produse unice din plante.\n\nPentru a asigura un succes autentic și durabil, „asul din mânecă” îl reprezintă echipa noastră — formată din oameni pasionați, bine motivați și orientați către rezultate, dar și din specialiști dedicați: biochimiști, cercetători și experți în fitoterapie care lucrează cu precizie și responsabilitate.",
            'ht_about_vision_note'  => "<strong>Ne facem singuri extractele, folosind o bază tehnologică excelentă, care ne permite să păstrăm viața și puterea plantelor în forma lor cea mai pură.</strong> Avem alături oameni minunați care fac asta de zeci de ani, cu grijă și fără greșeală.\n\nPlantele noastre sunt vii, iar extractele noastre dau din viața și energia lor trupului și minții tale. Noi creăm pentru tine și insuflețim natura urbană. Credem cu tărie în terapia plantelor și, cu fiecare produs pe care îl elaborăm, îți oferim un dar născut din credința noastră.",

            'ht_about_factory_title' => 'Fabrica și Depozit',

            'ht_about_location_title' => 'Unde ne gasesti',
            'ht_about_location_email' => 'info@herbal-therapy.ro',
            'ht_about_location_hours' => "Luni – Vineri: 08:00 – 17:00\nSâmbata - Duminica: Închis",
        ),

        'values' => array(
            array(
                'title' => 'Siguranță',
                'text'  => "Fiecare produs pe care îl creăm este realizat din ingrediente atent selecționate, iar înainte de a ajunge la tine, trece prin analize riguroase pentru a ne asigura că îndeplinește cele mai înalte cerințe de siguranță și calitate.\n\nAșadar, atunci când alegi un produs Herbal Terapy, alegi siguranță față de sănătatea ta.",
                'file'  => 'value-safety.webp',
            ),
            array(
                'title' => 'Eficacitate',
                'text'  => 'Pentru noi, eficacitatea înseamnă mai mult decât un termen din dicționar — este promisiunea că fiecare produs pe care îl creăm va aduce un rezultat real, vizibil și benefic. Eficacitatea se află în strânsă legătură cu productivitatea, eficiența și chiar rentabilitatea produselor noastre.',
                'file'  => 'value-efficacy.webp',
            ),
            array(
                'title' => 'Calitate',
                'text'  => "Pentru noi, calitatea nu este doar un standard, ci o promisiune – una dintre condițiile esențiale pentru a oferi încredere celor care ne aleg.\n\nZi de zi, în laboratorul nostru, ideile inovatoare prind viață, transformând secretele naturii în formule atent concepute.",
                'file'  => 'value-quality.webp',
            ),
        ),

        'locations' => array(
            array(
                'label'   => 'Sediu social:',
                'address' => 'Strada I.C. Brătianu 127, Botoşani, România',
                'file'    => 'map.webp',
                'href'    => '',
            ),
        ),
    ),

    /* -----------------------------------------------------------------------
     * Rusa
     * -------------------------------------------------------------------- */
    'ru' => array(
        'page' => array(
            'title' => 'О нас',
            'slug'  => 'o-nas',
        ),

        'texts' => array(
            'ht_about_hero_title' => 'О Herbal Therapy',
            'ht_about_hero_intro' => '<strong>Herbal Therapy</strong> — ведущий <strong>B2C</strong> и <strong>B2B</strong> поставщик пищевых добавок и косметических средств премиум-класса, раскрывающих силу природы. Мы специализируемся на создании продуктов высокого качества на основе сильнодействующих растительных экстрактов и местных ингредиентов из экологически ответственных источников.',

            'ht_about_care_title' => 'С заботой о вашем теле и окружающей среде',
            'ht_about_care_text'  => "Наша миссия — укреплять здоровье и благополучие, предлагая натуральные решения, которые одновременно эффективны и экологичны. Каждый продукт создаётся с особым вниманием к чистоте, силе действия и ответственности перед природой, чтобы вам было проще питать тело и кожу всем, что может дать природа.\n\nТри современных производственных предприятия в Румынии, одно в Республике Молдова и ещё одна площадка за рубежом позволили нашей компании прочно закрепиться на ключевых рынках Европы, Турции и Центральной Азии. Такой глобальный охват — свидетельство растущего спроса на нашу продукцию, широко известную в этих регионах своим качеством и эффективностью.",

            'ht_about_cosmetics_title' => 'О нашей косметике SPECTRU',
            'ht_about_cosmetics_text'  => 'В Herbal Therapy мы верим, что ключ к настоящей красоте и благополучию — в природе. Посвятив себя производству косметики премиум-класса, мы используем силу натуральных ингредиентов, растительных масел и экстрактов, создавая продукты, которые бережны к вашей коже и при этом по-настоящему эффективны.',

            'ht_about_mission_title' => 'Наша миссия',
            'ht_about_mission_text'  => "Наша миссия — соединить проверенные временем растительные традиции с современной наукой, предлагая средства по уходу за кожей, которые питают, защищают и подчёркивают естественную красоту. Каждая разработанная нами формула тщательно продумана, чтобы давать заметный результат, при этом на первом месте остаются устойчивое развитие и бережное отношение к окружающей среде.\n\n<strong>Знали ли вы, что мы сами производим собственные растительные экстракты?!</strong>",

            'ht_about_vision_title' => 'Видение нашего бренда',
            'ht_about_vision_lead'  => "Наш бренд родился с амбициозным видением: обновить рынок косметики и пищевых добавок — как через наши исследования и разработки, так и через создание уникальных продуктов на основе растений.\n\nЧтобы успех был настоящим и долгосрочным, нашим «козырем» стала команда — увлечённые, мотивированные и нацеленные на результат люди, а также преданные делу специалисты: биохимики, исследователи и эксперты в области фитотерапии, работающие точно и ответственно.",
            'ht_about_vision_note'  => "<strong>Мы сами производим свои экстракты на отличной технологической базе, которая позволяет сохранить жизнь и силу растений в самой чистой форме.</strong> Рядом с нами замечательные люди, которые занимаются этим десятилетиями — бережно и безошибочно.\n\nНаши растения живые, а наши экстракты передают их жизнь и энергию вашему телу и разуму. Мы создаём для вас и оживляем природу в городе. Мы твёрдо верим в терапию растениями, и с каждым созданным продуктом дарим вам подарок, рождённый из этой веры.",

            'ht_about_factory_title' => 'Фабрика и склад',

            'ht_about_location_title' => 'Где нас найти',
            'ht_about_location_email' => 'info@herbal-therapy.ro',
            'ht_about_location_hours' => "Понедельник – Пятница: 08:00 – 17:00\nСуббота – Воскресенье: выходной",
        ),

        'values' => array(
            array(
                'title' => 'Безопасность',
                'text'  => "Каждый продукт, который мы создаём, изготовлен из тщательно отобранных ингредиентов, а прежде чем попасть к вам, проходит строгие анализы, подтверждающие соответствие самым высоким требованиям безопасности и качества.\n\nВыбирая продукт Herbal Therapy, вы выбираете безопасность для своего здоровья.",
                'file'  => 'value-safety.webp',
            ),
            array(
                'title' => 'Эффективность',
                'text'  => 'Для нас эффективность — больше, чем слово из словаря: это обещание, что каждый созданный нами продукт принесёт реальный, заметный и полезный результат. Эффективность тесно связана с продуктивностью, результативностью и даже экономичностью нашей продукции.',
                'file'  => 'value-efficacy.webp',
            ),
            array(
                'title' => 'Качество',
                'text'  => "Для нас качество — не просто стандарт, а обещание, одно из главных условий доверия тех, кто нас выбирает.\n\nДень за днём в нашей лаборатории рождаются новаторские идеи, превращающие секреты природы в тщательно продуманные формулы.",
                'file'  => 'value-quality.webp',
            ),
        ),

        'locations' => array(
            array(
                'label'   => 'Юридический адрес:',
                'address' => 'ул. И.К. Брэтиану 127, Ботошани, Румыния',
                'file'    => 'map.webp',
                'href'    => '',
            ),
        ),
    ),
);
