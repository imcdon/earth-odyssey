/*
 * sw.js - Offline support for the river tool (scope: services/rivers/).
 * River pages and their chart data load fresh whenever there's signal and are saved on the device; with no
 * signal the saved copy is shown. Keeps every favorite plus the last 10 rivers viewed. Fishing reports and
 * admin pages are never saved. Pages talk to this worker from assets/js/rivers.js.
 */
'use strict';

var VERSION = 'v1';
var SHELL = 'eo-rivers-shell-' + VERSION;
var PAGES = 'eo-rivers-pages-' + VERSION;
var DATA = 'eo-rivers-data-' + VERSION;
var ASSETS = 'eo-rivers-assets-' + VERSION;
var META = 'eo-rivers-meta-' + VERSION;
var KEEP = [SHELL, PAGES, DATA, ASSETS, META];

var BASE = new URL('./', self.location.href);
var META_KEY = new URL('__meta', BASE).href;
var OFFLINE_URL = new URL('offline.php', BASE).href;
var KEEP_RECENT = 10;
var REFRESH_GAP = 10 * 60 * 1000;
var FONT_HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com'];
var NEVER = ['report.php', 'sw.js', 'manifest.php'];

self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(SHELL)
            .then(function (c) { return c.add(new Request(OFFLINE_URL, { cache: 'reload' })); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys()
            .then(function (names) {
                return Promise.all(names.filter(function (n) {
                    return n.indexOf('eo-rivers-') === 0 && KEEP.indexOf(n) === -1;
                }).map(function (n) { return caches.delete(n); }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);

    if (FONT_HOSTS.indexOf(url.hostname) !== -1) {
        e.respondWith(cacheFirst(e, req));
        return;
    }
    if (url.origin !== BASE.origin) return;
    if (url.pathname.indexOf(BASE.pathname) !== 0) {
        if (url.pathname.indexOf('/assets/') !== -1) e.respondWith(cacheFirst(e, req));
        return;
    }

    var page = url.pathname.slice(BASE.pathname.length);
    if (NEVER.indexOf(page) !== -1) return;
    if (page === 'api.php') {
        if (url.searchParams.get('action') === 'series') e.respondWith(series(e, req, url));
        return;
    }
    if (req.mode !== 'navigate') return;
    if (page === 'site.php') {
        e.respondWith(sitePage(e, req, url));
    } else if ((page === '' || page === 'index.php') && url.searchParams.get('app') === '1') {
        e.respondWith(appStart(req));
    } else {
        e.respondWith(fetch(req).catch(offlinePage));
    }
});

self.addEventListener('message', function (e) {
    var d = e.data || {};
    if (d.type === 'viewed' && d.id) {
        e.waitUntil(onViewed(d));
    } else if (d.type === 'sync') {
        e.waitUntil(onSync(d.favorites || []));
    } else if (d.type === 'assets') {
        e.waitUntil(cacheAssets(d.urls || []));
    } else if (d.type === 'list' && e.ports[0]) {
        e.waitUntil(listSaved().then(function (list) { e.ports[0].postMessage(list); }));
    }
});

/* ---------- Keys ---------- */

function siteKey(id) {
    return new URL('site.php?id=' + encodeURIComponent(id), BASE).href;
}

function seriesKey(id, win) {
    return new URL('api.php?action=series&site=' + encodeURIComponent(id) + '&win=' + encodeURIComponent(win), BASE).href;
}

function idFromKey(key) {
    var q = new URL(key).searchParams;
    return q.get('id') || q.get('site') || '';
}

/* ---------- Responses ---------- */

function fromCache(name, key) {
    return caches.open(name).then(function (c) { return c.match(key); });
}

function offlinePage() {
    return fromCache(SHELL, OFFLINE_URL).then(function (hit) {
        return hit || new Response('<!DOCTYPE html><meta charset="utf-8"><title>Offline</title><h1>You\'re offline</h1>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    });
}

/* A saved page gets data-offline="1" so rivers.js shows the "You're offline" bar. */
function markOffline(res) {
    return res.text().then(function (html) {
        return new Response(html.replace('data-river-page="site"', 'data-river-page="site" data-offline="1"'),
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    });
}

function jsonError(message) {
    return new Response(JSON.stringify({ error: message }), { status: 503, headers: { 'Content-Type': 'application/json' } });
}

/* Versioned CSS/JS and fonts: saved copy first; older versions of the same file are dropped. */
function cacheFirst(e, req) {
    return caches.open(ASSETS).then(function (c) {
        return c.match(req).then(function (hit) {
            if (hit) return hit;
            return fetch(req).then(function (res) {
                if (res.ok || res.type === 'opaque') e.waitUntil(putAsset(c, req.url, res.clone()));
                return res;
            }, function (err) {
                // A saved page may point at an older ?v= than the copy now stored; any version beats none.
                return c.match(req, { ignoreSearch: true }).then(function (any) { if (any) return any; throw err; });
            });
        });
    });
}

function putAsset(cache, url, res) {
    var u = new URL(url);
    return cache.keys().then(function (keys) {
        return Promise.all(keys.filter(function (k) {
            var o = new URL(k.url);
            return o.origin === u.origin && o.pathname === u.pathname && o.href !== u.href && u.origin === BASE.origin;
        }).map(function (k) { return cache.delete(k); }));
    }).then(function () { return cache.put(url, res); });
}

function sitePage(e, req, url) {
    var id = url.searchParams.get('id') || '';
    var saved = function () {
        return fromCache(PAGES, siteKey(id)).then(function (hit) { return hit ? markOffline(hit) : offlinePage(); });
    };
    return fetch(req).then(function (res) {
        if (res.ok && (res.headers.get('Content-Type') || '').indexOf('text/html') === 0) {
            var copy = res.clone();
            e.waitUntil(caches.open(PAGES).then(function (c) { return c.put(siteKey(id), copy); })
                .then(function () { return updateMeta(function (m) { m.saved[id] = Date.now(); }); }));
            return res;
        }
        return res.status >= 500 ? fromCache(PAGES, siteKey(id)).then(function (hit) { return hit ? markOffline(hit) : res; }) : res;
    }, saved);
}

function series(e, req, url) {
    var id = url.searchParams.get('site') || '';
    var key = seriesKey(id, (url.searchParams.get('win') || '1W').toUpperCase());
    return fetch(req).then(function (res) {
        if (res.ok) {
            var copy = res.clone();
            e.waitUntil(caches.open(DATA).then(function (c) { return c.put(key, copy); }));
            return res;
        }
        return fromCache(DATA, key).then(function (hit) { return hit || res; });
    }, function () {
        return fromCache(DATA, key).then(function (hit) {
            return hit || jsonError('You\'re offline, and this chart window wasn\'t saved. The 1W chart is saved for every river you\'ve opened.');
        });
    });
}

/* The home-screen icon opens the last river viewed. */
function appStart(req) {
    return readMeta().then(function (m) {
        if (m.recent[0]) return Response.redirect(siteKey(m.recent[0]), 302);
        return fetch(req).catch(offlinePage);
    });
}

/* ---------- What's saved (one JSON entry, updated one change at a time) ---------- */

var metaQueue = Promise.resolve();

function readMeta() {
    return fromCache(META, META_KEY)
        .then(function (r) { return r ? r.json() : null; })
        .catch(function () { return null; })
        .then(function (m) {
            m = m || {};
            return { recent: m.recent || [], favorites: m.favorites || [], names: m.names || {}, saved: m.saved || {}, refreshed: m.refreshed || {} };
        });
}

function updateMeta(change) {
    metaQueue = metaQueue.then(readMeta).then(function (m) {
        change(m);
        return caches.open(META).then(function (c) {
            return c.put(META_KEY, new Response(JSON.stringify(m), { headers: { 'Content-Type': 'application/json' } }));
        }).then(function () { return m; });
    }).catch(function () { return readMeta(); });
    return metaQueue;
}

function setFavorites(m, favorites) {
    m.favorites = favorites.filter(function (f) { return f && f.id; }).slice(0, 30).map(function (f) {
        if (f.name) m.names[f.id] = f.name;
        return f.id;
    });
}

function onViewed(d) {
    return updateMeta(function (m) {
        m.recent = [d.id].concat(m.recent.filter(function (x) { return x !== d.id; })).slice(0, KEEP_RECENT);
        if (d.name) m.names[d.id] = d.name;
        if (d.favorites) setFavorites(m, d.favorites);
        if (!d.offline) m.refreshed[d.id] = Date.now();
    }).then(function (m) {
        if (d.offline) return prune(m);
        var chain = d.win === '1W' ? Promise.resolve() : saveSeries(d.id);
        return chain.then(function () { return refreshFavorites(m); }).then(function () { return prune(m); });
    });
}

function onSync(favorites) {
    return updateMeta(function (m) { setFavorites(m, favorites); })
        .then(function (m) { return refreshFavorites(m).then(function () { return prune(m); }); });
}

function saveSeries(id) {
    return fetch(seriesKey(id, '1W')).then(function (res) {
        if (res.ok) return caches.open(DATA).then(function (c) { return c.put(seriesKey(id, '1W'), res); });
    }).catch(function () {});
}

/* Favorites are re-saved whenever a river page opens with signal (skipped if saved in the last 10 minutes). */
function refreshFavorites(m) {
    var due = m.favorites.filter(function (id) { return Date.now() - (m.refreshed[id] || 0) > REFRESH_GAP; });
    var next = function () {
        var id = due.shift();
        if (!id) return Promise.resolve();
        var req = new Request(siteKey(id), { headers: { 'X-EO-Offline-Refresh': '1' }, credentials: 'same-origin' });
        return fetch(req).then(function (res) {
            if (!res.ok) return;
            return caches.open(PAGES).then(function (c) { return c.put(siteKey(id), res); })
                .then(function () { return saveSeries(id); })
                .then(function () { return updateMeta(function (mm) { mm.saved[id] = mm.refreshed[id] = Date.now(); }); });
        }).catch(function () { due = []; }).then(next);
    };
    return Promise.all([next(), next(), next()]);
}

/* Drop anything that is no longer a favorite or one of the last 10 viewed. */
function prune(m) {
    var keep = {};
    m.favorites.concat(m.recent).forEach(function (id) { keep[id] = true; });
    var sweep = function (name) {
        return caches.open(name).then(function (c) {
            return c.keys().then(function (keys) {
                return Promise.all(keys.filter(function (k) { return !keep[idFromKey(k.url)]; })
                    .map(function (k) { return c.delete(k); }));
            });
        });
    };
    return Promise.all([sweep(PAGES), sweep(DATA)]).then(function () {
        return updateMeta(function (mm) {
            ['names', 'saved', 'refreshed'].forEach(function (field) {
                Object.keys(mm[field]).forEach(function (id) { if (!keep[id]) delete mm[field][id]; });
            });
        });
    });
}

function cacheAssets(urls) {
    return caches.open(ASSETS).then(function (c) {
        return Promise.all(urls.map(function (url) {
            var u = new URL(url, BASE);
            var ok = (u.origin === BASE.origin && u.pathname.indexOf('/assets/') !== -1) || FONT_HOSTS.indexOf(u.hostname) !== -1;
            if (!ok) return null;
            return c.match(u.href).then(function (hit) {
                if (hit) return null;
                return fetch(u.href, u.origin === BASE.origin ? {} : { mode: 'cors', credentials: 'omit' })
                    .then(function (res) { if (res.ok) return putAsset(c, u.href, res); })
                    .catch(function () {});
            });
        }));
    });
}

/* For the offline page: saved rivers, favorites first. */
function listSaved() {
    return Promise.all([readMeta(), caches.open(PAGES).then(function (c) { return c.keys(); })]).then(function (r) {
        var m = r[0];
        var have = {};
        r[1].forEach(function (k) { have[idFromKey(k.url)] = true; });
        var seen = {};
        return m.favorites.concat(m.recent).filter(function (id) {
            if (!have[id] || seen[id]) return false;
            seen[id] = true;
            return true;
        }).map(function (id) {
            return { id: id, name: m.names[id] || id, url: siteKey(id), savedAt: m.saved[id] || null, favorite: m.favorites.indexOf(id) !== -1 };
        });
    });
}
