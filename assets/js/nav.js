/*
 * nav.js - Header nav: hamburger toggle and collapse when nav overlaps logo.
 */
(function () {
    var header = document.querySelector('.site-header');
    var nav = document.querySelector('.header-nav');
    var toggle = document.querySelector('.nav-toggle');
    var menu = document.getElementById('nav-menu');
    var logo = document.querySelector('.header-logo');
    var search = document.querySelector('.header-search');
    if (!header || !nav || !toggle || !menu || !logo || !search) return;

    var buffer = 16;

    function setOpen(open) {
        nav.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function updateNavCollapse() {
        header.classList.remove('is-nav-collapsed');
        setOpen(false);

        var logoRect = logo.getBoundingClientRect();
        var navRect = menu.getBoundingClientRect();
        var searchRect = search.getBoundingClientRect();
        var navOverlaps = navRect.right + buffer > logoRect.left;
        var searchOverlaps = searchRect.left - buffer < logoRect.right;
        var shouldCollapse = navOverlaps || searchOverlaps;

        header.classList.toggle('is-nav-collapsed', shouldCollapse);
        if (shouldCollapse) {
            setOpen(false);
        }
    }

    toggle.addEventListener('click', function () {
        setOpen(!nav.classList.contains('is-open'));
    });

    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            setOpen(false);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    if (window.ResizeObserver) {
        var observer = new ResizeObserver(updateNavCollapse);
        observer.observe(header);
    }

    window.addEventListener('resize', updateNavCollapse);
    search.addEventListener('focusin', updateNavCollapse);
    search.addEventListener('focusout', updateNavCollapse);
    updateNavCollapse();
})();
