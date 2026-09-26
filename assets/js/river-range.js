/*
 * river-range.js - Range editor: switching US/metric converts the numbers already typed.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-range-form]');
    if (!form) return;

    var FACTOR = { '00060': 0.0283168, '00065': 0.3048 };
    var UNIT = { '00060': { us: 'cfs', metric: 'm³/s' }, '00065': { us: 'ft', metric: 'm' } };
    var current = (form.querySelector('input[name="units"]:checked') || {}).value || 'us';

    function round(v) {
        var digits = Math.abs(v) >= 100 ? 0 : 2;
        return String(parseFloat(v.toFixed(digits)));
    }

    form.querySelectorAll('input[name="units"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var next = radio.value;
            if (next === current) return;
            form.querySelectorAll('[data-range-code]').forEach(function (input) {
                var raw = input.value.replace(/,/g, '').trim();
                if (raw === '' || isNaN(raw)) return;
                var f = FACTOR[input.getAttribute('data-range-code')];
                input.value = round(next === 'metric' ? parseFloat(raw) * f : parseFloat(raw) / f);
            });
            form.querySelectorAll('[data-range-unit]').forEach(function (node) {
                node.textContent = UNIT[node.getAttribute('data-range-unit')][next];
            });
            document.querySelectorAll('[data-units-only]').forEach(function (node) {
                node.hidden = node.getAttribute('data-units-only') !== next;
            });
            current = next;
        });
    });
})();
