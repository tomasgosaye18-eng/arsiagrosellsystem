/**
 * =========================================================
 * UNIT MANAGEMENT – AJAX JS
 * =========================================================
 * File    : public/assets/js/admin/units.js
 * Purpose : AJAX CRUD for units of measurement:
 *           status toggle, soft-delete, restore,
 *           form validation, dropdown population.
 * Deps    : AAM global, Bootstrap 5, CSRF meta
 * =========================================================
 */

(function (AAM) {
  'use strict';

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : (window.AAM && window.AAM.csrfToken ? window.AAM.csrfToken : '');
  }

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
        post(this.dataset.action, {}, function (resp) {
          if (resp.success) {
            AAM.toast && AAM.toast(resp.message || 'Updated', 'success');
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
    document.querySelectorAll('.btn-unit-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Delete unit "' + this.dataset.name + '"?')) return;
        post(this.dataset.action, {}, function (resp) {
          if (resp.success) {
            AAM.toast && AAM.toast('Unit deleted', 'success');
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
    document.querySelectorAll('.btn-unit-restore').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Restore unit "' + this.dataset.name + '"?')) return;
        post(this.dataset.action, {}, function (resp) {
          if (resp.success) {
            AAM.toast && AAM.toast('Unit restored', 'success');
            setTimeout(function () { location.reload(); }, 700);
          } else {
            AAM.toast && AAM.toast(resp.message || 'Restore failed', 'error');
          }
        });
      });
    });
  }

  /* ── Auto-uppercase + clean code input ──────────────── */
  function initCodeInput() {
    var input = document.querySelector('[name="unit_code"]');
    if (!input) return;
    input.addEventListener('input', function () {
      this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
    });
  }

  /* ── Status label ────────────────────────────────────── */
  function initStatusLabel() {
    var toggle = document.getElementById('is_active');
    var label  = document.getElementById('statusLabel');
    if (!toggle || !label) return;
    toggle.addEventListener('change', function () {
      label.textContent = this.checked ? 'Active' : 'Inactive';
    });
  }

  /* ── Form AJAX submit ────────────────────────────────── */
  function initFormSubmit() {
    var form      = document.getElementById('unitForm');
    var submitBtn = document.getElementById('submitBtn');
    var spinner   = document.getElementById('spinner');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors();
      submitBtn.disabled = true;
      spinner && spinner.classList.remove('d-none');

      var fd     = new FormData(form);
      var active = document.getElementById('is_active');
      var base   = document.getElementById('is_base_unit');
      if (active && !active.checked)  fd.set('is_active', '0');
      if (base   && !base.checked)    fd.delete('is_base_unit');

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
          if (redirect) setTimeout(function () { window.location.href = redirect; }, 900);
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

  /* ── Filter auto-submit ──────────────────────────────── */
  function initAutoFilter() {
    ['typeFilter', 'statusFilter'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () {
        var f = document.getElementById('filterForm');
        if (f) f.submit();
      });
    });
  }

  /* ── Populate unit dropdown via AJAX ─────────────────── */
  AAM.loadUnitDropdown = function (selectId, selectedId, unitType) {
    var select = document.getElementById(selectId);
    if (!select) return;

    var base = (window.AAM && window.AAM.appUrl) ? window.AAM.appUrl : '';
    var url  = base + '/admin/units/data/active' + (unitType ? '?type=' + encodeURIComponent(unitType) : '');

    get(url, function (resp) {
      if (!resp.success) return;
      var current = select.value;
      select.innerHTML = '<option value="">— Select Unit —</option>';
      resp.units.forEach(function (u) {
        var opt = document.createElement('option');
        opt.value = u.unit_id;
        opt.textContent = u.unit_name + ' (' + u.abbreviation + ')';
        if (String(u.unit_id) === String(selectedId || current)) opt.selected = true;
        select.appendChild(opt);
      });
    });
  };

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

  /* ── Init ────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initStatusToggle();
    initDelete();
    initRestore();
    initCodeInput();
    initStatusLabel();
    initFormSubmit();
    initAutoFilter();
  });

}(window.AAM || {}));
