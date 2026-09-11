(function () {
    'use strict';

    /*
     * Beneficiile de pe prima pagina ("Puterea naturii").
     *
     * Pe desktop cardurile stau in grila (CSS); sub 900px grila ar insira
     * patru carduri inalte unul sub altul, asa ca acolo devin carusel: pe
     * telefon un card si un colt din urmatorul, pe tableta doua. Swiper se
     * porneste doar cat timp ecranul e sub prag si se distruge (cu stilurile
     * inline cu tot) cand se trece peste, ca grila sa ramana curata.
     */
    var QUERY = '(max-width: 899.98px)';

    function initSection(section) {
        var el = section.querySelector('.ht-home-benefits__swiper');

        if (!el || typeof Swiper === 'undefined' || !window.matchMedia) {
            return;
        }

        var mq = window.matchMedia(QUERY);
        var swiper = null;

        function mount() {
            if (swiper) {
                return;
            }

            swiper = new Swiper(el, {
                slidesPerView: 1.15,
                spaceBetween: 12,
                speed: 600,
                grabCursor: true,
                watchOverflow: true,
                pagination: {
                    el: section.querySelector('.ht-home-benefits__pagination'),
                    clickable: true
                },
                breakpoints: {
                    576: {slidesPerView: 2.15, spaceBetween: 16}
                }
            });
        }

        function unmount() {
            if (!swiper) {
                return;
            }

            swiper.destroy(true, true);
            swiper = null;
        }

        function sync() {
            if (mq.matches) {
                mount();
            } else {
                unmount();
            }
        }

        if (mq.addEventListener) {
            mq.addEventListener('change', sync);
        } else if (mq.addListener) {
            mq.addListener(sync);
        }

        sync();
    }

    document.querySelectorAll('[data-ht-home-benefits]').forEach(initSection);
})();
