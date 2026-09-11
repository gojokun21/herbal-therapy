(function () {
    'use strict';

    var el = document.querySelector('[data-ht-hero]');

    if (!el || typeof Swiper === 'undefined') {
        return;
    }

    var slides = el.querySelectorAll('.swiper-slide');

    if (!slides.length) {
        return;
    }

    /* reporneste animatia barei active la fiecare schimbare de slide */
    function restartProgress(swiper) {
        var bullet = swiper.pagination && swiper.pagination.bullets
            ? swiper.pagination.bullets[swiper.realIndex]
            : null;

        if (!bullet) {
            return;
        }

        var active = swiper.slides[swiper.activeIndex];
        var delay = active ? active.getAttribute('data-swiper-autoplay') : null;

        if (delay) {
            bullet.style.setProperty('--slide-duration', delay + 'ms');
        }

        /* fortam reflow ca animatia CSS sa o ia de la capat */
        bullet.classList.remove('swiper-pagination-bullet-active');
        void bullet.offsetWidth;
        bullet.classList.add('swiper-pagination-bullet-active');
    }

    var hero = new Swiper(el, {
        slidesPerView: 1,
        spaceBetween: 0,
        speed: 600,
        loop: slides.length > 1,
        grabCursor: true,
        watchSlidesProgress: true,
        autoplay: slides.length > 1 ? {
            delay: 9000,
            disableOnInteraction: false,
            pauseOnMouseEnter: true
        } : false,
        navigation: {
            nextEl: el.querySelector('.ht-hero__arrow--next'),
            prevEl: el.querySelector('.ht-hero__arrow--prev')
        },
        pagination: {
            el: el.querySelector('.ht-hero__pagination'),
            clickable: true
        },
        on: {
            init: restartProgress,
            slideChangeTransitionStart: restartProgress,
            autoplayPause: function () {
                el.classList.add('is-paused');
            },
            autoplayResume: function () {
                el.classList.remove('is-paused');
            }
        }
    });

    /* pauza cand tab-ul nu e vizibil - altfel bara de progres o ia inaintea slide-ului */
    document.addEventListener('visibilitychange', function () {
        if (!hero.autoplay) {
            return;
        }

        if (document.hidden) {
            hero.autoplay.pause();
        } else {
            hero.autoplay.resume();
            restartProgress(hero);
        }
    });
})();
