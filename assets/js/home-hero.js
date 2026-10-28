/*
 * home-hero.js - Rotate hero topic, photo, and search example; optional river type-ahead.
 * Next slide slides in over the previous (no fade).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-hero-rotator]');
    if (!root) return;

    var topics = root.querySelectorAll('[data-hero-topic]');
    var input = root.querySelector('[data-hero-input]');
    var images = root.querySelectorAll('[data-hero-image]');
    var list = root.querySelector('#river-results');
    var jsonEl = root.querySelector('[data-hero-slides]');
    var slides = [];
    try {
        slides = JSON.parse(jsonEl ? jsonEl.textContent : '[]');
    } catch (e) {
        slides = [];
    }
    if (!topics.length || !input || !slides.length) return;

    var current = 0;
    var timer = null;
    var animating = false;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var interval = parseInt(root.getAttribute('data-hero-interval'), 10) || 4500;
    var slideMs = 700;
    var app = document.querySelector('[data-river-page="home"]');
    var api = app ? app.getAttribute('data-api') : '';
    var siteUrl = app ? app.getAttribute('data-site-url') : '';
    var searchTimer = null;
    var activeOpt = -1;
    var lastQuery = '';

    function mode() {
        return slides[current].mode || 'search';
    }

    function closeList() {
        if (!list) return;
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        activeOpt = -1;
    }

    function applyMode(slide) {
        if (!input.value) {
            input.placeholder = slide.example;
        }
        input.setAttribute('data-hero-mode', slide.mode);
        var form = root.querySelector('[data-hero-search]');
        if (form) {
            var searchAction = form.getAttribute('data-search-action') || form.getAttribute('action');
            form.setAttribute('action', searchAction);
        }
        if (slide.mode === 'rivers' && list) {
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-controls', 'river-results');
        } else {
            input.removeAttribute('role');
            input.removeAttribute('aria-autocomplete');
            input.removeAttribute('aria-controls');
            closeList();
        }
    }

    function setTopic(next) {
        topics.forEach(function (el, i) {
            var on = i === next;
            el.classList.toggle('is-active', on);
            el.classList.remove('is-in', 'is-out');
            if (on) {
                el.removeAttribute('aria-hidden');
            } else {
                el.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function settle(next) {
        images.forEach(function (img, i) {
            img.classList.toggle('is-active', i === next);
            img.classList.remove('is-in');
        });
        setTopic(next);
        current = next;
        animating = false;
        applyMode(slides[current]);
    }

    function show(index) {
        if (animating || !slides.length) return;
        var next = (index + slides.length) % slides.length;
        if (next === current) return;

        if (reduced) {
            settle(next);
            return;
        }

        animating = true;
        var slide = slides[next];
        var outgoingTopic = topics[current];
        var incomingImage = images[next];
        var incomingTopic = topics[next];

        // Image slides down; previous topic clips away as it's covered; new topic reveals.
        if (incomingImage) {
            incomingImage.classList.remove('is-active', 'is-in');
        }
        if (incomingTopic) {
            incomingTopic.classList.remove('is-active', 'is-in', 'is-out');
            incomingTopic.removeAttribute('aria-hidden');
        }
        if (incomingImage) {
            void incomingImage.offsetWidth;
        } else if (incomingTopic) {
            void incomingTopic.offsetWidth;
        }
        if (incomingImage) {
            incomingImage.classList.add('is-in');
        }
        if (outgoingTopic) {
            outgoingTopic.classList.add('is-out');
        }
        if (incomingTopic) {
            incomingTopic.classList.add('is-in');
        }
        applyMode(slide);

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
            || (input.value && input.value.trim() !== '')
            || (list && !list.hidden);
    }

    function start() {
        if (reduced || slides.length < 2) return;
        stop();
        timer = window.setInterval(function () {
            if (!busy()) next();
        }, interval);
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function hrefFor(id) {
        return siteUrl + '?id=' + encodeURIComponent(id);
    }

    function renderRivers(results) {
        if (!list) return;
        list.textContent = '';
        if (!results.length) {
            var empty = document.createElement('li');
            empty.className = 'rivers-results-empty';
            empty.textContent = 'No gauges match "' + lastQuery + '"';
            list.appendChild(empty);
        }
        results.forEach(function (r, i) {
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.id = 'river-opt-' + i;
            var a = document.createElement('a');
            a.href = hrefFor(r.id);
            a.textContent = r.name;
            var meta = document.createElement('span');
            meta.className = 'rivers-results-meta';
            meta.textContent = [r.county, r.state].filter(Boolean).join(', ');
            li.appendChild(a);
            li.appendChild(meta);
            list.appendChild(li);
        });
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function searchRivers(q) {
        lastQuery = q;
        if (!api) return;
        fetch(api + '?action=search&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.q === lastQuery && mode() === 'rivers') {
                    renderRivers(data.results || []);
                }
            })
            .catch(closeList);
    }

    input.addEventListener('focus', stop);
    input.addEventListener('blur', function () {
        window.setTimeout(start, 400);
    });

    input.addEventListener('input', function () {
        var q = input.value.trim();
        stop();
        clearTimeout(searchTimer);
        if (mode() !== 'rivers' || q.length < 3) {
            closeList();
            return;
        }
        searchTimer = window.setTimeout(function () { searchRivers(q); }, 250);
    });

    input.addEventListener('keydown', function (e) {
        if (!list || list.hidden) return;
        var options = list.querySelectorAll('[role="option"]');
        if (!options.length) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            activeOpt = (activeOpt + (e.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach(function (o, i) { o.classList.toggle('is-active', i === activeOpt); });
            input.setAttribute('aria-activedescendant', options[activeOpt].id);
        } else if (e.key === 'Enter' && activeOpt >= 0) {
            e.preventDefault();
            window.location.href = options[activeOpt].querySelector('a').href;
        } else if (e.key === 'Escape') {
            closeList();
        }
    });

    document.addEventListener('click', function (e) {
        if (!list) return;
        if (!list.contains(e.target) && e.target !== input) closeList();
    });

    start();
})();
