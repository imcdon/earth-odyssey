/*
 * nav.js - Header Explore dropdown: toggle, Escape, outside click.
 */
(function () {
    var nav = document.querySelector('[data-nav]');
    var toggle = document.querySelector('[data-nav-toggle]');
    var menu = document.querySelector('[data-nav-menu]');
    if (!nav || !toggle || !menu) return;

    function setOpen(open) {
        nav.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            menu.removeAttribute('hidden');
        } else {
            menu.setAttribute('hidden', '');
        }
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
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

    document.addEventListener('click', function (event) {
        if (!nav.contains(event.target)) {
            setOpen(false);
        }
    });
})();
