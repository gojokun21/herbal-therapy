(function () {
    'use strict';

    var config = window.htFavoritesData || {};

    if (!config.rest) {
        return;
    }

    /* lista veche, de pe cand favoritele stateau doar in browser */
    var LEGACY_KEY = 'ht_favorites';

    /* {id: true} pentru produsele salvate; pornim de la ce a randat serverul */
    var state = {};

    /* cereri in curs, ca doua clicuri rapide sa nu se calce pe picioare */
    var pending = {};

    (config.ids || []).forEach(function (id) {
        state[String(id)] = true;
    });

    /* ------------------------------------------------------------------
     * Stare
     * ---------------------------------------------------------------- */

    function isFavorite(id) {
        return true === state[String(id)];
    }

    function ids() {
        return Object.keys(state).filter(function (id) {
            return state[id];
        });
    }

    /**
     * Sincronizeaza un buton cu starea din memorie. Eticheta si aria-pressed se
     * schimba odata cu inima - butonul e un comutator, nu doar o iconita.
     */
    function paint(button) {
        var id = button.getAttribute('data-product-id');
        var active = isFavorite(id);
        var label = active
            ? button.getAttribute('data-label-remove')
            : button.getAttribute('data-label-add');

        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');

        if (label) {
            button.setAttribute('aria-label', label);
        }
    }

    /**
     * Acelasi produs poate aparea de mai multe ori pe pagina (carusel + grila),
     * asa ca redesenam toate butoanele lui, nu doar pe cel apasat.
     */
    function paintAll(id) {
        var selector = id
            ? '[data-ht-favorite][data-product-id="' + id + '"]'
            : '[data-ht-favorite]';

        document.querySelectorAll(selector).forEach(paint);
    }

    function paintCount() {
        var total = ids().length;

        document.querySelectorAll('[data-ht-favorites-count]').forEach(function (badge) {
            badge.textContent = total ? String(total) : '';
        });
    }

    function render(id) {
        paintAll(id);
        paintCount();
    }

    /**
     * Preia lista intoarsa de server. Raspunsul e sursa de adevar: daca intre
     * timp un produs a fost sters, dispare si din starea locala.
     */
    function apply(data) {
        if (!data || !Array.isArray(data.ids)) {
            return;
        }

        var next = {};

        data.ids.forEach(function (id) {
            next[String(id)] = true;
        });

        /*
         * Produsele cu cereri inca pe drum raman pe starea aleasa de clic:
         * raspunsul unei cereri mai vechi nu stie de ele si le-ar rasturna.
         */
        Object.keys(pending).forEach(function (id) {
            if (state[id]) {
                next[id] = true;
            } else {
                delete next[id];
            }
        });

        state = next;

        render();
    }

    /* ------------------------------------------------------------------
     * Comunicarea cu serverul
     * ---------------------------------------------------------------- */

    function request(body) {
        var headers = {'Content-Type': 'application/json'};

        /* limba paginii - in REST, Polylang nu o poate deduce singur */
        if (config.lang) {
            body.lang = config.lang;
        }

        if (config.nonce) {
            headers['X-WP-Nonce'] = config.nonce;
        }

        return window.fetch(config.rest, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify(body)
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            return response.json();
        });
    }

    /*
     * Scrierile pleaca una dupa alta, nu in paralel: pe server lista se
     * citeste, se modifica si se scrie la loc, deci doua cereri simultane
     * s-ar suprascrie una pe alta si un produs abia bifat s-ar pierde.
     */
    var queue = Promise.resolve();

    function send(body) {
        var turn = function () {
            return request(body);
        };

        var result = queue.then(turn, turn);

        /* lantul merge mai departe indiferent de soarta cererii */
        queue = result.then(function () {}, function () {});

        return result;
    }

    /* ------------------------------------------------------------------
     * Comutarea
     * ---------------------------------------------------------------- */

    function toggle(button) {
        var id = String(button.getAttribute('data-product-id') || '');

        /* cardurile demo nu au produs in spate - butonul ramane doar vizual */
        if (!id || '0' === id) {
            button.classList.toggle('is-active');

            return;
        }

        if (pending[id]) {
            return;
        }

        pending[id] = true;

        var next = !isFavorite(id);

        /* raspuns imediat la clic; daca cererea cade, revenim la starea veche */
        state[id] = next;
        render(id);
        button.classList.add('is-busy');

        send({action: next ? 'add' : 'remove', id: parseInt(id, 10)})
            .then(function (data) {
                apply(data);

                document.dispatchEvent(new CustomEvent('ht:favorites', {
                    detail: {id: id, active: isFavorite(id), count: ids().length}
                }));
            })
            .catch(function () {
                state[id] = !next;
                render(id);
            })
            .then(function () {
                delete pending[id];
                button.classList.remove('is-busy');
            });
    }

    /*
     * Ascultam pe document, nu pe fiecare buton: cardurile aduse ulterior
     * (filtre, incarcare progresiva, produse asociate) functioneaza fara vreo
     * initializare in plus. Butonul sta in interiorul link-ului cardului, deci
     * oprim si navigarea.
     */
    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.closest) {
            return;
        }

        var button = event.target.closest('[data-ht-favorite]');

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        toggle(button);
    });

    /* ------------------------------------------------------------------
     * Lista veche din localStorage
     * ---------------------------------------------------------------- */

    /**
     * Ce a fost salvat inainte de mutarea pe server urca o singura data in cont
     * (sau in cookie), apoi cheia din browser dispare.
     */
    function migrateLegacy() {
        var raw;

        try {
            raw = window.localStorage.getItem(LEGACY_KEY);
        } catch (e) {
            return;
        }

        if (!raw) {
            return;
        }

        var list;

        try {
            list = JSON.parse(raw);
            window.localStorage.removeItem(LEGACY_KEY);
        } catch (e) {
            list = null;
        }

        if (!Array.isArray(list) || !list.length) {
            return;
        }

        send({
            action: 'add',
            ids: list.map(function (id) {
                return parseInt(id, 10);
            }).filter(Boolean)
        }).then(apply).catch(function () {
            /* ramane pe seama urmatorului clic */
        });
    }

    /* ------------------------------------------------------------------
     * Pagina de favorite
     * ---------------------------------------------------------------- */

    /**
     * Pe pagina listei, un produs scos din favorite nu mai are ce cauta in
     * grila. Cand grila ramane goala, ii ia locul mesajul de lista goala.
     */
    function initPage() {
        var grid = document.querySelector('[data-ht-favorites-grid]');

        if (!grid) {
            return;
        }

        var empty = document.querySelector('[data-ht-favorites-empty]');
        var clear = document.querySelector('[data-ht-favorites-clear]');

        function sync() {
            var left = grid.querySelectorAll('[data-favorite-item]').length;

            grid.hidden = 0 === left;

            if (empty) {
                empty.hidden = left > 0;
            }

            if (clear) {
                clear.hidden = 0 === left;
            }
        }

        document.addEventListener('ht:favorites', function (event) {
            if (event.detail.active) {
                return;
            }

            var item = grid.querySelector('[data-favorite-item="' + event.detail.id + '"]');

            if (item) {
                item.remove();
            }

            sync();
        });

        if (clear) {
            clear.addEventListener('click', function () {
                clear.disabled = true;

                send({action: 'clear'})
                    .then(function (data) {
                        apply(data);

                        grid.querySelectorAll('[data-favorite-item]').forEach(function (item) {
                            item.remove();
                        });

                        sync();
                    })
                    .catch(function () {
                        /* lista ramane pe ecran, utilizatorul poate reincerca */
                    })
                    .then(function () {
                        clear.disabled = false;
                    });
            });
        }
    }

    /* ------------------------------------------------------------------
     * Pornire
     * ---------------------------------------------------------------- */

    render();
    initPage();
    migrateLegacy();

    /*
     * Cardurile aduse dupa incarcare vin cu inima asa cum a randat-o serverul
     * la vremea lor - taburile din "Top vanzari" refolosesc HTML din cache,
     * filtrele aduc grile noi - asa ca le aducem la starea curenta de indata
     * ce apar in pagina.
     */
    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            var added = mutations.some(function (mutation) {
                return Array.prototype.some.call(mutation.addedNodes, function (node) {
                    return 1 === node.nodeType && (
                        (node.matches && node.matches('[data-ht-favorite]'))
                        || (node.querySelector && null !== node.querySelector('[data-ht-favorite]'))
                    );
                });
            });

            if (added) {
                render();
            }
        }).observe(document.documentElement, {childList: true, subtree: true});
    }

    /* expus pentru restul temei (pagina de favorite, teste manuale) */
    window.htFavorites = {
        has: isFavorite,
        ids: ids,
        count: function () {
            return ids().length;
        },
        toggle: function (id) {
            var button = document.querySelector('[data-ht-favorite][data-product-id="' + id + '"]');

            if (button) {
                toggle(button);
            }
        },
        refresh: function () {
            var url = config.rest
                + (config.lang ? '?lang=' + encodeURIComponent(config.lang) : '');

            return window.fetch(url, {credentials: 'same-origin'})
                .then(function (response) {
                    return response.json();
                })
                .then(apply);
        }
    };
})();
