/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8D
 * =========================================================
 * File    : public/assets/js/settings.js
 * Purpose : Settings module JavaScript utilities.
 *           - AJAX form binding with spinner & alerts
 *           - Confirm danger dialog (SweetAlert2 / fallback)
 *           - Generic AJAX action helper
 *           - Toast notifications (uses AAM.toast if available)
 *           - Log level filter live search
 *           - Theme toggle (dark/light)
 *           - Export progress tracking
 * =========================================================
 */
(function (global) {
    'use strict';

    /* ── Namespace ──────────────────────────────────────── */
    var AAMSettings = {};

    /**
     * Bind an HTML form to an AJAX endpoint.
     *
     * Options:
     *   - successMsg  {string}   Flash message on success
     *   - submitBtnId {string}   ID of the submit button
     *   - alertId     {string}   ID of the alert <div>
     *   - transform   {function} Transform FormData before send
     *   - onSuccess   {function} Callback(resp) on success
     *   - onError     {function} Callback(resp) on error
     *
     * @param {string} formId   - ID of the <form>
     * @param {string} url      - POST endpoint
     * @param {object} options
     */
    AAMSettings.bindAjaxForm = function (formId, url, options) {
        var form = document.getElementById(formId);
        if (!form) return;

        options = options || {};

        var isSubmitting = false;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;
            AAMSettings._showAlert(options.alertId, '');

            var btn = options.submitBtnId ? document.getElementById(options.submitBtnId) : null;
            var origLabel = btn ? btn.innerHTML : null;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
            }

            var fd = new FormData(form);
            if (typeof options.transform === 'function') {
                fd = options.transform(fd) || fd;
            }

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (r) {
                if (r.status === 401 || r.redirected) {
                    window.location.href = r.url || '/arsiagro/app/login';
                    return null;
                }
                return r.json();
            })
            .then(function (resp) {
                if (!resp) return;
                if (btn) { btn.disabled = false; btn.innerHTML = origLabel; }
                isSubmitting = false;

                if (resp.success) {
                    var msg = options.successMsg || resp.message || 'Saved.';
                    AAMSettings._showAlert(options.alertId, msg, 'success');
                    if (global.AAM && global.AAM.toast) {
                        global.AAM.toast(msg, 'success');
                    }
                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess(resp);
                    }
                } else {
                    var errMsg = resp.message || 'Save failed.';
                    var errors = resp.errors || {};
                    var detail = Object.values(errors).join(' ');
                    AAMSettings._showAlert(options.alertId, errMsg + (detail ? ' ' + detail : ''), 'danger');
                    if (global.AAM && global.AAM.toast) {
                        global.AAM.toast(errMsg, 'error');
                    }
                    if (typeof options.onError === 'function') {
                        options.onError(resp);
                    }
                }
            })
            .catch(function (err) {
                if (btn) { btn.disabled = false; btn.innerHTML = origLabel; }
                isSubmitting = false;
                AAMSettings._showAlert(options.alertId, 'Network error. Please try again.', 'danger');
            });
        });
    };

    /**
     * Perform a generic AJAX POST action (verify, delete, toggle etc.)
     *
     * @param {string}   url
     * @param {object}   extraData   Additional fields beyond csrf_token
     * @param {function} callback    callback(resp)
     */
    AAMSettings.ajaxAction = function (url, extraData, callback) {
        var fd = new FormData();
        // Get CSRF token from meta tag or first hidden csrf_token input
        var metaToken = document.querySelector('meta[name="csrf-token"]');
        var inputToken = document.querySelector('input[name="csrf_token"]');
        var token = (metaToken ? metaToken.content : null) ||
                    (inputToken ? inputToken.value : '');
        fd.append('csrf_token', token);
        if (extraData) {
            Object.keys(extraData).forEach(function (k) {
                fd.append(k, extraData[k]);
            });
        }
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (typeof callback === 'function') callback(resp);
        })
        .catch(function () {
            if (typeof callback === 'function') callback({ success: false, message: 'Network error.' });
        });
    };

    /**
     * Show a danger confirmation and run callback on confirm.
     *
     * Uses SweetAlert2 if available, otherwise native confirm().
     *
     * @param {string}   title
     * @param {string}   text
     * @param {function} onConfirm
     * @param {string}   [confirmBtnClass]
     */
    AAMSettings.confirmDanger = function (title, text, onConfirm, confirmBtnClass) {
        if (global.Swal) {
            global.Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
            }).then(function (result) {
                if (result.isConfirmed) { onConfirm(); }
            });
        } else {
            if (confirm(title + '\n' + text)) { onConfirm(); }
        }
    };

    /**
     * Confirm info dialog (for non-destructive confirms).
     */
    AAMSettings.confirmInfo = function (title, text, onConfirm) {
        if (global.Swal) {
            global.Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#0d6efd',
            }).then(function (result) {
                if (result.isConfirmed) { onConfirm(); }
            });
        } else {
            if (confirm(title + '\n' + text)) { onConfirm(); }
        }
    };

    /**
     * Show / hide an alert <div>.
     * @private
     */
    AAMSettings._showAlert = function (alertId, message, type) {
        if (!alertId) return;
        var el = document.getElementById(alertId);
        if (!el) return;
        if (!message) {
            el.className = 'd-none';
            return;
        }
        var icon = type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle';
        el.className = 'alert alert-' + (type || 'info') + ' d-flex align-items-center gap-2 mb-3';
        el.innerHTML = '<i class="bi ' + icon + ' flex-shrink-0"></i><div>' + message + '</div>';
    };

    /**
     * Live search/filter for any table with data-search attribute.
     * Call: AAMSettings.initLiveSearch('searchInputId', 'tableBodyId')
     */
    AAMSettings.initLiveSearch = function (inputId, tbodyId) {
        var input = document.getElementById(inputId);
        var tbody = document.getElementById(tbodyId);
        if (!input || !tbody) return;

        input.addEventListener('input', function () {
            var term = this.value.toLowerCase().trim();
            Array.from(tbody.rows).forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = (!term || text.includes(term)) ? '' : 'none';
            });
        });
    };

    /**
     * Toggle dark/light mode.
     * Delegates to AAMTheme (theme.js) — the single source of truth.
     * Kept for backward compatibility in case any settings view calls it.
     */
    AAMSettings.toggleTheme = function () {
        if (global.AAMTheme) {
            global.AAMTheme.toggle();
            return global.AAMTheme.current() === 'dark';
        }
        return false;
    };

    /**
     * Apply saved theme on page load.
     * theme.js already handles this via the inline <head> script,
     * so this is a no-op. Kept for backward compatibility.
     */
    AAMSettings.applyStoredTheme = function () {
        // No-op: theme is applied by the inline <head> script in master.php
        // and managed entirely by theme.js (AAMTheme).
    };

    /**
     * Character counter for textareas.
     * Call: AAMSettings.bindCharCounter('textareaId', 'counterId', maxLen)
     */
    AAMSettings.bindCharCounter = function (textareaId, counterId, maxLen) {
        var ta = document.getElementById(textareaId);
        var counter = document.getElementById(counterId);
        if (!ta || !counter) return;
        function update() {
            var rem = maxLen - ta.value.length;
            counter.textContent = rem + ' characters remaining';
            counter.className = rem < 20 ? 'form-text text-danger' : 'form-text text-muted';
        }
        ta.addEventListener('input', update);
        update();
    };

    /**
     * Initialize all tooltips on the page.
     */
    AAMSettings.initTooltips = function () {
        if (global.bootstrap && global.bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new global.bootstrap.Tooltip(el);
            });
        }
    };

    /**
     * Auto-uppercase code input.
     */
    AAMSettings.bindUppercase = function (inputId) {
        var el = document.getElementById(inputId);
        if (!el) return;
        el.addEventListener('input', function () {
            var pos = this.selectionStart;
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
            this.setSelectionRange(pos, pos);
        });
    };

    /* ── Document ready init ────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        // Theme is applied by inline <head> script + theme.js — no action needed here.
        AAMSettings.initTooltips();
    });

    /* ── Expose globally ────────────────────────────────── */
    global.AAMSettings = AAMSettings;

})(window);
