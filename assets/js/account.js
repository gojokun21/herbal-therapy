/**
 * Pagina de cont.
 *
 * Sub 992px meniul contului devine o banda care se deruleaza pe orizontala
 * (account.css). Intrarea curenta poate ramane in afara cadrului, asa ca o
 * aducem in vizor la incarcare.
 */
(function () {
    'use strict';

    var menu = document.querySelector('.ht-account__menu');

    if (!menu) {
        return;
    }

    var active = menu.querySelector('.is-active');

    if (!active) {
        return;
    }

    /* pe desktop meniul e o coloana fara derulare - nu e nimic de mutat */
    if (menu.scrollWidth <= menu.clientWidth) {
        return;
    }

    /* lasam un pic de aer la stanga, ca sa se vada ca banda continua */
    menu.scrollLeft = active.offsetLeft - 16;
})();

/**
 * Comutarea Autentificare / Inregistrare de pe pagina de logare.
 *
 * Starea initiala vine din PHP (data-active + hidden pe panouri); aici doar
 * comutam la click pe link-urile "Inregistrare" / "Autentificare" si
 * deschidem direct panoul de inregistrare pentru link-urile /cont/#register.
 */
(function () {
    'use strict';

    var auth = document.querySelector('.ht-account__auth[data-active]');

    if (!auth) {
        return;
    }

    var panels = {
        login: document.getElementById('ht-auth-panel-login'),
        register: document.getElementById('ht-auth-panel-register')
    };

    if (!panels.login || !panels.register) {
        return;
    }

    var tabs = auth.querySelectorAll('.ht-account__tab');

    function activate(name) {
        Object.keys(panels).forEach(function (key) {
            panels[key].hidden = key !== name;
        });

        tabs.forEach(function (tab) {
            var current = tab.dataset.tab === name;

            tab.classList.toggle('is-active', current);
            tab.setAttribute('aria-selected', current ? 'true' : 'false');
        });

        auth.dataset.active = name;
    }

    auth.addEventListener('click', function (event) {
        var link = event.target.closest('[data-tab]');

        if (link) {
            activate(link.dataset.tab);
        }
    });

    if ('#register' === window.location.hash) {
        activate('register');
    }
})();
