/*
 * galleries-hub.js - Hero category rotation (from the right) + panel preview rotators.
 */
(function () {
    'use strict';

    /* —— Hero: image + search term slide from the right; Galleries/lede stay put —— */
    (function initHeroRotator() {
        var root = document.querySelector('[data-galleries-hero-rotator]');
        if (!root) return;

        var form = root.querySelector('.galleries-hero-search');
        var terms = root.querySelectorAll('[data-galleries-hero-term]');
        var images = root.querySelectorAll('[data-galleries-hero-image]');
        var input = root.querySelector('[data-galleries-hero-input]');
        if (!form || terms.length < 2 || !input) return;

        var current = 0;
        var timer = null;
        var animating = false;
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var interval = parseInt(root.getAttribute('data-hero-interval'), 10) || 4500;
        var slideMs = 700;

        function syncFormState() {
            form.classList.toggle('is-focused', document.activeElement === input);
            form.classList.toggle('is-typing', !!(input.value && input.value.trim() !== ''));
        }

        function settle(next) {
            images.forEach(function (img, i) {
                img.classList.toggle('is-active', i === next);
                img.classList.remove('is-in');
            });
            terms.forEach(function (el, i) {
                el.classList.toggle('is-active', i === next);
                el.classList.remove('is-out');
            });
            current = next;
            animating = false;
        }

        function show(index) {
            if (animating) return;
            var next = (index + terms.length) % terms.length;
            if (next === current) return;

            if (reduced) {
                settle(next);
                return;
            }

            animating = true;
            var outgoingTerm = terms[current];
            var incomingTerm = terms[next];
            var incomingImage = images[next];

            if (incomingImage) {
                incomingImage.classList.remove('is-active', 'is-in');
                void incomingImage.offsetWidth;
                incomingImage.classList.add('is-in');
            }

            if (incomingTerm) {
                incomingTerm.classList.remove('is-active', 'is-out');
                void incomingTerm.offsetWidth;
            }
            if (outgoingTerm) outgoingTerm.classList.add('is-out');
            if (incomingTerm) incomingTerm.classList.add('is-active');

            var done = false;
            function finish() {
                if (done) return;
                done = true;
                if (incomingImage) {
                    incomingImage.removeEventListener('transitionend', onEnd);
                }
                settle(next);
            }

            function onEnd(e) {
                if (e.propertyName === 'transform') finish();
            }

            if (incomingImage) {
                incomingImage.addEventListener('transitionend', onEnd);
            }
            window.setTimeout(finish, slideMs + 80);
        }

        function next() {
            show(current + 1);
        }

        function busy() {
            return animating
                || document.activeElement === input
                || (input.value && input.value.trim() !== '');
        }

        function start() {
            if (reduced) return;
            stop();
            timer = window.setInterval(function () {
                if (!busy()) next();
            }, interval);
        }

        function stop() {
            if (!timer) return;
            window.clearInterval(timer);
            timer = null;
        }

        input.addEventListener('focus', function () {
            syncFormState();
            stop();
        });
        input.addEventListener('blur', function () {
            syncFormState();
            start();
        });
        input.addEventListener('input', syncFormState);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop();
            else start();
        });

        syncFormState();
        start();
    })();

    /* —— Category panels: cycle preview photos —— */
    (function initPanelRotators() {
        var root = document.querySelector('[data-galleries-panels]');
        if (!root) return;

        var INTERVAL_MS = 4200;
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function initPanel(panel) {
            var slides = Array.prototype.slice.call(panel.querySelectorAll('.galleries-panel-slide'));
            if (slides.length < 2 || reduced) return;

            var index = slides.findIndex(function (el) {
                return el.classList.contains('is-active');
            });
            if (index < 0) index = 0;

            var timer = null;

            function show(next) {
                slides[index].classList.remove('is-active');
                index = (next + slides.length) % slides.length;
                slides[index].classList.add('is-active');
            }

            function start() {
                if (timer) return;
                timer = window.setInterval(function () {
                    show(index + 1);
                }, INTERVAL_MS);
            }

            function stop() {
                if (!timer) return;
                window.clearInterval(timer);
                timer = null;
            }

            panel.addEventListener('mouseenter', stop);
            panel.addEventListener('mouseleave', start);
            panel.addEventListener('focusin', stop);
            panel.addEventListener('focusout', start);

            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) start();
                        else stop();
                    });
                }, { threshold: 0.35 });
                observer.observe(panel);
            } else {
                start();
            }
        }

        root.querySelectorAll('[data-gallery-panel]').forEach(initPanel);
    })();
})();
