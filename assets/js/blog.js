(function () {
    'use strict';

    if (typeof Swiper === 'undefined') {
        return;
    }

    /*
     * Parametrii sunt cei din referinta: sub 651px doua carduri si un pic din
     * al treilea, peste prag patru carduri fixe. Fara paginare - referinta se
     * bazeaza doar pe gest si pe sageti.
     */
    function initSection(section) {
        var el = section.querySelector('.ht-blog__swiper');

        if (!el) {
            return;
        }

        new Swiper(el, {
            slidesPerView: 2.01,
            spaceBetween: 10,
            speed: 1000,
            watchOverflow: true,
            navigation: {
                nextEl: section.querySelector('.ht-blog__arrow--next'),
                prevEl: section.querySelector('.ht-blog__arrow--prev'),
                disabledClass: 'swiper-button-disabled',
                lockClass: 'swiper-button-lock'
            },
            breakpoints: {
                651: {slidesPerView: 4, spaceBetween: 15}
            }
        });
    }

    document.querySelectorAll('[data-ht-blog]').forEach(initSection);

    /* expus pentru sectiunile aduse ulterior prin AJAX */
    window.htBlog = {initSection: initSection};
})();
