/**
 * Pagina de cos - cantitati, stergere si cod promotional, fara reincarcarea paginii.
 *
 * Vorbeste cu acelasi endpoint ca panoul cosului rapid (htMinicartData din
 * minicart.js) si trimite in plus 'ht_cart_page', ca serverul sa intoarca si
 * fragmentul paginii (inc/cart.php).
 */
(function () {
    'use strict';

    var root = document.querySelector('.ht-cart');

    if (!root || typeof window.htMinicartData === 'undefined') {
        return;
    }

    var data = window.htMinicartData;
    var pending = 0;

    /* ------------------------------------------------------------------
     * Mesajul de deasupra listei
     * ---------------------------------------------------------------- */

    function alertBox() {
        return root.querySelector('[data-ht-cart-alert]');
    }

    function showAlert(text, isError) {
        var box = alertBox();

        if (!box) {
            return;
        }

        if (!text) {
            box.textContent = '';
            box.classList.remove('is-visible', 'is-error');

            return;
        }

        box.textContent = text;
        box.classList.add('is-visible');
        box.classList.toggle('is-error', !!isError);
    }

    /* ------------------------------------------------------------------
     * Dialogul cu serverul
     * ---------------------------------------------------------------- */

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

    function body() {
        return root.querySelector('[data-ht-cart-body]');
    }

    function request(payload, url) {
        var form = new FormData();

        form.append('nonce', data.nonce);
        form.append('ht_cart_page', '1');

        Object.keys(payload).forEach(function (key) {
            form.append(key, payload[key]);
        });

        pending++;

        var current = body();

        if (current) {
            current.classList.add('is-busy');
        }

        return fetch(url || data.url, {
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
            /* fragmentul paginii inlocuieste si mesajul, deci se scrie dupa */
            applyFragments(result.fragments);
            showAlert(result.notice, result.error);

            document.body.dispatchEvent(new CustomEvent('ht:cart:page-updated', {detail: result}));

            return result;
        }).catch(function () {
            showAlert(data.i18n.error, true);

            return null;
        }).then(function (result) {
            pending--;

            if (pending <= 0) {
                pending = 0;

                var fresh = body();

                if (fresh) {
                    fresh.classList.remove('is-busy');
                }
            }

            return result;
        });
    }

    /* ------------------------------------------------------------------
     * Cantitate si stergere
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
     * Metoda de livrare
     * ---------------------------------------------------------------- */

    root.addEventListener('change', function (event) {
        var radio = event.target.closest('[data-ht-cart-shipping] input.shipping_method');

        if (!radio || radio.type !== 'radio') {
            return;
        }

        var payload = {};

        payload['shipping_method[' + radio.getAttribute('data-index') + ']'] = radio.value;

        request(payload, data.url.replace('ht_minicart', 'ht_cart_shipping'));
    });

    /* ------------------------------------------------------------------
     * Codul promotional
     * ---------------------------------------------------------------- */

    root.addEventListener('input', function (event) {
        var input = event.target.closest('[data-ht-coupon-input]');

        if (!input) {
            return;
        }

        var field = input.closest('.ht-cart-promo__field');
        var apply = field ? field.querySelector('[data-ht-coupon-apply]') : null;

        if (apply) {
            apply.disabled = (input.value.trim() === '');
        }

        if (field) {
            field.classList.remove('is-error');
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

        request({ht_action: 'coupon', code: code}).then(function (result) {
            if (!result || !result.error) {
                return;
            }

            /* fragmentul a refacut campul; codul gresit ramane scris, ca sa poata fi corectat */
            var fresh = root.querySelector('.ht-cart-promo__field');

            if (!fresh) {
                return;
            }

            fresh.classList.add('is-error');

            var field = fresh.querySelector('[data-ht-coupon-input]');
            var apply = fresh.querySelector('[data-ht-coupon-apply]');

            if (field) {
                field.value = code;
                field.focus();
            }

            if (apply) {
                apply.disabled = false;
            }
        });
    }

    root.addEventListener('click', function (event) {
        var apply = event.target.closest('[data-ht-coupon-apply]');

        if (apply) {
            event.preventDefault();

            var field = apply.closest('.ht-cart-promo__field');
            var input = field ? field.querySelector('[data-ht-coupon-input]') : null;

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
     * Modificari facute din alta parte
     *
     * Panoul cosului rapid si butoanele din carduri nu cer fragmentul paginii,
     * deci dupa ele lista se reia de aici cu o cerere goala.
     * ---------------------------------------------------------------- */

    function refresh(event) {
        var detail = event && event.detail;

        if (detail && detail.fragments && detail.fragments['div[data-ht-cart-body]']) {
            return;
        }

        request({ht_action: 'refresh'});
    }

    document.body.addEventListener('ht:cart:updated', refresh);

    if (window.jQuery) {
        window.jQuery(document.body).on('added_to_cart removed_from_cart', function () {
            refresh();
        });
    }
})();
