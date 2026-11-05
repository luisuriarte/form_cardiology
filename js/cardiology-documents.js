/**
 * cardiology-documents.js
 *
 * Front-end controller for cardiology study document attachments.
 * Opens the reusable upload dialog, attaches existing patient documents,
 * unlinks attachments, and refreshes only the attachment table.
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0 and Bootstrap 4 / 5.
 */

(function () {
    'use strict';

    function init() {
        const container = document.getElementById('cardiology-documents-container');
        if (!container) {
            return;
        }

        const cardCode     = container.getAttribute('data-card-code') || '';
        const studyId      = container.getAttribute('data-study-id') || '';
        const pid          = container.getAttribute('data-pid') || '';
        const isFinal      = container.getAttribute('data-is-final') === '1';
        const finalizedAt  = container.getAttribute('data-finalized-at') || '';
        const canWrite     = container.getAttribute('data-can-write') === '1';
        const apiUrl       = container.getAttribute('data-api-url') || '';
        const uploadUrl    = container.getAttribute('data-upload-url') || '';
        const retrieveBase = container.getAttribute('data-retrieve-base') || '';
    const retrieveSuffix = container.getAttribute('data-retrieve-suffix') || '';
        const csrfToken    = container.getAttribute('data-csrf-token') || '';

        const alertBox   = document.getElementById('cardiology-docs-alert');
        const countBadge = document.getElementById('cardiology-docs-count-badge');
        const docsTbody  = document.getElementById('cardiology-docs-tbody');
        const btnOpenUpload = document.getElementById('cardiology-btn-open-upload');

        const i18n = loadI18n();

        function loadI18n() {
            const node = document.getElementById('cardiology-docs-i18n');
            if (!node) {
                return {};
            }
            try {
                return JSON.parse(node.textContent);
            } catch (e) {
                return {};
            }
        }

        function t(key, fallback) {
            return i18n[key] || fallback || key;
        }

        function fmt(template, values) {
            let index = 0;
            const list = values || [];
            return String(template || '').replace(/%[ds]/g, function () {
                const value = list[index++];
                return value === undefined || value === null ? '' : String(value);
            });
        }

        function showAlert(message, type) {
            if (!alertBox) {
                return;
            }
            alertBox.className = 'alert alert-' + type + ' alert-dismissible fade show';
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
            if (type === 'success') {
                setTimeout(function () {
                    alertBox.classList.add('d-none');
                }, 8000);
            }
        }

        function hideAlert() {
            if (alertBox) {
                alertBox.classList.add('d-none');
            }
        }

        function updateCountBadge(count) {
            if (countBadge) {
                countBadge.textContent = String(count);
            }
        }

        function formatBytes(bytes) {
            const b = parseInt(bytes, 10);
            if (isNaN(b) || b <= 0) {
                return '0 B';
            }
            const units = ['B', 'KB', 'MB', 'GB'];
            const power = Math.min(Math.floor(Math.log(b) / Math.log(1024)), units.length - 1);
            return (b / Math.pow(1024, power)).toFixed(1) + ' ' + units[power];
        }

        function escapeHtml(value) {
            return String(value === undefined || value === null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderAttachments(attachments) {
            if (!docsTbody) {
                return;
            }
            docsTbody.innerHTML = '';

            if (!attachments || attachments.length === 0) {
                const tr = document.createElement('tr');
                tr.id = 'cardiology-docs-empty-row';
                tr.innerHTML = '<td colspan="6" class="text-center text-muted fst-italic py-3">' +
                    escapeHtml(t('empty', 'No documents attached to this study.')) + '</td>';
                docsTbody.appendChild(tr);
                updateCountBadge(0);
                return;
            }

            updateCountBadge(attachments.length);

            attachments.forEach(function (att) {
                const docId = att.document_id;
                const linkId = att.link_id;
                const docName = att.doc_name || '';
                const catName = att.category_name || '';
                const docDate = att.doc_date || att.linked_at || '';
                const uploadedBy = att.created_by || '—';
                const sizeFormatted = formatBytes(att.size || 0);
                const linkedAt = att.linked_at || '';

                let isPostFinal = false;
                if (isFinal && finalizedAt && linkedAt && linkedAt > finalizedAt) {
                    isPostFinal = true;
                }

                const openUrl = retrieveBase + encodeURIComponent(docId) + retrieveSuffix;

                const tr = document.createElement('tr');
                tr.setAttribute('data-link-id', linkId);
                tr.setAttribute('data-doc-id', docId);

                let actionsHtml = '<a href="' + escapeHtml(openUrl) + '" target="_blank" rel="noopener" ' +
                    'class="btn btn-xs btn-outline-primary me-1" title="' + escapeHtml(t('openTitle', 'Open document')) + '">' +
                    '<i class="fa fa-external-link" aria-hidden="true"></i> ' + escapeHtml(t('open', 'Open')) + '</a>';

                if (!isFinal && canWrite) {
                    actionsHtml += '<button type="button" class="btn btn-xs btn-outline-danger cardiology-btn-unlink" ' +
                        'data-link-id="' + escapeHtml(String(linkId)) + '" title="' + escapeHtml(t('unlinkTitle', 'Unlink this attachment')) + '">' +
                        '<i class="fa fa-unlink" aria-hidden="true"></i> ' + escapeHtml(t('unlink', 'Unlink')) + '</button>';
                }

                let postFinalBadge = '';
                if (isPostFinal) {
                    postFinalBadge = ' <span class="badge bg-info text-dark ms-1" title="' +
                        escapeHtml(t('postFinalTitle', 'This document was attached after the study was finalized.')) + '">' +
                        '<i class="fa fa-clock-o me-1" aria-hidden="true"></i> ' +
                        escapeHtml(t('postFinal', 'Added after finalization')) + '</span>';
                }

                tr.innerHTML =
                    '<td><i class="fa fa-file-text-o me-1 text-muted" aria-hidden="true"></i><strong>' + escapeHtml(docName) + '</strong>' + postFinalBadge + '</td>' +
                    '<td><span class="badge bg-light text-dark border">' + escapeHtml(catName) + '</span></td>' +
                    '<td class="small text-muted">' + escapeHtml(docDate) + '</td>' +
                    '<td class="small">' + escapeHtml(uploadedBy) + '</td>' +
                    '<td class="small text-muted">' + escapeHtml(sizeFormatted) + '</td>' +
                    '<td class="text-end">' + actionsHtml + '</td>';

                docsTbody.appendChild(tr);
            });
        }

        function postApi(params) {
            const body = new URLSearchParams();
            body.append('csrf_token_form', csrfToken);
            Object.keys(params).forEach(function (key) {
                body.append(key, params[key]);
            });

            return fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-Token': csrfToken
                },
                body: body.toString()
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            });
        }

        function refreshAttachments() {
            return postApi({
                action: 'list',
                card_code: cardCode,
                study_id: studyId
            }).then(function (result) {
                if (result.ok && result.data && result.data.success) {
                    renderAttachments(result.data.documents || []);
                } else {
                    const message = (result.data && result.data.message)
                        ? result.data.message
                        : fmt(t('networkError', 'Network error: %s'), ['']);
                    showAlert(message, 'danger');
                }
            }).catch(function (error) {
                showAlert(fmt(t('networkError', 'Network error: %s'), [error.message]), 'danger');
            });
        }

        function handleUploadDone(payload) {
            if (!payload) {
                return;
            }

            const documents = payload.documents || [];
            const errors = payload.errors || [];

            if (Array.isArray(payload.attachments)) {
                renderAttachments(payload.attachments);
            } else if (documents.length > 0) {
                refreshAttachments();
            }

            if (documents.length > 0 && errors.length === 0) {
                showAlert(fmt(t('uploadSuccess', '%d document(s) uploaded successfully.'), [documents.length]), 'success');
            } else if (documents.length > 0 && errors.length > 0) {
                const detail = errors.map(function (item) {
                    return (item.name ? item.name + ': ' : '') + item.message;
                }).join(' ');
                showAlert(fmt(t('uploadPartial', '%d uploaded, %d failed.'), [documents.length, errors.length]) + ' ' + detail, 'warning');
            } else {
                const detail = errors.length > 0 ? ' ' + errors.map(function (item) {
                    return (item.name ? item.name + ': ' : '') + item.message;
                }).join(' ') : '';
                showAlert(t('uploadFailed', 'No documents were uploaded.') + detail, 'danger');
            }
        }

        if (btnOpenUpload) {
            btnOpenUpload.addEventListener('click', function () {
                hideAlert();
                if (!window.CardUpload || typeof window.CardUpload.open !== 'function') {
                    showAlert(fmt(t('networkError', 'Network error: %s'), ['CardUpload is not available.']), 'danger');
                    return;
                }
                window.CardUpload.open({
                    pid: pid,
                    cardCode: cardCode,
                    studyId: studyId,
                    multiple: 1,
                    keepOpen: true,
                    url: uploadUrl,
                    onDone: handleUploadDone
                });
            });
        }

        // Toggle Candidate Documents Picker Panel
        const btnOpenPicker = document.getElementById('cardiology-btn-open-picker');
        const pickerPanel = document.getElementById('cardiology-picker-panel');
        const pickerLoading = document.getElementById('cardiology-picker-loading');
        const pickerSelect = document.getElementById('cardiology-candidate-select');
        const btnConfirmLink = document.getElementById('cardiology-btn-confirm-link');
        const btnPickerCancel = document.getElementById('cardiology-picker-cancel-btn');

        function loadCandidates() {
            if (!pickerSelect) {
                return;
            }
            pickerSelect.innerHTML = '<option value="">' + escapeHtml(t('selectCandidate', '-- Select an existing patient document --')) + '</option>';
            if (btnConfirmLink) {
                btnConfirmLink.disabled = true;
            }
            if (pickerLoading) {
                pickerLoading.classList.remove('d-none');
            }

            postApi({
                action: 'list_candidates',
                card_code: cardCode,
                study_id: studyId
            }).then(function (result) {
                if (pickerLoading) {
                    pickerLoading.classList.add('d-none');
                }
                if (result.ok && result.data && result.data.success) {
                    const list = result.data.candidates || [];
                    if (list.length === 0) {
                        pickerSelect.innerHTML = '<option value="">' + escapeHtml(t('noCandidates', 'No unlinked patient documents found.')) + '</option>';
                        return;
                    }
                    pickerSelect.innerHTML = '<option value="">' +
                        escapeHtml(fmt(t('candidatesAvailable', '-- Select a document (%d available) --'), [list.length])) +
                        '</option>';
                    list.forEach(function (candidate) {
                        const option = document.createElement('option');
                        option.value = candidate.id;
                        const categoryLabel = candidate.category_name ? ' [' + candidate.category_name + ']' : '';
                        const dateLabel = candidate.date ? ' (' + candidate.date + ')' : '';
                        option.textContent = '#' + candidate.id + ' - ' + (candidate.name || 'Document') + categoryLabel + dateLabel;
                        pickerSelect.appendChild(option);
                    });
                } else {
                    const message = (result.data && result.data.message)
                        ? result.data.message
                        : t('errorLoading', 'Error loading candidate documents.');
                    showAlert(message, 'danger');
                }
            }).catch(function (error) {
                if (pickerLoading) {
                    pickerLoading.classList.add('d-none');
                }
                showAlert(fmt(t('networkError', 'Network error: %s'), [error.message]), 'danger');
            });
        }

        if (btnOpenPicker && pickerPanel) {
            btnOpenPicker.addEventListener('click', function () {
                hideAlert();
                pickerPanel.classList.toggle('d-none');
                if (!pickerPanel.classList.contains('d-none')) {
                    loadCandidates();
                }
            });
        }

        if (btnPickerCancel && pickerPanel) {
            btnPickerCancel.addEventListener('click', function () {
                pickerPanel.classList.add('d-none');
            });
        }

        if (pickerSelect) {
            pickerSelect.addEventListener('change', function () {
                if (btnConfirmLink) {
                    btnConfirmLink.disabled = !pickerSelect.value || parseInt(pickerSelect.value, 10) <= 0;
                }
            });
        }

        // Attach Existing Document
        if (btnConfirmLink) {
            btnConfirmLink.addEventListener('click', function () {
                const documentId = pickerSelect ? pickerSelect.value : '';
                if (!documentId || parseInt(documentId, 10) <= 0) {
                    return;
                }

                hideAlert();
                btnConfirmLink.disabled = true;

                postApi({
                    action: 'attach_existing',
                    card_code: cardCode,
                    study_id: studyId,
                    document_id: documentId
                }).then(function (result) {
                    btnConfirmLink.disabled = false;
                    if (result.ok && result.data && result.data.success) {
                        showAlert(result.data.message || t('linkSuccess', 'Existing document linked successfully.'), 'success');
                        if (pickerPanel) {
                            pickerPanel.classList.add('d-none');
                        }
                        if (result.data.documents) {
                            renderAttachments(result.data.documents);
                        } else {
                            refreshAttachments();
                        }
                    } else {
                        const message = (result.data && result.data.message)
                            ? result.data.message
                            : t('linkError', 'Failed to attach existing document.');
                        showAlert(message, 'danger');
                    }
                }).catch(function (error) {
                    btnConfirmLink.disabled = false;
                    showAlert(fmt(t('networkError', 'Network error: %s'), [error.message]), 'danger');
                });
            });
        }

        // Unlink Attachment (delegated click on docsTbody)
        if (docsTbody) {
            docsTbody.addEventListener('click', function (event) {
                const button = event.target.closest('.cardiology-btn-unlink');
                if (!button) {
                    return;
                }

                const linkId = button.getAttribute('data-link-id');
                if (!linkId) {
                    return;
                }

                if (!window.confirm(t('unlinkConfirm', 'Are you sure you want to unlink this document from the study? The file itself will remain safely in the patient documents.'))) {
                    return;
                }

                hideAlert();
                button.disabled = true;

                postApi({
                    action: 'unlink',
                    card_code: cardCode,
                    study_id: studyId,
                    link_id: linkId
                }).then(function (result) {
                    if (result.ok && result.data && result.data.success) {
                        showAlert(result.data.message || t('unlinkSuccess', 'Attachment unlinked successfully.'), 'success');
                        if (result.data.documents) {
                            renderAttachments(result.data.documents);
                        } else {
                            refreshAttachments();
                        }
                    } else {
                        button.disabled = false;
                        const message = (result.data && result.data.message)
                            ? result.data.message
                            : t('unlinkError', 'Failed to unlink document.');
                        showAlert(message, 'danger');
                    }
                }).catch(function (error) {
                    button.disabled = false;
                    showAlert(fmt(t('networkError', 'Network error: %s'), [error.message]), 'danger');
                });
            });
        }

        // uploadUrl is supplied by PHP so multi-install webroots keep working.
        container.setAttribute('data-upload-endpoint', uploadUrl);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
