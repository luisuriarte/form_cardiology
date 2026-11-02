/**
 * cardiology-upload-dialog.js
 *
 * Upload dialog for cardiology documents with drag/drop, queue management,
 * per-file XHR uploads with progress, retry, cancel, and keyboard support.
 *
 * The server (documents_upload.php) supplies every limit and translation.
 * The dialog reports results to its opener with window.parent.postMessage()
 * using the documented cardiology-upload payload.
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0 and Bootstrap 4/5.
 */

(function () {
    'use strict';

    function init() {
        var config = window.CARD_UPLOAD_CONFIG || {};
        var i18n = window.CARD_UPLOAD_I18N || {};

        var dropZone = document.getElementById('cardiology-upload-dropzone');
        var fileInput = document.getElementById('cardiology-upload-input');
        var queueEl = document.getElementById('cardiology-upload-queue');
        var alertEl = document.getElementById('cardiology-upload-alert');
        var btnSubmit = document.getElementById('cardiology-upload-submit');
        var btnCancel = document.getElementById('cardiology-upload-cancel');
        var btnRetry = document.getElementById('cardiology-upload-retry');
        var btnCloseX = document.getElementById('cardiology-upload-close-x');
        var btnCloseBottom = document.getElementById('cardiology-upload-close');

        var queue = [];
        var isUploading = false;
        var cancelled = false;
        var activeXhr = null;
        var reported = false;

        function msg(key, fallback) {
            if (i18n[key]) {
                return i18n[key];
            }
            if (typeof window.xl === 'function') {
                return window.xl(fallback || key);
            }
            return fallback || key;
        }

        function showAlert(message, type) {
            if (!alertEl) {
                return;
            }
            alertEl.className = 'alert alert-' + type;
            alertEl.textContent = message;
            alertEl.classList.remove('d-none');
        }

        function hideAlert() {
            if (alertEl) {
                alertEl.classList.add('d-none');
                alertEl.textContent = '';
            }
        }

        function formatBytes(bytes) {
            if (!bytes || bytes <= 0) {
                return '0 B';
            }
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            return String(text === undefined || text === null ? '' : text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function extensionOf(name) {
            var idx = String(name || '').lastIndexOf('.');
            return idx === -1 ? '' : String(name).substring(idx + 1).toLowerCase();
        }

        function maxFiles() {
            var configured = parseInt(config.maxFiles, 10);
            if (!isNaN(configured) && configured > 0) {
                return configured;
            }
            // The server always supplies maxFiles. If it is missing (for example
            // because the dialog page failed to render its config), stay at the
            // safest possible value instead of guessing a larger batch.
            return 1;
        }

        function validateFile(file) {
            var ext = extensionOf(file.name);
            var allowed = (config.allowedExts || []).map(function (value) {
                return String(value).toLowerCase();
            });
            if (ext === '' || allowed.indexOf(ext) === -1) {
                throw new Error(msg('fileTypeNotAllowed', 'File type not allowed.'));
            }

            var limit = parseInt(config.maxUploadBytes, 10);
            if (!isNaN(limit) && limit > 0 && file.size > limit) {
                throw new Error(msg('fileTooLarge', 'The file exceeds the maximum upload limit.'));
            }

            return true;
        }

        function addFiles(fileList) {
            if (isUploading) {
                return;
            }
            cancelled = false;
            reported = false;
            hideAlert();

            var incoming = Array.prototype.slice.call(fileList || []);
            if (incoming.length === 0) {
                return;
            }

            if (config.multiple !== 1) {
                queue = [];
            }

            var limit = maxFiles();
            var skipped = 0;
            for (var i = 0; i < incoming.length; i++) {
                if (queue.length >= limit) {
                    skipped++;
                    continue;
                }
                try {
                    validateFile(incoming[i]);
                    queue.push({
                        id: 'u' + Date.now().toString() + Math.random().toString(36).substr(2, 9),
                        file: incoming[i],
                        name: incoming[i].name,
                        size: incoming[i].size,
                        status: 'pending',
                        progress: 0,
                        error: '',
                        document: null
                    });
                } catch (error) {
                    showAlert(error.message, 'warning');
                }
            }

            if (skipped > 0) {
                showAlert(msg('tooManyFiles', 'Too many files selected.'), 'warning');
            }

            renderQueue();
            updateButtons();
            if (queueEl) {
                queueEl.focus();
            }
        }

        function renderQueue() {
            if (!queueEl) {
                return;
            }
            queueEl.innerHTML = '';

            queue.forEach(function (item) {
                var row = document.createElement('div');
                row.className = 'cardiology-upload-item d-flex align-items-start justify-content-between p-2 mb-2 border rounded';
                row.setAttribute('data-id', item.id);

                var statusClass = 'text-muted';
                var statusText = msg('pending', 'Pending');
                if (item.status === 'uploading') {
                    statusClass = 'text-primary';
                    statusText = msg('uploading', 'Uploading');
                } else if (item.status === 'uploaded') {
                    statusClass = 'text-success';
                    statusText = msg('uploaded', 'Uploaded');
                } else if (item.status === 'failed') {
                    statusClass = 'text-danger';
                    statusText = msg('failed', 'Failed');
                }

                var actionHtml = '';
                if (item.status === 'pending' && !isUploading) {
                    actionHtml = '<button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2 cardiology-upload-remove" data-id="' + escapeHtml(item.id) + '" title="' + escapeHtml(msg('remove', 'Remove')) + '" aria-label="' + escapeHtml(msg('remove', 'Remove')) + '"><i class="fa fa-times" aria-hidden="true"></i></button>';
                } else if (item.status === 'failed' && !isUploading) {
                    actionHtml = '<button type="button" class="btn btn-sm btn-link text-primary p-0 ms-2 cardiology-upload-retry-item" data-id="' + escapeHtml(item.id) + '" title="' + escapeHtml(msg('retry', 'Retry')) + '" aria-label="' + escapeHtml(msg('retry', 'Retry')) + '"><i class="fa fa-refresh" aria-hidden="true"></i></button>';
                }

                var progressHtml = '';
                if (item.status === 'uploading') {
                    progressHtml = '<div class="progress cardiology-upload-progress mt-1" style="height:4px;">' +
                        '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + item.progress + '" style="width:' + item.progress + '%"></div>' +
                        '</div>';
                }

                row.innerHTML = '<div class="flex-grow-1 pr-2" style="min-width:0;">' +
                    '<div class="d-flex align-items-center justify-content-between">' +
                    '<div class="text-truncate mr-2"><strong>' + escapeHtml(item.name) + '</strong> <small class="text-muted">(' + formatBytes(item.size) + ')</small></div>' +
                    '<div class="d-flex align-items-center text-nowrap"><span class="small ' + statusClass + '">' + escapeHtml(statusText) +
                    (item.status === 'uploading' ? ' ' + item.progress + '%' : '') + '</span>' + actionHtml + '</div>' +
                    '</div>' + progressHtml +
                    (item.error ? '<div class="small text-danger mt-1">' + escapeHtml(item.error) + '</div>' : '') +
                    (item.status === 'uploaded' && item.document ? '<div class="small text-success mt-1">#' + escapeHtml(item.document.id) + '</div>' : '') +
                    '</div>';

                queueEl.appendChild(row);
            });

            Array.prototype.forEach.call(queueEl.querySelectorAll('.cardiology-upload-remove'), function (button) {
                button.addEventListener('click', function () {
                    if (!isUploading) {
                        removeItem(this.getAttribute('data-id'));
                    }
                });
            });

            Array.prototype.forEach.call(queueEl.querySelectorAll('.cardiology-upload-retry-item'), function (button) {
                button.addEventListener('click', function () {
                    if (!isUploading) {
                        retryItem(this.getAttribute('data-id'));
                        startUpload();
                    }
                });
            });
        }

        function removeItem(id) {
            for (var i = 0; i < queue.length; i++) {
                if (queue[i].id === id && queue[i].status === 'pending') {
                    queue.splice(i, 1);
                    break;
                }
            }
            renderQueue();
            updateButtons();
        }

        function retryItem(id) {
            for (var i = 0; i < queue.length; i++) {
                if (queue[i].id === id && queue[i].status === 'failed') {
                    queue[i].status = 'pending';
                    queue[i].progress = 0;
                    queue[i].error = '';
                    break;
                }
            }
        }

        function counts() {
            var result = { pending: 0, uploading: 0, uploaded: 0, failed: 0 };
            queue.forEach(function (item) {
                if (Object.prototype.hasOwnProperty.call(result, item.status)) {
                    result[item.status]++;
                }
            });
            return result;
        }

        function updateButtons() {
            var current = counts();

            if (btnSubmit) {
                btnSubmit.disabled = isUploading || current.pending === 0;
            }
            if (btnRetry) {
                btnRetry.classList.toggle('d-none', isUploading || current.failed === 0);
            }
            if (btnCloseBottom) {
                btnCloseBottom.classList.toggle('d-none', isUploading || queue.length === 0);
            }
            if (btnCancel) {
                btnCancel.textContent = isUploading ? msg('cancelUpload', 'Cancel upload') : msg('cancel', 'Cancel');
            }
        }

        function startUpload() {
            if (isUploading) {
                return;
            }
            if (counts().pending === 0) {
                return;
            }

            hideAlert();
            cancelled = false;
            isUploading = true;
            updateButtons();
            uploadNext();
        }

        function uploadNext() {
            if (cancelled) {
                isUploading = false;
                activeXhr = null;
                updateButtons();
                return;
            }

            var item = null;
            for (var i = 0; i < queue.length; i++) {
                if (queue[i].status === 'pending') {
                    item = queue[i];
                    break;
                }
            }

            if (!item) {
                isUploading = false;
                activeXhr = null;
                finishBatch();
                updateButtons();
                return;
            }

            item.status = 'uploading';
            item.progress = 0;
            item.error = '';
            renderQueue();
            updateButtons();

            var formData = new FormData();
            formData.append('csrf_token_form', config.csrfToken || '');
            formData.append('pid', config.pid || '');
            if (config.categoryId) {
                formData.append('category_id', config.categoryId);
            }
            if (config.cardCode) {
                formData.append('card_code', config.cardCode);
            }
            if (config.studyId) {
                formData.append('study_id', config.studyId);
            }
            formData.append('multiple', config.multiple === 1 ? '1' : '0');
            formData.append('files[]', item.file);

            var xhr = new XMLHttpRequest();
            activeXhr = xhr;

            if (xhr.upload) {
                xhr.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable) {
                        item.progress = Math.max(0, Math.min(100, Math.round((event.loaded / event.total) * 100)));
                        renderQueue();
                    }
                });
            }

            xhr.addEventListener('load', function () {
                activeXhr = null;
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && (response.status === 'ok' || response.status === 'partial')) {
                        if (response.documents && response.documents.length > 0) {
                            item.status = 'uploaded';
                            item.progress = 100;
                            item.document = response.documents[0];
                        } else {
                            item.status = 'failed';
                            item.error = (response.errors && response.errors[0] && response.errors[0].message)
                                ? response.errors[0].message
                                : msg('uploadFailed', 'Upload failed.');
                        }
                    } else {
                        item.status = 'failed';
                        item.error = (response.errors && response.errors[0] && response.errors[0].message)
                            ? response.errors[0].message
                            : msg('networkError', 'Network error while uploading.');
                    }
                } catch (error) {
                    item.status = 'failed';
                    item.error = msg('networkError', 'Network error while uploading.');
                }
                renderQueue();
                updateButtons();
                uploadNext();
            });

            xhr.addEventListener('error', function () {
                activeXhr = null;
                if (cancelled) {
                    return;
                }
                item.status = 'failed';
                item.error = msg('networkError', 'Network error while uploading.');
                renderQueue();
                updateButtons();
                uploadNext();
            });

            xhr.addEventListener('abort', function () {
                activeXhr = null;
                if (cancelled) {
                    item.status = 'pending';
                    item.progress = 0;
                    renderQueue();
                    return;
                }
                item.status = 'failed';
                item.error = msg('uploadCancelled', 'Upload cancelled.');
                renderQueue();
                updateButtons();
                uploadNext();
            });

            try {
                xhr.open('POST', config.uploadUrl || window.location.href, true);
                xhr.send(formData);
            } catch (error) {
                activeXhr = null;
                item.status = 'failed';
                item.error = msg('networkError', 'Network error while uploading.');
                renderQueue();
                updateButtons();
                uploadNext();
            }
        }

        function buildPayload() {
            var documents = [];
            var errors = [];

            queue.forEach(function (item) {
                if (item.status === 'uploaded' && item.document) {
                    documents.push({
                        id: parseInt(item.document.id, 10) || 0,
                        name: String(item.document.name || item.name),
                        category_id: parseInt(item.document.category_id, 10) || 0,
                        linked: item.document.linked === true || item.document.linked === 1 || item.document.linked === '1'
                    });
                } else if (item.status === 'failed') {
                    errors.push({
                        name: item.name,
                        message: item.error || msg('uploadFailed', 'Upload failed.')
                    });
                }
            });

            var status = 'error';
            if (documents.length > 0 && errors.length === 0) {
                status = 'ok';
            } else if (documents.length > 0 && errors.length > 0) {
                status = 'partial';
            }

            return {
                source: 'cardiology-upload',
                status: status,
                documents: documents,
                errors: errors
            };
        }

        function postToOpener(payload) {
            try {
                var target = (window.parent && window.parent !== window) ? window.parent : window.opener;
                if (target && typeof target.postMessage === 'function') {
                    target.postMessage(payload, window.location.origin);
                    return true;
                }
            } catch (error) {
                // Cross-origin or missing opener: keep the dialog open with its summary.
            }
            return false;
        }

        function finishBatch() {
            var current = counts();

            if (current.uploaded > 0 && current.failed === 0) {
                showAlert(msg('uploadComplete', 'Upload completed successfully.'), 'success');
            } else if (current.uploaded > 0 && current.failed > 0) {
                showAlert(msg('uploadPartial', 'Some files were uploaded. Review the failed files below.'), 'warning');
            } else if (current.failed > 0) {
                showAlert(msg('uploadFailed', 'Upload failed.'), 'danger');
            }

            var payload = buildPayload();

            // When everything succeeds, automatically report to the opener and
            // allow the opener to close the dialog via postMessage. When some
            // files fail, keep the dialog open so the user can retry failed files.
            if (!reported && current.failed === 0 && payload.documents.length > 0) {
                reported = true;
                postToOpener(payload);
            }

            if (btnRetry) {
                btnRetry.classList.toggle('d-none', current.failed === 0);
            }
            if (btnCloseBottom) {
                btnCloseBottom.classList.remove('d-none');
            }
        }

        function closeDialog() {
            var payload = buildPayload();
            var hasResults = payload.documents.length > 0 || payload.errors.length > 0;
            var posted = false;

            if (hasResults && !reported) {
                reported = true;
                posted = postToOpener(payload);
            }

            var selfClose = function () {
                try {
                    if (window.parent && window.parent !== window && typeof window.parent.dlgclose === 'function') {
                        window.parent.dlgclose();
                    } else if (typeof window.dlgclose === 'function') {
                        window.dlgclose();
                    }
                } catch (error) {
                    // The opener hides the dialog after receiving the message.
                }
            };

            // Give the opener a chance to receive the message before the frame
            // is removed. Without a receiver the dialog still closes itself.
            if (posted) {
                window.setTimeout(selfClose, 150);
            } else {
                selfClose();
            }
        }

        function cancelUpload() {
            cancelled = true;
            if (activeXhr) {
                try {
                    activeXhr.abort();
                } catch (error) {
                    // ignore
                }
            }
            isUploading = false;
            activeXhr = null;
            showAlert(msg('uploadCancelled', 'Upload cancelled.'), 'info');
            renderQueue();
            updateButtons();
        }

        function retryFailed() {
            if (isUploading) {
                return;
            }
            var hasFailed = false;
            queue.forEach(function (item) {
                if (item.status === 'failed') {
                    item.status = 'pending';
                    item.progress = 0;
                    item.error = '';
                    hasFailed = true;
                }
            });
            if (hasFailed) {
                reported = false;
                hideAlert();
                renderQueue();
                updateButtons();
                startUpload();
            }
        }

        if (dropZone) {
            dropZone.addEventListener('click', function () {
                if (!isUploading && fileInput) {
                    fileInput.click();
                }
            });

            dropZone.addEventListener('keydown', function (event) {
                if (isUploading) {
                    return;
                }
                if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                    event.preventDefault();
                    if (fileInput) {
                        fileInput.click();
                    }
                }
            });

            dropZone.addEventListener('dragover', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!isUploading) {
                    dropZone.classList.add('dragover');
                }
            });

            dropZone.addEventListener('dragleave', function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropZone.classList.remove('dragover');
                if (!isUploading) {
                    addFiles(event.dataTransfer ? event.dataTransfer.files : []);
                }
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (!isUploading) {
                    addFiles(fileInput.files);
                }
                fileInput.value = '';
            });
        }

        if (btnSubmit) {
            btnSubmit.addEventListener('click', startUpload);
        }

        if (btnCancel) {
            btnCancel.addEventListener('click', function () {
                if (isUploading) {
                    cancelUpload();
                } else {
                    closeDialog();
                }
            });
        }

        if (btnRetry) {
            btnRetry.addEventListener('click', retryFailed);
        }

        if (btnCloseX) {
            btnCloseX.addEventListener('click', function () {
                if (isUploading) {
                    cancelUpload();
                } else {
                    closeDialog();
                }
            });
        }

        if (btnCloseBottom) {
            btnCloseBottom.addEventListener('click', closeDialog);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            if (isUploading) {
                cancelUpload();
            } else {
                closeDialog();
            }
        });

        updateButtons();
        renderQueue();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
