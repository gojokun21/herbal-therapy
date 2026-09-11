/**
 * Bara de filtre a catalogului.
 *
 * Filtrele, sortarea si paginarea se aplica fara reincarcarea paginii: cerem
 * aceeasi adresa cu parametrul 'ht_ajax' adaugat si inlocuim doar coloana din
 * dreapta (inc/shop-ajax.php). Adresa din bara browserului se schimba odata cu
 * ea, deci o listare filtrata se poate pune la favorite sau trimite mai
 * departe, iar sagetile inainte/inapoi functioneaza.
 *
 * Adresa e singura sursa de adevar: din ea se sincronizeaza bifele si glisorul
 * de pret, catre ea se construiesc toate cererile.
 *
 * Fara JavaScript formularul ramane un GET obisnuit catre aceeasi adresa; daca
 * o cerere esueaza, navigam acolo, ca vizitatorul sa vada tot rezultatul.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-ht-filters]');

    if (!form) {
        return;
    }

    var main = document.querySelector('[data-ht-shop-main]');
    var panel = document.getElementById('htShopFilters');

    var range = form.querySelector('[data-ht-range]');
    var floor = range ? parseFloat(range.getAttribute('data-floor')) : 0;
    var ceil = range ? parseFloat(range.getAttribute('data-ceil')) : 0;
    var span = (ceil - floor) || 1;

    var handleMin = range ? range.querySelector('[data-ht-range-min]') : null;
    var handleMax = range ? range.querySelector('[data-ht-range-max]') : null;
    var fill = range ? range.querySelector('[data-ht-range-fill]') : null;
    var fieldMin = form.querySelector('[data-ht-range-input="min"]');
    var fieldMax = form.querySelector('[data-ht-range-input="max"]');

    /* grupurile de bife, in ordinea in care intra in adresa */
    var GROUPS = ['cat', 'price', 'stock'];

    /* sortarea nu are camp in formular: o tinem aici si o punem in adresa */
    var orderby = readParam(window.location.href, 'orderby');

    /* cererea in curs, ca doua bife apropiate sa nu se calce pe picioare */
    var pending = null;

    /* ----------------------------------------------------------------------
     * Adresa
     * ------------------------------------------------------------------- */

    /**
     * @param {string} url Adresa, absoluta sau relativa.
     *
     * @return {URL}
     */
    function parse(url) {
        return new URL(url, window.location.href);
    }

    /**
     * @param {string} url Adresa citita.
     * @param {string} key Numele parametrului.
     *
     * @return {string} Sirul gol cand parametrul lipseste.
     */
    function readParam(url, key) {
        return parse(url).searchParams.get(key) || '';
    }

    /**
     * Valorile unui grup, indiferent daca adresa le poarta separate prin
     * virgula (link-urile temei) sau ca 'cat[]=a&cat[]=b' (formularul).
     *
     * @param {string} url Adresa citita.
     * @param {string} key Numele grupului.
     *
     * @return {Array} Valori unice.
     */
    function readGroup(url, key) {
        var params = parse(url).searchParams;
        var out = [];

        [key, key + '[]'].forEach(function (name) {
            params.getAll(name).forEach(function (raw) {
                String(raw).split(',').forEach(function (value) {
                    if ('' !== value && -1 === out.indexOf(value)) {
                        out.push(value);
                    }
                });
            });
        });

        return out;
    }

    /**
     * Capatul de pret din adresa.
     *
     * @param {string} url Adresa citita.
     * @param {string} key 'min_price' sau 'max_price'.
     *
     * @return {number|null} Null cand parametrul lipseste.
     */
    function readPrice(url, key) {
        var raw = readParam(url, key);

        if ('' === raw) {
            return null;
        }

        var number = parseInt(raw.replace(/\D/g, ''), 10);

        return isNaN(number) ? null : number;
    }

    /**
     * Scoate parametrul cererii AJAX dintr-o adresa.
     *
     * Ajunge acolo prin link-urile de paginare, pe care WooCommerce le
     * construieste din adresa ceruta; in bara browserului nu are ce cauta.
     *
     * @param {string} url Adresa curatata.
     *
     * @return {string}
     */
    function strip(url) {
        return url.replace(/([?&])ht_ajax=[^&#]*&?/g, '$1').replace(/[?&]$/, '');
    }

    /**
     * @param {string} url   Adresa de pornire.
     * @param {string} extra Perechea adaugata.
     *
     * @return {string}
     */
    function append(url, extra) {
        var cut = url.indexOf('#');
        var base = (-1 === cut) ? url : url.slice(0, cut);

        return base + ((-1 === base.indexOf('?')) ? '?' : '&') + extra;
    }

    /* ----------------------------------------------------------------------
     * Starea formularului
     * ------------------------------------------------------------------- */

    /**
     * Bifele si glisorul, stranse in adresa listarii.
     *
     * Pastreaza sortarea si porneste mereu de la prima pagina - alt filtru
     * inseamna alt set de rezultate.
     *
     * @return {string}
     */
    function buildUrl() {
        var parts = [];

        GROUPS.forEach(function (key) {
            var checked = [];

            Array.prototype.forEach.call(
                form.querySelectorAll('input[name="' + key + '[]"]:checked'),
                function (box) {
                    checked.push(encodeURIComponent(box.value));
                }
            );

            if (checked.length) {
                parts.push(key + '=' + checked.join(','));
            }
        });

        var price = priceValues();

        if (price) {
            parts.push('min_price=' + price.min);
            parts.push('max_price=' + price.max);
        }

        if ('' !== orderby) {
            parts.push('orderby=' + encodeURIComponent(orderby));
        }

        var base = form.getAttribute('action') || window.location.pathname;

        if (!parts.length) {
            return base;
        }

        return base + ((-1 === base.indexOf('?')) ? '?' : '&') + parts.join('&');
    }

    /**
     * Capetele de pret, normalizate si scrise inapoi in campuri.
     *
     * @return {Object|null} Null cand intervalul e cel intreg, deci nu are ce
     *                       cauta in adresa.
     */
    function priceValues() {
        if (!fieldMin || !fieldMax) {
            return null;
        }

        var min = digits(fieldMin.value, floor);
        var max = digits(fieldMax.value, ceil);

        if (min > max) {
            var swap = min;

            min = max;
            max = swap;
        }

        min = clamp(min);
        max = clamp(max);

        fieldMin.value = min;
        fieldMax.value = max;

        return (min <= floor && max >= ceil) ? null : {min: min, max: max};
    }

    /**
     * @param {string} value    Textul din camp, cu tot cu separatorii de mii.
     * @param {number} fallback Valoarea intoarsa cand campul nu are cifre.
     *
     * @return {number}
     */
    function digits(value, fallback) {
        var number = parseInt(String(value).replace(/\D/g, ''), 10);

        return isNaN(number) ? fallback : number;
    }

    /**
     * @param {number} value Valoarea adusa intre capetele glisorului.
     *
     * @return {number}
     */
    function clamp(value) {
        return Math.min(Math.max(value, floor), ceil);
    }

    /**
     * Aduce formularul in starea descrisa de adresa.
     *
     * @param {string} url Adresa citita.
     */
    function applyUrl(url) {
        GROUPS.forEach(function (key) {
            var checked = readGroup(url, key);

            Array.prototype.forEach.call(
                form.querySelectorAll('input[name="' + key + '[]"]'),
                function (box) {
                    box.checked = (-1 !== checked.indexOf(box.value));
                }
            );
        });

        orderby = readParam(url, 'orderby');

        if (!range) {
            return;
        }

        var min = readPrice(url, 'min_price');
        var max = readPrice(url, 'max_price');

        handleMin.value = (null === min) ? floor : clamp(min);
        handleMax.value = (null === max) ? ceil : clamp(max);

        syncFields();
        paint();
    }

    /**
     * Goleste un grup de filtre; cu sirul gol, goleste tot.
     *
     * Aceeasi impartire ca la legaturile de resetare din bara laterala:
     * intervalul liber de pret pleaca odata cu intervalele bifate.
     *
     * @param {string} key Numele grupului golit.
     */
    function clearGroup(key) {
        (key ? [key] : GROUPS).forEach(function (name) {
            Array.prototype.forEach.call(
                form.querySelectorAll('input[name="' + name + '[]"]:checked'),
                function (box) {
                    box.checked = false;
                }
            );
        });

        if (range && ('' === key || 'price' === key)) {
            handleMin.value = floor;
            handleMax.value = ceil;
            syncFields();
            paint();
        }
    }

    /* ----------------------------------------------------------------------
     * Cererea
     * ------------------------------------------------------------------- */

    /**
     * Aduce listarea de la adresa data si inlocuieste coloana din dreapta.
     *
     * @param {string} url     Adresa listarii, fara parametrul de AJAX.
     * @param {Object} options push - false lasa istoricul neatins (revenirea
     *                         prin sagetile browserului);
     *                         scroll - true urca la inceputul listarii.
     */
    function load(url, options) {
        var settings = options || {};

        url = strip(url);

        if (!main || !window.fetch) {
            window.location.href = url;

            return;
        }

        if (pending) {
            pending.abort();
        }

        var controller = window.AbortController ? new window.AbortController() : null;

        pending = controller;

        main.classList.add('is-loading');
        main.setAttribute('aria-busy', 'true');

        window.fetch(append(url, 'ht_ajax=1'), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: controller ? controller.signal : undefined
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('http');
                }

                return response.json();
            })
            .then(function (data) {
                if (!data || !data.success || !data.data) {
                    throw new Error('payload');
                }

                main.innerHTML = data.data.html;
                applyFacets(data.data.facets);

                if (false !== settings.push) {
                    window.history.pushState({htShop: true}, '', url);
                }

                if (settings.scroll) {
                    scrollToResults();
                }

                /* restul temei poate porni ce mai are nevoie pe cardurile noi */
                document.dispatchEvent(new CustomEvent('ht:shop-updated', {detail: data.data}));
            })
            .catch(function (error) {
                /* cererea a fost inlocuita de una mai noua - nu s-a stricat nimic */
                if (error && 'AbortError' === error.name) {
                    return;
                }

                window.location.href = url;
            })
            .then(function () {
                if (pending !== controller) {
                    return;
                }

                pending = null;
                main.classList.remove('is-loading');
                main.removeAttribute('aria-busy');
            });
    }

    /**
     * Scrie in bara laterala contoarele primite cu fragmentul.
     *
     * Bara nu se re-randeaza (ar pierde cautarea din lista de categorii si
     * focusul), doar numerele din dreapta optiunilor se schimba. Grupul de
     * categorii vine deja restrans la listarea curenta din PHP, deci aici nu
     * apar si nu dispar optiuni.
     *
     * @param {Object} facets {cat: {slug: n}, price: {...}, stock: {...}}
     */
    function applyFacets(facets) {
        if (!facets) {
            return;
        }

        GROUPS.forEach(function (key) {
            var counts = facets[key];

            if (!counts) {
                return;
            }

            Array.prototype.forEach.call(
                form.querySelectorAll('input[name="' + key + '[]"]'),
                function (box) {
                    if (!Object.prototype.hasOwnProperty.call(counts, box.value)) {
                        return;
                    }

                    var count = box.closest('label').querySelector('.ht-check__count');

                    if (count) {
                        count.textContent = formatCount(counts[box.value]);
                    }
                }
            );
        });
    }

    /**
     * Numarul cu separator de mii, ca in PHP (number_format_i18n).
     *
     * @param {number} value Numarul afisat.
     *
     * @return {string}
     */
    function formatCount(value) {
        try {
            return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);
        } catch (error) {
            return String(value);
        }
    }

    /**
     * Urca la inceputul listarii, dupa o schimbare de pagina.
     */
    function scrollToResults() {
        var top = main.getBoundingClientRect().top + window.pageYOffset - 24;

        window.scrollTo({top: Math.max(top, 0), behavior: 'smooth'});
    }

    /* ----------------------------------------------------------------------
     * Formularul
     * ------------------------------------------------------------------- */

    form.addEventListener('change', function (event) {
        if (event.target && 'checkbox' === event.target.type) {
            load(buildUrl());
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        load(buildUrl());
    });

    /* legaturile de resetare stau in bara laterala, care nu se re-randeaza:
       le calculam din starea formularului, nu din adresa lor */
    form.addEventListener('click', function (event) {
        var reset = event.target.closest ? event.target.closest('[data-ht-filters-reset]') : null;

        if (!reset) {
            return;
        }

        event.preventDefault();
        clearGroup(reset.getAttribute('data-ht-filters-reset') || '');
        load(buildUrl());
    });

    /* ----------------------------------------------------------------------
     * Etichetele, sortarea si paginarea
     *
     * Toate trei se re-randeaza la fiecare cerere, deci ascultam delegat pe
     * containerul care le cuprinde.
     * ------------------------------------------------------------------- */

    if (main) {
        main.addEventListener('click', function (event) {
            if (!event.target.closest) {
                return;
            }

            var chip = event.target.closest('.ht-chip');

            if (chip) {
                event.preventDefault();
                applyUrl(chip.href);
                load(chip.href);

                return;
            }

            var page = event.target.closest('.woocommerce-pagination a');

            if (page) {
                event.preventDefault();
                load(page.href, {scroll: true});
            }
        });

        main.addEventListener('change', function (event) {
            if (event.target && 'orderby' === event.target.name) {
                orderby = event.target.value;
                load(buildUrl());
            }
        });

        /* formularul de sortare are si un buton propriu, pentru cazul fara JavaScript */
        main.addEventListener('submit', function (event) {
            if (event.target && event.target.classList.contains('woocommerce-ordering')) {
                event.preventDefault();
                load(buildUrl());
            }
        });
    }

    window.addEventListener('popstate', function () {
        applyUrl(window.location.href);
        load(window.location.href, {push: false});
    });

    /* ----------------------------------------------------------------------
     * Cautarea in lista de categorii
     * ------------------------------------------------------------------- */

    var search = form.querySelector('[data-ht-filters-search]');
    var list = form.querySelector('[data-ht-filters-searchable]');
    var empty = form.querySelector('[data-ht-filters-empty]');

    if (search && list) {
        search.addEventListener('input', function () {
            var needle = search.value.trim().toLowerCase();
            var shown = 0;

            Array.prototype.forEach.call(list.children, function (item) {
                var label = item.querySelector('.ht-check__label');
                var text = label ? label.textContent.toLowerCase() : '';
                var match = ('' === needle || -1 !== text.indexOf(needle));

                item.hidden = !match;

                if (match) {
                    shown++;
                }
            });

            if (empty) {
                empty.hidden = (0 !== shown);
            }
        });

        /* Enter in campul de cautare nu trimite formularul, doar filtreaza lista */
        search.addEventListener('keydown', function (event) {
            if ('Enter' === event.key) {
                event.preventDefault();
            }
        });
    }

    /* ----------------------------------------------------------------------
     * Glisorul de pret
     * ------------------------------------------------------------------- */

    /**
     * Deseneaza portiunea plina dintre cele doua manete.
     */
    function paint() {
        if (!fill) {
            return;
        }

        fill.style.left = (((parseFloat(handleMin.value) - floor) / span) * 100) + '%';
        fill.style.right = (100 - (((parseFloat(handleMax.value) - floor) / span) * 100)) + '%';
    }

    /**
     * Trece valorile manetelor in campurile de sub glisor.
     */
    function syncFields() {
        if (fieldMin) {
            fieldMin.value = handleMin.value;
        }

        if (fieldMax) {
            fieldMax.value = handleMax.value;
        }
    }

    /**
     * Manetele nu se pot depasi una pe alta.
     *
     * @param {string} moved Maneta care tocmai s-a miscat.
     */
    function keepOrder(moved) {
        var low = parseFloat(handleMin.value);
        var high = parseFloat(handleMax.value);

        if (low <= high) {
            return;
        }

        if ('min' === moved) {
            handleMin.value = high;
        } else {
            handleMax.value = low;
        }
    }

    if (range) {
        [['min', handleMin], ['max', handleMax]].forEach(function (pair) {
            pair[1].addEventListener('input', function () {
                keepOrder(pair[0]);
                syncFields();
                paint();
            });

            pair[1].addEventListener('change', function () {
                load(buildUrl());
            });
        });

        /* campurile de sub glisor misca manetele si trimit la iesire */
        [['min', fieldMin, handleMin], ['max', fieldMax, handleMax]].forEach(function (pair) {
            if (!pair[1]) {
                return;
            }

            pair[1].addEventListener('input', function () {
                var number = parseInt(String(pair[1].value).replace(/\D/g, ''), 10);

                if (isNaN(number)) {
                    return;
                }

                pair[2].value = clamp(number);
                keepOrder(pair[0]);
                paint();
            });

            pair[1].addEventListener('change', function () {
                load(buildUrl());
            });
        });

        paint();
    }

    /* ----------------------------------------------------------------------
     * Panoul de filtre pe ecrane mici
     * ------------------------------------------------------------------- */

    var toggle = panel ? panel.querySelector('[data-ht-filters-toggle]') : null;

    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            var open = panel.classList.toggle('is-open');

            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
}());
