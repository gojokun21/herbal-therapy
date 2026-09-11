(function () {
    'use strict';

    /*
     * Butonul de favorite este in assets/js/favorites.js: asculta delegat pe
     * document, deci prinde si cardurile aduse dupa incarcarea paginii.
     */

    /* ------------------------------------------------------------------
     * Butonul de adaugare in cos
     * ---------------------------------------------------------------- */

    /*
     * Doar oprim navigarea link-ului parinte. Cererea AJAX ramane in seama
     * scriptului WooCommerce, care asculta tot delegat, pe document.body.
     *
     * Ascultatorul e pe document, nu pe fiecare buton: asa prinde si cardurile
     * aduse dupa incarcarea paginii (cosul rapid, filtre, incarcare progresiva).
     */
    document.addEventListener('click', function (event) {
        if (event.target.closest('.ht-card__cart')) {
            event.preventDefault();
        }
    });

    /*
     * Confirmarea de pe buton: dupa adaugare, butonul ramane cateva secunde in
     * culoarea de hover, cu eticheta "In cos", apoi revine la starea initiala.
     * Se aplica oricarui buton cu 'data-added-text' (cardurile, upgrade-ul de
     * pachet din pagina de produs).
     */
    var ADDED_DELAY = 2500;
    var timers = new WeakMap();

    function confirmAdded(button) {
        var label = button.querySelector('.ht-card__cart-text, [data-ht-cart-text]');
        var added = button.getAttribute('data-added-text');

        if (label && added) {
            /* textul initial e citit o singura data: la a doua apasare rapida,
             * label-ul arata deja confirmarea */
            if (!button.hasAttribute('data-default-text')) {
                button.setAttribute('data-default-text', label.textContent);
            }

            label.textContent = added;
        }

        button.classList.add('is-added');
        clearTimeout(timers.get(button));

        timers.set(button, setTimeout(function () {
            var initial = button.getAttribute('data-default-text');

            if (label && initial) {
                label.textContent = initial;
            }

            /* 'added' e pusa de WooCommerce; o scoatem odata cu a noastra */
            button.classList.remove('is-added', 'added');
            timers.delete(button);
        }, ADDED_DELAY));
    }

    /*
     * 'added_to_cart' e un eveniment jQuery al pluginului, deci nu se aude prin
     * addEventListener. Al treilea argument e chiar butonul apasat.
     */
    if (window.jQuery) {
        window.jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, $button) {
            var button = $button && $button[0];

            if (button && button.hasAttribute('data-added-text')) {
                confirmAdded(button);
            }
        });
    }

    /* ------------------------------------------------------------------
     * Caruselul de produse
     * ---------------------------------------------------------------- */

    function initSection(section) {
        var el = section.querySelector('.ht-products__swiper');

        /*
         * Sectiunea poate fi carusel (prima pagina) sau grila statica (listarile
         * de magazin). Fara pista, nu avem ce porni.
         */
        if (!el || typeof Swiper === 'undefined') {
            return;
        }

        new Swiper(el, {
            slidesPerView: 2,
            spaceBetween: 10,
            speed: 1000,
            grabCursor: true,
            watchOverflow: true,
            navigation: {
                nextEl: section.querySelector('.ht-products__arrow--next'),
                prevEl: section.querySelector('.ht-products__arrow--prev'),
                disabledClass: 'swiper-button-disabled'
            },
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
    }

    document.querySelectorAll('[data-ht-products]').forEach(initSection);

    /*
     * Expus pentru sectiunile aduse ulterior prin AJAX (filtre, incarcare
     * progresiva).
     */
    window.htProducts = {
        initSection: initSection
    };
})();
