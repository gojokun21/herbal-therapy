(function () {
    'use strict';

    /*
     * Caruselul de recenzii de pe prima pagina: acelasi tipar ca blog.js -
     * doua carduri si un pic din al treilea pe telefon, patru pe desktop,
     * sageti in capul sectiunii, fara paginare si fara auto-play (continut
     * de citit, nu de privit in treacat).
     */

    if (typeof Swiper === 'undefined') {
        return;
    }

    function initSection(section) {
        var el = section.querySelector('.ht-home-reviews__swiper');

        if (!el) {
            return;
        }

        new Swiper(el, {
            slidesPerView: 1.15,
            spaceBetween: 10,
            speed: 1000,
            grabCursor: true,
            watchOverflow: true,
            navigation: {
                nextEl: section.querySelector('.ht-products__arrow--next'),
                prevEl: section.querySelector('.ht-products__arrow--prev'),
                disabledClass: 'swiper-button-disabled',
                lockClass: 'swiper-button-lock'
            },
            breakpoints: {
                651: {slidesPerView: 2, spaceBetween: 15},
                1024: {slidesPerView: 3, spaceBetween: 20},
                1280: {slidesPerView: 4, spaceBetween: 32}
            }
        });
    }

    document.querySelectorAll('[data-ht-home-reviews]').forEach(initSection);
})();
