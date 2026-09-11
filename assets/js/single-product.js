/*
 * Pagina de produs: galeria, cantitatea, caruselul din cardul de cumparare,
 * taburile de detalii si comutatoarele din blocul de recenzii.
 *
 * Fiecare bucata isi verifica singura elementele, deci lipsa uneia (produs fara
 * galerie, produs fara recenzii) nu opreste restul.
 */

/* ---------------------------------------------------------------------------
 * Cantitatea de langa butonul de cos
 *
 * Campul poarta name="quantity" si e legat de formular prin atributul 'form',
 * deci trimiterea clasica merge si fara scriptul asta. Aici tinem doar butoanele
 * de +/- si oglindim valoarea in data-quantity, de unde o citeste AJAX-ul
 * WooCommerce (add-to-cart.js prefera dataset-ul in fata cache-ului jQuery).
 * ------------------------------------------------------------------------ */
(function () {
    'use strict';

    var input = document.querySelector('[data-ht-pp-qty]');

    if (!input) {
        return;
    }

    var control = input.closest('.ht-pp__qty');
    var cart = document.querySelector('.ht-pp__cart');
    var minus = control.querySelector('[data-ht-pp-step="-1"]');
    var plus = control.querySelector('[data-ht-pp-step="1"]');

    var min = parseInt(input.getAttribute('data-min'), 10) || 1;
    /* 0 inseamna ca produsul nu tine stoc, deci nu are plafon */
    var max = parseInt(input.getAttribute('data-max'), 10) || 0;
    var step = parseInt(input.getAttribute('data-step'), 10) || 1;

    /* valorile valide sunt min, min + step, min + 2 * step ... pana la max */
    function normalize(value) {
        if (isNaN(value) || value < min) {
            return min;
        }

        value = min + Math.round((value - min) / step) * step;

        if (max && value > max) {
            value = min + Math.floor((max - min) / step) * step;
        }

        return value;
    }

    function apply(value) {
        input.value = value;

        if (cart) {
            cart.setAttribute('data-quantity', value);
        }

        minus.disabled = value <= min;
        plus.disabled = !!max && value + step > max;
    }

    control.addEventListener('click', function (event) {
        var button = event.target.closest('[data-ht-pp-step]');

        if (!button || button.disabled) {
            return;
        }

        var direction = parseInt(button.getAttribute('data-ht-pp-step'), 10);

        apply(normalize((parseInt(input.value, 10) || min) + direction * step));
    });

    /* orice se tasteaza in camp se aseaza pe treapta cea mai apropiata */
    input.addEventListener('change', function () {
        apply(normalize(parseInt(input.value, 10)));
    });

    apply(normalize(parseInt(input.value, 10)));
})();

/* ---------------------------------------------------------------------------
 * Galeria: imaginea mare plus randul de miniaturi
 * ------------------------------------------------------------------------ */
(function () {
    'use strict';

    if (typeof Swiper === 'undefined') {
        return;
    }

    var gallery = document.querySelector('[data-ht-gallery]');

    if (!gallery) {
        return;
    }

    var stageEl = gallery.querySelector('.ht-pp__stage-swiper');

    if (!stageEl) {
        return;
    }

    var thumbsEl = gallery.querySelector('.ht-pp__thumbs-swiper');
    var thumbs = null;

    if (thumbsEl) {
        thumbs = new Swiper(thumbsEl, {
            slidesPerView: 'auto',
            spaceBetween: 16,
            freeMode: true,
            watchSlidesProgress: true
        });
    }

    var slider = new Swiper(stageEl, {
        slidesPerView: 1,
        spaceBetween: 0,
        speed: 500,
        watchOverflow: true,
        grabCursor: true,
        navigation: {
            prevEl: gallery.querySelector('.ht-pp__arrow--prev'),
            nextEl: gallery.querySelector('.ht-pp__arrow--next'),
            disabledClass: 'swiper-button-disabled'
        },
        keyboard: {
            enabled: true
        },
        thumbs: thumbs ? {swiper: thumbs} : undefined
    });

    window.htSingleProduct = {
        slider: slider,
        thumbs: thumbs
    };
})();

/* ---------------------------------------------------------------------------
 * Lightbox-ul galeriei (Fancybox)
 * ------------------------------------------------------------------------ */
