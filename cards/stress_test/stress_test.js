/**
 * stress_test.js
 *
 * Client-side logic for the Exercise Stress Test card.
 *
 * Derived values computed live (no page reload):
 *   - max_predicted_hr  : 220 - age_years
 *   - target_hr         : round(0.85 × max_predicted_hr)
 *   - peak_hr_pct       : peak_hr / max_predicted × 100
 *   - double_product    : peak_hr × peak_bp_sys
 *   - duke_score        : (exercise_duration_s / 60) - 5×st_depression_mm - 4×angina_index
 *   - duration_mmss     : exercise_duration_s formatted as mm:ss
 *
 * Dynamic stage table:
 *   #st-add-stage      – clones <template id="st-stage-template">; replaces __IDX__
 *   .st-remove-stage   – sets .st-delete-flag to 1 and hides the row
 *
 * @package   OpenEMR
 * @subpackage Cardiology\StressTest
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
        var el = document.getElementById(id);
        if (!el) { return NaN; }
        var v = parseFloat(el.value);
        return isNaN(v) ? NaN : v;
    }

    /**
     * Set a derived (readonly) input to a formatted number, or clear it.
     */
    function setDerived(id, v, decimals) {
        var el = document.getElementById(id);
        if (!el) { return; }
        el.value = isNaN(v) ? '' : v.toFixed(decimals);
    }

    /**
     * Set an arbitrary element's textContent (for spans, etc.).
     */
    function setText(id, str) {
        var el = document.getElementById(id);
        if (!el) { return; }
        el.value = str;
    }

    // -------------------------------------------------------------------------
    // Max Predicted HR: 220 - age
    // -------------------------------------------------------------------------
    function calcMaxPred() {
        var age = val('st_age_years');
        var maxPred = NaN;
        if (!isNaN(age) && age >= 0) {
            maxPred = 220 - age;
        }
        setDerived('st_max_pred_hr', maxPred, 0);
        return maxPred;
    }

    // -------------------------------------------------------------------------
    // Target HR: round(0.85 × max_predicted)
    // -------------------------------------------------------------------------
    function calcTargetHr(maxPred) {
        var target = NaN;
        if (!isNaN(maxPred) && maxPred > 0) {
            target = Math.round(0.85 * maxPred);
        }
        setDerived('st_target_hr', target, 0);
    }

    // -------------------------------------------------------------------------
    // Peak HR % Predicted: peak_hr / max_predicted × 100
    // -------------------------------------------------------------------------
    function calcPeakHrPct(maxPred) {
        var peak = val('st_peak_hr_bpm');
        var pct = NaN;
        if (!isNaN(peak) && !isNaN(maxPred) && maxPred > 0) {
            pct = peak / maxPred * 100;
        }
        setDerived('st_peak_hr_pct', pct, 1);
    }

    // -------------------------------------------------------------------------
    // Double Product: peak_hr × peak_bp_sys
    // -------------------------------------------------------------------------
    function calcDoubleProduct() {
        var hr  = val('st_peak_hr_bpm');
        var sys = val('st_peak_bp_sys');
        var dp  = NaN;
        if (!isNaN(hr) && !isNaN(sys)) {
            dp = hr * sys;
        }
        setDerived('st_double_product', dp, 0);
    }

    // -------------------------------------------------------------------------
    // Duke Score: (duration_s / 60) - 5×st_depression - 4×angina_index
    // Updates both #st_duke_score (numeric) and #st_duke_label (risk text).
    // -------------------------------------------------------------------------
    function calcDukeScore() {
        var dur = val('st_exercise_duration_s');
        var dep = val('st_st_dep');
        // angina_index is a radio group
        var anginaEl = document.querySelector('input[name="angina_index"]:checked');
        var angina   = anginaEl ? parseFloat(anginaEl.value) : NaN;

        var score = NaN;
        if (!isNaN(dur) && !isNaN(dep) && !isNaN(angina)) {
            score = (dur / 60.0) - 5.0 * dep - 4.0 * angina;
        }

        setDerived('st_duke_score', score, 1);

        var labelEl = document.getElementById('st_duke_label');
        if (labelEl) {
            if (isNaN(score)) {
                labelEl.value = '';
                labelEl.className = labelEl.className.replace(/\bbg-\S+/g, '') + ' bg-light';
            } else if (score >= 5) {
                labelEl.value = window.stDukeLabels ? window.stDukeLabels.low : 'Low risk';
                labelEl.style.backgroundColor = '#d1e7dd';
                labelEl.style.color           = '#0f5132';
            } else if (score >= -10) {
                labelEl.value = window.stDukeLabels ? window.stDukeLabels.intermediate : 'Intermediate risk';
                labelEl.style.backgroundColor = '#fff3cd';
                labelEl.style.color           = '#664d03';
            } else {
                labelEl.value = window.stDukeLabels ? window.stDukeLabels.high : 'High risk';
                labelEl.style.backgroundColor = '#f8d7da';
                labelEl.style.color           = '#842029';
            }
        }
    }

    // -------------------------------------------------------------------------
    // Duration formatter: seconds → mm:ss displayed in #st_duration_mmss
    // -------------------------------------------------------------------------
    function updateDurationDisplay() {
        var el  = document.getElementById('st_exercise_duration_s');
        var out = document.getElementById('st_duration_mmss');
        if (!el || !out) { return; }
        var s = parseInt(el.value, 10);
        if (isNaN(s) || s < 0) {
            out.textContent = '';
        } else {
            var m = Math.floor(s / 60);
            var r = s % 60;
            out.textContent = m + ':' + (r < 10 ? '0' : '') + r;
        }
    }

    // -------------------------------------------------------------------------
    // Run all HR / Duke recalculations in the correct dependency order
    // -------------------------------------------------------------------------
    function recalcAll() {
        var maxPred = calcMaxPred();
        calcTargetHr(maxPred);
        calcPeakHrPct(maxPred);
        calcDoubleProduct();
        updateDurationDisplay();
        calcDukeScore();
    }

    // -------------------------------------------------------------------------
    // Attach event listeners for derived fields
    // -------------------------------------------------------------------------
    function initCalculations() {
        var hrInputIds = ['st_age_years', 'st_peak_hr_bpm', 'st_peak_bp_sys'];
        hrInputIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', recalcAll); }
        });

        var durationEl = document.getElementById('st_exercise_duration_s');
        if (durationEl) {
            durationEl.addEventListener('input', function () {
                updateDurationDisplay();
                calcDukeScore();
            });
        }

        var depEl = document.getElementById('st_st_dep');
        if (depEl) { depEl.addEventListener('input', calcDukeScore); }

        // Radio group: angina_index
        document.querySelectorAll('input[name="angina_index"]').forEach(function (el) {
            el.addEventListener('change', calcDukeScore);
        });
    }

    // -------------------------------------------------------------------------
    // Dynamic stage table
    // -------------------------------------------------------------------------
    var stageCount = 0;   // used to generate unique __IDX__ replacements

    function initStageCounting() {
        // Count existing rows so new index continues from there
        stageCount = document.querySelectorAll('#st-stages-tbody .st-stage-row').length;
    }

    function addStage() {
        var tpl = document.getElementById('st-stage-template');
        if (!tpl) { return; }

        var html = tpl.innerHTML.replace(/__IDX__/g, String(stageCount));
        stageCount++;

        var tbody = document.getElementById('st-stages-tbody');
        if (!tbody) { return; }

        // Create a temporary wrapper to parse the HTML
        var tmp = document.createElement('table');
        tmp.innerHTML = '<tbody>' + html + '</tbody>';
        var newRow = tmp.querySelector('tr');
        if (!newRow) { return; }

        // Attach remove handler on the new row's button
        var btn = newRow.querySelector('.st-remove-stage');
        if (btn) { btn.addEventListener('click', removeStageHandler); }

        tbody.appendChild(newRow);

        // Mark form dirty
        var form = document.getElementById('stress-test-form');
        if (form) { form.dispatchEvent(new Event('input', { bubbles: true })); }
    }

    function removeStageHandler() {
        var row = this.closest('tr');
        if (!row) { return; }

        var flagEl = row.querySelector('.st-delete-flag');
        var idEl   = row.querySelector('input[type="hidden"][name$="[id]"]');

        if (flagEl && idEl && idEl.value !== '0') {
            // Existing DB row: mark for deletion, hide from view
            flagEl.value = '1';
            row.style.display = 'none';
        } else {
            // New row (id=0 or not yet saved): remove from DOM entirely
            row.remove();
        }

        // Mark form dirty
        var form = document.getElementById('stress-test-form');
        if (form) { form.dispatchEvent(new Event('input', { bubbles: true })); }
    }

    function initStageTable() {
        initStageCounting();

        var addBtn = document.getElementById('st-add-stage');
        if (addBtn) { addBtn.addEventListener('click', addStage); }

        // Attach handlers to existing remove buttons
        document.querySelectorAll('.st-remove-stage').forEach(function (btn) {
            btn.addEventListener('click', removeStageHandler);
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
        var form = document.getElementById('stress-test-form');
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
        initStageTable();
        // Populate derived fields from values already loaded in the form
        recalcAll();
    });

}(typeof jQuery !== 'undefined' ? jQuery : { fn: {} }));

