/**
 * Finalizarea comenzii.
 *
 * Formularul in sine e al WooCommerce - validarea, reincarcarea sumarului si
 * trimiterea comenzii raman la checkout.js din plugin. Aici sunt doar bucatile
 * pe care tema le-a mutat in afara lui:
 *
 *   - codul promotional, care sta in sumar, deci in interiorul formularului de
 *     comanda si nu poate fi un al doilea <form>; se trimite pe acelasi endpoint
 *     ca in cosul rapid (ht_minicart), apoi se cere o recalculare;
 *   - contorul de produse din titlul sumarului, care sta in afara fragmentului
 *     reincarcat prin AJAX;
 *   - derularea la mesajele de eroare, care in plugin lasa doar 100px deasupra
 *     lor - prea putin sub header-ul lipicios al temei.
 */
(function () {
    'use strict';

    var form = document.querySelector('form.checkout');

    if (!form) {
        return;
    }

    var data = window.htCheckoutData || {i18n: {}};
    var cart = window.htMinicartData || null;

    /* ------------------------------------------------------------------
     * Derularea la erori
     * ---------------------------------------------------------------- */

    /*
     * Pluginul deruleaza la lista de erori cu 100px deasupra ei
     * ($.scroll_to_notices, woocommerce.js); header-ul lipicios al temei e mai
     * inalt, deci mesajul ramanea ascuns sub el. Aceeasi functie, cu inaltimea
     * reala a header-ului. Scriptul e incarcat dupa woocommerce.js (vezi
     * dependintele din inc/checkout.php), deci suprascrierea ramane.
     */
    if (window.jQuery && typeof window.jQuery.scroll_to_notices === 'function') {
        window.jQuery.scroll_to_notices = function (element) {
            if (!element || !element.length) {
                return;
            }

            var header = document.querySelector('.ht-header');
            var offset = (header ? header.getBoundingClientRect().height : 0) + 24;

            window.jQuery('html, body').animate({scrollTop: element.offset().top - offset}, 600);
        };
    }

    /* ------------------------------------------------------------------
     * Ajutoare
     * ---------------------------------------------------------------- */

    /**
     * Cere WooCommerce sa recalculeze sumarul.
     *
     * Evenimentul e al pluginului si merge prin jQuery, deci are nevoie de el;
     * pe pagina de finalizare jQuery e mereu incarcat, ca dependinta a lui
     * checkout.js.
     */
    function refresh() {
        if (window.jQuery) {
            window.jQuery(document.body).trigger('update_checkout');
        }
    }

    function promoBox() {
        return document.querySelector('[data-ht-checkout-promo]');
    }

    /**
     * Mesajul de sub campul de cod, cu starea lui.
     *
     * @param {string}  text  Mesajul; sir gol il ascunde.
     * @param {boolean} error Mesajul e de eroare?
     */
    function promoNote(text, error) {
        var box = promoBox();

        if (!box) {
            return;
        }

        var note = box.querySelector('[data-ht-checkout-promo-note]');

        box.classList.remove('is-error', 'is-applied');

        if (text) {
            box.classList.add(error ? 'is-error' : 'is-applied');
        }

        if (note) {
            note.textContent = text || '';
        }
    }

    /**
     * Trimite o comanda catre cos, pe endpoint-ul cosului rapid.
     *
     * @param {Object} payload Campurile cererii.
     *
     * @return {Promise} Raspunsul serverului.
     */
    function request(payload) {
        if (!cart) {
            return Promise.reject(new Error('missing-cart-endpoint'));
        }

        var body = new FormData();

        body.append('nonce', cart.nonce);

        Object.keys(payload).forEach(function (key) {
            body.append(key, payload[key]);
        });

        return fetch(cart.url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(response.status);
            }

            return response.json();
        });
    }

    /* ------------------------------------------------------------------
     * Codul promotional
     * ---------------------------------------------------------------- */

    function applyCoupon() {
        var box = promoBox();
        var input = box ? box.querySelector('[data-ht-checkout-coupon]') : null;
        var code = input ? input.value.trim() : '';

        if (!code) {
            promoNote(data.i18n.empty, true);
            return;
        }

        var button = box.querySelector('[data-ht-checkout-coupon-apply]');

        if (button) {
            button.disabled = true;
        }

        request({ht_action: 'coupon', code: code}).then(function (result) {
            promoNote(result.notice, result.error);

            if (!result.error && input) {
                input.value = '';
            }

            refresh();
        }).catch(function () {
            promoNote(data.i18n.error, true);
        }).then(function () {
            /* butonul se reactiveaza doar daca a mai ramas ceva scris in camp */
            if (button && input) {
                button.disabled = ('' === input.value.trim());
            }
        });
    }

    function removeCoupon(code) {
        request({ht_action: 'remove_coupon', code: code}).then(function (result) {
            promoNote(result.notice, result.error);
            refresh();
        }).catch(function () {
            promoNote(data.i18n.error, true);
        });
    }

    /* butonul de trimitere e activ doar cand exista un cod scris */
    document.addEventListener('input', function (event) {
        var input = event.target.closest('[data-ht-checkout-coupon]');

        if (!input) {
            return;
        }

        var button = input.closest('[data-ht-checkout-promo]')
            .querySelector('[data-ht-checkout-coupon-apply]');

        if (button) {
            button.disabled = ('' === input.value.trim());
        }
    });

    /* Enter in campul de cod aplica cuponul, nu trimite comanda */
    document.addEventListener('keydown', function (event) {
        if ('Enter' !== event.key || !event.target.closest('[data-ht-checkout-coupon]')) {
            return;
        }

        event.preventDefault();
        applyCoupon();
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-ht-checkout-coupon-apply]')) {
            event.preventDefault();
            applyCoupon();
            return;
        }

        var drop = event.target.closest('[data-ht-checkout-coupon-remove]');

        if (drop) {
            event.preventDefault();
            removeCoupon(drop.getAttribute('data-ht-checkout-coupon-remove'));
        }
    });

    /* ------------------------------------------------------------------
     * Contorul din titlul sumarului
     * ---------------------------------------------------------------- */

    /**
     * Reface numarul de produse din titlu.
     *
     * Titlul e in afara fragmentului pe care il inlocuieste WooCommerce, deci
     * numarul se aduna din bulinele de cantitate ale liniilor proaspat randate.
     */
    function syncCount() {
        var badge = document.querySelector('.ht-checkout__summary-count');
        var total = 0;

        document.querySelectorAll('.ht-checkout-review__qty').forEach(function (node) {
            total += parseInt(node.textContent, 10) || 0;
        });

        if (badge) {
            badge.textContent = total ? String(total) : '';
        }
    }

    if (window.jQuery) {
        window.jQuery(document.body).on('updated_checkout', syncCount);
    }

    /*
     * Modificarile facute din cosul rapid (cantitati, stergeri) trebuie sa se
     * vada si in sumar - panoul se poate deschide si de pe pagina asta.
     */
    document.body.addEventListener('ht:cart:updated', function () {
        refresh();
    });
})();
