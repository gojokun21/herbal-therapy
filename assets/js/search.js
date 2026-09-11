(function () {
    'use strict';

    var panel = document.getElementById('htSearch');
    var data = window.htSearchData || {};

    if (!panel || !data.rest) {
        return;
    }

    var form = panel.querySelector('[data-ht-search-form]');
    var input = panel.querySelector('[data-ht-search-input]');
    var body = panel.querySelector('[data-ht-search-body]');
    var clear = panel.querySelector('[data-ht-search-clear]');

    if (!form || !input || !body) {
        return;
    }

    var STORAGE_KEY = 'ht_recent_searches';
    var RECENT_MAX = 6;
    var DELAY = 250;

    var timer = null;
    var request = null;
    var lastQuery = null;

    /* ------------------------------------------------------------------
     * Cautari recente
     * ---------------------------------------------------------------- */

    function readRecent() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            var list = raw ? JSON.parse(raw) : [];

            return Array.isArray(list) ? list.filter(function (item) {
                return typeof item === 'string' && item.trim() !== '';
            }) : [];
        } catch (err) {
            return [];
        }
    }

    function writeRecent(list) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch (err) {
            /* stocare indisponibila (mod privat, cota plina) - istoricul e optional */
        }
    }

    function rememberQuery(query) {
        query = query.trim();

        if (query.length < (data.min || 2)) {
            return;
        }

        var list = readRecent().filter(function (item) {
            return item.toLowerCase() !== query.toLowerCase();
        });

        list.unshift(query);
        writeRecent(list.slice(0, RECENT_MAX));
    }

    /* Blocul de istoric se re-randeaza dupa fiecare raspuns: markup-ul venit de
       pe server il contine gol, iar lista traieste doar in browser. */
    function renderRecent() {
        var block = body.querySelector('[data-ht-recent]');
        var list = block ? block.querySelector('[data-ht-recent-list]') : null;

        if (!block || !list) {
            return;
        }

        var items = readRecent();

        if (!items.length) {
            block.hidden = true;
            list.textContent = '';

            return;
        }

        list.textContent = '';

        items.forEach(function (item) {
            var li = document.createElement('li');
            var btn = document.createElement('button');

            btn.type = 'button';
            btn.className = 'ht-search__chip';
            btn.textContent = item;
            btn.setAttribute('data-ht-recent-term', item);

            li.appendChild(btn);
            list.appendChild(li);
        });

        block.hidden = false;
    }

    /* ------------------------------------------------------------------
     * Sugestii
     * ---------------------------------------------------------------- */

    /* campul gol nu arata nimic: panoul ramane doar bara de cautare */
    function showInitial() {
        lastQuery = '';

        if (window.AbortController && request) {
            request.abort();
            request = null;
        }

        body.innerHTML = '';
        body.classList.remove('is-loading');
    }

    function fetchSuggestions(query) {
        if (window.AbortController && request) {
            request.abort();
        }

        var controller = window.AbortController ? new AbortController() : null;
        request = controller;

        body.classList.add('is-loading');

        var url = data.rest + '?q=' + encodeURIComponent(query);

        /* adresa REST nu are prefix de limba: fara asta panoul ar amesteca limbile */
        if (data.lang) {
            url += '&lang=' + encodeURIComponent(data.lang);
        }

        window.fetch(url, {
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(response.status);
        }).then(function (payload) {
            /* raspunsul unei taste vechi nu trebuie sa suprascrie ce se vede acum */
            if (payload.query !== input.value.trim()) {
                return;
            }

            body.innerHTML = payload.html;
            body.classList.remove('is-loading');
            renderRecent();
        }).catch(function (err) {
            if (err && err.name === 'AbortError') {
                return;
            }

            body.classList.remove('is-loading');
        });
    }

    function update() {
        var query = input.value.trim();

        if (clear) {
            clear.hidden = query === '';
        }

        if (query === lastQuery) {
            return;
        }

        if (query.length < (data.min || 2)) {
            showInitial();

            return;
        }

        lastQuery = query;
        fetchSuggestions(query);
    }

    input.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(update, DELAY);
    });

    form.addEventListener('submit', function () {
        rememberQuery(input.value);
    });

    if (clear) {
        clear.addEventListener('click', function () {
            input.value = '';
            input.focus();
            update();
        });
    }

    /* ------------------------------------------------------------------
     * Click-uri din corpul panoului
     * ---------------------------------------------------------------- */

    body.addEventListener('click', function (event) {
        var chip = event.target.closest('[data-ht-recent-term]');

        if (chip) {
            input.value = chip.getAttribute('data-ht-recent-term');
            input.focus();
            update();

            return;
        }

        if (event.target.closest('[data-ht-recent-clear]')) {
            writeRecent([]);
            renderRecent();

            return;
        }

        /* un rezultat deschis din panou intra si el in istoric */
        if (event.target.closest('.ht-suggest, .ht-search__all')) {
            rememberQuery(input.value);
        }
    });

    /* ------------------------------------------------------------------
     * Navigare cu sagetile
     * ---------------------------------------------------------------- */

    function items() {
        return Array.prototype.slice.call(body.querySelectorAll('.ht-suggest, .ht-search__links a, .ht-search__all'));
    }

    function move(step) {
        var list = items();

        if (!list.length) {
            return;
        }

        var current = list.indexOf(document.activeElement);
        var next = current + step;

        if (current === -1) {
            next = step > 0 ? 0 : list.length - 1;
        }

        if (next < 0) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);

            return;
        }

        if (next >= list.length) {
            next = list.length - 1;
        }

        list[next].focus();
    }

    panel.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(-1);
        }
    });

    /* ------------------------------------------------------------------
     * Deschiderea panoului (semnalul vine din header.js)
     * ---------------------------------------------------------------- */

    document.addEventListener('ht:search', function (event) {
        if (!event.detail || !event.detail.open) {
            return;
        }

        if (clear) {
            clear.hidden = input.value.trim() === '';
        }

        /* cu text ramas in camp se reiau sugestiile; gol, panoul e doar bara */
        lastQuery = null;
        update();

        input.select();
    });

    panel.addEventListener('click', function (event) {
        if (event.target.closest('[data-ht-search-close]') && window.htHeader) {
            window.htHeader.toggleSearch(false);
        }
    });
})();
