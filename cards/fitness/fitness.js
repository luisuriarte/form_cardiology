/**
 * Fitness card – fitness.js
 *
 * Real-time client-side calculation of BMI and UI state management for
 * restrictions field when determination is 'fit_restrictions'.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 */
document.addEventListener('DOMContentLoaded', function () {
    const heightInput = document.getElementById('fit_height_cm');
    const weightInput = document.getElementById('fit_weight_kg');
    const bmiOutput   = document.getElementById('fit_bmi');
    const determSelect= document.getElementById('determination');
    const reqBadge    = document.getElementById('restrictions_required_badge');

    function updateBMI() {
        if (!heightInput || !weightInput || !bmiOutput) {
            return;
        }

        const h = parseFloat(heightInput.value.replace(',', '.'));
        const w = parseFloat(weightInput.value.replace(',', '.'));

        if (!isNaN(h) && h > 0 && !isNaN(w) && w > 0) {
            const hM = h / 100.0;
            const bmi = Math.round((w / (hM * hM)) * 10) / 10;
            bmiOutput.value = bmi.toFixed(1);
        } else {
            if (!heightInput.value || !weightInput.value) {
                bmiOutput.value = '';
            }
        }
    }

    function updateRestrictionsRequirement() {
        if (!determSelect || !reqBadge) {
            return;
        }
        if (determSelect.value === 'fit_restrictions') {
            reqBadge.style.display = 'inline';
        } else {
            reqBadge.style.display = 'none';
        }
    }

    if (heightInput && weightInput) {
        heightInput.addEventListener('input', updateBMI);
        weightInput.addEventListener('input', updateBMI);
        heightInput.addEventListener('change', updateBMI);
        weightInput.addEventListener('change', updateBMI);
    }

    if (determSelect) {
        determSelect.addEventListener('change', updateRestrictionsRequirement);
        updateRestrictionsRequirement();
    }
});

