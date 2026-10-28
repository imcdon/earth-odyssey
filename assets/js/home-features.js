/*
 * home-features.js - Step through the homepage panels when they scroll sideways.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-feature-scroller]');
    if (!root) return;

    var scroller = root.querySelector('.home-features-grid');
    var cards = scroller ? scroller.querySelectorAll('.home-feature') : [];
    var prev = root.querySelector('[data-feature-prev]');
    var next = root.querySelector('[data-feature-next]');
    if (!scroller || cards.length < 2 || !prev || !next) return;

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function nearest() {
        var box = scroller.getBoundingClientRect();
        var center = box.left + box.width / 2;
        var best = 0;
        var bestDist = Infinity;
        cards.forEach(function (card, i) {
            var r = card.getBoundingClientRect();
            var dist = Math.abs(r.left + r.width / 2 - center);
            if (dist < bestDist) {
                best = i;
                bestDist = dist;
            }
        });
        return best;
    }

    function scrollToCard(index) {
        var card = cards[index];
        if (!card) return;
        var box = scroller.getBoundingClientRect();
        var cardBox = card.getBoundingClientRect();
        var left = cardBox.left - box.left + scroller.scrollLeft - (box.width - cardBox.width) / 2;
        scroller.scrollTo({ left: left, behavior: reduced ? 'auto' : 'smooth' });
    }

    function update() {
        var max = scroller.scrollWidth - scroller.clientWidth;
        prev.hidden = scroller.scrollLeft <= 4;
        next.hidden = max <= 4 || scroller.scrollLeft >= max - 4;
    }

    function go(direction) {
        var index = Math.min(cards.length - 1, Math.max(0, nearest() + direction));
        scrollToCard(index);
    }

    prev.addEventListener('click', function () { go(-1); });
    next.addEventListener('click', function () { go(1); });
    scroller.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();
