(function () {
    'use strict';

    /*
     * Caruselul de categorii. In design randul are patru placi de 345px, cu
     * 20px intre ele; sub 1280px scad treptat, iar pe telefon a doua placa se
     * vede pe jumatate, ca sa se inteleaga ca randul continua.
     */
    function initSection(section) {
        var el = section.querySelector('.ht-cats__swiper');

        if (!el || typeof Swiper === 'undefined') {
            return;
        }

        new Swiper(el, {
            slidesPerView: 1.35,
            spaceBetween: 12,
            speed: 700,
            grabCursor: true,
            watchOverflow: true,
            navigation: {
                nextEl: section.querySelector('.ht-cats__arrow--next'),
                prevEl: section.querySelector('.ht-cats__arrow--prev'),
                disabledClass: 'swiper-button-disabled'
            },
            pagination: {
                el: section.querySelector('.ht-cats__pagination'),
                clickable: true
            },
            breakpoints: {
                576: {slidesPerView: 2, spaceBetween: 16},
                992: {slidesPerView: 3, spaceBetween: 20},
                1280: {slidesPerView: 4, spaceBetween: 20}
            }
        });
    }

    document.querySelectorAll('[data-ht-cats]').forEach(initSection);

    window.htCategories = {
        initSection: initSection
    };
})();
