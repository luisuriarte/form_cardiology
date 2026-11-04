/**
 * cardiology-upload.js
 *
 * Public API for opening the cardiology document upload dialog.
 *
 * CardUpload.open({
 *   pid, categoryId, cardCode, studyId, multiple, onDone, onClose, title
 * });
 *
 * Uses dlgopen() when available and falls back to a Bootstrap 4/5 iframe
 * modal. The dialog returns its result with window.parent.postMessage().
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0 and Bootstrap 4/5.
 */

(function (window) {
    'use strict';

    var CardUpload = window.CardUpload || {};
    var active = null;

    function formRoot() {
        if (typeof window.webroot_url === 'string' && window.webroot_url !== '') {
            return window.webroot_url;
        }
        try {
            if (window.top && typeof window.top.webroot_url === 'string' && window.top.webroot_url !== '') {
                return window.top.webroot_url;
            }
        } catch (e) {
            // cross-origin top
        }
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].getAttribute('src') || '';
            var marker = '/interface/forms/form_cardiology/js/cardiology-upload.js';
            var idx = src.indexOf(marker);
            if (idx >= 0) {
                return src.substring(0, idx);
            }
        }
        return '';
    }

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function invokeCallback(callback, payload) {
        if (!callback) {
            return;
        }
        try {
            if (typeof callback === 'function') {
                callback(payload);
            } else if (typeof callback === 'string') {
                if (typeof window[callback] === 'function') {
                    window[callback](payload);
                } else if (window.top && typeof window.top[callback] === 'function') {
                    window.top[callback](payload);
                }
            }
        } catch (e) {
            // Callbacks must not break dialog cleanup.
        }
    }

    function removeListener(ref) {
        if (!ref || !ref.listener) {
            return;
        }
        ref.hosts.forEach(function (host) {
            try {
                host.removeEventListener('message', ref.listener);
            } catch (e) {
                // ignore
            }
        });
        ref.listenerAttached = false;
    }

    function frameFor(ref) {
        for (var i = 0; i < ref.hosts.length; i++) {
            try {
                var doc = ref.hosts[i].document;
                var frame = doc.querySelector('iframe[name="' + ref.winname + '"]');
                if (frame && frame.contentWindow) {
                    return frame.contentWindow;
                }
            } catch (e) {
                // ignore
            }
        }
        return null;
    }

    function hideDialog(ref) {
        if (!ref || ref.hidden) {
            return;
        }
        ref.hidden = true;

        ref.hosts.forEach(function (host) {
            var node = null;
            try {
                node = host.document.getElementById(ref.winname);
            } catch (e) {
                return;
            }
            if (!node) {
                // dlgopen() mounts the modal on window.top; the form iframe
                // hosting this call must not stop the search.
                return;
            }
            try {
                if (host.jQuery && typeof host.jQuery.fn.modal === 'function') {
                    host.jQuery(node).modal('hide');
                    return;
                }
            } catch (e) {
                // ignore
            }
            try {
                if (host.bootstrap && host.bootstrap.Modal) {
                    var instance = host.bootstrap.Modal.getInstance(node);
                    if (instance) {
                        instance.hide();
                    }
                }
            } catch (e) {
                // ignore
            }
        });
    }

    function closeRef(ref) {
        hideDialog(ref);
        if (ref.popup) {
            // Native window.open() fallback: the dialog only asks the opener to
            // close it, so the opener must dispose the actual popup window.
            try {
                if (!ref.popup.closed) {
                    ref.popup.close();
                }
            } catch (e) {
                // ignore cross-origin close failures
            }
            ref.popup = null;
        }
        if (ref.fallbackContainer && ref.fallbackContainer.parentNode) {
            var container = ref.fallbackContainer;
            // Let Bootstrap finish its hide transition and backdrop cleanup
            // before removing our wrapper from the DOM.
            window.setTimeout(function () {
                if (container.parentNode) {
                    container.parentNode.removeChild(container);
                }
            }, 350);
            ref.fallbackContainer = null;
        }
    }

    function finalize(ref, payload) {
        if (!ref || ref.closed) {
            return;
        }
        ref.closed = true;
        removeListener(ref);

        if (ref.popupTimer) {
            try {
                window.clearInterval(ref.popupTimer);
            } catch (e) {
                // ignore
            }
            ref.popupTimer = null;
        }

        // dlgopen calls this global by name when its modal is hidden. Keep a
        // no-op in place so the hide handler never throws.
        if (ref.closedCallback && ref.closedHost) {
            try {
                ref.closedHost[ref.closedCallback] = function () {};
            } catch (e) {
                // ignore
            }
        }

        if (active === ref) {
            active = null;
        }

        if (ref.onClose) {
            // onClose is a host-page notification, not a result payload. The
            // documented status values (ok|partial|error) only come from a
            // completed batch, so a close without results passes null.
            invokeCallback(ref.onClose, payload || null);
        }
    }

    /**
     * @param {object} options
     * @param {number} options.pid
     * @param {number} [options.categoryId]
     * @param {string} [options.cardCode]
     * @param {number} [options.studyId]
     * @param {number} [options.multiple=1]
     * @param {boolean} [options.keepOpen=false]
     * @param {function} [options.onDone]
     * @param {function} [options.onClose]
     * @param {function|string} [options.callBack]
     * @param {string} [options.title]
     */
    CardUpload.open = function (options) {
        options = options || {};

        if (active) {
            closeRef(active);
            finalize(active, null);
        }

        var pid = options.pid;
        if (pid === undefined || pid === null) {
            pid = window.pid;
        }
        pid = parseInt(pid, 10);
        if (isNaN(pid) || pid <= 0) {
            console.error('CardUpload.open: pid is required and must be a positive integer.');
            return false;
        }

        var categoryId = parseInt(options.categoryId, 10);
        if (isNaN(categoryId) || categoryId <= 0) {
            categoryId = null;
        }

        var studyId = parseInt(options.studyId, 10);
        if (isNaN(studyId) || studyId <= 0) {
            studyId = null;
        }

        var cardCode = String(options.cardCode || '').trim();
        var multiple = (typeof options.multiple === 'undefined' || options.multiple) ? 1 : 0;
        var keepOpen = options.keepOpen === true || options.keepOpen === 1 || options.keepOpen === '1';
        var title = options.title || ((typeof window.xl === 'function') ? window.xl('Upload Documents') : 'Upload Documents');

        var params = ['pid=' + encodeURIComponent(pid)];
        if (categoryId) {
            params.push('category_id=' + encodeURIComponent(categoryId));
        }
        if (cardCode !== '') {
            params.push('card_code=' + encodeURIComponent(cardCode));
        }
        if (studyId) {
            params.push('study_id=' + encodeURIComponent(studyId));
        }
        params.push('multiple=' + encodeURIComponent(multiple));
        params.push('keep_open=' + (keepOpen ? '1' : '0'));

        var url = options.url ? String(options.url) : (formRoot() + '/interface/forms/form_cardiology/documents_upload.php');
        if (!/^https?:\/\//i.test(url) && url.charAt(0) !== '/') {
            url = formRoot() + '/interface/forms/form_cardiology/documents_upload.php';
        }
        url += (url.indexOf('?') === -1 ? '?' : '&') + params.join('&');
        var winname = 'cardiology-upload-' + Date.now().toString() + '-' + Math.floor(Math.random() * 100000);

        var hosts = [window];
        try {
            if (window.top && window.top !== window && window.top.document) {
                hosts.push(window.top);
            }
        } catch (e) {
            // cross-origin top
        }

        var ref = {
            winname: winname,
            hosts: hosts,
            listener: null,
            listenerAttached: false,
            hidden: false,
            closed: false,
            onDone: typeof options.onDone === 'function' ? options.onDone : null,
            onClose: typeof options.onClose === 'function' ? options.onClose : null,
            keepOpen: keepOpen,
            callBack: options.callBack || null,
            fallbackContainer: null,
            frameWindow: null,
            popup: null,
            popupTimer: null,
            closedCallback: null,
            closedHost: null
        };

        ref.listener = function (event) {
            if (ref.closed) {
                return;
            }
            if (!event || event.origin !== window.location.origin) {
                return;
            }
            if (!event.data || event.data.source !== 'cardiology-upload') {
                return;
            }

            // event.source must be the dialog window we opened. Never accept a
            // matching payload from another frame or tab.
            var expected = ref.frameWindow || ref.popup || frameFor(ref);
            if (!expected || event.source !== expected) {
                return;
            }

            var payload = event.data;

            // The dialog asks the opener to close it (Cancel/Close/Escape, and
            // the end of a keepOpen batch). This keeps fallback iframe modals in
            // sync without relying on a global dlgclose() helper.
            if (payload.action === 'close') {
                closeRef(ref);
                finalize(ref, null);
                return;
            }

            if (ref.onDone) {
                try {
                    ref.onDone(payload);
                } catch (e) {
                    // ignore callback errors
                }
            }
            invokeCallback(ref.callBack, payload);

            // keepOpen: the dialog stays mounted so the user can upload more
            // batches while the opener refreshes its own table live.
            var shouldKeepOpen = ref.keepOpen
                || (payload && (payload.keepOpen === true || payload.keepOpen === 1 || payload.keepOpen === '1'));
            if (shouldKeepOpen) {
                return;
            }

            closeRef(ref);
            finalize(ref, payload);
        };

        hosts.forEach(function (host) {
            try {
                host.addEventListener('message', ref.listener);
                ref.listenerAttached = true;
            } catch (e) {
                // ignore
            }
        });

        active = ref;

        var dlgopenFn = null;
        try {
            if (typeof window.dlgopen === 'function') {
                dlgopenFn = window.dlgopen;
            } else if (hosts[hosts.length - 1] && typeof hosts[hosts.length - 1].dlgopen === 'function') {
                dlgopenFn = hosts[hosts.length - 1].dlgopen;
            }
        } catch (e) {
            dlgopenFn = null;
        }

        if (dlgopenFn) {
            ref.closedCallback = '__cardiologyUploadClosed_' + winname.replace(/[^a-zA-Z0-9_]/g, '_');
            ref.closedHost = window;
            try {
                window[ref.closedCallback] = function () {
                    finalize(ref, null);
                };
                if (window.top && window.top !== window) {
                    try {
                        window.top[ref.closedCallback] = window[ref.closedCallback];
                        ref.closedHost = window.top;
                    } catch (e) {
                        // keep window callback
                    }
                }

                dlgopenFn(url, winname, 'modal-lg', 520, '', title, {
                    type: 'iframe',
                    allowDrag: true,
                    allowResize: true,
                    onClosed: ref.closedCallback
                });
                ref.frameWindow = frameFor(ref);
                return winname;
            } catch (e) {
                // Fall through to the Bootstrap modal.
            }
        }

        var $ = window.jQuery || window.$;
        var hasJqueryModal = !!($ && typeof $.fn.modal === 'function');
        var hasBootstrapModal = !!(window.bootstrap && typeof window.bootstrap.Modal === 'function');
        if (hasJqueryModal || hasBootstrapModal) {
            var closeLabel = (typeof window.xl === 'function') ? window.xl('Close') : 'Close';
            var closeMarkup = '<button type="button" class="close btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="' + escapeHtml(closeLabel) + '"><span aria-hidden="true">&times;</span></button>';
            var modalHtml = '<div class="modal fade" id="' + escapeHtml(winname) + '" tabindex="-1" role="dialog" aria-modal="true">' +
                '<div class="modal-dialog modal-lg" role="document" style="max-width:900px;">' +
                '<div class="modal-content">' +
                '<div class="modal-header"><h5 class="modal-title">' + escapeHtml(title) + '</h5>' + closeMarkup + '</div>' +
                '<div class="modal-body p-0" style="height:520px;">' +
                '<iframe name="' + escapeHtml(winname) + '" title="' + escapeHtml(title) + '" src="' + escapeHtml(url) + '" style="width:100%;height:100%;border:0;"></iframe>' +
                '</div></div></div></div>';

            ref.fallbackContainer = document.createElement('div');
            ref.fallbackContainer.innerHTML = modalHtml;
            document.body.appendChild(ref.fallbackContainer);

            var fallbackFrame = ref.fallbackContainer.querySelector('iframe[name="' + winname + '"]');
            if (fallbackFrame) {
                ref.frameWindow = fallbackFrame.contentWindow;
            }

            var modalNode = ref.fallbackContainer.querySelector('#' + winname);
            if (modalNode) {
                modalNode.addEventListener('hidden.bs.modal', function () {
                    if (!ref.closed) {
                        closeRef(ref);
                        finalize(ref, null);
                    }
                });
            }

            if (hasJqueryModal) {
                $(ref.fallbackContainer).find('iframe[name="' + winname + '"]').on('load', function () {
                    try {
                        ref.frameWindow = this.contentWindow;
                    } catch (e) {
                        ref.frameWindow = null;
                    }
                });
                $(ref.fallbackContainer).find('#' + winname).modal({ backdrop: 'static', keyboard: true });
            } else if (modalNode) {
                var fallbackIframe = ref.fallbackContainer.querySelector('iframe[name="' + winname + '"]');
                if (fallbackIframe) {
                    fallbackIframe.addEventListener('load', function () {
                        try {
                            ref.frameWindow = this.contentWindow;
                        } catch (e) {
                            ref.frameWindow = null;
                        }
                    });
                }
                var bootstrapModal = new window.bootstrap.Modal(modalNode, { backdrop: 'static', keyboard: true });
                bootstrapModal.show();
            }
            return winname;
        }

        // Last resort: a native popup can still postMessage to this window.
        try {
            var popup = window.open(url, winname, 'width=900,height=650,scrollbars=yes,resizable=yes');
            if (!popup) {
                finalize(ref, null);
                return false;
            }
            ref.popup = popup;
            ref.popupTimer = window.setInterval(function () {
                var isClosed = false;
                try {
                    isClosed = popup.closed;
                } catch (e) {
                    isClosed = true;
                }
                if (isClosed && !ref.closed) {
                    closeRef(ref);
                    finalize(ref, null);
                }
            }, 800);
            return winname;
        } catch (e) {
            finalize(ref, null);
            return false;
        }
    };

    window.CardUpload = CardUpload;
})(window);
