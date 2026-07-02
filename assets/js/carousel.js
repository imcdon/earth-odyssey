/*
 * carousel.js - Hero carousel: auto-rotate, prev/next, dots, pause on hover.
 */
(function () {
    var carousel = document.querySelector('.hero-carousel');
    if (!carousel) return;

    var slides = carousel.querySelectorAll('.hero-slide');
    var dots = carousel.querySelectorAll('.hero-dot');
    var prevBtn = carousel.querySelector('.hero-prev');
    var nextBtn = carousel.querySelector('.hero-next');
    var current = 0;
    var timer = null;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function show(index) {
        current = (index + slides.length) % slides.length;
        slides.forEach(function (slide, i) {
            var active = i === current;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        dots.forEach(function (dot, i) {
            var active = i === current;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-current', active ? 'true' : 'false');
        });
    }

    function next() {
        show(current + 1);
    }

    function prev() {
        show(current - 1);
    }

    function startTimer() {
        if (reducedMotion) return;
        stopTimer();
        timer = setInterval(next, 5000);
    }

    function stopTimer() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    prevBtn.addEventListener('click', function (event) {
        event.preventDefault();
        prev();
        startTimer();
    });

    nextBtn.addEventListener('click', function (event) {
        event.preventDefault();
        next();
        startTimer();
    });

    dots.forEach(function (dot, index) {
        dot.addEventListener('click', function () {
            show(index);
            startTimer();
        });
    });

    carousel.addEventListener('mouseenter', stopTimer);
    carousel.addEventListener('mouseleave', startTimer);

    startTimer();
})();
