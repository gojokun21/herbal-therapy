(function () {
    'use strict';

    /*
     * Sectiunea "Top vânzări": acelasi carusel ca in products.js, plus taburile
     * de categorii. Caruselul se porneste aici, nu in products.js, pentru ca
     * instanta Swiper trebuie tinuta minte: la schimbarea tabului inlocuim
     * slide-urile si ii cerem sa se remasoare.
     *
     * Butonul de cos si cel de favorite asculta delegat pe document (products.js,
     * favorites.js, WooCommerce), deci prind si cardurile aduse prin REST.
     */

    function initSection(section) {
        var el = section.querySelector('.ht-products__swiper');

        if (!el || typeof Swiper === 'undefined') {
            return;
        }

        /* spre deosebire de products.js, aici nu sunt sageti in antet: sectiunea
         * are deja perechea de la taburi, produsele se conduc din puncte */
        var swiper = new Swiper(el, {
            slidesPerView: 2,
            spaceBetween: 10,
            speed: 1000,
            grabCursor: true,
            watchOverflow: true,
            pagination: {
                el: section.querySelector('.ht-products__pagination'),
                clickable: true
            },
            /* in design randul are 5 carduri de 262px, cu 32px intre ele */
            breakpoints: {
                768: {slidesPerView: 3, spaceBetween: 20},
                1024: {slidesPerView: 4, spaceBetween: 26},
                1280: {slidesPerView: 5, spaceBetween: 32}
            }
        });

        /*
         * Randul de taburi e si el un Swiper: rotita mouse-ului nu deruleaza
         * containerele orizontale, asa ca pe desktop chip-urile de dupa margine
         * se aduc din sagetile laterale. slidesPerView 'auto' lasa fiecare chip
         * pe latimea lui, iar watchOverflow ascunde sagetile cand toate incap.
         */
        var tabsEl = section.querySelector('.ht-top-sales__tabs');

        if (tabsEl) {
            new Swiper(tabsEl, {
                slidesPerView: 'auto',
                slidesPerGroupAuto: true,
                spaceBetween: 8,
                speed: 600,
                watchOverflow: true,
                navigation: {
                    nextEl: section.querySelector('.ht-top-sales__tabs-arrow--next'),
                    prevEl: section.querySelector('.ht-top-sales__tabs-arrow--prev'),
                    disabledClass: 'swiper-button-disabled'
                },
                /* distantele din design: 8px intre chip-uri pe mobil, 12px pe desktop */
                breakpoints: {
                    768: {spaceBetween: 12}
                }
            });
        }

        var data = window.htTopSalesData || {};
        var tabs = Array.prototype.slice.call(section.querySelectorAll('.ht-top-sales__tab'));
        var wrapper = el.querySelector('.swiper-wrapper');
        var limit = parseInt(section.getAttribute('data-limit'), 10) || 10;

        /* slide-urile deja randate nu se mai cer inca o data */
        var cache = {};
        var request = 0;

        var initial = section.querySelector('.ht-top-sales__tab.is-active');

        if (initial && wrapper) {
            cache[initial.getAttribute('data-cat')] = wrapper.innerHTML;
        }

        function activate(tab) {
            tabs.forEach(function (other) {
                var on = other === tab;

                other.classList.toggle('is-active', on);
                other.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function swap(html) {
            wrapper.innerHTML = html;
            swiper.update();
            swiper.slideTo(0, 0);
        }

        function load(tab) {
            var cat = tab.getAttribute('data-cat');

            if (typeof cache[cat] === 'string') {
                swap(cache[cat]);

                return;
            }

            /* raspunsurile intarziate ale unui tab parasit se arunca */
            var token = ++request;
            var url = data.rest + '?cat=' + encodeURIComponent(cat) + '&limit=' + limit +
                (data.lang ? '&lang=' + encodeURIComponent(data.lang) : '');

            section.classList.add('is-loading');

            window.fetch(url)
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (json) {
                    if (!json || typeof json.html !== 'string') {
                        return;
                    }

                    cache[cat] = json.html;

                    if (token === request) {
                        swap(json.html);
                    }
                })
                .catch(function () {
                    /* la o cadere de retea tabul ramane pe produsele dinainte */
                })
                .then(function () {
                    if (token === request) {
                        section.classList.remove('is-loading');
                    }
                });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                if (tab.classList.contains('is-active')) {
                    return;
                }

                activate(tab);

                if (data.rest && wrapper) {
                    load(tab);
                }
            });
        });
    }

    document.querySelectorAll('[data-ht-top-sales]').forEach(initSection);
})();
