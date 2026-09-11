(function () {
    'use strict';

    var header = document.getElementById('htHeader');
    if (!header) {
        return;
    }

    var body = document.body;
    var drawer = document.getElementById('htDrawer');
    var overlay = document.getElementById('htOverlay');
    var search = document.getElementById('htSearch');
    var catalog = document.getElementById('htCatalog');
    var searchToggles = document.querySelectorAll('[data-ht-search-toggle]');
    var catalogToggles = document.querySelectorAll('[data-ht-catalog-toggle]');
    var drawerToggles = document.querySelectorAll('[data-ht-drawer-toggle]');

    /* ---------- header lipit la scroll ---------- */
    var onScroll = function () {
        header.classList.toggle('is-stuck', window.scrollY > 4);
    };
    window.addEventListener('scroll', onScroll, {passive: true});
    onScroll();

    /*
     * Pe prima pagina header-ul e transparent peste hero; orice panou deschis il
     * face alb. Panourile se inchid unul dupa altul, deci nu ne uitam doar la
     * cel care tocmai s-a schimbat, ci si la celelalte, ca sa nu redevina
     * transparent peste un panou inca deschis.
     */
    function setPanelOpen(open) {
        var any = open
            || (search && search.classList.contains('is-open'))
            || (catalog && catalog.classList.contains('is-open'));

        header.classList.toggle('is-panel-open', !!any);
    }

    /* ---------- cautare ---------- */
    function toggleSearch(force) {
        if (!search) {
            return;
        }
        var open = typeof force === 'boolean' ? force : !search.classList.contains('is-open');
        if (open === search.classList.contains('is-open')) {
            return;
        }
        if (open) {
            toggleCatalog(false);
            toggleLang(false);
        }

        search.classList.toggle('is-open', open);
        setPanelOpen(open);
        body.classList.toggle('ht-no-scroll', open);
        searchToggles.forEach(function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        if (open) {
            var field = search.querySelector('.ht-search__field');
            if (field) {
                field.focus();
            }
        }
        /* sugestiile traiesc in assets/js/search.js si asculta acest semnal */
        document.dispatchEvent(new CustomEvent('ht:search', {detail: {open: open}}));
    }

    searchToggles.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleSearch();
        });
    });

    /* ---------- catalog ---------- */
    /*
     * Butonul "Catalog" este un link catre magazin, ca sa ramana folositor si
     * fara JavaScript. Aici ii preluam clicul si deschidem in loc panoul cu
     * categorii, lipit sub header, ca la cautare.
     */
    function toggleCatalog(force) {
        if (!catalog) {
            return;
        }

        var open = typeof force === 'boolean' ? force : !catalog.classList.contains('is-open');

        if (open === catalog.classList.contains('is-open')) {
            return;
        }

        if (open) {
            toggleSearch(false);
            toggleLang(false);
            closeAllDesktop();
        }

        catalog.classList.toggle('is-open', open);
        setPanelOpen(open);

        catalogToggles.forEach(function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    catalogToggles.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            /* fara panou in pagina lasam link-ul sa mearga catre magazin */
            if (!catalog) {
                return;
            }

            e.preventDefault();
            toggleCatalog();
        });
    });

    if (catalog) {
        catalog.querySelectorAll('[data-ht-catalog-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                toggleCatalog(false);
            });
        });

        /* coloana din stanga comanda ce subcategorii se vad in dreapta */
        var catalogRoots = catalog.querySelectorAll('[data-ht-catalog-cat]');
        var catalogPanes = catalog.querySelectorAll('[data-ht-catalog-pane]');

        var activateCategory = function (id) {
            catalogRoots.forEach(function (link) {
                var active = link.getAttribute('data-ht-catalog-cat') === id;
                link.parentElement.classList.toggle('is-active', active);
            });

            catalogPanes.forEach(function (pane) {
                pane.classList.toggle('is-active', pane.getAttribute('data-ht-catalog-pane') === id);
            });
        };

        catalogRoots.forEach(function (link) {
            var id = link.getAttribute('data-ht-catalog-cat');

            link.addEventListener('mouseenter', function () {
                activateCategory(id);
            });

            link.addEventListener('focus', function () {
                activateCategory(id);
            });
        });
    }

    /* ---------- comutator limba ---------- */
    var langToggle = header.querySelector('[data-ht-lang-toggle]');
    var langList = header.querySelector('[data-ht-lang-list]');

    function toggleLang(force) {
        if (!langList) {
            return;
        }

        var open = typeof force === 'boolean' ? force : !langList.classList.contains('is-open');

        langList.classList.toggle('is-open', open);
        langToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (langToggle && langList) {
        langToggle.addEventListener('click', function (e) {
            e.preventDefault();
            toggleLang();
        });

        /* lista e mica: o inchidem la orice clic in afara ei */
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.ht-lang')) {
                toggleLang(false);
            }
        });
    }

    /* ---------- comutator oras ---------- */
    /*
     * Orasul se alege doar din drawer: in bara de sus locul lui e luat de
     * butonul "Catalog". Ascultatorii merg pe toate butoanele cu [data-ht-city],
     * oriunde ar fi randate.
     */
    var cityOptions = document.querySelectorAll('[data-ht-city]');
    var cityLabels = document.querySelectorAll('[data-ht-city-label]');
    var STORAGE_KEY = 'ht_city';

    /**
     * @param {string}  city    Orasul ales.
     * @param {boolean} persist Fals doar la marcarea orasului implicit, ca o
     *                          simpla vizita sa nu treaca drept alegere.
     */
    function setCity(city, persist) {
        cityLabels.forEach(function (el) {
            el.textContent = city;
        });
        cityOptions.forEach(function (btn) {
            var active = btn.getAttribute('data-ht-city') === city;
            btn.classList.toggle('is-active', active);
            if (btn.hasAttribute('aria-pressed')) {
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            }
        });

        if (persist === false) {
            return;
        }

        try {
            window.localStorage.setItem(STORAGE_KEY, city);
        } catch (err) {
            /* localStorage indisponibil - ignoram */
        }
    }

    if (cityOptions.length) {
        var saved = null;
        try {
            saved = window.localStorage.getItem(STORAGE_KEY);
        } catch (err) {
            saved = null;
        }
        if (saved && document.querySelector('[data-ht-city="' + saved.replace(/"/g, '') + '"]')) {
            setCity(saved);
        } else {
            /* fara alegere salvata, primul oras din lista e cel marcat */
            setCity(cityOptions[0].getAttribute('data-ht-city'), false);
        }

        cityOptions.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                setCity(btn.getAttribute('data-ht-city'));
            });
        });
    }

    /* ---------- drawer mobil ---------- */
    function toggleDrawer(force) {
        if (!drawer) {
            return;
        }
        var open = typeof force === 'boolean' ? force : !drawer.classList.contains('is-open');

        if (open) {
            toggleCatalog(false);
        }

        drawer.classList.toggle('is-open', open);
        if (overlay) {
            overlay.classList.toggle('is-open', open);
        }
        body.classList.toggle('ht-no-scroll', open);
        drawerToggles.forEach(function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    drawerToggles.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleDrawer();
        });
    });

    if (overlay) {
        overlay.addEventListener('click', function () {
            toggleDrawer(false);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            toggleDrawer(false);
            toggleSearch(false);
            toggleCatalog(false);
            toggleLang(false);
            closeAllDesktop();
        }
    });

    /* ---------- acordeon in drawer ---------- */
    if (drawer) {
        drawer.addEventListener('click', function (e) {
            var link = e.target.closest('.ht-menu__link, .ht-mega__link');
            if (!link || !drawer.contains(link)) {
                return;
            }

            var item = link.parentElement;
            if (!item.classList.contains('menu-item-has-children')) {
                return;
            }

            var href = link.getAttribute('href');
            var isRealLink = href && href !== '#' && link.tagName === 'A';

            /* prima atingere deschide submeniul, a doua urmeaza linkul */
            if (isRealLink && item.classList.contains('is-open')) {
                return;
            }

            e.preventDefault();

            var siblings = item.parentElement ? item.parentElement.children : [];
            Array.prototype.forEach.call(siblings, function (sib) {
                if (sib !== item) {
                    sib.classList.remove('is-open');
                }
            });

            item.classList.toggle('is-open');
        });
    }

    /* ---------- meniu desktop: hover + tastatura ---------- */
    var desktopItems = header.querySelectorAll('.ht-menu__item.menu-item-has-children');

    function closeAllDesktop() {
        setPanelOpen(false);
        desktopItems.forEach(function (item) {
            item.classList.remove('is-open');
            var link = item.querySelector(':scope > .ht-menu__link');
            if (link) {
                link.setAttribute('aria-expanded', 'false');
            }
        });
    }

    desktopItems.forEach(function (item) {
        var link = item.querySelector(':scope > .ht-menu__link');
        if (!link) {
            return;
        }

        link.addEventListener('focus', function () {
            closeAllDesktop();
            item.classList.add('is-open');
            link.setAttribute('aria-expanded', 'true');
            setPanelOpen(true);
        });

        link.addEventListener('click', function (e) {
            var href = link.getAttribute('href');
            if (!href || href === '#') {
                e.preventDefault();
                var open = !item.classList.contains('is-open');
                closeAllDesktop();
                item.classList.toggle('is-open', open);
                link.setAttribute('aria-expanded', open ? 'true' : 'false');
                setPanelOpen(open);
            }
        });

        item.addEventListener('mouseenter', function () {
            toggleSearch(false);
            toggleCatalog(false);
            setPanelOpen(true);
        });

        item.addEventListener('mouseleave', function () {
            item.classList.remove('is-open');
            link.setAttribute('aria-expanded', 'false');
            setPanelOpen(false);
        });
    });

    document.addEventListener('click', function (e) {
        if (!header.contains(e.target)) {
            closeAllDesktop();
            toggleSearch(false);
            toggleCatalog(false);
        }
    });

    /* la trecerea pe desktop inchidem drawer-ul ca sa nu ramana blocat scroll-ul */
    var mq = window.matchMedia('(min-width: 1025px)');
    var onChange = function (ev) {
        if (ev.matches) {
            toggleDrawer(false);
        }
    };
    if (mq.addEventListener) {
        mq.addEventListener('change', onChange);
    } else if (mq.addListener) {
        mq.addListener(onChange);
    }

    /* deschis pentru scripturile care au nevoie sa inchida panourile (cautarea) */
    window.htHeader = {
        toggleSearch: toggleSearch,
        toggleCatalog: toggleCatalog,
        toggleLang: toggleLang,
        toggleDrawer: toggleDrawer
    };
})();
