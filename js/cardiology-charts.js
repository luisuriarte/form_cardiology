/**
 * Cardiology Charts Engine.
 *
 * Renders config-driven longitudinal and study-level charts using Chart.js (v4).
 * Accessible, color-blind safe, responsive, and theme-adaptive.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 */

(function(window, document) {
    'use strict';

    var CardiologyCharts = {
        instances: {},
        isReady: false,
        readyCallbacks: []
    };

    // Color-blind safe palette (Okabe-Ito / Wong)
    var PALETTE = [
        { color: '#0072B2', style: 'circle',   dash: [] },             // Blue
        { color: '#D55E00', style: 'triangle', dash: [5, 5] },         // Vermilion
        { color: '#009E73', style: 'rect',     dash: [2, 2] },         // Bluish green
        { color: '#CC79A7', style: 'diamond',  dash: [8, 4, 2, 4] },   // Reddish purple
        { color: '#E69F00', style: 'crossRot', dash: [6, 3] },         // Orange
        { color: '#56B4E9', style: 'star',     dash: [4, 4] }          // Sky blue
    ];

    /**
     * Detect theme colors from body styling.
     */
    function getThemeColors() {
        var bodyStyle = window.getComputedStyle(document.body);
        var textColor = bodyStyle.color || '#212529';
        return {
            textColor: textColor,
            gridColor: 'rgba(128, 128, 128, 0.2)',
            tickColor: textColor
        };
    }

    /**
     * Initialize all charts present on the page.
     */
    CardiologyCharts.init = function() {
        if (typeof window.Chart === 'undefined') {
            return;
        }

        var theme = getThemeColors();
        var cardElements = document.querySelectorAll('.cardiology-chart-card');

        cardElements.forEach(function(cardEl) {
            var chartId = cardEl.getAttribute('data-chart-id');
            var scriptEl = cardEl.querySelector('.cardiology-chart-json');
            var canvas = cardEl.querySelector('canvas');
            var emptyEl = cardEl.querySelector('.cardiology-chart-empty');

            if (!chartId || !scriptEl || !canvas) {
                return;
            }

            var config;
            try {
                config = JSON.parse(scriptEl.textContent);
            } catch (e) {
                return;
            }

            // Check if chart has data to plot
            if (!config.has_data || !config.datasets || config.datasets.length === 0) {
                canvas.style.display = 'none';
                if (emptyEl) {
                    emptyEl.classList.remove('d-none');
                }
                return;
            }

            // Clean up existing instance if any
            if (CardiologyCharts.instances[chartId]) {
                try {
                    CardiologyCharts.instances[chartId].destroy();
                } catch (e) {
                    // Ignore destroy errors
                }
                delete CardiologyCharts.instances[chartId];
            }

            var isStudy = (config.type === 'study');
            var isPrint = document.body.classList.contains('p-3') || window.location.pathname.indexOf('print.php') !== -1;

            // Build Chart.js datasets
            var datasets = [];
            config.datasets.forEach(function(ds, idx) {
                var paletteEntry = PALETTE[idx % PALETTE.length];
                var seriesColor = paletteEntry.color;
                var pointStyle = paletteEntry.style;
                var borderDash = paletteEntry.dash;

                var pointRadius = [];
                var pointHoverRadius = [];
                var pointBgColor = [];
                var pointBorderColor = [];
                var pointBorderWidth = [];

                var dataLength = ds.data ? ds.data.length : 0;
                for (var i = 0; i < dataLength; i++) {
                    var isCurrent = config.is_current ? !!config.is_current[i] : false;
                    var isDraft = config.is_draft ? !!config.is_draft[i] : false;

                    // Current open study gets a prominent marker
                    if (isCurrent) {
                        pointRadius.push(7);
                        pointHoverRadius.push(9);
                        pointBorderWidth.push(3);
                    } else {
                        // If single point, render clearly
                        pointRadius.push(dataLength === 1 ? 6 : 4);
                        pointHoverRadius.push(dataLength === 1 ? 8 : 6);
                        pointBorderWidth.push(1.5);
                    }

                    // Draft study gets a hollow marker
                    if (isDraft) {
                        pointBgColor.push('#ffffff');
                        pointBorderColor.push(seriesColor);
                    } else {
                        pointBgColor.push(seriesColor);
                        pointBorderColor.push(seriesColor);
                    }
                }

                datasets.push({
                    label: ds.label || ds.field,
                    data: ds.data,
                    borderColor: seriesColor,
                    backgroundColor: seriesColor,
                    borderDash: borderDash,
                    pointStyle: pointStyle,
                    pointRadius: pointRadius,
                    pointHoverRadius: pointHoverRadius,
                    pointBackgroundColor: pointBgColor,
                    pointBorderColor: pointBorderColor,
                    pointBorderWidth: pointBorderWidth,
                    fill: false,
                    tension: 0.15,
                    spanGaps: false, // Maintain gaps on null values
                    unit: config.unit || ''
                });
            });

            // Configure scales and options
            var chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                animation: isPrint ? false : { duration: 400 },
                plugins: {
                    legend: {
                        display: datasets.length > 1,
                        position: 'top',
                        labels: {
                            color: theme.textColor,
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 10,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        enabled: !isPrint,
                        callbacks: {
                            title: function(items) {
                                if (!items || items.length === 0) {
                                    return '';
                                }
                                var index = items[0].dataIndex;
                                if (isStudy && config.meta && config.meta[index]) {
                                    var meta = config.meta[index];
                                    return meta.phase + ' – Stage ' + meta.stage_no + ' (' + items[0].label + ')';
                                }
                                var title = items[0].label || '';
                                if (config.is_draft && config.is_draft[index]) {
                                    title += ' (Draft)';
                                }
                                if (config.is_current && config.is_current[index]) {
                                    title += ' [Current Study]';
                                }
                                return title;
                            },
                            label: function(item) {
                                var label = item.dataset.label || '';
                                var val = item.parsed.y;
                                var unit = item.dataset.unit || '';
                                return label + ': ' + (val !== null ? val : '—') + (unit ? ' ' + unit : '');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'category',
                        grid: {
                            color: theme.gridColor
                        },
                        ticks: {
                            color: theme.tickColor,
                            font: { size: 10 },
                            maxRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    },
                    y: {
                        grid: {
                            color: theme.gridColor
                        },
                        ticks: {
                            color: theme.tickColor,
                            font: { size: 10 }
                        },
                        title: {
                            display: !!config.unit,
                            text: config.unit || '',
                            color: theme.textColor,
                            font: { size: 11 }
                        }
                    }
                }
            };

            if (config.y_min !== null && config.y_min !== undefined) {
                chartOptions.scales.y.min = config.y_min;
            }
            if (config.y_max !== null && config.y_max !== undefined) {
                chartOptions.scales.y.max = config.y_max;
            }

            try {
                CardiologyCharts.instances[chartId] = new window.Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: config.labels || [],
                        datasets: datasets
                    },
                    options: chartOptions
                });
            } catch (err) {
                // If chart creation encounters canvas context issue, fail gracefully
            }
        });

        CardiologyCharts.isReady = true;
        while (CardiologyCharts.readyCallbacks.length > 0) {
            var cb = CardiologyCharts.readyCallbacks.shift();
            try {
                cb();
            } catch (e) {
                // Ignore callback errors
            }
        }
    };

    /**
     * Resize all active charts (e.g. after Bootstrap collapse opens).
     */
    CardiologyCharts.resizeAll = function() {
        Object.keys(CardiologyCharts.instances).forEach(function(id) {
            var chart = CardiologyCharts.instances[id];
            if (chart && typeof chart.resize === 'function') {
                chart.resize();
            }
        });
    };

    /**
     * Register a callback to fire when charts are initialized.
     */
    CardiologyCharts.onReady = function(callback) {
        if (CardiologyCharts.isReady) {
            callback();
        } else {
            CardiologyCharts.readyCallbacks.push(callback);
        }
    };

    // Attach collapse listener so hidden charts compute dimensions upon expand
    document.addEventListener('DOMContentLoaded', function() {
        CardiologyCharts.init();

        var collapseEl = document.getElementById('cardiology-charts-collapse');
        if (collapseEl) {
            var handleShow = function() {
                setTimeout(function() {
                    CardiologyCharts.resizeAll();
                }, 150);
            };

            collapseEl.addEventListener('shown.bs.collapse', handleShow);
            if (window.jQuery) {
                window.jQuery(collapseEl).on('shown.bs.collapse', handleShow);
            }
        }
    });

    // Fallback if DOMContentLoaded has already fired
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        CardiologyCharts.init();
    }

    window.CardiologyCharts = CardiologyCharts;

})(window, document);

