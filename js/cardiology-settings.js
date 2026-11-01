/**
 * cardiology-settings.js
 *
 * Front-end controller for Cardiology Settings screens.
 * Handles AJAX form submissions and action buttons (list management, preferences, cards).
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const body = document.querySelector('.cardiology-settings-page');
        if (!body) {
            return;
        }

        const apiEndpoint = body.getAttribute('data-settings-api') || '';
        const csrfToken   = body.getAttribute('data-settings-csrf') || '';

        // Handle standard submit on cardiology-settings-ajax-form
        document.querySelectorAll('.cardiology-settings-ajax-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                // Allow standard form submission if no JS API endpoint
                if (!apiEndpoint) return;

                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                const formData = new FormData(form);
                if (!formData.has('csrf_token_form')) {
                    formData.append('csrf_token_form', csrfToken);
                }

                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    },
                    body: formData
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (submitBtn) submitBtn.disabled = false;
                    if (result.ok && result.data && result.data.success) {
                        window.location.reload();
                    } else {
                        const msg = (result.data && result.data.message) ? result.data.message : 'Error processing request.';
                        alert(msg);
                    }
                })
                .catch(function (err) {
                    if (submitBtn) submitBtn.disabled = false;
                    alert('Network error: ' + err.message);
                });
            });
        });

        // Handle action buttons with data-settings-action
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-settings-action]');
            if (!btn) return;

            const action = btn.getAttribute('data-settings-action');
            if (!action || !apiEndpoint) return;

            const confirmMsg = btn.getAttribute('data-confirm');
            if (confirmMsg && !confirm(confirmMsg)) {
                return;
            }

            e.preventDefault();
            btn.disabled = true;

            const params = new URLSearchParams();
            params.append('action', action);
            params.append('csrf_token_form', csrfToken);

            if (btn.hasAttribute('data-list-id')) {
                params.append('list_id', btn.getAttribute('data-list-id'));
            }
            if (btn.hasAttribute('data-option-id')) {
                params.append('option_id', btn.getAttribute('data-option-id'));
            }
            if (btn.hasAttribute('data-active')) {
                params.append('active', btn.getAttribute('data-active'));
            }
            if (btn.hasAttribute('data-direction')) {
                params.append('direction', btn.getAttribute('data-direction'));
            }

            fetch(apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: params.toString()
            })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (result) {
                btn.disabled = false;
                if (result.ok && result.data && result.data.success) {
                    window.location.reload();
                } else {
                    const msg = (result.data && result.data.message) ? result.data.message : 'Action failed.';
                    alert(msg);
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                alert('Network error: ' + err.message);
            });
        });
    });
})();

