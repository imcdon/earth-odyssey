/*
 * rivers.js - River tool: gauge type-ahead, favorites, and the site page chart (uPlot) with window/units toggles.
 * All data comes from services/rivers/api.php; the browser never calls USGS.
 */
(function () {
    'use strict';

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
        if (code === '00010') return units === 'us' ? v * 9 / 5 + 32 : v;
        if (code === '00060') return units === 'metric' ? v * 0.0283168 : v;
        if (code === '00065') return units === 'metric' ? v * 0.3048 : v;
        return v;
    }

    function unitLabel(code, units, fallback) {
        if (code === '00010') return units === 'us' ? '°F' : '°C';
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

    var page = root.getAttribute('data-river-page');
    if (page === 'index') initIndex();
    else if (page === 'browse') initBrowse();
    else if (page === 'offline') initOfflinePage();
    else initSite();
    initOffline();

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
                var shown = code === '00010' ? (units === 'us' ? v * 9 / 5 : v) : convert(code, v, units);
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

    /* ---------- Index: search + favorites ---------- */

    function initIndex() {
        var siteUrl = root.getAttribute('data-site-url');
        var input = root.querySelector('[data-river-search]');
        var list = root.querySelector('#river-results');
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

        var favSection = root.querySelector('[data-river-favs]');
        var favs = favorites();
        if (favs.length) {
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
                        if (items[id]) items[id].appendChild(el('span', { class: 'river-badge is-' + b.level, title: b.title }, b.label));
                    });
                })
                .catch(function () { /* badges are optional */ });
        }
    }

    /* ---------- Site page ---------- */

    function initSite() {
        var siteId = root.getAttribute('data-site');
        var siteName = root.getAttribute('data-name');
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

        // Favorites toggle
        var favBtn = root.querySelector('[data-river-fav]');
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
                    { label: 'This period (' + curYear + ')', stroke: '#9e2a2b', width: 2, value: function (u, v) { return fmt(v) + ' ' + unit; } },
                    { label: 'Same dates ' + prevYear, stroke: '#5e574c', width: 1.5, dash: [6, 4], value: function (u, v) { return fmt(v) + ' ' + unit; } }
                ],
                axes: [
                    { stroke: '#5e574c', grid: { stroke: '#e9e1d2' }, ticks: { stroke: '#d5cab6' } },
                    { stroke: '#5e574c', grid: { stroke: '#e9e1d2' }, ticks: { stroke: '#d5cab6' }, size: 64, label: p.label + ' (' + unit + ')',
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
                if (code === '00010' && s.change_abs != null) {
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
