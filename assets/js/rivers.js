/*
 * rivers.js - River tool: gauge type-ahead, favorites, and the site page chart (uPlot) with window/units toggles.
 * All data comes from services/rivers/api.php; the browser never calls USGS.
 */
(function () {
    'use strict';

    /* Prefer the previous page when the visitor came from this site; otherwise use the map href. */
    document.querySelectorAll('[data-river-back]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var sameOrigin = document.referrer && document.referrer.indexOf(location.origin) === 0;
            if (!sameOrigin) return;
            e.preventDefault();
            history.back();
        });
    });

    var root = document.querySelector('[data-river-page]');
    if (!root) return;

    var api = root.getAttribute('data-api');
    var FAV_KEY = 'eo-river-favs';
    var UNITS_KEY = 'eo-river-units';

    function store(key, value) {
        try {
            if (value === undefined) return JSON.parse(localStorage.getItem(key) || 'null');
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) { return null; }
    }

    function favorites() { return store(FAV_KEY) || []; }

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        if (text != null) node.textContent = text;
        return node;
    }

    /* Mirrors river_convert() in includes/rivers.php. */
    function convert(code, v, units) {
        if (v == null) return null;
        if (code === '00010' || code === '00020') return units === 'us' ? v * 9 / 5 + 32 : v;
        if (code === '00060') return units === 'metric' ? v * 0.0283168 : v;
        if (code === '00065') return units === 'metric' ? v * 0.3048 : v;
        return v;
    }

    function unitLabel(code, units, fallback) {
        if (code === '00010' || code === '00020') return units === 'us' ? '°F' : '°C';
        if (code === '00060') return units === 'metric' ? 'm³/s' : 'cfs';
        if (code === '00065') return units === 'metric' ? 'm' : 'ft';
        return fallback || '';
    }

    function fmt(v) {
        if (v == null || isNaN(v)) return '–';
        var a = Math.abs(v);
        return v.toLocaleString(undefined, { maximumFractionDigits: a >= 100 ? 0 : a >= 10 ? 1 : 2 });
    }

    function ago(iso) {
        var mins = Math.max(0, Math.floor((Date.now() - Date.parse(iso)) / 60000));
        if (mins < 1) return 'just now';
        if (mins < 60) return mins + ' min ago';
        if (mins < 1440) return Math.floor(mins / 60) + ' hr ago';
        return Math.floor(mins / 1440) + ' days ago';
    }

    function when(t) {
        return new Date(t).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function apiPost(action, payload) {
        return fetch(api + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': root.getAttribute('data-csrf') || ''
            },
            body: JSON.stringify(Object.assign({ csrf: root.getAttribute('data-csrf') || '' }, payload || {}))
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); });
    }

    var page = root.getAttribute('data-river-page');
    if (page === 'home') {
        initStateMap();
        return;
    }
    if (page === 'index') initIndex();
    else if (page === 'browse') initBrowse();
    else if (page === 'offline') initOfflinePage();
    else if (page === 'dashboard') initDashboard();
    else initSite();
    initOffline();
    initStateMap();
    maybeImportFavorites();

    /* ---------- Offline support (services/rivers/sw.js) ---------- */

    function tellWorker(msg) {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker.ready.then(function (reg) { if (reg.active) reg.active.postMessage(msg); });
    }

    function initOffline() {
        if (!('serviceWorker' in navigator)) return;
        var base = api.replace(/api\.php.*$/, '');
        navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () { /* offline support is optional */ });

        var urls = [];
        document.querySelectorAll('link[rel="stylesheet"][href], script[src]').forEach(function (n) { urls.push(n.href || n.src); });
        (performance.getEntriesByType ? performance.getEntriesByType('resource') : []).forEach(function (r) {
            if (/^https:\/\/fonts\.(googleapis|gstatic)\.com\//.test(r.name)) urls.push(r.name);
        });
        tellWorker({ type: 'assets', urls: urls });

        if (page === 'site') {
            tellWorker({
                type: 'viewed',
                id: root.getAttribute('data-site'),
                name: root.getAttribute('data-name'),
                win: root.getAttribute('data-win'),
                offline: root.getAttribute('data-offline') === '1',
                favorites: favorites()
            });
        } else if (page !== 'offline') {
            tellWorker({ type: 'sync', favorites: favorites() });
        }
    }

    function initOfflinePage() {
        var list = root.querySelector('[data-saved-list]');
        var empty = root.querySelector('[data-saved-empty]');
        var controller = 'serviceWorker' in navigator && navigator.serviceWorker.controller;
        if (!controller) { empty.hidden = false; return; }
        var channel = new MessageChannel();
        channel.port1.onmessage = function (e) {
            var items = e.data || [];
            empty.hidden = items.length > 0;
            items.forEach(function (r) {
                var li = el('li');
                li.appendChild(el('a', { href: r.url }, r.name));
                li.appendChild(el('span', { class: 'rivers-list-meta' },
                    (r.favorite ? '★ Favorite · ' : '') + (r.savedAt ? 'Saved ' + when(r.savedAt) : 'Saved')));
                list.appendChild(li);
            });
        };
        controller.postMessage({ type: 'list' }, [channel.port2]);
    }

    /* ---------- Browse: biggest changes ---------- */

    function initBrowse() {
        var units = root.getAttribute('data-units');

        function paint() {
            root.querySelectorAll('[data-delta]').forEach(function (node) {
                var code = node.getAttribute('data-code');
                var v = parseFloat(node.getAttribute('data-delta'));
                var shown = (code === '00010' || code === '00020') ? (units === 'us' ? v * 9 / 5 : v) : convert(code, v, units);
                node.textContent = (shown > 0 ? '+' : '') + fmt(shown) + ' ' + unitLabel(code, units);
            });
            root.querySelectorAll('.river-mover-range').forEach(function (node) {
                var code = node.getAttribute('data-code');
                node.textContent = fmt(convert(code, parseFloat(node.getAttribute('data-a')), units)) + ' → '
                    + fmt(convert(code, parseFloat(node.getAttribute('data-b')), units)) + ' ' + unitLabel(code, units);
            });
            root.querySelectorAll('[data-units-btn]').forEach(function (b) {
                b.setAttribute('aria-pressed', b.getAttribute('data-units-btn') === units ? 'true' : 'false');
            });
            root.querySelectorAll('.river-tabs a, .river-mover a').forEach(function (a) {
                a.href = a.href.replace(/([?&]units=)(us|metric)/, '$1' + units);
            });
            var q = new URLSearchParams(location.search);
            q.set('units', units);
            history.replaceState(null, '', '?' + q.toString());
        }

        root.querySelectorAll('[data-units-btn]').forEach(function (b) {
            b.addEventListener('click', function () {
                units = b.getAttribute('data-units-btn');
                store(UNITS_KEY, units);
                paint();
            });
        });

        if (!/[?&]units=/.test(location.search) && store(UNITS_KEY) === 'metric') {
            units = 'metric';
            paint();
        }
    }

    /* ---------- State map caption ---------- */

    function initStateMap() {
        var map = root.querySelector('[data-river-map]');
        if (!map) return;
        var caption = map.querySelector('[data-map-caption]');
        if (!caption) return;
        var idle = caption.textContent;

        function show(label) { caption.textContent = label || idle; }

        map.querySelectorAll('.river-map-link').forEach(function (link) {
            var label = link.getAttribute('data-label') || link.getAttribute('aria-label') || '';
            link.addEventListener('mouseenter', function () { show(label); });
            link.addEventListener('focus', function () { show(label); });
            link.addEventListener('mouseleave', function () { show(idle); });
            link.addEventListener('blur', function () { show(idle); });
        });
    }

    /* ---------- Gauge type-ahead (rivers hub + home hero) ---------- */

    function initSearch() {
        var siteUrl = root.getAttribute('data-site-url');
        var input = root.querySelector('[data-river-search]');
        var list = root.querySelector('#river-results');
        if (!siteUrl || !input || !list) return;

        var timer = null;
        var active = -1;
        var lastQuery = '';

        function hrefFor(id) { return siteUrl + '?id=' + encodeURIComponent(id); }

        function close() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function render(results) {
            list.textContent = '';
            if (!results.length) {
                list.appendChild(el('li', { class: 'rivers-results-empty' }, 'No gauges match "' + lastQuery + '"'));
            }
            results.forEach(function (r, i) {
                var li = el('li', { role: 'option', id: 'river-opt-' + i });
                var a = el('a', { href: hrefFor(r.id) }, r.name);
                li.appendChild(a);
                li.appendChild(el('span', { class: 'rivers-results-meta' }, [r.county, r.state].filter(Boolean).join(', ')));
                list.appendChild(li);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function search(q) {
            lastQuery = q;
            fetch(api + '?action=search&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) { if (data.q === lastQuery) render(data.results || []); })
                .catch(close);
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 3) { close(); return; }
            timer = setTimeout(function () { search(q); }, 250);
        });

        input.addEventListener('keydown', function (e) {
            var options = list.querySelectorAll('[role="option"]');
            if (list.hidden || !options.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options.forEach(function (o, i) { o.classList.toggle('is-active', i === active); });
                input.setAttribute('aria-activedescendant', options[active].id);
            } else if (e.key === 'Enter' && active >= 0) {
                e.preventDefault();
                window.location.href = options[active].querySelector('a').href;
            } else if (e.key === 'Escape') {
                close();
            }
        });

        document.addEventListener('click', function (e) {
            if (!list.contains(e.target) && e.target !== input) close();
        });
    }

    /* ---------- Index: search + favorites + state map ---------- */

    function initIndex() {
        var siteUrl = root.getAttribute('data-site-url');
        function hrefFor(id) { return siteUrl + '?id=' + encodeURIComponent(id); }

        initSearch();
        initMapfillChrome();
        initStateGaugeMap();
        initStatePanels();

        var favSection = root.querySelector('[data-river-favs]');
        var favs = favorites();
        if (favSection && favs.length) {
            var ul = favSection.querySelector('ul');
            var items = {};
            favs.forEach(function (f) {
                var li = el('li');
                li.appendChild(el('a', { href: hrefFor(f.id) }, f.name));
                ul.appendChild(li);
                items[f.id] = li;
            });
            favSection.hidden = false;
            var units = store(UNITS_KEY) === 'metric' ? 'metric' : 'us';
            fetch(api + '?action=badges&units=' + units + '&ids=' + encodeURIComponent(Object.keys(items).join(',')))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    Object.keys(data.badges || {}).forEach(function (id) {
                        var b = data.badges[id];
                        if (items[id]) {
                            var inRange = b.level === 'prime' || b.level === 'fishable';
                            var badge = el('span', {
                                class: 'river-badge is-' + b.level + (inRange ? ' is-fish' : ' is-alert'),
                                title: b.title
                            });
                            badge.appendChild(el('span', { class: 'river-badge-mark', 'aria-hidden': 'true' }));
                            badge.appendChild(document.createTextNode(b.label));
                            items[id].appendChild(badge);
                        }
                    });
                })
                .catch(function () { /* badges are optional */ });
        }
    }

    /* Keep the map viewport flush under the real site header (padding can exceed --header-height). */
    function initMapfillChrome() {
        if (!document.body.classList.contains('rivers-mapfill')) return;
        var header = document.querySelector('.site-header');
        if (!header) return;
        function sync() {
            document.body.style.setProperty('--rivers-header-offset', header.getBoundingClientRect().height + 'px');
        }
        sync();
        window.addEventListener('resize', sync);
        if (window.ResizeObserver) new ResizeObserver(sync).observe(header);
    }

    /* Sparkline path from native values (same geometry as PHP river_sparkline). */
    function sparkSvg(values) {
        var pts = (values || []).filter(function (v) { return typeof v === 'number' && !isNaN(v); });
        if (pts.length < 2) return '';
        var min = Math.min.apply(null, pts);
        var range = (Math.max.apply(null, pts) - min) || 1;
        var d = '';
        pts.forEach(function (v, i) {
            d += (i ? 'L' : 'M') + Math.round(i / (pts.length - 1) * 84) + ' '
                + Math.round(22 - (v - min) / range * 20);
        });
        return '<svg class="river-spark" viewBox="0 0 84 24" aria-hidden="true"><path d="' + d + '"/></svg>';
    }

    function mapTooltipHtml(site) {
        var html = '<div class="river-map-tip">';
        html += '<p class="river-map-tip-name">' + escapeHtml(site.name) + '</p>';
        html += '<p class="river-map-tip-id">USGS ' + escapeHtml(site.siteNo) + '</p>';
        if (site.trend) {
            var t = site.trend;
            var delta = (t.delta > 0 ? '+' : '') + fmt(t.delta) + ' ' + t.unit;
            var pct = t.pct != null ? ' (' + (t.pct > 0 ? '+' : '') + fmt(t.pct) + '%)' : '';
            html += '<p class="river-map-tip-flow">Flow ' + fmt(t.now) + ' ' + escapeHtml(t.unit)
                + ' · 24h ' + delta + pct + '</p>';
            html += sparkSvg(t.spark);
        } else {
            html += '<p class="river-map-tip-flow">24h flow trend not available yet</p>';
        }
        if (site.badge) {
            var inRangeTip = site.badge.level === 'prime' || site.badge.level === 'fishable';
            html += '<span class="river-badge is-' + escapeHtml(site.badge.level)
                + (inRangeTip ? ' is-fish' : ' is-alert') + '" title="'
                + escapeHtml(site.badge.title) + '"><span class="river-badge-mark" aria-hidden="true"></span>'
                + escapeHtml(site.badge.label) + '</span>';
        }
        html += '</div>';
        return html;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var BADGE_STROKE = { prime: '#2f9bff', fishable: '#8a8a94', low: '#9b9ba3', high: '#62b4ff' };

    function initStateGaugeMap() {
        var section = root.querySelector('[data-river-state-map]');
        var mapEl = document.getElementById('river-state-map');
        var status = section && section.querySelector('[data-map-status]');
        var caption = section && section.querySelector('[data-map-caption]');
        if (!section || !mapEl || typeof L === 'undefined') {
            if (status) status.textContent = 'Map could not load.';
            return;
        }

        var state = root.getAttribute('data-state');
        var siteUrl = root.getAttribute('data-site-url');
        var units = root.getAttribute('data-units') || 'us';
        if (!/[?&]units=/.test(location.search) && store(UNITS_KEY) === 'metric') {
            units = 'metric';
            root.setAttribute('data-units', units);
        }

        var map = L.map(mapEl, { scrollWheelZoom: false, attributionControl: true });
        /* Esri World Topo: free raster tiles with clear river/lake linework (no API key). */
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, USGS, NOAA, and others',
            maxZoom: 16
        }).addTo(map);
        map.setView([39.5, -98.35], 4);

        var layer = L.layerGroup().addTo(map);
        var openTip = null;

        function setStatus(msg, isError) {
            if (!status) return;
            status.hidden = !msg;
            status.textContent = msg || '';
            status.classList.toggle('is-error', !!isError);
        }

        function paintUnits() {
            root.querySelectorAll('[data-units-btn]').forEach(function (b) {
                b.setAttribute('aria-pressed', b.getAttribute('data-units-btn') === units ? 'true' : 'false');
            });
            var q = new URLSearchParams(location.search);
            q.set('units', units);
            history.replaceState(null, '', '?' + q.toString());
        }

        function render(data) {
            layer.clearLayers();
            openTip = null;
            var sites = data.sites || [];
            if (!sites.length) {
                setStatus('No gauges with map coordinates for this state yet.', true);
                if (caption) caption.hidden = true;
                return;
            }

            setStatus('');
            var bounds = [];
            sites.forEach(function (site) {
                var stroke = (site.badge && BADGE_STROKE[site.badge.level]) || '#2f9bff';
                var marker = L.circleMarker([site.lat, site.lon], {
                    radius: 6,
                    weight: 2,
                    color: stroke,
                    fillColor: '#2f9bff',
                    fillOpacity: 0.85,
                    className: 'river-map-pin'
                });
                marker.bindTooltip(mapTooltipHtml(site), {
                    className: 'river-map-tooltip',
                    direction: 'top',
                    offset: [0, -8],
                    opacity: 1,
                    sticky: false
                });
                marker.on('click', function (e) {
                    var href = siteUrl + '?id=' + encodeURIComponent(site.id);
                    /* Touch: first tap sticks the tip; second tap opens the gauge. */
                    if (window.matchMedia('(pointer: coarse)').matches) {
                        L.DomEvent.stopPropagation(e);
                        if (openTip === marker && marker.isTooltipOpen()) {
                            window.location.href = href;
                            return;
                        }
                        if (openTip && openTip !== marker) openTip.closeTooltip();
                        marker.openTooltip();
                        openTip = marker;
                        return;
                    }
                    window.location.href = href;
                });
                marker.on('tooltipopen', function () { openTip = marker; });
                marker.on('tooltipclose', function () {
                    if (openTip === marker) openTip = null;
                });
                marker.addTo(layer);
                bounds.push([site.lat, site.lon]);
            });

            if (bounds.length === 1) {
                map.setView(bounds[0], 9);
            } else {
                map.fitBounds(bounds, { padding: [28, 28], maxZoom: 10 });
            }

            if (caption) {
                var bits = [sites.length + ' gauge' + (sites.length === 1 ? '' : 's') + ' on the map'];
                if (data.missingCoords > 0) {
                    bits.push(data.missingCoords + ' without coordinates (still in Browse gauges)');
                }
                bits.push('Hover a pin for flow trend; tap or click to open the gauge');
                caption.textContent = bits.join(' · ');
                caption.hidden = false;
            }
            map.invalidateSize();
        }

        function load() {
            setStatus('Loading gauges…');
            fetch(api + '?action=statemap&state=' + encodeURIComponent(state) + '&units=' + encodeURIComponent(units))
                .then(function (r) {
                    if (!r.ok) throw new Error('bad status');
                    return r.json();
                })
                .then(render)
                .catch(function () {
                    setStatus('Could not load the map. Browse gauges below still works.', true);
                    if (caption) caption.hidden = true;
                });
        }

        root.querySelectorAll('[data-units-btn]').forEach(function (b) {
            b.addEventListener('click', function () {
                units = b.getAttribute('data-units-btn');
                store(UNITS_KEY, units);
                root.setAttribute('data-units', units);
                paintUnits();
                load();
                root.dispatchEvent(new CustomEvent('eo-river-units', { detail: { units: units } }));
            });
        });

        paintUnits();
        load();

        map.on('click', function () {
            if (openTip) openTip.closeTooltip();
        });

        var resizeTimer = null;
        function refreshMapSize() {
            map.invalidateSize();
        }
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(refreshMapSize, 150);
        });
        if (window.ResizeObserver) {
            var stage = root.querySelector('.rivers-map-stage');
            if (stage) {
                new ResizeObserver(function () { refreshMapSize(); }).observe(stage);
            }
        }
        requestAnimationFrame(refreshMapSize);
    }

    /* ---------- State ranking panels (under the map) ---------- */

    function initStatePanels() {
        var section = root.querySelector('[data-river-state-panels]');
        if (!section) return;

        var state = root.getAttribute('data-state');
        var siteUrl = root.getAttribute('data-site-url');
        var units = root.getAttribute('data-units') || 'us';
        if (!/[?&]units=/.test(location.search) && store(UNITS_KEY) === 'metric') {
            units = 'metric';
            root.setAttribute('data-units', units);
        }

        var cache = null;

        function rowHtml(row) {
            var html = '<li class="river-panel-row">';
            html += '<a class="river-panel-name" href="' + siteUrl + '?id=' + encodeURIComponent(row.id) + '">'
                + escapeHtml(row.name) + '</a>';
            html += '<span class="river-panel-stat">' + escapeHtml(row.stat || '') + '</span>';
            html += sparkSvg(row.spark || (row.trend && row.trend.spark) || []);
            html += '</li>';
            return html;
        }

        function paint(data) {
            cache = data;
            var panels = (data && data.panels) || {};
            section.querySelectorAll('[data-panel]').forEach(function (details) {
                var key = details.getAttribute('data-panel');
                var panel = panels[key] || { rows: [], empty: 'No data.', title: key };
                var list = details.querySelector('[data-panel-list]');
                var status = details.querySelector('[data-panel-status]');
                var count = details.querySelector('[data-panel-count]');
                var rows = panel.rows || [];
                if (count) count.textContent = rows.length ? String(rows.length) : '';
                if (!rows.length) {
                    if (status) {
                        status.hidden = false;
                        status.textContent = panel.empty || 'No data.';
                    }
                    if (list) {
                        list.hidden = true;
                        list.innerHTML = '';
                    }
                    return;
                }
                if (status) status.hidden = true;
                if (list) {
                    list.innerHTML = rows.map(rowHtml).join('');
                    list.hidden = false;
                }
            });
        }

        function load() {
            section.querySelectorAll('[data-panel-status]').forEach(function (s) {
                s.hidden = false;
                s.textContent = 'Loading…';
            });
            fetch(api + '?action=statepanels&state=' + encodeURIComponent(state) + '&units=' + encodeURIComponent(units))
                .then(function (r) {
                    if (!r.ok) throw new Error('bad status');
                    return r.json();
                })
                .then(paint)
                .catch(function () {
                    section.querySelectorAll('[data-panel-status]').forEach(function (s) {
                        s.hidden = false;
                        s.textContent = 'Could not load rankings.';
                    });
                });
        }

        root.addEventListener('eo-river-units', function (e) {
            units = (e.detail && e.detail.units) || units;
            load();
        });

        load();
    }

    function maybeImportFavorites() {
        if (root.getAttribute('data-logged-in') !== '1') return;
        if (!root.getAttribute('data-csrf')) return;
        var flag = 'eo-river-favs-imported';
        try { if (localStorage.getItem(flag) === '1') return; } catch (e) { return; }
        var favs = favorites();
        if (!favs.length) {
            try { localStorage.setItem(flag, '1'); } catch (e2) {}
            return;
        }
        apiPost('importfavs', { favorites: favs }).then(function () {
            try { localStorage.setItem(flag, '1'); } catch (e3) {}
        }).catch(function () {});
    }

    function initDashboard() {
        root.querySelectorAll('[data-unsave-site]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-unsave-site');
                apiPost('unsave', { site: id }).then(function (res) {
                    if (!res.ok) return;
                    var panel = root.querySelector('[data-dash-site="' + id + '"]');
                    if (panel) panel.remove();
                });
            });
        });
        var mark = root.querySelector('[data-alerts-mark-read]');
        if (mark) {
            mark.addEventListener('click', function () {
                apiPost('alertsread', {}).then(function () {
                    root.querySelectorAll('.river-alert-item.is-unread').forEach(function (li) {
                        li.classList.remove('is-unread');
                    });
                });
            });
        }
        root.querySelectorAll('[data-dash-chart]').forEach(function (box) {
            var siteId = box.getAttribute('data-site');
            var param = box.getAttribute('data-param') || '00060';
            var units = root.getAttribute('data-units') || 'us';
            fetch(api + '?action=series&site=' + encodeURIComponent(siteId) + '&win=1W')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !window.uPlot || !data.params) {
                        box.textContent = '';
                        box.appendChild(el('p', { class: 'river-chart-message' }, 'Chart unavailable.'));
                        return;
                    }
                    var p = data.params[param] || data.params[Object.keys(data.params)[0]];
                    if (!p || !data.x || !p.cur || p.cur.length < 2) {
                        box.textContent = '';
                        box.appendChild(el('p', { class: 'river-chart-message' }, 'Not enough data for a chart.'));
                        return;
                    }
                    var code = param in data.params ? param : Object.keys(data.params)[0];
                    var ys = p.cur.map(function (v) { return convert(code, v, units); });
                    box.textContent = '';
                    // eslint-disable-next-line no-new
                    new uPlot({
                        width: Math.max(280, box.clientWidth || 480),
                        height: 160,
                        series: [{}, { stroke: '#2f9bff', width: 2, spanGaps: true }],
                        axes: [
                            { stroke: '#9b9ba3', grid: { stroke: '#2c2c32' } },
                            { stroke: '#9b9ba3', grid: { stroke: '#2c2c32' } }
                        ],
                        scales: { x: { time: true } }
                    }, [data.x, ys], box);
                })
                .catch(function () {
                    box.textContent = '';
                    box.appendChild(el('p', { class: 'river-chart-message' }, 'Chart unavailable.'));
                });
        });
    }

    function initSiteAccountForms() {
        if (root.getAttribute('data-logged-in') !== '1') return;
        var siteId = root.getAttribute('data-site');

        root.querySelectorAll('[data-apply-auto]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var code = btn.getAttribute('data-apply-auto');
                var fieldset = root.querySelector('[data-range-param="' + code + '"]');
                if (!fieldset) return;
                fieldset.querySelectorAll('input[data-auto-value]').forEach(function (input) {
                    input.value = input.getAttribute('data-auto-value') || '';
                });
            });
        });

        var rangeForm = root.querySelector('[data-user-range-form]');
        if (rangeForm) {
            rangeForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(rangeForm);
                var payload = { site: siteId, units: fd.get('units') || 'us', r: {} };
                fd.forEach(function (val, key) {
                    var m = /^r\[(\w+)\]\[(\w+)\]$/.exec(key);
                    if (!m) return;
                    payload.r[m[1]] = payload.r[m[1]] || {};
                    payload.r[m[1]][m[2]] = val;
                });
                var ok = root.querySelector('[data-range-status]');
                var err = root.querySelector('[data-range-error]');
                apiPost('userranges', payload).then(function (res) {
                    if (ok) { ok.hidden = !!res.ok; ok.textContent = res.ok ? 'Ranges saved.' : ''; }
                    if (err) { err.hidden = res.ok; err.textContent = res.ok ? '' : (res.body.error || 'Could not save.'); }
                });
            });
        }

        var alertForm = root.querySelector('[data-alert-form]');
        if (alertForm) {
            alertForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(alertForm);
                var payload = { site: siteId };
                fd.forEach(function (val, key) { payload[key] = val; });
                ['trigger_enter_prime', 'trigger_leave_fishable', 'trigger_above_high', 'trigger_below_low', 'trigger_unusual', 'channel_in_app', 'channel_email', 'channel_sms'].forEach(function (k) {
                    if (!(k in payload)) payload[k] = 0;
                });
                var ok = root.querySelector('[data-alert-status]');
                var err = root.querySelector('[data-alert-error]');
                apiPost('alertsave', payload).then(function (res) {
                    if (ok) { ok.hidden = !res.ok; ok.textContent = res.ok ? 'Alerts saved.' : ''; }
                    if (err) { err.hidden = res.ok; err.textContent = res.ok ? '' : (res.body.error || 'Could not save.'); }
                });
            });
        }

        var del = root.querySelector('[data-alert-delete]');
        if (del && alertForm) {
            del.addEventListener('click', function () {
                var param = (alertForm.querySelector('[name=param]') || {}).value || '00060';
                apiPost('alertdelete', { site: siteId, param: param }).then(function (res) {
                    if (res.ok) location.reload();
                });
            });
        }
    }

    /* ---------- Site page ---------- */

    function initSite() {
        var siteId = root.getAttribute('data-site');
        var siteName = root.getAttribute('data-name');
        var loggedIn = root.getAttribute('data-logged-in') === '1';
        var state = {
            win: root.getAttribute('data-win'),
            param: root.getAttribute('data-param'),
            units: root.getAttribute('data-units')
        };
        var chartBox = root.querySelector('[data-river-chart]');
        var statsTable = root.querySelector('[data-river-stats]');
        var note = root.querySelector('[data-river-note]');
        var cache = {};
        var plot = null;
        var current = null;

        if (!/[?&]units=/.test(location.search) && store(UNITS_KEY) === 'metric') state.units = 'metric';

        var saveBtn = root.querySelector('[data-river-save]');
        if (saveBtn && loggedIn) {
            saveBtn.addEventListener('click', function () {
                var on = saveBtn.getAttribute('aria-pressed') === 'true';
                apiPost(on ? 'unsave' : 'save', { site: siteId }).then(function (res) {
                    if (!res.ok) return;
                    saveBtn.setAttribute('aria-pressed', on ? 'false' : 'true');
                    saveBtn.textContent = on ? '☆ Save to my rivers' : '★ Saved to my rivers';
                });
            });
        }

        // Anonymous favorites toggle
        var favBtn = root.querySelector('[data-river-fav]');
        if (favBtn) {
            function paintFav() {
                var on = favorites().some(function (f) { return f.id === siteId; });
                favBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                favBtn.textContent = on ? '★ Saved' : '☆ Save to favorites';
            }
            favBtn.addEventListener('click', function () {
                var list = favorites().filter(function (f) { return f.id !== siteId; });
                if (favBtn.getAttribute('aria-pressed') !== 'true') list.unshift({ id: siteId, name: siteName });
                store(FAV_KEY, list.slice(0, 30));
                paintFav();
                tellWorker({ type: 'sync', favorites: favorites() });
            });
            paintFav();
        }

        initSiteAccountForms();

        if (root.getAttribute('data-offline') === '1') {
            var bar = el('p', { class: 'river-offline-bar', role: 'status' },
                'You\'re offline. Showing data saved ' + when(root.getAttribute('data-rendered')) + '.');
            root.insertBefore(bar, root.firstChild);
            root.querySelectorAll('[data-time]').forEach(function (node) { node.textContent = ago(node.getAttribute('data-time')); });
        }

        function syncUrl(push) {
            var q = '?id=' + encodeURIComponent(siteId) + '&win=' + state.win + '&p=' + state.param + '&units=' + state.units;
            history[push ? 'pushState' : 'replaceState'](state, '', q);
            root.querySelectorAll('[data-win-tab]').forEach(function (a) {
                var key = a.getAttribute('data-win-tab');
                a.href = '?id=' + encodeURIComponent(siteId) + '&win=' + key + '&p=' + state.param + '&units=' + state.units;
                if (key === state.win) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
            });
            root.querySelectorAll('[data-param-btn]').forEach(function (b) {
                b.setAttribute('aria-pressed', b.getAttribute('data-param-btn') === state.param ? 'true' : 'false');
            });
            root.querySelectorAll('[data-units-btn]').forEach(function (b) {
                b.setAttribute('aria-pressed', b.getAttribute('data-units-btn') === state.units ? 'true' : 'false');
            });
        }

        function paintReadings() {
            root.querySelectorAll('[data-reading]').forEach(function (card) {
                var code = card.getAttribute('data-code');
                var v = convert(code, parseFloat(card.getAttribute('data-value')), state.units);
                card.querySelector('[data-reading-value]').textContent = fmt(v);
                card.querySelector('[data-reading-unit]').textContent = unitLabel(code, state.units, card.querySelector('[data-reading-unit]').textContent);
            });
            root.querySelectorAll('[data-units-only]').forEach(function (node) {
                node.hidden = node.getAttribute('data-units-only') !== state.units;
            });
        }

        function showMessage(text, retry) {
            if (plot) { plot.destroy(); plot = null; }
            chartBox.textContent = '';
            var p = el('p', { class: 'river-chart-message' }, text);
            if (retry) {
                var b = el('button', { type: 'button', class: 'btn-secondary' }, 'Try again');
                b.addEventListener('click', load);
                p.appendChild(document.createTextNode(' '));
                p.appendChild(b);
            }
            chartBox.appendChild(p);
        }

        function drawChart() {
            var d = current;
            var p = d.params[state.param];
            if (!p) {
                var label = (root.querySelector('[data-param-btn="' + state.param + '"]') || {}).textContent;
                showMessage(label
                    ? label.trim() + ' has no ' + (d.source === 'daily' ? 'daily history' : 'readings') + ' for this window. Try 3D or 1W, or another measurement.'
                    : 'No data for this window.');
                return;
            }
            var conv = function (arr) { return arr.map(function (v) { return convert(state.param, v, state.units); }); };
            var unit = unitLabel(state.param, state.units, p.unit);
            var prevYear = new Date(d.range.prev[0]).getUTCFullYear();
            var curYear = new Date(d.range.cur[1]).getUTCFullYear();
            var data = [d.x, conv(p.cur), conv(p.prev)];

            if (plot) plot.destroy();
            chartBox.textContent = '';
            var width = chartBox.clientWidth || 600;
            plot = new uPlot({
                width: width,
                height: Math.max(220, Math.min(360, Math.round(width * 0.45))),
                scales: { x: { time: true } },
                cursor: { drag: { x: false, y: false } },
                series: [
                    {},
                    { label: 'This period (' + curYear + ')', stroke: '#2f9bff', width: 2, value: function (u, v) { return fmt(v) + ' ' + unit; } },
                    { label: 'Same dates ' + prevYear, stroke: '#9b9ba3', width: 1.5, dash: [6, 4], value: function (u, v) { return fmt(v) + ' ' + unit; } }
                ],
                axes: [
                    { stroke: '#9b9ba3', grid: { stroke: '#2c2c32' }, ticks: { stroke: '#2c2c32' } },
                    { stroke: '#9b9ba3', grid: { stroke: '#2c2c32' }, ticks: { stroke: '#2c2c32' }, size: 64, label: p.label + ' (' + unit + ')',
                      values: function (u, vals) { return vals.map(fmt); } }
                ]
            }, data, chartBox);
        }

        function statCell(s, code) {
            if (!s) return 'No data';
            return fmt(convert(code, s.avg, state.units)) + ' avg (' + fmt(convert(code, s.min, state.units)) + '–' + fmt(convert(code, s.max, state.units)) + ')';
        }

        function drawStats() {
            var tbody = statsTable.querySelector('tbody');
            tbody.textContent = '';
            Object.keys(current.params).forEach(function (code) {
                var p = current.params[code];
                var unit = unitLabel(code, state.units, p.unit);
                var s = p.stats;
                var change = '–';
                if ((code === '00010' || code === '00020') && s.change_abs != null) {
                    var delta = state.units === 'us' ? s.change_abs * 9 / 5 : s.change_abs;
                    change = (delta > 0 ? '+' : '') + fmt(delta) + ' ' + unit;
                } else if (s.change_pct != null) {
                    change = (s.change_pct > 0 ? '+' : '') + s.change_pct + '%';
                }
                var tr = el('tr', code === state.param ? { class: 'is-selected' } : {});
                tr.appendChild(el('th', { scope: 'row' }, p.label + ' (' + unit + ')'));
                tr.appendChild(el('td', {}, statCell(s.cur, code)));
                tr.appendChild(el('td', {}, statCell(s.prev, code)));
                tr.appendChild(el('td', { class: 'river-change' }, change));
                tbody.appendChild(tr);
            });
            statsTable.hidden = !tbody.children.length;

            var parts = [];
            if (current.stale) parts.push('USGS isn\'t responding; showing saved data.');
            if (current.as_of) parts.push('Updated ' + new Date(current.as_of).toLocaleString());
            note.textContent = parts.join(' ');
        }

        function render() {
            drawChart();
            drawStats();
        }

        function load() {
            var win = state.win;
            if (cache[win]) { current = cache[win]; render(); return; }
            if (plot) { plot.destroy(); plot = null; }
            chartBox.innerHTML = '<div class="river-chart-skeleton">Loading chart…</div>';
            fetch(api + '?action=series&site=' + encodeURIComponent(siteId) + '&win=' + win)
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.body.error || 'Request failed');
                    cache[win] = res.body;
                    if (state.win === win) { current = res.body; render(); }
                })
                .catch(function (e) { showMessage((e && e.message) || 'Could not load data.', true); });
        }

        root.querySelectorAll('[data-win-tab]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                state.win = a.getAttribute('data-win-tab');
                syncUrl(true);
                load();
            });
        });

        root.querySelectorAll('[data-param-btn]').forEach(function (b) {
            b.addEventListener('click', function () {
                state.param = b.getAttribute('data-param-btn');
                syncUrl(false);
                if (current) render();
            });
        });

        root.querySelectorAll('[data-units-btn]').forEach(function (b) {
            b.addEventListener('click', function () {
                state.units = b.getAttribute('data-units-btn');
                store(UNITS_KEY, state.units);
                syncUrl(false);
                paintReadings();
                if (current) render();
            });
        });

        window.addEventListener('popstate', function (e) {
            if (!e.state) return;
            state.win = e.state.win;
            state.param = e.state.param;
            state.units = e.state.units;
            syncUrl(false);
            paintReadings();
            load();
        });

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () { if (current && plot) drawChart(); }, 150);
        });

        syncUrl(false);
        paintReadings();
        if (typeof uPlot === 'undefined') { showMessage('Chart library failed to load.', false); return; }
        load();
    }
})();
