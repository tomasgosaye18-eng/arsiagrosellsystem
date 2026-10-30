/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : public/assets/js/organization.js
 * Purpose : Shared JavaScript utilities for the Organization
 *           Management Module.
 *           Covers: AJAX helpers, cascading dropdowns,
 *           live search, form validation helpers, toast
 *           wrappers, loading overlay.
 * Deps    : Bootstrap 5, app.js (AAM global)
 * How     : Included via <?php $view->section('scripts') ?> in
 *           org module views. Uses window.AAM namespace.
 * =========================================================
 */

(function (AAM) {
  'use strict';

  // ─── CSRF Token helper ──────────────────────────────────
  function getCsrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  // ─── Generic POST ────────────────────────────────────────
  AAM.orgPost = function (url, extraData, onSuccess, onError) {
    var fd = new FormData();
    fd.append('csrf_token', getCsrf());
    if (extraData) {
      Object.keys(extraData).forEach(function (k) { fd.append(k, extraData[k]); });
    }
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        if (resp.success) {
          onSuccess(resp);
        } else {
          if (typeof onError === 'function') {
            onError(resp);
          } else {
            AAM.toast(resp.message || 'An error occurred', 'error');
          }
        }
      })
      .catch(function () {
        AAM.toast('Network error. Please try again.', 'error');
      });
  };

  // ─── Generic GET (JSON) ──────────────────────────────────
  AAM.orgGet = function (url, onSuccess) {
    fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        if (resp.success) {
          onSuccess(resp);
        } else {
          AAM.toast(resp.message || 'Request failed', 'error');
        }
      })
      .catch(function () {
        AAM.toast('Network error', 'error');
      });
  };

  // ─── Populate a <select> from data ──────────────────────
  AAM.populateSelect = function (selectEl, items, valueKey, labelKey, placeholder) {
    selectEl.innerHTML = '<option value="">' + (placeholder || '— Select —') + '</option>';
    if (items && items.length) {
      items.forEach(function (item) {
        var opt = document.createElement('option');
        opt.value = item[valueKey];
        opt.textContent = item[labelKey];
        selectEl.appendChild(opt);
      });
    }
    selectEl.disabled = false;
  };

  // ─── Cascading Dropdown: Campus → Buildings ──────────────
  AAM.initCampusBuildingDropdown = function (campusSelectId, buildingSelectId) {
    var campusSel   = document.getElementById(campusSelectId);
    var buildingSel = document.getElementById(buildingSelectId);
    if (!campusSel || !buildingSel) return;

    campusSel.addEventListener('change', function () {
      var campusId = this.value;
      if (!campusId) {
        buildingSel.innerHTML = '<option value="">— Select Campus First —</option>';
        buildingSel.disabled = true;
        return;
      }
      buildingSel.innerHTML = '<option value="">Loading…</option>';
      buildingSel.disabled = true;

      AAM.orgGet(
        window.AAM.appUrl + '/admin/organization/locations/data/buildings-by-campus/' + campusId,
        function (resp) {
          AAM.populateSelect(buildingSel, resp.buildings, 'building_id', 'building_name', '— Select Building —');
        }
      );
    });
  };

  // ─── Cascading Dropdown: Region → Zones ─────────────────
  AAM.initRegionZoneDropdown = function (regionSelectId, zoneSelectId) {
    var regionSel = document.getElementById(regionSelectId);
    var zoneSel   = document.getElementById(zoneSelectId);
    if (!regionSel || !zoneSel) return;

    regionSel.addEventListener('change', function () {
      var regionId = this.value;
      if (!regionId) {
        zoneSel.innerHTML = '<option value="">— Select Region First —</option>';
        return;
      }
      zoneSel.innerHTML = '<option value="">Loading…</option>';
      AAM.orgGet(
        window.AAM.appUrl + '/admin/organization/locations/data/zones-by-region/' + regionId,
        function (resp) {
          AAM.populateSelect(zoneSel, resp.zones, 'zone_id', 'zone_name', '— Select Zone —');
        }
      );
    });
  };

  // ─── Cascading Dropdown: Zone → Cities ──────────────────
  AAM.initZoneCityDropdown = function (zoneSelectId, citySelectId) {
    var zoneSel = document.getElementById(zoneSelectId);
    var citySel = document.getElementById(citySelectId);
    if (!zoneSel || !citySel) return;

    zoneSel.addEventListener('change', function () {
      var zoneId = this.value;
      if (!zoneId) {
        citySel.innerHTML = '<option value="">— Select Zone First —</option>';
        return;
      }
      citySel.innerHTML = '<option value="">Loading…</option>';
      AAM.orgGet(
        window.AAM.appUrl + '/admin/organization/locations/data/cities-by-zone/' + zoneId,
        function (resp) {
          AAM.populateSelect(citySel, resp.cities, 'city_id', 'city_name', '— Select City —');
        }
      );
    });
  };

  // ─── Loading Overlay ─────────────────────────────────────
  AAM.showLoading = function () {
    var el = document.getElementById('orgLoadingOverlay');
    if (!el) {
      el = document.createElement('div');
      el.id = 'orgLoadingOverlay';
      el.className = 'loading-overlay';
      el.innerHTML = '<div class="text-center"><div class="spinner-border text-primary mb-2" role="status"></div><div class="text-muted small">Loading…</div></div>';
      document.body.appendChild(el);
    }
    el.style.display = 'flex';
  };

  AAM.hideLoading = function () {
    var el = document.getElementById('orgLoadingOverlay');
    if (el) el.style.display = 'none';
  };

  // ─── Confirm + AJAX action (for delete/restore) ──────────
  AAM.confirmAction = function (message, url, successMsg, afterCb) {
    if (!confirm(message)) return;
    AAM.orgPost(url, null, function () {
      AAM.toast(successMsg, 'success');
      if (typeof afterCb === 'function') {
        afterCb();
      } else {
        setTimeout(function () { location.reload(); }, 700);
      }
    });
  };

  // ─── Form error display helper ───────────────────────────
  AAM.showFormErrors = function (errorsObj, errBoxId, errListId) {
    var box  = document.getElementById(errBoxId);
    var list = document.getElementById(errListId);
    if (!box) return;
    if (list) {
      list.innerHTML = '';
      Object.values(errorsObj || {}).forEach(function (msg) {
        if (msg) list.innerHTML += '<li>' + msg + '</li>';
      });
    }
    box.classList.remove('d-none');
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  AAM.hideFormErrors = function (errBoxId) {
    var box = document.getElementById(errBoxId);
    if (box) box.classList.add('d-none');
  };

  // ─── Submit button state helpers ─────────────────────────
  AAM.btnLoading = function (btn, text) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + (text || 'Loading…');
  };

  AAM.btnReset = function (btn, html) {
    btn.disabled = false;
    btn.innerHTML = html;
  };

  // ─── Live search debounce ─────────────────────────────────
  AAM.debounce = function (fn, delay) {
    var timer;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(null, args); }, delay || 350);
    };
  };

  // ─── Init tooltips on org pages ──────────────────────────
  AAM.initOrgTooltips = function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new bootstrap.Tooltip(el);
    });
  };

  // Auto-init on DOM ready
  document.addEventListener('DOMContentLoaded', function () {
    AAM.initOrgTooltips();
  });

})(window.AAM = window.AAM || {});
