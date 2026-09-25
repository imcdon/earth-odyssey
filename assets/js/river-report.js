/*
 * river-report.js - Fishing report editor: gauge type-ahead, catch rows, in-browser photo resizing, draft autosave.
 * Photos are shrunk to 1600px (WebP, or JPEG where the browser can't make WebP) before upload, so uploads stay
 * small and the server never needs an image library.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-report-form]');
    if (!form) return;

    var api = form.getAttribute('data-api');
    var PHOTO_EDGE = 1600;

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        if (text != null) node.textContent = text;
        return node;
    }

    /* ---------- Gauge type-ahead ---------- */

    form.querySelectorAll('[data-gauge-slot]').forEach(function (slot) {
        var input = slot.querySelector('[data-gauge-search]');
        var hidden = slot.querySelector('[data-gauge-id]');
        var list = slot.querySelector('.rivers-results');
        var timer = null;
        var last = '';

        function close() { list.hidden = true; }

        input.addEventListener('input', function () {
            hidden.value = '';
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 3) { close(); return; }
            timer = setTimeout(function () {
                last = q;
                fetch(api + '?action=search&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.q !== last) return;
                        list.textContent = '';
                        (data.results || []).forEach(function (r) {
                            var li = el('li');
                            var b = el('button', { type: 'button' }, r.name);
                            li.appendChild(b);
                            li.appendChild(el('span', { class: 'rivers-results-meta' }, [r.county, r.state].filter(Boolean).join(', ')));
                            b.addEventListener('click', function () {
                                hidden.value = r.id;
                                input.value = r.name + ' (' + r.id + ')';
                                close();
                                saveDraft();
                            });
                            list.appendChild(li);
                        });
                        if (!list.children.length) list.appendChild(el('li', { class: 'rivers-results-empty' }, 'No gauges match'));
                        list.hidden = false;
                    })
                    .catch(close);
            }, 250);
        });
        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        document.addEventListener('click', function (e) { if (!slot.contains(e.target)) close(); });
    });

    /* ---------- Catch rows ---------- */

    var tbody = form.querySelector('[data-catch-table] tbody');
    var template = tbody.querySelector('[data-catch-row]:last-child').cloneNode(true);
    template.querySelectorAll('input, select').forEach(function (f) { f.value = ''; });

    function addRow(focus) {
        var row = template.cloneNode(true);
        tbody.appendChild(row);
        if (focus) row.querySelector('input').focus();
        return row;
    }

    form.querySelector('[data-catch-add]').addEventListener('click', function () { addRow(true); });
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-catch-remove]');
        if (!btn) return;
        var row = btn.closest('tr');
        if (tbody.children.length > 1) row.remove();
        else row.querySelectorAll('input, select').forEach(function (f) { f.value = ''; });
        saveDraft();
    });

    form.querySelectorAll('[data-units-radio]').forEach(function (r) {
        r.addEventListener('change', function () {
            var metric = r.value === 'metric' && r.checked;
            form.querySelector('[data-unit-length]').textContent = metric ? 'cm' : 'in';
            form.querySelector('[data-unit-weight]').textContent = metric ? 'kg' : 'lb';
        });
    });

    /* ---------- Photos: resize in the browser, upload with the form ---------- */

    var photoInput = form.querySelector('[data-photo-input]');
    var previews = form.querySelector('[data-photo-previews]');
    var status = form.querySelector('[data-photo-status]');
    var maxPhotos = parseInt(form.getAttribute('data-max-photos'), 10);
    var pending = [];

    function slotsLeft() {
        var existing = parseInt(form.getAttribute('data-photo-count'), 10);
        var removing = form.querySelectorAll('[data-photo-delete]:checked').length;
        return maxPhotos - (existing - removing) - pending.length;
    }

    function decode(file) {
        if (window.createImageBitmap) {
            return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return decodeWithImg(file); });
        }
        return decodeWithImg(file);
    }

    function decodeWithImg(file) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            var src = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(src); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(src); reject(new Error('decode')); };
            img.src = src;
        });
    }

    function shrink(file) {
        return decode(file).then(function (img) {
            var w = img.width, h = img.height;
            var scale = Math.min(1, PHOTO_EDGE / Math.max(w, h));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(w * scale);
            canvas.height = Math.round(h * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            if (img.close) img.close();
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    if (blob && blob.type === 'image/webp') { resolve(blob); return; }
                    canvas.toBlob(resolve, 'image/jpeg', 0.85);
                }, 'image/webp', 0.82);
            });
        });
    }

    function renderPending() {
        previews.textContent = '';
        pending.forEach(function (p, i) {
            var li = el('li');
            var img = el('img', { src: p.url, alt: '' });
            var cap = el('input', { type: 'text', placeholder: 'Caption', 'aria-label': 'Caption', maxlength: '255' });
            cap.value = p.caption;
            cap.addEventListener('input', function () { p.caption = cap.value; });
            var rm = el('button', { type: 'button', class: 'btn-ghost' }, 'Remove');
            rm.addEventListener('click', function () {
                URL.revokeObjectURL(p.url);
                pending.splice(i, 1);
                renderPending();
            });
            li.appendChild(img);
            li.appendChild(cap);
            li.appendChild(rm);
            previews.appendChild(li);
        });
        var left = slotsLeft();
        status.textContent = pending.length
            ? pending.length + ' photo' + (pending.length > 1 ? 's' : '') + ' ready to upload (' + Math.round(pending.reduce(function (s, p) { return s + p.blob.size; }, 0) / 1024) + ' KB). ' + Math.max(0, left) + ' slot' + (left === 1 ? '' : 's') + ' left.'
            : 'Photos are resized to 1600px before uploading.';
    }

    photoInput.addEventListener('change', function () {
        var files = Array.prototype.slice.call(photoInput.files || []);
        photoInput.value = '';
        var room = slotsLeft();
        var skipped = Math.max(0, files.length - room);
        files = files.slice(0, Math.max(0, room));
        status.textContent = files.length ? 'Resizing ' + files.length + ' photo' + (files.length > 1 ? 's' : '') + '…' : status.textContent;
        var failed = [];
        Promise.all(files.map(function (f) {
            return shrink(f).then(function (blob) {
                if (!blob) throw new Error('encode');
                pending.push({ blob: blob, url: URL.createObjectURL(blob), caption: '', name: f.name.replace(/\.[^.]+$/, '') });
            }).catch(function () { failed.push(f.name); });
        })).then(function () {
            renderPending();
            var notes = [];
            if (skipped) notes.push(skipped + ' photo' + (skipped > 1 ? 's' : '') + ' skipped (limit ' + maxPhotos + ').');
            if (failed.length) notes.push('Couldn\'t read ' + failed.join(', ') + '. Try a JPEG export.');
            if (notes.length) status.textContent += ' ' + notes.join(' ');
        });
    });

    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-photo-delete]')) renderPending();
    });

    form.addEventListener('submit', function (e) {
        var submitter = e.submitter;
        if (!pending.length || (submitter && submitter.value !== 'save')) return;
        e.preventDefault();
        var fd = new FormData(form);
        fd.delete('photos[]');
        fd.set('action', 'save');
        pending.forEach(function (p) {
            fd.append('photos[]', p.blob, p.name + (p.blob.type === 'image/webp' ? '.webp' : '.jpg'));
            fd.append('new_photo_caption[]', p.caption);
        });
        var btn = form.querySelector('[data-report-submit]');
        btn.disabled = true;
        btn.textContent = 'Saving…';
        fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) {
                if (res.redirected) { window.location.href = res.url; return; }
                return res.text().then(function (html) {
                    document.open();
                    document.write(html);
                    document.close();
                });
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = 'Save report';
                status.textContent = 'Upload failed. Check your connection and try again; your photos are still here.';
            });
    });

    /* ---------- Draft autosave (text fields only; photos aren't kept) ---------- */

    var draftKey = form.getAttribute('data-draft-key');
    var updated = parseInt(form.getAttribute('data-updated'), 10) || 0;
    var banner = document.querySelector('[data-report-draft]');
    var draftTimer = null;

    function fields() {
        return Array.prototype.filter.call(form.elements, function (f) {
            return f.name && f.name !== 'csrf_token' && f.type !== 'file' && f.type !== 'submit' && f.type !== 'button';
        });
    }

    function saveDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(function () {
            var data = fields().map(function (f) {
                return (f.type === 'checkbox' || f.type === 'radio') ? [f.name, f.value, f.checked] : [f.name, f.value];
            });
            try { localStorage.setItem(draftKey, JSON.stringify({ t: Date.now(), fields: data })); } catch (err) { /* storage full or blocked */ }
        }, 800);
    }

    function restore(draft) {
        var rows = draft.fields.filter(function (f) { return f[0] === 'catch_species[]'; }).length;
        while (tbody.children.length < rows) addRow(false);
        var seen = {};
        draft.fields.forEach(function (f) {
            var name = f[0];
            var matches = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
            if (f.length === 3) {
                matches.forEach(function (m) { if (m.value === f[1]) m.checked = f[2]; });
                return;
            }
            var i = seen[name] = (seen[name] || 0);
            seen[name]++;
            if (matches[i]) matches[i].value = f[1];
        });
        form.querySelectorAll('[data-units-radio]:checked').forEach(function (r) { r.dispatchEvent(new Event('change')); });
    }

    var stored = null;
    try { stored = JSON.parse(localStorage.getItem(draftKey) || 'null'); } catch (err) { stored = null; }
    if (form.getAttribute('data-saved') === '1') {
        try {
            localStorage.removeItem(draftKey);
            localStorage.removeItem('eo-report-draft-new');
        } catch (err) { /* ignore */ }
    } else if (stored && stored.t > updated + 2000) {
        banner.querySelector('[data-report-draft-text]').textContent =
            'You have unsaved changes from ' + new Date(stored.t).toLocaleString() + '.';
        banner.hidden = false;
        banner.querySelector('[data-report-draft-restore]').addEventListener('click', function () {
            restore(stored);
            banner.hidden = true;
        });
        banner.querySelector('[data-report-draft-discard]').addEventListener('click', function () {
            try { localStorage.removeItem(draftKey); } catch (err) { /* ignore */ }
            banner.hidden = true;
        });
    }

    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);
})();
