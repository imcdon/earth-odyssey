/*
 * gallery-admin.js - Choose → resize → submit gallery uploads, then confirm.
 * Large originals are downsampled during decode (one at a time) so they don't exhaust memory.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-gallery-upload]');
    if (!form) return;

    var photoInput = form.querySelector('[data-photo-input]');
    var queueList = form.querySelector('[data-photo-queue]');
    var chooseStatus = form.querySelector('[data-choose-status]');
    var resizeBtn = form.querySelector('[data-resize-btn]');
    var backBtn = form.querySelector('[data-back-choose]');
    var previews = form.querySelector('[data-photo-previews]');
    var resizeStatus = form.querySelector('[data-resize-status]');
    var submit = form.querySelector('[data-upload-submit]');
    var confirmBox = form.querySelector('[data-upload-confirm]');
    var stepChoose = form.querySelector('[data-step="choose"]');
    var stepResize = form.querySelector('[data-step="resize"]');

    var maxEdge = parseInt(form.getAttribute('data-max-edge'), 10) || 2400;
    var variantWidths = (form.getAttribute('data-variant-widths') || '480,960,1600')
        .split(',')
        .map(function (n) { return parseInt(n, 10); })
        .filter(function (n) { return n > 0; });

    // Originals above this are decoded with an in-engine downsample (never load full-res pixels).
    var LARGE_FILE_BYTES = 3 * 1024 * 1024;
    var MAX_INPUT_BYTES = 80 * 1024 * 1024;

    var selected = [];
    var pending = [];
    var resizing = false;

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        }
        if (text != null) node.textContent = text;
        return node;
    }

    function showStep(name) {
        var onChoose = name === 'choose';
        stepChoose.hidden = !onChoose;
        stepResize.hidden = onChoose;
        stepChoose.classList.toggle('is-active', onChoose);
        stepResize.classList.toggle('is-active', !onChoose);
    }

    function clearConfirm() {
        if (!confirmBox) return;
        confirmBox.hidden = true;
        confirmBox.textContent = '';
        confirmBox.classList.remove('is-error');
    }

    function showConfirm(text, isError) {
        if (!confirmBox) return;
        confirmBox.hidden = false;
        confirmBox.textContent = text;
        confirmBox.classList.toggle('is-error', !!isError);
        confirmBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function yieldUi() {
        return new Promise(function (resolve) {
            requestAnimationFrame(function () {
                setTimeout(resolve, 0);
            });
        });
    }

    function randomStem() {
        var bytes = new Uint8Array(8);
        crypto.getRandomValues(bytes);
        var hex = Array.prototype.map.call(bytes, function (b) {
            return b.toString(16).padStart(2, '0');
        }).join('');
        return 'img_' + hex;
    }

    function closeSource(source) {
        if (source && typeof source.close === 'function') {
            try { source.close(); } catch (e) { /* ignore */ }
        }
    }

    function decodeWithImg(file) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            var src = URL.createObjectURL(file);
            img.onload = function () {
                URL.revokeObjectURL(src);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(src);
                reject(new Error('Could not read this image.'));
            };
            img.src = src;
        });
    }

    function bitmapOpts(extra) {
        var opts = { imageOrientation: 'from-image' };
        if (extra) {
            Object.keys(extra).forEach(function (k) { opts[k] = extra[k]; });
        }
        return opts;
    }

    /* Probe aspect with a tiny bitmap, then decode already fitted to maxEdge (no full-res buffer). */
    function decodeDownsampled(file, edge) {
        return createImageBitmap(file, bitmapOpts({
            resizeWidth: 128,
            resizeQuality: 'pixelated',
        })).then(function (probe) {
            var aspect = probe.width / Math.max(1, probe.height);
            closeSource(probe);
            var opts = bitmapOpts({ resizeQuality: 'high' });
            if (aspect >= 1) {
                opts.resizeWidth = edge;
            } else {
                opts.resizeHeight = edge;
            }
            return createImageBitmap(file, opts);
        });
    }

    function decodeFull(file) {
        if (window.createImageBitmap) {
            return createImageBitmap(file, bitmapOpts()).catch(function () {
                return decodeWithImg(file);
            });
        }
        return decodeWithImg(file);
    }

    /*
     * Prefer downsampled decode for big files. Fall back if the browser can't resize during decode.
     * Always canvas-scale with min(1, …) so small photos are never upscaled.
     */
    function decodeForResize(file, edge) {
        var large = file.size >= LARGE_FILE_BYTES;
        if (large && window.createImageBitmap) {
            return decodeDownsampled(file, edge).catch(function () {
                return decodeFull(file);
            });
        }
        return decodeFull(file).catch(function () {
            if (!window.createImageBitmap) throw new Error('Could not read this image.');
            return decodeDownsampled(file, edge);
        });
    }

    function canvasToWebp(canvas, quality) {
        return new Promise(function (resolve) {
            if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (blob && blob.type === 'image/webp') {
                        resolve(blob);
                        return;
                    }
                    canvas.toBlob(function (jpegBlob) {
                        resolve(jpegBlob);
                    }, 'image/jpeg', 0.85);
                }, 'image/webp', quality);
                return;
            }
            resolve(null);
        });
    }

    function drawToMax(source, maxW, maxH) {
        var w = source.width;
        var h = source.height;
        if (!w || !h) throw new Error('size');
        var scale = Math.min(1, maxW / w, maxH / h);
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(w * scale));
        canvas.height = Math.max(1, Math.round(h * scale));
        var ctx = canvas.getContext('2d', { alpha: false });
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    function readExifLite(file) {
        return new Promise(function (resolve) {
            if (!file || !file.slice || file.size > 40 * 1024 * 1024) {
                resolve(null);
                return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                try {
                    var view = new DataView(reader.result);
                    if (view.byteLength < 14 || view.getUint16(0) !== 0xffd8) {
                        resolve(null);
                        return;
                    }
                    var offset = 2;
                    while (offset + 4 < view.byteLength) {
                        if (view.getUint16(offset) !== 0xffe1) {
                            if ((view.getUint16(offset) & 0xff00) !== 0xff00) break;
                            offset += 2 + view.getUint16(offset + 2);
                            continue;
                        }
                        var length = view.getUint16(offset + 2);
                        if (offset + 4 + length > view.byteLength) break;
                        if (view.getUint32(offset + 4) !== 0x45786966) {
                            offset += 2 + length;
                            continue;
                        }
                        var tiff = offset + 10;
                        var little = view.getUint16(tiff) === 0x4949;
                        function u16(o) { return little ? view.getUint16(o, true) : view.getUint16(o, false); }
                        function u32(o) { return little ? view.getUint32(o, true) : view.getUint32(o, false); }
                        var ifd = tiff + u32(tiff + 4);
                        var entries = u16(ifd);
                        var make = null;
                        var model = null;
                        var datetime = null;
                        for (var i = 0; i < entries; i++) {
                            var entry = ifd + 2 + i * 12;
                            var tag = u16(entry);
                            var type = u16(entry + 2);
                            var count = u32(entry + 4);
                            var valueOffset = entry + 8;
                            var size = (type === 2 ? 1 : type === 3 ? 2 : 4) * count;
                            var dataAt = size <= 4 ? valueOffset : tiff + u32(valueOffset);
                            if (type === 2) {
                                var chars = [];
                                for (var c = 0; c < count - 1; c++) {
                                    chars.push(String.fromCharCode(view.getUint8(dataAt + c)));
                                }
                                var str = chars.join('').trim();
                                if (tag === 0x010f) make = str;
                                if (tag === 0x0110) model = str;
                                if (tag === 0x9003 || tag === 0x0132) datetime = str;
                            }
                        }
                        resolve({ make: make, model: model, datetime_original: datetime });
                        return;
                    }
                } catch (e) {
                    resolve(null);
                    return;
                }
                resolve(null);
            };
            reader.onerror = function () { resolve(null); };
            reader.readAsArrayBuffer(file.slice(0, 128 * 1024));
        });
    }

    function processFile(file) {
        return Promise.all([
            decodeForResize(file, maxEdge),
            readExifLite(file),
        ]).then(function (parts) {
            var source = parts[0];
            var meta = parts[1];
            var stem = randomStem();
            var baseCanvas = drawToMax(source, maxEdge, maxEdge);
            closeSource(source);

            var files = [];
            return canvasToWebp(baseCanvas, 0.82).then(function (baseBlob) {
                if (!baseBlob || baseBlob.type !== 'image/webp') {
                    throw new Error('Browser could not create WebP. Try Chrome or Edge.');
                }
                files.push({ name: stem + '.webp', blob: baseBlob });

                var chain = Promise.resolve();
                variantWidths.forEach(function (width) {
                    if (baseCanvas.width <= width) return;
                    chain = chain.then(function () {
                        var variantCanvas = drawToMax(baseCanvas, width, 100000);
                        return canvasToWebp(variantCanvas, 0.78).then(function (blob) {
                            variantCanvas.width = 0;
                            variantCanvas.height = 0;
                            if (blob && blob.type === 'image/webp') {
                                files.push({ name: stem + '-' + width + '.webp', blob: blob });
                            }
                        });
                    });
                });

                return chain.then(function () {
                    var item = {
                        stem: stem,
                        label: file.name,
                        url: URL.createObjectURL(baseBlob),
                        files: files,
                        meta: meta,
                        width: baseCanvas.width,
                        height: baseCanvas.height,
                        bytes: files.reduce(function (sum, f) { return sum + f.blob.size; }, 0),
                    };
                    baseCanvas.width = 0;
                    baseCanvas.height = 0;
                    return item;
                });
            });
        });
    }

    function renderQueue() {
        queueList.textContent = '';
        selected.forEach(function (file, index) {
            var li = el('li');
            var mb = file.size / (1024 * 1024);
            var sizeLabel = mb >= 1 ? (Math.round(mb * 10) / 10) + ' MB' : Math.round(file.size / 1024) + ' KB';
            li.appendChild(el('strong', null, file.name));
            li.appendChild(el('span', null, sizeLabel + (file.size >= LARGE_FILE_BYTES ? ' · large' : '')));
            var rm = el('button', { type: 'button', class: 'btn-secondary' }, 'Remove');
            rm.addEventListener('click', function () {
                if (resizing) return;
                selected.splice(index, 1);
                renderQueue();
            });
            li.appendChild(rm);
            queueList.appendChild(li);
        });
        resizeBtn.disabled = selected.length === 0 || resizing;
        chooseStatus.textContent = selected.length
            ? selected.length + ' photo' + (selected.length === 1 ? '' : 's') +
              ' ready for the resizer. Large files are downsized one at a time.'
            : 'No photos selected yet. Camera originals (20–50+ MB) are fine.';
    }

    function clearPending() {
        pending.forEach(function (item) {
            if (item.url) URL.revokeObjectURL(item.url);
        });
        pending = [];
        previews.textContent = '';
        submit.disabled = true;
    }

    function renderPending() {
        previews.textContent = '';
        pending.forEach(function (item, index) {
            var li = el('li');
            li.appendChild(el('img', { src: item.url, alt: '' }));
            var body = el('div');
            body.appendChild(el('strong', null, item.label));
            body.appendChild(el(
                'span',
                null,
                item.width + '×' + item.height + ' · ' + Math.round(item.bytes / 1024) + ' KB · ' + item.files.length + ' files'
            ));
            var rm = el('button', { type: 'button', class: 'btn-secondary' }, 'Remove');
            rm.addEventListener('click', function () {
                URL.revokeObjectURL(item.url);
                pending.splice(index, 1);
                renderPending();
                resizeStatus.textContent = pending.length
                    ? pending.length + ' resized photo' + (pending.length === 1 ? '' : 's') + ' ready to submit.'
                    : 'No resized photos left. Go back and choose photos again.';
            });
            body.appendChild(rm);
            li.appendChild(body);
            previews.appendChild(li);
        });
        submit.disabled = pending.length === 0 || resizing;
    }

    photoInput.addEventListener('change', function () {
        clearConfirm();
        var files = Array.prototype.slice.call(photoInput.files || []);
        photoInput.value = '';
        var skipped = [];
        files.forEach(function (file) {
            if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                skipped.push((file && file.name) || 'file');
                return;
            }
            if (file.size > MAX_INPUT_BYTES) {
                skipped.push(file.name + ' (over 80 MB)');
                return;
            }
            selected.push(file);
        });
        renderQueue();
        if (skipped.length) {
            chooseStatus.textContent += ' Skipped: ' + skipped.join(', ') + '.';
        }
    });

    resizeBtn.addEventListener('click', function () {
        if (!selected.length || resizing) return;
        clearConfirm();
        clearPending();
        showStep('resize');
        resizing = true;
        resizeBtn.disabled = true;
        backBtn.disabled = true;
        submit.disabled = true;

        var batch = selected.slice();
        var failed = [];
        var index = 0;

        function processNext() {
            if (index >= batch.length) {
                return Promise.resolve();
            }
            var file = batch[index];
            var n = index + 1;
            resizeStatus.textContent = 'Resizing ' + n + ' of ' + batch.length + ' — ' + file.name +
                (file.size >= LARGE_FILE_BYTES ? ' (large photo, downsampling…)' : '…');
            return processFile(file).then(function (item) {
                pending.push(item);
                renderPending();
                index += 1;
                return yieldUi().then(processNext);
            }).catch(function (err) {
                failed.push(file.name + (err && err.message ? ' (' + err.message + ')' : ''));
                index += 1;
                return yieldUi().then(processNext);
            });
        }

        processNext().then(function () {
            resizing = false;
            backBtn.disabled = false;
            selected = [];
            renderQueue();
            renderPending();

            if (!pending.length) {
                resizeStatus.textContent = 'Resizing failed. Go back and try again.';
                showConfirm(
                    'Could not process these photos. Try Chrome/Edge, or fewer photos at once.',
                    true
                );
                return;
            }

            var msg = pending.length + ' photo' + (pending.length === 1 ? '' : 's') +
                ' resized and ready. Click Submit photos to add them to this album.';
            if (failed.length) {
                msg += ' Skipped: ' + failed.join('; ') + '.';
            }
            resizeStatus.textContent = msg;
        });
    });

    backBtn.addEventListener('click', function () {
        if (resizing) return;
        clearConfirm();
        clearPending();
        showStep('choose');
        resizeStatus.textContent = 'Waiting for photos…';
    });

    // One file per request avoids PHP max_file_uploads / post_max_size traps.
    // Never use form.action when an input[name=action|gallery_action] exists — it returns the input, not the URL.
    function formActionUrl() {
        return form.getAttribute('action') || window.location.href;
    }
    var uploadUrl = form.getAttribute('data-upload-url') || formActionUrl();

    function csrfValue() {
        var input = form.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    function explainFailure(res, data, raw) {
        if (data && data.error) {
            var extra = '';
            if (data.debug) {
                extra = ' [files=' + (data.debug.photo_count || 0) +
                    ', max_file_uploads=' + (data.debug.max_file_uploads || '?') +
                    ', post_max_size=' + (data.debug.post_max_size || '?') + ']';
            }
            return data.error + extra;
        }
        if (res && res.status === 401) return 'Not logged in. Refresh and sign in again.';
        if (res && res.status === 403) return 'Access denied. Editor login required.';
        if (res && res.status === 413) return 'Upload too large for the server. Try one photo.';
        if (raw && /login|sign in|session expired/i.test(raw)) {
            return 'Session expired. Refresh, sign in, and try again.';
        }
        if (raw && /something went wrong/i.test(raw)) {
            return 'Server error while saving. Check logs/earth-odyssey.log or folder permissions on assets/img/galleries/.';
        }
        var snippet = (raw || '').replace(/\s+/g, ' ').trim().slice(0, 160);
        return 'Upload failed (HTTP ' + (res ? res.status : '?') + ')' +
            (snippet ? ': ' + snippet : '.');
    }

    function postOneFile(filePart, metaJson, label) {
        var fd = new FormData();
        fd.append('csrf_token', csrfValue());
        fd.append('category_id', String(form.querySelector('input[name="category_id"]').value || ''));
        fd.append('gallery_action', 'upload');
        fd.append('photos[]', filePart.blob, filePart.name);
        if (metaJson) {
            var stem = filePart.name.replace(/\.webp$/i, '').replace(/-(?:480|960|1600)$/i, '');
            fd.append('photo_meta[' + stem + ']', metaJson);
        }
        if (label) resizeStatus.textContent = label;

        return fetch(uploadUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        }).then(function (res) {
            return res.text().then(function (raw) {
                var data = null;
                try { data = raw ? JSON.parse(raw) : null; } catch (e) { data = null; }
                if (!res.ok || !data || !data.ok) {
                    throw new Error(explainFailure(res, data, raw));
                }
                return data;
            });
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!pending.length || resizing) return;

        clearConfirm();
        submit.disabled = true;
        backBtn.disabled = true;

        var queue = [];
        pending.forEach(function (item) {
            var metaJson = null;
            if (item.meta && (item.meta.make || item.meta.model || item.meta.datetime_original)) {
                metaJson = JSON.stringify(item.meta);
            }
            item.files.forEach(function (f) {
                var isVariant = /-(?:480|960|1600)\.webp$/i.test(f.name);
                queue.push({
                    file: f,
                    meta: (!isVariant && metaJson) ? metaJson : null,
                    isVariant: isVariant,
                    label: item.label,
                });
            });
        });

        // Bases first, then variants.
        queue.sort(function (a, b) {
            return (a.isVariant === b.isVariant) ? 0 : (a.isVariant ? 1 : -1);
        });

        var albumName = '';
        var addedTotal = 0;
        var index = 0;
        var softWarnings = [];

        function next() {
            if (index >= queue.length) {
                return Promise.resolve();
            }
            var job = queue[index];
            var n = index + 1;
            var kind = job.isVariant ? 'variant' : 'photo';
            return postOneFile(
                job.file,
                job.meta,
                'Submitting ' + kind + ' ' + n + ' of ' + queue.length + ' — ' + job.label
            ).then(function (data) {
                addedTotal += (data.added || data.bases || 0);
                if (data.category) albumName = data.category;
                index += 1;
                return yieldUi().then(next);
            }).catch(function (err) {
                if (job.isVariant) {
                    softWarnings.push(job.file.name + ': ' + ((err && err.message) || 'failed'));
                    index += 1;
                    return yieldUi().then(next);
                }
                throw err;
            });
        }

        next().then(function () {
            if (addedTotal <= 0) {
                throw new Error('No photos were saved. Try one photo, then check you are still logged in.');
            }
            var album = albumName || 'this album';
            var confirmText = addedTotal === 1
                ? 'Upload complete — 1 photo was added to “' + album + '”.'
                : 'Upload complete — ' + addedTotal + ' photos were added to “' + album + '”.';
            if (softWarnings.length) {
                confirmText += ' Some size variants were skipped.';
            }
            showConfirm(confirmText, false);
            resizeStatus.textContent = confirmText;
            clearPending();
            selected = [];
            renderQueue();
            showStep('choose');

            var url = formActionUrl();
            url += (String(url).indexOf('?') >= 0 ? '&' : '?') + 'saved=' + encodeURIComponent(String(addedTotal));
            window.setTimeout(function () {
                window.location.href = url;
            }, 1200);
        }).catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Upload failed.';
            resizeStatus.textContent = msg;
            showConfirm(msg, true);
            submit.disabled = pending.length === 0;
            backBtn.disabled = false;
        });
    });

    showStep('choose');
    renderQueue();
})();
