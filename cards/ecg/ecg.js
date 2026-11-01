/**
 * ECG card – ecg.js
 *
 * Real-time client-side calculation preview for QTc Bazett and Fridericia.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 */
document.addEventListener('DOMContentLoaded', function () {
    const hrInput = document.getElementById('ecg_heart_rate');
    const qtInput = document.getElementById('ecg_qt');
    const bazettOut = document.getElementById('ecg_qtc_bazett');
    const fridericiaOut = document.getElementById('ecg_qtc_fridericia');

    function updateQTC() {
        if (!hrInput || !qtInput || !bazettOut || !fridericiaOut) {
            return;
        }

        const hr = parseFloat(hrInput.value.replace(',', '.'));
        const qt = parseFloat(qtInput.value.replace(',', '.'));

        if (!isNaN(hr) && hr > 0 && !isNaN(qt) && qt > 0) {
            const rr = 60.0 / hr; // RR in seconds
            const bazett = Math.round(qt / Math.sqrt(rr));
            const fridericia = Math.round(qt / Math.cbrt(rr));

            bazettOut.value = bazett;
            fridericiaOut.value = fridericia;
        } else {
            // Leave unchanged or clear if inputs empty
            if (!hrInput.value || !qtInput.value) {
                bazettOut.value = '';
                fridericiaOut.value = '';
            }
        }
    }

    if (hrInput && qtInput) {
        hrInput.addEventListener('input', updateQTC);
        qtInput.addEventListener('input', updateQTC);
        hrInput.addEventListener('change', updateQTC);
        qtInput.addEventListener('change', updateQTC);
    }
});

