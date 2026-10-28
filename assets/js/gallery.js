/*
 * gallery.js - Full-screen category viewer with margin chrome and keyboard nav.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-gallery-viewer]');
    if (!root) return;

    var jsonEl = root.querySelector('[data-gallery-photos]');
    var img = root.querySelector('[data-gallery-image]');
    var titleEl = root.querySelector('[data-gallery-title]');
    var descEl = root.querySelector('[data-gallery-desc]');
    var countEl = root.querySelector('[data-gallery-count]');
    var prevBtn = root.querySelector('[data-gallery-prev]');
    var nextBtn = root.querySelector('[data-gallery-next]');
    if (!jsonEl || !img) return;

    var photos = [];
    try {
        photos = JSON.parse(jsonEl.textContent || '[]');
    } catch (e) {
        return;
    }
    if (!photos.length) return;

    var current = parseInt(root.getAttribute('data-index') || '0', 10) || 0;
    if (current < 0 || current >= photos.length) current = 0;

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function show(index) {
        current = (index + photos.length) % photos.length;
        var photo = photos[current];
        img.src = photo.src;
        if (photo.srcset) {
            img.setAttribute('srcset', photo.srcset);
            img.setAttribute('sizes', '100vw');
        } else {
            img.removeAttribute('srcset');
            img.removeAttribute('sizes');
        }
        img.alt = photo.title || '';
        if (photo.width) img.width = photo.width;
        if (photo.height) img.height = photo.height;
        if (titleEl) titleEl.textContent = photo.title || '';
        if (descEl) descEl.textContent = photo.description || '';
        if (countEl) countEl.textContent = pad(current + 1) + ' / ' + pad(photos.length);
        root.setAttribute('data-index', String(current));
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { show(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { show(current + 1); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            show(current - 1);
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            show(current + 1);
        }
        if (e.key === 'Escape') {
            var back = root.querySelector('.gallery-viewer-back');
            if (back && back.href) window.location.href = back.href;
        }
    });

    var touchX = null;
    root.addEventListener('touchstart', function (e) {
        if (!e.changedTouches || !e.changedTouches.length) return;
        touchX = e.changedTouches[0].clientX;
    }, { passive: true });

    root.addEventListener('touchend', function (e) {
        if (touchX === null || !e.changedTouches || !e.changedTouches.length) return;
        var dx = e.changedTouches[0].clientX - touchX;
        touchX = null;
        if (Math.abs(dx) < 50) return;
        if (dx < 0) show(current + 1);
        else show(current - 1);
    }, { passive: true });

    show(current);
})();
