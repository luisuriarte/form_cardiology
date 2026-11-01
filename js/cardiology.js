/**
 * cardiology.js
 *
 * Client-side behaviour for the Cardiology container form.
 *
 * Rules:
 *   - No inline JS event handlers in PHP templates; all handlers are attached here.
 *   - No inline styles.
 *   - No framework dependency beyond what Header::setupHeader() already loads
 *     (jQuery + Bootstrap 4/5 are available in all OE 8.2-8.5 installations).
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 */

/* global top */

(function ($) {
    'use strict';

    // -------------------------------------------------------------------------
    // Bootstrap tooltip initialisation (card tiles marked "Not installed")
    // -------------------------------------------------------------------------
    function initTooltips() {
        var tooltipEls = document.querySelectorAll('[data-toggle="tooltip"]');
        if (typeof $.fn.tooltip === 'function') {
            $(tooltipEls).tooltip();
        }
        // Bootstrap 5 uses data-bs-toggle
        var tooltipEls5 = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        if (window.bootstrap && window.bootstrap.Tooltip) {
            tooltipEls5.forEach(function (el) {
                new window.bootstrap.Tooltip(el);
            });
        }
    }

    // -------------------------------------------------------------------------
    // Auto-dismiss Bootstrap alerts after a timeout
    // -------------------------------------------------------------------------
    function autoDismissAlerts() {
        var alerts = document.querySelectorAll('.alert.alert-success.alert-dismissible');
        alerts.forEach(function (alert) {
            setTimeout(function () {
                // Bootstrap 5
                if (window.bootstrap && window.bootstrap.Alert) {
                    var bsAlert = window.bootstrap.Alert.getOrCreateInstance(alert);
                    if (bsAlert) { bsAlert.close(); }
                    return;
                }
                // Bootstrap 4
                if (typeof $.fn.alert === 'function') {
                    $(alert).alert('close');
                }
            }, 4000);
        });
    }

    // -------------------------------------------------------------------------
    // Confirm navigation away from unsaved form changes
    // -------------------------------------------------------------------------
    function initUnsavedWarning() {
        var form = document.getElementById('cardiology-container-form');
        if (!form) { return; }

        var dirty = false;

        form.addEventListener('input', function () {
            dirty = true;
        });

        // The cancel button closes the tab; no navigation warning needed there.
        var cancelBtn = form.querySelector('.btn-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                dirty = false;
            });
        }

        window.addEventListener('beforeunload', function (e) {
            if (dirty) {
                e.preventDefault();
                // Chrome requires returnValue to be set.
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', function () {
            // Clear dirty flag on intentional submit so beforeunload does not fire.
            dirty = false;
        });
    }

    // -------------------------------------------------------------------------
    // Restore session before navigating to a child-card link
    // -------------------------------------------------------------------------
    function initCardLinks() {
        document.querySelectorAll('a.btn[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (typeof top !== 'undefined' && typeof top.restoreSession === 'function') {
                    top.restoreSession();
                }
            });
        });
    }

    // -------------------------------------------------------------------------
    // Open tool cards (Settings) in an OpenEMR dialog iframe
    // -------------------------------------------------------------------------
    function initToolCardModal() {
        document.querySelectorAll('.cardiology-btn-tool').forEach(function (link) {
            link.addEventListener('click', function (event) {
                var url = link.getAttribute('data-cardiology-tool-url');
                var title = link.getAttribute('data-cardiology-tool-title') || '';

                if (!url) {
                    return;
                }

                if (typeof top !== 'undefined' && typeof top.restoreSession === 'function') {
                    top.restoreSession();
                }

                var dlg = (typeof window.dlgopen === 'function') ? window.dlgopen
                    : (typeof parent !== 'undefined' && typeof parent.dlgopen === 'function') ? parent.dlgopen
                    : (typeof top !== 'undefined' && typeof top.dlgopen === 'function') ? top.dlgopen
                    : null;

                if (dlg) {
                    event.preventDefault();
                    dlg(
                        url,
                        'cardiologySettings',
                        1050,
                        720,
                        false,
                        title,
                        { onClosed: 'reload' }
                    );
                }
            });
        });
    }

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        autoDismissAlerts();
        initUnsavedWarning();
        initCardLinks();
        initToolCardModal();
    });

}(typeof jQuery !== 'undefined' ? jQuery : { fn: {} }));

