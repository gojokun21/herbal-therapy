/**
 * Notificarile de cos.
 *
 * Markup-ul vine gata facut de pe server (inc/toast.php): scriptul doar il aseaza
 * in stiva, il tine cat trebuie pe ecran si il scoate. Doua surse:
 *
 *   - fragmentele evenimentului jQuery 'added_to_cart', la adaugarea prin AJAX;
 *   - <template data-ht-toast-pending>, dupa o adaugare cu reincarcarea paginii.
 */
(function () {
    'use strict';

    var stack = document.querySelector('[data-ht-toast]');

    if (!stack) {
        return;
    }

    var data = window.htToastData || {};
    var LIFE = Math.max(2000, parseInt(data.life, 10) || 6000);
    var MAX = Math.max(1, parseInt(data.max, 10) || 3);

    /* durata din CSS tine bara de timp sincronizata cu cronometrul din JS */
    stack.style.setProperty('--ht-t-life', LIFE + 'ms');

    /* ------------------------------------------------------------------
     * Ajutoare
     * ---------------------------------------------------------------- */

    function calm() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function live() {
        return Array.prototype.filter.call(stack.children, function (node) {
            return !node.classList.contains('is-closing');
        });
    }

    /* ------------------------------------------------------------------
     * Cronometrul
     * ---------------------------------------------------------------- */

    var timers = new WeakMap();

    function stop(item) {
        var id = timers.get(item);

        if (id) {
            window.clearTimeout(id);
            timers.delete(item);
        }
    }

    function start(item) {
        stop(item);
        timers.set(item, window.setTimeout(function () {
            dismiss(item);
        }, LIFE));
    }

    /* ------------------------------------------------------------------
     * Intrarea si iesirea
     * ---------------------------------------------------------------- */

    function dismiss(item) {
        if (!item || item.classList.contains('is-closing')) {
            return;
        }

        stop(item);
        item.classList.remove('is-open');
        item.classList.add('is-closing');

        var drop = function () {
            if (item.parentNode) {
                item.parentNode.removeChild(item);
            }
        };

        if (calm()) {
            drop();
            return;
        }

        item.addEventListener('transitionend', drop, {once: true});

        /* plasa de siguranta: daca tranzitia nu porneste, cartonasul tot pleaca */
        window.setTimeout(drop, 600);
    }

    /**
     * Baga un cartonas in stiva.
     *
     * Daca acelasi produs e adaugat din nou, cartonasul deschis isi schimba doar
     * continutul - altfel s-ar aduna cate unul la fiecare apasare.
     */
    function push(item) {
        var key = item.getAttribute('data-ht-toast-key');
        var open = key ? stack.querySelector('[data-ht-toast-key="' + key + '"]:not(.is-closing)') : null;

        if (open) {
            open.innerHTML = item.innerHTML;
            open.classList.remove('is-bumped');

            /* repornirea animatiei cere o citire intre stergere si adaugare */
            void open.offsetWidth;

            open.classList.add('is-bumped');
            restartBar(open);
            start(open);

            return;
        }

        watch(item);
        stack.appendChild(item);

        live().slice(0, -MAX).forEach(dismiss);

        /* doua cadre: primul aseaza starea de intrare, al doilea porneste tranzitia */
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                item.classList.add('is-open');
            });
        });

        start(item);
    }

    /**
     * Bara de timp o ia de la capat dupa o improspatare.
     */
    function restartBar(item) {
        var bar = item.querySelector('.ht-toast__bar');

        if (!bar) {
            return;
        }

        bar.style.animation = 'none';
        void bar.offsetWidth;
        bar.style.animation = '';
    }

    /**
     * Arata markup-ul primit de pe server.
     *
     * @param {string} html Unul sau mai multe cartonase.
     */
    function show(html) {
        var text = String(html || '').trim();

        if (!text) {
            return;
        }

        var box = document.createElement('div');
        box.innerHTML = text;

        /* fragmentele vin impachetate in <template>, al carui continut sta intr-un
         * document separat si nu se vede prin querySelectorAll */
        var tpl = box.querySelector('template');

        if (tpl) {
            box = document.createElement('div');
            box.innerHTML = tpl.innerHTML;
        }

        Array.prototype.slice.call(box.querySelectorAll('[data-ht-toast-item]')).forEach(push);
    }

    /* ------------------------------------------------------------------
     * Interactiunea
     * ---------------------------------------------------------------- */

    stack.addEventListener('click', function (event) {
        var item = event.target.closest('[data-ht-toast-item]');

        if (!item) {
            return;
        }

        if (event.target.closest('[data-ht-toast-close]')) {
            dismiss(item);
            return;
        }

        /* dupa "Vezi coșul" panoul preia treaba, deci cartonasul se retrage */
        if (event.target.closest('[data-ht-minicart-open]')) {
            dismiss(item);
        }
    });

    /**
     * Cat timp cursorul sau focusul sta pe cartonas, numaratoarea asteapta.
     *
     * Bara nu se poate pune pe pauza si relua din acelasi punct fara sa citim
     * timpul ramas, deci la iesire porneste o numaratoare noua, de la capat.
     */
    function hold(item, on) {
        if (item.classList.contains('is-closing')) {
            return;
        }

        item.classList.toggle('is-held', on);

        if (on) {
            stop(item);
        } else {
            restartBar(item);
            start(item);
        }
    }

    /* ascultatorii stau pe cartonas, nu pe stiva: asa nu se declanseaza si la
     * trecerea cursorului dintr-un element interior in altul */
    function watch(item) {
        item.addEventListener('pointerenter', function () { hold(item, true); });
        item.addEventListener('pointerleave', function () { hold(item, false); });
        item.addEventListener('focusin', function () { hold(item, true); });
        item.addEventListener('focusout', function () { hold(item, false); });
    }

    /* ------------------------------------------------------------------
     * Sursele
     * ---------------------------------------------------------------- */

    /* adaugarea prin AJAX: fragmentul vine direct in argumentele evenimentului */
    if (window.jQuery) {
        window.jQuery(document.body).on('added_to_cart', function (event, fragments) {
            var slot = fragments && fragments['div[data-ht-toast-slot]'];

            if (slot) {
                show(slot);
            }
        });
    }

    /* adaugarea clasica: cartonasul asteapta in <template>, din sesiune */
    var pending = document.querySelector('[data-ht-toast-pending]');

    if (pending) {
        show(pending.innerHTML);
        pending.remove();
    }

    /* usa pentru restul temei: window.htToast.show('<article ...>') */
    window.htToast = {show: show, dismiss: dismiss};
}());
