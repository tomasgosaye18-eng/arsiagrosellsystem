/**
 * =========================================================
 * CATEGORY MANAGEMENT – AJAX JS
 * =========================================================
 * File    : public/assets/js/admin/categories.js
 * Purpose : AJAX CRUD for product categories:
 *           status toggle, soft-delete, restore,
 *           icon live preview, form validation feedback.
 * Deps    : AAM global, Bootstrap 5, CSRF meta
 * =========================================================
 */

(function (AAM) {
  'use strict';

  /* ── CSRF helper ─────────────────────────────────────── */
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : (window.AAM && window.AAM.csrfToken ? window.AAM.csrfToken : '');
  }

  /* ── POST helper ─────────────────────────────────────── */
  function post(url, extra, cb) {
    var fd = new FormData();
    fd.append('csrf_token', csrf());
    if (extra) Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(function (r) { return r.json(); })
    .then(cb)
    .catch(function () { AAM.toast && AAM.toast('Network error', 'error'); });
  }

  /* ── GET helper ──────────────────────────────────────── */
  function get(url, cb) {
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(cb)
      .catch(function () { AAM.toast && AAM.toast('Network error', 'error'); });
  }

  /* ── Status toggle ───────────────────────────────────── */
  function initStatusToggle() {
    document.querySelectorAll('.btn-toggle-status').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url = this.dataset.action;
        post(url, {}, function (resp) {
          if (resp.success) {
            AAM.toast && AAM.toast(resp.message || 'Status updated', 'success');
            setTimeout(function () { location.reload(); }, 650);
          } else {
            AAM.toast && AAM.toast(resp.message || 'Failed', 'error');
          }
        });
      });
    });
  }

  /* ── Delete ──────────────────────────────────────────── */
  function initDelete() {
    document.querySelectorAll('.btn-cat-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = this.dataset.name;
        if (!confirm('Delete category "' + name + '"?\nCannot be done if it has products.')) return;
        post(this.dataset.action, {}, function (resp) {
          if (resp.success) {
            AAM.toast && AAM.toast('Category deleted', 'success');
            setTimeout(function () { location.reload(); }, 700);
          } else {
            AAM.toast && AAM.toast(resp.message || 'Delete failed', 'error');
          }
        });
      });
    });
  }

  /* ── Restore ─────────────────────────────────────────── */
  function initRestore() {
    document.querySelectorAll('.btn-cat-restore').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = this.dataset.name;
        if (!confirm('Restore category "' + name + '"?')) return;
        post(this.dataset.action, {}, function (resp) {
          if (resp.success) {
            AAM.toast && AAM.toast('Category restored', 'success');
            setTimeout(function () { location.reload(); }, 700);
          } else {
            AAM.toast && AAM.toast(resp.message || 'Restore failed', 'error');
          }
        });
      });
    });
  }

  /* ── Icon live preview (on create/edit forms) ────────── */
  function initIconPreview() {
    var iconInput   = document.getElementById('iconInput');
    var iconPreview = document.getElementById('iconPreview');
    if (!iconInput || !iconPreview) return;

    iconInput.addEventListener('input', function () {
      var val = this.value.trim();
      iconPreview.innerHTML = '<i class="bi ' + (val || 'bi-tag') + '"></i>';
    });
  }

  /* ── Auto-uppercase code field ───────────────────────── */
  function initCodeInput() {
    var codeInput = document.querySelector('[name="category_code"]');
    if (!codeInput) return;
    codeInput.addEventListener('input', function () {
      this.value = this.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
    });
  }

  /* ── Form AJAX submit ────────────────────────────────── */
  function initFormSubmit() {
    var form      = document.getElementById('categoryForm');
    var submitBtn = document.getElementById('submitBtn');
    var spinner   = document.getElementById('spinner');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors();
      submitBtn.disabled = true;
      spinner && spinner.classList.remove('d-none');

      var fd  = new FormData(form);
      var toggle = document.getElementById('is_active');
      if (toggle && !toggle.checked) fd.set('is_active', '0');

      // Determine action URL (create vs update)
      var url = form.dataset.actionUrl || form.action;

      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        submitBtn.disabled = false;
        spinner && spinner.classList.add('d-none');
        if (resp.success) {
          AAM.toast && AAM.toast(resp.message || 'Saved', 'success');
          var redirect = (resp.data && resp.data.redirect) ? resp.data.redirect : resp.redirect;
          if (redirect) {
            setTimeout(function () { window.location.href = redirect; }, 900);
          }
        } else {
          if (resp.errors) showErrors(resp.errors);
          AAM.toast && AAM.toast(resp.message || 'Validation failed', 'error');
        }
      })
      .catch(function () {
        submitBtn.disabled = false;
        spinner && spinner.classList.add('d-none');
        AAM.toast && AAM.toast('Network error', 'error');
      });
    });
  }

  /* ── Status toggle label ─────────────────────────────── */
  function initStatusLabel() {
    var toggle = document.getElementById('is_active');
    var label  = document.getElementById('statusLabel');
    if (!toggle || !label) return;
    toggle.addEventListener('change', function () {
      label.textContent = this.checked ? 'Active' : 'Inactive';
    });
  }

  /* ── Filter auto-submit ──────────────────────────────── */
  function initAutoFilter() {
    ['statusFilter'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () {
        var form = document.getElementById('filterForm');
        if (form) form.submit();
      });
    });
  }

  /* ── Error helpers ───────────────────────────────────── */
  function showErrors(errors) {
    Object.keys(errors).forEach(function (key) {
      var el  = document.querySelector('[name="' + key + '"]');
      var err = document.getElementById('err_' + key);
      if (el)  el.classList.add('is-invalid');
      if (err) { err.textContent = errors[key]; err.style.display = 'block'; }
    });
  }

  function clearErrors() {
    document.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    document.querySelectorAll('[id^="err_"]').forEach(function (el) { el.textContent = ''; el.style.display = ''; });
  }

  /* ── Populate category dropdown via AJAX ─────────────── */
  AAM.loadCategoryDropdown = function (selectId, selectedId) {
    var select = document.getElementById(selectId);
    if (!select) return;

    var baseUrl = (window.AAM && window.AAM.appUrl) ? window.AAM.appUrl : '';
    get(baseUrl + '/admin/categories/data/active', function (resp) {
      if (!resp.success) return;
      var current = select.value;
      select.innerHTML = '<option value="">— Select Category —</option>';
      resp.categories.forEach(function (cat) {
        var opt = document.createElement('option');
        opt.value = cat.category_id;
        opt.textContent = cat.category_name;
        if (String(cat.category_id) === String(selectedId || current)) opt.selected = true;
        select.appendChild(opt);
      });
    });
  };

  /* ── Init ────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initStatusToggle();
    initDelete();
    initRestore();
    initIconPreview();
    initCodeInput();
    initFormSubmit();
    initStatusLabel();
    initAutoFilter();
  });

}(window.AAM || {}));
