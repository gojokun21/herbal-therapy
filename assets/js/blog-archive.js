/**
 * Bara de teme a listarii de articole.
 *
 * Formularul e un GET obisnuit catre adresa listarii, deci filtrarea merge si
 * fara JavaScript. Scriptul face doar doua lucruri: trimite formularul la
 * bifare (si scoate butonul devenit inutil) si deschide panoul pe ecrane mici.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-ht-blog-filters]');

    if (!form) {
        return;
    }

    var toggle = form.querySelector('.ht-blog-filters__toggle');
    var panel = form.querySelector('.ht-blog-filters__panel');

    /* marcheaza ca CSS-ul poate ascunde butonul si inchide panoul pe mobil */
    form.classList.add('is-scripted');

    /* o bifa noua inseamna o listare noua, deci pornim de la prima pagina */
    form.addEventListener('change', function (event) {
        if (!event.target.classList.contains('ht-check__input')) {
            return;
        }

        form.submit();
    });

    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', function () {
        var open = form.classList.toggle('is-open');

        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}());
