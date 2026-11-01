/**
 * cardiology-documents.js
 *
 * Front-end controller for cardiology study document attachments.
 * Handles file uploading, attaching existing patient documents, and unlinking.
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0 and Bootstrap 4 / 5.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
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
        const retrieveBase = container.getAttribute('data-retrieve-base') || '';
        const csrfToken    = container.getAttribute('data-csrf-token') || '';

        const alertBox       = document.getElementById('cardiology-docs-alert');
        const countBadge     = document.getElementById('cardiology-docs-count-badge');
        const docsTbody      = document.getElementById('cardiology-docs-tbody');

        // Toggle buttons and panels
        const btnToggleUpload = document.getElementById('cardiology-btn-toggle-upload');
        const uploadPanel     = document.getElementById('cardiology-upload-panel');
        const uploadForm      = document.getElementById('cardiology-upload-form');
        const fileInput       = document.getElementById('cardiology-file-input');
        const btnUploadSubmit = document.getElementById('cardiology-upload-submit-btn');
        const btnUploadCancel = document.getElementById('cardiology-upload-cancel-btn');
        const uploadProgress  = document.getElementById('cardiology-upload-progress');

        const btnOpenPicker   = document.getElementById('cardiology-btn-open-picker');
        const pickerPanel     = document.getElementById('cardiology-picker-panel');
        const pickerLoading   = document.getElementById('cardiology-picker-loading');
        const pickerSelect    = document.getElementById('cardiology-candidate-select');
        const btnConfirmLink  = document.getElementById('cardiology-btn-confirm-link');
        const btnPickerCancel = document.getElementById('cardiology-picker-cancel-btn');

        function showAlert(message, type) {
            if (!alertBox) return;
            alertBox.className = 'alert alert-' + type + ' alert-dismissible fade show';
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
            // Auto hide after 8 seconds for success
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
            if (isNaN(b) || b <= 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            const power = Math.min(Math.floor(Math.log(b) / Math.log(1024)), units.length - 1);
            return (b / Math.pow(1024, power)).toFixed(1) + ' ' + units[power];
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function renderAttachments(attachments) {
            if (!docsTbody) return;
            docsTbody.innerHTML = '';

            if (!attachments || attachments.length === 0) {
                const tr = document.createElement('tr');
                tr.id = 'cardiology-docs-empty-row';
                tr.innerHTML = '<td colspan="6" class="text-center text-muted fst-italic py-3">No documents attached to this study.</td>';
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

                const openUrl = retrieveBase + encodeURIComponent(docId);

                const tr = document.createElement('tr');
                tr.setAttribute('data-link-id', linkId);
                tr.setAttribute('data-doc-id', docId);

                let actionsHtml = '<a href="' + escapeHtml(openUrl) + '" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary me-1" title="Open document"><i class="fa fa-external-link" aria-hidden="true"></i> Open</a>';
                if (!isFinal && canWrite) {
                    actionsHtml += '<button type="button" class="btn btn-xs btn-outline-danger cardiology-btn-unlink" data-link-id="' + escapeHtml(String(linkId)) + '" title="Unlink this attachment"><i class="fa fa-unlink" aria-hidden="true"></i> Unlink</button>';
                }

                let postFinalBadge = '';
                if (isPostFinal) {
                    postFinalBadge = ' <span class="badge bg-info text-dark ms-1" title="This document was attached after the study was finalized."><i class="fa fa-clock-o me-1"></i> Added after finalization</span>';
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

        // Toggle Upload Panel
        if (btnToggleUpload && uploadPanel) {
            btnToggleUpload.addEventListener('click', function () {
                hideAlert();
                uploadPanel.classList.toggle('d-none');
                if (pickerPanel) pickerPanel.classList.add('d-none');
            });
        }

        if (btnUploadCancel && uploadPanel) {
            btnUploadCancel.addEventListener('click', function () {
                uploadPanel.classList.add('d-none');
                if (fileInput) fileInput.value = '';
            });
        }

        // Handle File Upload Form Submission
        if (uploadForm) {
            uploadForm.addEventListener('submit', function (e) {
                e.preventDefault();
                hideAlert();

                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    showAlert('Please select at least one file to upload.', 'warning');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('card_code', cardCode);
                formData.append('study_id', studyId);
                formData.append('csrf_token_form', csrfToken);

                for (let i = 0; i < fileInput.files.length; i++) {
                    formData.append('doc_files[]', fileInput.files[i]);
                }

                // UI loading state
                if (btnUploadSubmit) btnUploadSubmit.disabled = true;
                if (btnUploadCancel) btnUploadCancel.disabled = true;
                if (uploadProgress) uploadProgress.classList.remove('d-none');

                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': csrfToken
                    },
                    body: formData
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function (resObj) {
                    if (btnUploadSubmit) btnUploadSubmit.disabled = false;
                    if (btnUploadCancel) btnUploadCancel.disabled = false;
                    if (uploadProgress) uploadProgress.classList.add('d-none');

                    if (resObj.ok && resObj.data && resObj.data.success) {
                        showAlert(resObj.data.message || 'Document uploaded successfully.', 'success');
                        uploadForm.reset();
                        uploadPanel.classList.add('d-none');
                        if (resObj.data.documents) {
                            renderAttachments(resObj.data.documents);
                        }
                    } else {
                        const errMsg = (resObj.data && resObj.data.message) ? resObj.data.message : 'Upload failed.';
                        showAlert(errMsg, 'danger');
                    }
                })
                .catch(function (err) {
                    if (btnUploadSubmit) btnUploadSubmit.disabled = false;
                    if (btnUploadCancel) btnUploadCancel.disabled = false;
                    if (uploadProgress) uploadProgress.classList.add('d-none');
                    showAlert('Network error while uploading: ' + err.message, 'danger');
                });
            });
        }

        // Toggle Candidate Documents Picker Panel
        if (btnOpenPicker && pickerPanel) {
            btnOpenPicker.addEventListener('click', function () {
                hideAlert();
                pickerPanel.classList.toggle('d-none');
                if (uploadPanel) uploadPanel.classList.add('d-none');

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

        function loadCandidates() {
            if (!pickerSelect) return;
            pickerSelect.innerHTML = '<option value="">-- Select an existing patient document --</option>';
            if (btnConfirmLink) btnConfirmLink.disabled = true;
            if (pickerLoading) pickerLoading.classList.remove('d-none');

            const params = new URLSearchParams();
            params.append('action', 'list_candidates');
            params.append('card_code', cardCode);
            params.append('study_id', studyId);
            params.append('csrf_token_form', csrfToken);

            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-Token': csrfToken
                },
                body: params.toString()
            })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (resObj) {
                if (pickerLoading) pickerLoading.classList.add('d-none');
                if (resObj.ok && resObj.data && resObj.data.candidates) {
                    const list = resObj.data.candidates;
                    if (list.length === 0) {
                        pickerSelect.innerHTML = '<option value="">No unlinked patient documents found.</option>';
                        return;
                    }
                    pickerSelect.innerHTML = '<option value="">-- Select a document (' + list.length + ' available) --</option>';
                    list.forEach(function (cand) {
                        const opt = document.createElement('option');
                        opt.value = cand.id;
                        const catLabel = cand.category_name ? ' [' + cand.category_name + ']' : '';
                        const dateLabel = cand.date ? ' (' + cand.date + ')' : '';
                        opt.textContent = '#' + cand.id + ' - ' + (cand.name || 'Document') + catLabel + dateLabel;
                        pickerSelect.appendChild(opt);
                    });
                } else {
                    const err = (resObj.data && resObj.data.message) ? resObj.data.message : 'Error loading candidate documents.';
                    showAlert(err, 'danger');
                }
            })
            .catch(function (err) {
                if (pickerLoading) pickerLoading.classList.add('d-none');
                showAlert('Error loading candidate documents: ' + err.message, 'danger');
            });
        }

        if (pickerSelect) {
            pickerSelect.addEventListener('change', function () {
                const val = pickerSelect.value;
                if (btnConfirmLink) {
                    btnConfirmLink.disabled = !val || parseInt(val, 10) <= 0;
                }
            });
        }

        // Attach Existing Document
        if (btnConfirmLink) {
            btnConfirmLink.addEventListener('click', function () {
                const docId = pickerSelect ? pickerSelect.value : '';
                if (!docId || parseInt(docId, 10) <= 0) return;

                hideAlert();
                btnConfirmLink.disabled = true;

                const params = new URLSearchParams();
                params.append('action', 'attach_existing');
                params.append('card_code', cardCode);
                params.append('study_id', studyId);
                params.append('document_id', docId);
                params.append('csrf_token_form', csrfToken);

                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-Token': csrfToken
                    },
                    body: params.toString()
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    });
                })
                .then(function (resObj) {
                    btnConfirmLink.disabled = false;
                    if (resObj.ok && resObj.data && resObj.data.success) {
                        showAlert(resObj.data.message || 'Document linked successfully.', 'success');
                        pickerPanel.classList.add('d-none');
                        if (resObj.data.documents) {
                            renderAttachments(resObj.data.documents);
                        }
                    } else {
                        const errMsg = (resObj.data && resObj.data.message) ? resObj.data.message : 'Failed to attach existing document.';
                        showAlert(errMsg, 'danger');
                    }
                })
                .catch(function (err) {
                    btnConfirmLink.disabled = false;
                    showAlert('Network error while linking document: ' + err.message, 'danger');
                });
            });
        }

        // Unlink Attachment (delegated click on docsTbody)
        if (docsTbody) {
            docsTbody.addEventListener('click', function (e) {
                const btn = e.target.closest('.cardiology-btn-unlink');
                if (!btn) return;

                const linkId = btn.getAttribute('data-link-id');
                if (!linkId) return;

                if (!confirm('Are you sure you want to unlink this document from the study? The file itself will remain safely in the patient documents.')) {
                    return;
                }

                hideAlert();
                btn.disabled = true;

                const params = new URLSearchParams();
                params.append('action', 'unlink');
                params.append('card_code', cardCode);
                params.append('study_id', studyId);
                params.append('link_id', linkId);
                params.append('csrf_token_form', csrfToken);

                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-Token': csrfToken
                    },
                    body: params.toString()
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    });
                })
                .then(function (resObj) {
                    if (resObj.ok && resObj.data && resObj.data.success) {
                        showAlert(resObj.data.message || 'Attachment unlinked successfully.', 'success');
                        if (resObj.data.documents) {
                            renderAttachments(resObj.data.documents);
                        }
                    } else {
                        btn.disabled = false;
                        const errMsg = (resObj.data && resObj.data.message) ? resObj.data.message : 'Failed to unlink document.';
                        showAlert(errMsg, 'danger');
                    }
                })
                .catch(function (err) {
                    btn.disabled = false;
                    showAlert('Network error while unlinking document: ' + err.message, 'danger');
                });
            });
        }
    });
})();

