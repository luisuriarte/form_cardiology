/**
 * Preoperative Risk card – preop_risk.js
 *
 * Real-time client-side calculation of Lee Revised Cardiac Risk Index (RCRI)
 * score (0-6) and class (I, II, III, IV).
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 */
document.addEventListener('DOMContentLoaded', function () {
    const flags = document.querySelectorAll('.lee-flag');
    const scoreDisplay = document.getElementById('lee_score_display');
    const classDisplay = document.getElementById('lee_class_display');
    const scoreInput = document.getElementById('lee_score');
    const classInput = document.getElementById('lee_class');

    function updateLee() {
        let score = 0;
        flags.forEach(function (cb) {
            if (cb.checked) {
                score++;
            }
        });

        let leeClass = 'I';
        if (score === 1) {
            leeClass = 'II';
        } else if (score === 2) {
            leeClass = 'III';
        } else if (score >= 3) {
            leeClass = 'IV';
        }

        if (scoreDisplay) scoreDisplay.textContent = score;
        if (classDisplay) classDisplay.textContent = leeClass;
        if (scoreInput) scoreInput.value = score;
        if (classInput) classInput.value = leeClass;
    }

    flags.forEach(function (cb) {
        cb.addEventListener('change', updateLee);
    });
});

