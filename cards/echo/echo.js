/**
 * echo.js
 *
 * Client-side calculations for the Doppler Echocardiogram card.
 *
 * Derived values computed live (no page reload required):
 *   - BSA     : Du Bois formula: 0.007184 × H^0.725 × W^0.425
 *   - LV Mass Index : LV Mass (g) / BSA (m²)
 *   - E/A ratio     : Mitral E / Mitral A
 *   - E/e' ratio    : E / avg(e'_septal, e'_lateral)
 *
 * All derived inputs are marked readonly in the HTML; their values are
 * submitted normally (not disabled) so the server receives and stores them.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Echo
 */

/* global top */

(function ($) {
    'use strict';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Parse a numeric input value; return NaN when blank or non-numeric.
     */
    function val(id) {
        var v = parseFloat(document.getElementById(id) ? document.getElementById(id).value : '');
        return isNaN(v) ? NaN : v;
    }

    /**
     * Set a derived (readonly) input to a formatted value, or clear it.
     */
    function setDerived(id, v, decimals) {
        var el = document.getElementById(id);
        if (!el) { return; }
        el.value = isNaN(v) ? '' : v.toFixed(decimals);
    }

    // -------------------------------------------------------------------------
    // BSA: Du Bois formula
    // -------------------------------------------------------------------------
    function calcBSA() {
        var h = val('echo_height_cm');
        var w = val('echo_weight_kg');
        var bsa = NaN;
        if (!isNaN(h) && !isNaN(w) && h > 0 && w > 0) {
            bsa = 0.007184 * Math.pow(h, 0.725) * Math.pow(w, 0.425);
        }
        setDerived('echo_bsa_m2', bsa, 2);
        return bsa;
    }

    // -------------------------------------------------------------------------
    // LV Mass Index: LV Mass / BSA
    // -------------------------------------------------------------------------
    function calcLVMassIndex() {
        var bsa  = val('echo_bsa_m2');
        var mass = val('echo_lv_mass_g');
        var idx  = NaN;
        if (!isNaN(bsa) && !isNaN(mass) && bsa > 0) {
            idx = mass / bsa;
        }
        setDerived('echo_lv_mass_index_g_m2', idx, 1);
    }

    // -------------------------------------------------------------------------
    // E/A ratio
    // -------------------------------------------------------------------------
    function calcEA() {
        var e = val('echo_mitral_e_cm_s');
        var a = val('echo_mitral_a_cm_s');
        var ea = NaN;
        if (!isNaN(e) && !isNaN(a) && a > 0) {
            ea = e / a;
        }
        setDerived('echo_mitral_e_a_ratio', ea, 2);
    }

    // -------------------------------------------------------------------------
    // E/e' ratio: E / mean(e'_septal, e'_lateral)
    // If only one is available, use it alone.
    // -------------------------------------------------------------------------
    function calcEePrime() {
        var e    = val('echo_mitral_e_cm_s');
        var sep  = val('echo_e_prime_septal_cm_s');
        var lat  = val('echo_e_prime_lateral_cm_s');
        var ratio = NaN;

        if (!isNaN(e)) {
            var denom = NaN;
            if (!isNaN(sep) && !isNaN(lat)) {
                denom = (sep + lat) / 2.0;
            } else if (!isNaN(sep)) {
                denom = sep;
            } else if (!isNaN(lat)) {
                denom = lat;
            }
            if (!isNaN(denom) && denom > 0) {
                ratio = e / denom;
            }
        }
        setDerived('echo_e_e_prime_ratio', ratio, 1);
    }

    // -------------------------------------------------------------------------
    // Run all derived calculations
    // -------------------------------------------------------------------------
    function recalcAll() {
        var bsa = calcBSA();
        calcLVMassIndex();
        calcEA();
        calcEePrime();
    }

    // -------------------------------------------------------------------------
    // Attach event listeners
    // -------------------------------------------------------------------------
    function initCalculations() {
        var bsaInputs = ['echo_height_cm', 'echo_weight_kg'];
        bsaInputs.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', recalcAll); }
        });

        var massInput = document.getElementById('echo_lv_mass_g');
        if (massInput) { massInput.addEventListener('input', calcLVMassIndex); }

        var eaInputs = ['echo_mitral_e_cm_s', 'echo_mitral_a_cm_s'];
        eaInputs.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', function () { calcEA(); calcEePrime(); }); }
        });

        var eprimeInputs = ['echo_e_prime_septal_cm_s', 'echo_e_prime_lateral_cm_s'];
        eprimeInputs.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', calcEePrime); }
        });
    }

    // -------------------------------------------------------------------------
    // Bootstrap tooltips
    // -------------------------------------------------------------------------
    function initTooltips() {
        var els = document.querySelectorAll('[data-toggle="tooltip"]');
        if (typeof $.fn.tooltip === 'function') { $(els).tooltip(); }
        var els5 = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        if (window.bootstrap && window.bootstrap.Tooltip) {
            els5.forEach(function (el) { new window.bootstrap.Tooltip(el); });
        }
    }

    // -------------------------------------------------------------------------
    // Unsaved-changes warning
    // -------------------------------------------------------------------------
    function initUnsavedWarning() {
        var form = document.getElementById('echo-form');
        if (!form) { return; }
        var dirty = false;
        form.addEventListener('input', function () { dirty = true; });
        form.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });
    }

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        initUnsavedWarning();
        initCalculations();
        // Run once on load to populate derived fields from existing data
        recalcAll();
    });

}(typeof jQuery !== 'undefined' ? jQuery : { fn: {} }));

