(function () {
    'use strict';

    var root = document.getElementById('htMinicart');

    if (!root || typeof window.htMinicartData === 'undefined') {
        return;
    }

    var data = window.htMinicartData;
    var body = document.body;
    var panel = root.querySelector('.ht-minicart__panel');
    var scroll = root.querySelector('[data-ht-minicart-scroll]');
    var alertBox = root.querySelector('[data-ht-minicart-alert]');
    var lastFocused = null;
    var pending = 0;

    /* ------------------------------------------------------------------
     * Deschidere / inchidere
     * ---------------------------------------------------------------- */

    function open() {
        if (root.classList.contains('is-open')) {
            return;
        }

        lastFocused = document.activeElement;
        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');
        body.classList.add('ht-no-scroll');

        initProducts();

        /* panoul se deschide mereu de la primul produs, nu de unde a ramas */
        if (scroll) {
            scroll.scrollTop = 0;
        }

        /* panoul aluneca 0.3s; focusul se muta dupa, ca sa nu sara pagina */
        window.setTimeout(function () {
            var close = root.querySelector('[data-ht-minicart-close]');

            if (close && root.classList.contains('is-open')) {
                close.focus();
            }
        }, 300);
    }

    function close() {
        if (!root.classList.contains('is-open')) {
            return;
        }

        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        body.classList.remove('ht-no-scroll');
        clearAlert();

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    /* iconita de cos din header - link catre pagina de cos, preluat de panou */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-ht-minicart-open]');

        if (trigger) {
            event.preventDefault();
            open();

            return;
        }

        if (event.target.closest('[data-ht-minicart-close]')) {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!root.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            close();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        /* focusul ramane in panou cat timp e deschis */
        var focusable = panel.querySelectorAll(
            'a[href], button:not(:disabled), input:not(:disabled), [tabindex]:not([tabindex="-1"])'
        );

        if (!focusable.length) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    /* ------------------------------------------------------------------
     * Mesajul din antet
     * ---------------------------------------------------------------- */

    function showAlert(text, isError) {
        if (!alertBox) {
            return;
        }

        if (!text) {
            clearAlert();

            return;
        }

        alertBox.textContent = text;
        alertBox.classList.add('is-visible');
        alertBox.classList.toggle('is-error', !!isError);
    }

    function clearAlert() {
        if (alertBox) {
            alertBox.textContent = '';
            alertBox.classList.remove('is-visible', 'is-error');
        }
    }

    /* ------------------------------------------------------------------
     * Dialogul cu serverul
     * ---------------------------------------------------------------- */

    /**
     * Inlocuieste fragmentele intoarse de server.
     *
     * Cheile sunt selectori CSS, in acelasi format ca fragmentele WooCommerce,
     * deci panoul se actualizeaza la fel si cand produsul e adaugat din card.
     */
    function applyFragments(fragments) {
        if (!fragments) {
            return;
        }

        Object.keys(fragments).forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (node) {
                node.outerHTML = fragments[selector];
            });
        });
    }

    function setCount(count) {
        root.querySelectorAll('[data-ht-minicart-count]').forEach(function (node) {
            node.textContent = count ? String(count) : '';
        });
    }

    function request(payload, source) {
        var form = new FormData();

        form.append('nonce', data.nonce);

        Object.keys(payload).forEach(function (key) {
            form.append(key, payload[key]);
        });

        pending++;
        root.classList.add('is-busy');

        if (source) {
            source.classList.add('is-busy');
        }

        /* pozitia in lista nu trebuie sa sara la fiecare modificare */
        var top = scroll ? scroll.scrollTop : 0;

        return fetch(data.url, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(response.status);
            }

            return response.json();
        }).then(function (result) {
            applyFragments(result.fragments);
            setCount(result.count);
            showAlert(result.notice, result.error);

            if (scroll) {
                scroll.scrollTop = top;
            }

            /*
             * Inimile vin deja desenate corect din PHP (ht_is_favorite citeste
             * aceeasi sesiune), deci nu mai e nevoie sa le resincronizam aici.
             */
            document.body.dispatchEvent(new CustomEvent('ht:cart:updated', {detail: result}));

            return result;
        }).catch(function () {
            showAlert(data.i18n.error, true);

            return null;
        }).then(function (result) {
            pending--;

            if (pending <= 0) {
                pending = 0;
                root.classList.remove('is-busy');
            }

            /* raspunsul merge mai departe: apelantul decide ce face cu el */
            return result;
        });
    }

    /* ------------------------------------------------------------------
     * Actiunile din lista
     * ---------------------------------------------------------------- */

    function itemOf(node) {
        return node.closest('[data-ht-cart-item]');
    }

    function sendQuantity(item, quantity) {
        item.classList.add('is-busy');

        request({
            ht_action: 'qty',
            key: item.getAttribute('data-ht-cart-item'),
            quantity: quantity
        });
    }

    root.addEventListener('click', function (event) {
        var step = event.target.closest('[data-ht-cart-step]');

        if (step) {
            event.preventDefault();

            var item = itemOf(step);
            var input = item ? item.querySelector('[data-ht-cart-qty]') : null;

            if (!item || !input) {
                return;
            }

            var next = (parseInt(input.value, 10) || 1) + parseInt(step.getAttribute('data-ht-cart-step'), 10);
            var max = parseInt(input.getAttribute('data-max'), 10) || 0;

            if (next < 1 || (max && next > max)) {
                return;
            }

            /* raspunsul vine cu randul refacut; pana atunci aratam valoarea noua */
            input.value = next;
            sendQuantity(item, next);

            return;
        }

        var remove = event.target.closest('[data-ht-cart-remove]');

        if (remove) {
            event.preventDefault();

            var row = itemOf(remove);

            if (row) {
                row.classList.add('is-busy');
                request({ht_action: 'remove', key: row.getAttribute('data-ht-cart-item')});
            }
        }
    });

    /* valoarea tastata direct in camp */
    root.addEventListener('change', function (event) {
        var input = event.target.closest('[data-ht-cart-qty]');

        if (!input) {
            return;
        }

        var item = itemOf(input);
        var max = parseInt(input.getAttribute('data-max'), 10) || 0;
        var value = parseInt(input.value, 10);

        if (!item || isNaN(value) || value < 0) {
            value = 1;
        }

        if (max && value > max) {
            value = max;
        }

        input.value = value;
        sendQuantity(item, value);
    });

    /* ------------------------------------------------------------------
     * Codul promotional
     * ---------------------------------------------------------------- */

    root.addEventListener('input', function (event) {
        var input = event.target.closest('[data-ht-coupon-input]');

        if (!input) {
            return;
        }

        var label = input.closest('.ht-minicart-promo__label');
        var send = label ? label.querySelector('[data-ht-coupon-apply]') : null;

        if (send) {
            send.disabled = (input.value.trim() === '');
        }

        if (label) {
            label.classList.remove('is-error');
        }
    });

    root.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.target.closest('[data-ht-coupon-input]')) {
            return;
        }

        event.preventDefault();
        applyCoupon(event.target);
    });

    function applyCoupon(input) {
        var code = input.value.trim();

        if (!code) {
            return;
        }

        var label = input.closest('.ht-minicart-promo__label');

        request({ht_action: 'coupon', code: code}).then(function (result) {
            /* fragmentele au inlocuit campul; marcam noul camp doar la eroare */
            if (!result || !result.error) {
                return;
            }

            var fresh = root.querySelector('.ht-minicart-promo__label');

            if (fresh) {
                fresh.classList.add('is-error');

                var field = fresh.querySelector('[data-ht-coupon-input]');

                if (field) {
                    field.value = code;
                    field.focus();
                }

                var send = fresh.querySelector('[data-ht-coupon-apply]');

                if (send) {
                    send.disabled = false;
                }
            } else if (label) {
                label.classList.add('is-error');
            }
        });
    }

    root.addEventListener('click', function (event) {
        var apply = event.target.closest('[data-ht-coupon-apply]');

        if (apply) {
            event.preventDefault();

            var label = apply.closest('.ht-minicart-promo__label');
            var input = label ? label.querySelector('[data-ht-coupon-input]') : null;

            if (input) {
                applyCoupon(input);
            }

            return;
        }

        var drop = event.target.closest('[data-ht-coupon-remove]');

        if (drop) {
            event.preventDefault();
            request({ht_action: 'remove_coupon', code: drop.getAttribute('data-ht-coupon-remove')});
        }
    });

    /* ------------------------------------------------------------------
     * Caruselul de recomandari
     *
     * Se porneste la prima deschidere: intr-un panou ascuns Swiper masoara
     * latimi de zero si slide-urile ies suprapuse.
     * ---------------------------------------------------------------- */

    var productsReady = false;

    function initProducts() {
        if (productsReady) {
            return;
        }

        var section = root.querySelector('[data-ht-minicart-products]');
        var track = section ? section.querySelector('.ht-products__swiper') : null;

        if (!track || typeof window.Swiper === 'undefined') {
            productsReady = true;

            return;
        }

        productsReady = true;

        /*
         * Spatiul dintre slide-uri vine din CSS: acolo intra si in calculul care
         * aseaza sagetile pe mijlocul imaginii, deci trebuie sa fie o singura valoare.
         *
         * Se citeste din 'column-gap', nu din variabila: variabila ajunge aici ca
         * text nedesfacut ('calc(12 * var(...))'), pe cand proprietatea reala e
         * deja rezolvata in pixeli.
         */
        var gapHost = section.querySelector('.ht-minicart__products-track') || section;
        var gap = parseFloat(getComputedStyle(gapHost).columnGap);

        new window.Swiper(track, {
            slidesPerView: 2,
            spaceBetween: isNaN(gap) ? 12 : gap,
            speed: 600,
            watchOverflow: true,
            navigation: {
                nextEl: section.querySelector('.ht-minicart__arrow--next'),
                prevEl: section.querySelector('.ht-minicart__arrow--prev'),
                disabledClass: 'swiper-button-disabled'
            }
        });
    }

    /*
     * Adaugarile facute din cardurile de produs nu au nevoie de nimic aici:
     * lista, totalurile si contorul din titlu sunt fragmente WooCommerce, iar
     * scriptul pluginului le inlocuieste singur, cu acelasi mecanism.
     */

    window.htMinicart = {open: open, close: close};
})();
