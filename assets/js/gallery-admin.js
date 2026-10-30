/*
 * gallery-admin.js - Resize gallery uploads in-browser to WebP + responsive variants.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-gallery-upload]');
    if (!form) return;

    var photoInput = form.querySelector('[data-photo-input]');
    var previews = form.querySelector('[data-photo-previews]');
    var status = form.querySelector('[data-photo-status]');
    var submit = form.querySelector('[data-upload-submit]');
    var maxEdge = parseInt(form.getAttribute('data-max-edge'), 10) || 2400;
    var variantWidths = (form.getAttribute('data-variant-widths') || '480,960,1600')
        .split(',')
        .map(function (n) { return parseInt(n, 10); })
        .filter(function (n) { return n > 0; });

    var pending = [];

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        }
        if (text != null) node.textContent = text;
        return node;
    }

    function randomStem() {
        var bytes = new Uint8Array(8);
        crypto.getRandomValues(bytes);
        var hex = Array.prototype.map.call(bytes, function (b) {
            return b.toString(16).padStart(2, '0');
        }).join('');
        return 'img_' + hex;
    }

    function decode(file) {
        if (window.createImageBitmap) {
            return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () {
                return decodeWithImg(file);
            });
        }
        return decodeWithImg(file);
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
                reject(new Error('decode'));
            };
            img.src = src;
        });
    }

    function canvasToWebp(canvas, quality) {
        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) {
                if (blob && blob.type === 'image/webp') {
                    resolve(blob);
                    return;
                }
                canvas.toBlob(function (jpegBlob) {
                    resolve(jpegBlob);
                }, 'image/jpeg', 0.85);
            }, 'image/webp', quality);
        });
    }

    function drawScaled(img, maxW) {
        var w = img.width;
        var h = img.height;
        if (!w || !h) throw new Error('size');
        var scale = Math.min(1, maxW / w);
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(w * scale));
        canvas.height = Math.max(1, Math.round(h * scale));
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    function readExifLite(file) {
        return new Promise(function (resolve) {
            if (!file || !file.slice) {
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
        return Promise.all([decode(file), readExifLite(file)]).then(function (parts) {
            var img = parts[0];
            var meta = parts[1];
            var stem = randomStem();
            var baseCanvas = drawScaled(img, maxEdge);
            var files = [];

            return canvasToWebp(baseCanvas, 0.82).then(function (baseBlob) {
                if (!baseBlob || baseBlob.type !== 'image/webp') {
                    throw new Error('webp');
                }
                files.push({ name: stem + '.webp', blob: baseBlob });

                var chain = Promise.resolve();
                variantWidths.forEach(function (width) {
                    if (baseCanvas.width <= width) return;
                    chain = chain.then(function () {
                        var variantCanvas = drawScaled(img, width);
                        return canvasToWebp(variantCanvas, 0.78).then(function (blob) {
                            if (blob && blob.type === 'image/webp') {
                                files.push({ name: stem + '-' + width + '.webp', blob: blob });
                            }
                        });
                    });
                });

                return chain.then(function () {
                    if (img.close) img.close();
                    return {
                        stem: stem,
                        label: file.name,
                        url: URL.createObjectURL(baseBlob),
                        files: files,
                        meta: meta,
                        bytes: files.reduce(function (sum, f) { return sum + f.blob.size; }, 0),
                    };
                });
            });
        });
    }

    function renderPending() {
        previews.textContent = '';
        pending.forEach(function (item, index) {
            var li = el('li');
            li.appendChild(el('img', { src: item.url, alt: '' }));
            var body = el('div');
            body.appendChild(el('strong', null, item.label));
            body.appendChild(el('span', null, Math.round(item.bytes / 1024) + ' KB · ' + item.files.length + ' files'));
            var rm = el('button', { type: 'button', class: 'btn-secondary' }, 'Remove');
            rm.addEventListener('click', function () {
                URL.revokeObjectURL(item.url);
                pending.splice(index, 1);
                renderPending();
            });
            body.appendChild(rm);
            li.appendChild(body);
            previews.appendChild(li);
        });

        submit.disabled = pending.length === 0;
        status.textContent = pending.length
            ? pending.length + ' photo' + (pending.length === 1 ? '' : 's') + ' ready (' +
              Math.round(pending.reduce(function (s, p) { return s + p.bytes; }, 0) / 1024) + ' KB).'
            : 'Choose photos to resize and upload.';
    }

    photoInput.addEventListener('change', function () {
        var files = Array.prototype.slice.call(photoInput.files || []);
        photoInput.value = '';
        if (!files.length) return;

        status.textContent = 'Resizing ' + files.length + ' photo' + (files.length === 1 ? '' : 's') + '…';
        submit.disabled = true;
        var failed = [];

        Promise.all(files.map(function (file) {
            return processFile(file).then(function (item) {
                pending.push(item);
            }).catch(function () {
                failed.push(file.name);
            });
        })).then(function () {
            renderPending();
            if (failed.length) {
                status.textContent += ' Skipped: ' + failed.join(', ') + '.';
            }
        });
    });

    form.addEventListener('submit', function (e) {
        if (!pending.length) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        submit.disabled = true;
        status.textContent = 'Uploading…';

        var fd = new FormData(form);
        pending.forEach(function (item) {
            item.files.forEach(function (f) {
                fd.append('photos[]', f.blob, f.name);
            });
            if (item.meta && (item.meta.make || item.meta.model || item.meta.datetime_original)) {
                fd.append('photo_meta[' + item.stem + ']', JSON.stringify(item.meta));
            }
        });

        fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        }).then(function (res) {
            return res.json().catch(function () {
                return null;
            }).then(function (data) {
                if (!res.ok || !data || !data.ok) {
                    throw new Error((data && data.error) || 'Upload failed.');
                }
                var url = form.action;
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'saved=1';
                window.location.href = url;
            });
        }).catch(function (err) {
            status.textContent = (err && err.message ? err.message + ' ' : '') +
                'Your photos are still queued here — try again.';
            submit.disabled = false;
        });
    });
})();