(function () {
    'use strict';

    if (typeof Fancybox === 'undefined') {
        return;
    }

    var data = window.htSingleProductData || {};

    Fancybox.bind('[data-fancybox="ht-product-gallery"]', {
        Hash: false,
        l10n: data.fancyboxL10n || undefined,
        on: {
            /* slide-ul mare ramane in pas cu imaginea rasfoita in lightbox */
            'Carousel.change': function (fancybox) {
                var slide = fancybox.getSlide();

                if (slide && window.htSingleProduct && window.htSingleProduct.slider) {
                    window.htSingleProduct.slider.slideTo(slide.index, 0);
                }
            }
        }
    });
})();

/* ---------------------------------------------------------------------------
 * Caruselul din cardul de cumparare
 * ------------------------------------------------------------------------ */
(function () {
    'use strict';

    if (typeof Swiper === 'undefined') {
        return;
    }

    var block = document.querySelector('[data-ht-pp-together]');

    if (!block) {
        return;
    }

    var swiperEl = block.querySelector('.ht-pp__together-swiper');

    if (!swiperEl) {
        return;
    }

    new Swiper(swiperEl, {
        slidesPerView: 'auto',
        spaceBetween: 10,
        watchOverflow: true,
        navigation: {
            prevEl: block.querySelector('.ht-pp__together-arrow--prev'),
            nextEl: block.querySelector('.ht-pp__together-arrow--next'),
            disabledClass: 'swiper-button-disabled'
        }
    });
})();

/* ---------------------------------------------------------------------------
 * Taburile de detalii si legaturile care sar in ele
 * ------------------------------------------------------------------------ */
(function () {
    'use strict';

    var tabs = document.querySelector('[data-ht-pp-tabs]');

    function open(id) {
        if (!tabs) {
            return;
        }

        var buttons = tabs.querySelectorAll('[data-ht-pp-tab]');
        var panels = tabs.querySelectorAll('[data-ht-pp-panel]');

        Array.prototype.forEach.call(buttons, function (button) {
            var active = button.getAttribute('data-ht-pp-tab') === id;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        Array.prototype.forEach.call(panels, function (panel) {
            var active = panel.getAttribute('data-ht-pp-panel') === id;

            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
    }

    if (tabs) {
        tabs.addEventListener('click', function (event) {
            var button = event.target.closest('[data-ht-pp-tab]');

            if (button) {
                open(button.getAttribute('data-ht-pp-tab'));
            }
        });
    }

    /* "Descriere detaliata" deschide primul tab si duce apoi la sectiune. */
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-ht-pp-goto]');

        if (!link) {
            return;
        }

        open(link.getAttribute('data-ht-pp-goto'));
    });
})();

/* ---------------------------------------------------------------------------
 * Blocul de recenzii: sortarea
 * ------------------------------------------------------------------------ */
(function () {
    'use strict';

    var block = document.querySelector('[data-ht-rev]');

    if (!block) {
        return;
    }

    /*
     * Sortarea lucreaza pe recenziile deja randate, nu cere alta pagina: lista
     * are cel mult cate recenzii afiseaza WordPress pe o pagina, iar datele de
     * care are nevoie (data si nota) stau pe fiecare element.
     */
    var sort = block.querySelector('[data-ht-rev-sort]');
    var list = block.querySelector('.ht-rev__list');

    if (!sort) {
        return;
    }

    var orders = ['date', 'rating'];

    /* etichetele vin traduse din sablon (data-label-*); rezerva e textul deja
     * afisat pe buton, ca sa nu ajunga in pagina un sir netradus din JavaScript */
    var current_label = sort.querySelector('[data-ht-rev-sort-label]');
    var fallback = current_label ? current_label.textContent.trim() : '';
    var labels = {
        date: sort.getAttribute('data-label-date') || fallback,
        rating: sort.getAttribute('data-label-rating') || fallback
    };
    var current = 0;

    sort.addEventListener('click', function () {
        current = (current + 1) % orders.length;

        var order = orders[current];
        var text = sort.querySelector('[data-ht-rev-sort-label]');

        if (text) {
            text.textContent = labels[order];
        }

        if (!list) {
            return;
        }

        var items = Array.prototype.slice.call(list.children);

        items.sort(function (a, b) {
            if ('rating' === order) {
                return (parseFloat(b.getAttribute('data-rating')) || 0)
                    - (parseFloat(a.getAttribute('data-rating')) || 0);
            }

            return (parseInt(b.getAttribute('data-time'), 10) || 0)
                - (parseInt(a.getAttribute('data-time'), 10) || 0);
        });

        items.forEach(function (item) {
            list.appendChild(item);
        });
    });
})();
