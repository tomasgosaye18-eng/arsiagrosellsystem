/**
 * =========================================================
 * PRODUCT MANAGEMENT – MAIN JS
 * =========================================================
 * File    : public/assets/js/admin/products.js
 * Purpose : AJAX CRUD, live search, barcode lookup,
 *           modal handling, dynamic validation,
 *           status & featured toggling, bulk actions.
 * Deps    : AAM global (AAM.toast), Bootstrap 5, CSRF meta
 * =========================================================
 */

(function (AAM) {
  'use strict';

  /* ─── Helpers ─────────────────────────────────────────── */
  var csrfToken = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  };

  function postAjax(url, extraData, callback) {
    var fd = new FormData();
    fd.append('csrf_token', csrfToken());
    if (extraData && typeof extraData === 'object') {
      Object.keys(extraData).forEach(function (k) { fd.append(k, extraData[k]); });
    }
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(function (r) { return r.json(); })
    .then(callback)
    .catch(function () { AAM.toast('Network error – please try again.', 'error'); });
  }

  function getAjax(url, callback) {
    fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(callback)
    .catch(function () { AAM.toast('Network error', 'error'); });
  }

  function reloadAfter(ms) {
    setTimeout(function () { location.reload(); }, ms || 700);
  }

  /* ─── Toggle Product Status ───────────────────────────── */
  function initToggleStatus() {
    document.querySelectorAll('.js-toggle-product-status').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url = this.dataset.url;
        var name = this.dataset.name;
        if (!url) return;
        postAjax(url, {}, function (resp) {
          if (resp.success) {
            AAM.toast(resp.message || 'Status updated', 'success');
            reloadAfter(600);
          } else {
            AAM.toast(resp.message || 'Failed to update status', 'error');
          }
        });
      });
    });
  }

  /* ─── Toggle Featured ─────────────────────────────────── */
  function initToggleFeatured() {
    document.querySelectorAll('.js-toggle-featured').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url  = this.dataset.url;
        var icon = this.querySelector('i');
        postAjax(url, {}, function (resp) {
          if (resp.success) {
            if (resp.is_featured) {
              icon.className = 'bi bi-star-fill text-warning';
              btn.title = 'Remove from featured';
            } else {
              icon.className = 'bi bi-star text-muted';
              btn.title = 'Mark as featured';
            }
          } else {
            AAM.toast(resp.message || 'Failed', 'error');
          }
        });
      });
    });
  }

  /* ─── Soft Delete ─────────────────────────────────────── */
  function initDelete() {
    document.querySelectorAll('.js-product-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = this.dataset.name;
        var url  = this.dataset.url;
        if (!confirm('Delete "' + name + '"?\nThe product will be soft-deleted and can be restored.')) return;
        postAjax(url, {}, function (resp) {
          if (resp.success) {
            AAM.toast('Product deleted', 'success');
            var row = document.querySelector('tr[data-id="' + btn.dataset.id + '"]');
            if (row) { row.style.opacity = '0'; }
            reloadAfter(900);
          } else {
            AAM.toast(resp.message || 'Delete failed', 'error');
          }
        });
      });
    });
  }

  /* ─── Restore ─────────────────────────────────────────── */
  function initRestore() {
    document.querySelectorAll('.js-product-restore').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = this.dataset.name;
        var url  = this.dataset.url;
        if (!confirm('Restore "' + name + '"?')) return;
        postAjax(url, {}, function (resp) {
          if (resp.success) { AAM.toast('Product restored', 'success'); reloadAfter(600); }
          else { AAM.toast(resp.message || 'Restore failed', 'error'); }
        });
      });
    });
  }

  /* ─── Live Search (debounced) ─────────────────────────── */
  function initLiveSearch() {
    var input     = document.getElementById('liveProductSearch');
    var container = document.getElementById('liveSearchResults');
    if (!input || !container) return;

    var timer;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = this.value.trim();
      if (q.length < 2) { container.innerHTML = ''; return; }
      timer = setTimeout(function () {
        var catId = document.getElementById('searchCategoryFilter')
                    ? document.getElementById('searchCategoryFilter').value
                    : '';
        var url = window.AAM_ROUTES.productSearch
                + '?q=' + encodeURIComponent(q)
                + '&category_id=' + encodeURIComponent(catId);
        getAjax(url, function (resp) {
          if (!resp.success || !resp.products.length) {
            container.innerHTML = '<div class="p-3 text-muted small text-center">No results</div>';
            return;
          }
          container.innerHTML = resp.products.map(function (p) {
            return '<a href="' + window.AAM_ROUTES.productBase + '/' + p.product_id + '"'
              + ' class="d-flex align-items-center gap-2 p-2 text-decoration-none text-dark border-bottom live-search-item">'
              + '<span class="font-monospace small text-muted">' + escHtml(p.product_code) + '</span>'
              + '<span class="fw-semibold flex-grow-1">' + escHtml(p.product_name) + '</span>'
              + '<span class="badge bg-success">ETB ' + parseFloat(p.selling_price).toFixed(2) + '</span>'
              + '</a>';
          }).join('');
        });
      }, 300);
    });

    // Hide results when clicking outside
    document.addEventListener('click', function (e) {
      if (!input.contains(e.target) && !container.contains(e.target)) {
        container.innerHTML = '';
      }
    });
  }

  /* ─── Barcode Quick Lookup ─────────────────────────────── */
  function initBarcodeSearch() {
    var input     = document.getElementById('barcodeSearchInput');
    var btn       = document.getElementById('barcodeSearchBtn');
    var resultEl  = document.getElementById('barcodeResult');
    if (!input || !btn) return;

    function doLookup() {
      var code = input.value.trim();
      if (!code) return;
      resultEl.innerHTML = '<span class="spinner-border spinner-border-sm text-info me-2"></span>';
      getAjax(window.AAM_ROUTES.barcodeSearch + '?barcode=' + encodeURIComponent(code),
        function (resp) {
          if (resp.success) {
            var p = resp.product;
            resultEl.innerHTML =
              '<div class="d-flex align-items-center gap-2 flex-wrap">'
              + '<i class="bi bi-check-circle-fill text-success"></i>'
              + '<strong>' + escHtml(p.product_name) + '</strong>'
              + '<span class="font-monospace text-muted small">' + escHtml(p.product_code) + '</span>'
              + '<span class="badge bg-success">ETB ' + parseFloat(p.selling_price).toFixed(2) + '</span>'
              + '<span class="badge bg-light text-dark border">' + escHtml(p.category_name) + '</span>'
              + '<a href="' + window.AAM_ROUTES.productBase + '/' + p.product_id + '"'
              + '   class="btn btn-sm btn-outline-primary py-0">View</a>'
              + '</div>';
          } else {
            resultEl.innerHTML =
              '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>'
              + 'Not found: <strong>' + escHtml(code) + '</strong></span>';
          }
        });
    }

    btn.addEventListener('click', doLookup);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
    });
  }

  /* ─── Barcode Generate / Preview ─────────────────────── */
  function initBarcodeGenerate() {
    var genBtn    = document.getElementById('generateBarcodeBtn');
    var preBtn    = document.getElementById('previewBarcodeBtn');
    var codeInput = document.getElementById('barcodeInput');
    var previewEl = document.getElementById('barcodePreviewSvg');
    var container = document.getElementById('barcodePreviewContainer');
    if (!codeInput) return;

    function fetchSvg(code) {
      if (!code) return;
      getAjax(window.AAM_ROUTES.barcodeSvg + '?code=' + encodeURIComponent(code),
        function (resp) {
          if (resp.success && previewEl) {
            previewEl.innerHTML = resp.svg;
            if (container) container.style.display = '';
          }
        });
    }

    if (genBtn) {
      genBtn.addEventListener('click', function () {
        genBtn.disabled = true;
        getAjax(window.AAM_ROUTES.barcodeGenerate, function (resp) {
          if (resp.success) {
            var barcode = resp.barcode || (resp.data && resp.data.barcode);
            if (barcode) { codeInput.value = barcode; fetchSvg(barcode); }
          }
          genBtn.disabled = false;
        });
      });
    }

    if (preBtn) {
      preBtn.addEventListener('click', function () { fetchSvg(codeInput.value.trim()); });
    }

    codeInput.addEventListener('input', function () {
      var v = this.value.trim();
      if (v.length >= 8) fetchSvg(v);
    });

    // Show initial preview
    if (codeInput.value.trim()) fetchSvg(codeInput.value.trim());
  }

  /* ─── Image Preview (before upload) ──────────────────── */
  function initImagePreview() {
    var fileInput   = document.getElementById('imageUploadInput');
    var preview     = document.getElementById('imagePreview');
    var placeholder = document.getElementById('imagePlaceholder');
    var clearBtn    = document.getElementById('clearImageBtn');
    var dropZone    = document.getElementById('imagePreviewContainer');
    if (!fileInput) return;

    function handleFile(file) {
      var allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (!allowed.includes(file.type)) {
        AAM.toast('Only JPEG, PNG, WEBP allowed', 'error'); return;
      }
      if (file.size > 5 * 1024 * 1024) {
        AAM.toast('File too large (max 5MB)', 'error'); return;
      }
      var reader = new FileReader();
      reader.onload = function (e) {
        if (preview) {
          preview.src = e.target.result;
          preview.classList.remove('d-none');
        }
        if (placeholder) placeholder.style.display = 'none';
        if (clearBtn) clearBtn.style.display = '';
      };
      reader.readAsDataURL(file);
    }

    fileInput.addEventListener('change', function () {
      if (this.files[0]) handleFile(this.files[0]);
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        fileInput.value = '';
        if (preview) { preview.src = '#'; preview.classList.add('d-none'); }
        if (placeholder) placeholder.style.display = '';
        this.style.display = 'none';
      });
    }

    if (dropZone) {
      dropZone.addEventListener('dragover',  function (e) { e.preventDefault(); this.classList.add('dragover-active'); });
      dropZone.addEventListener('dragleave', function ()  { this.classList.remove('dragover-active'); });
      dropZone.addEventListener('drop', function (e) {
        e.preventDefault(); this.classList.remove('dragover-active');
        var files = e.dataTransfer.files;
        if (files.length) {
          fileInput.files = files;
          handleFile(files[0]);
        }
      });
    }
  }

  /* ─── Markup Calculator ───────────────────────────────── */
  function initMarkupCalc() {
    var costEl   = document.getElementById('costPriceInput');
    var sellEl   = document.getElementById('sellingPriceInput');
    var markupEl = document.getElementById('markupDisplay');
    if (!costEl || !sellEl || !markupEl) return;

    function calc() {
      var cost = parseFloat(costEl.value) || 0;
      var sell = parseFloat(sellEl.value) || 0;
      if (cost > 0) {
        var m = ((sell - cost) / cost * 100).toFixed(1);
        markupEl.value = m + '%';
        markupEl.classList.toggle('text-danger', parseFloat(m) < 0);
        markupEl.classList.toggle('text-success', parseFloat(m) >= 0);
      } else {
        markupEl.value = '—';
      }
    }

    costEl.addEventListener('input', calc);
    sellEl.addEventListener('input', calc);
  }

  /* ─── Bulk Select & Batch Print ──────────────────────── */
  function initBulkActions() {
    var selectAll  = document.getElementById('selectAll');
    var countEl    = document.getElementById('selectedCount');
    var numEl      = document.getElementById('selectedNum');
    var printBtn   = document.getElementById('batchPrintBtn');
    var batchForm  = document.getElementById('batchForm');

    function updateUI() {
      var checked = document.querySelectorAll('.row-check:checked').length;
      if (countEl) countEl.style.display = checked > 0 ? 'inline' : 'none';
      if (numEl)   numEl.textContent = checked;
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(function (cb) {
          cb.checked = selectAll.checked;
        });
        updateUI();
      });
    }

    document.querySelectorAll('.row-check').forEach(function (cb) {
      cb.addEventListener('change', updateUI);
    });

    if (printBtn && batchForm) {
      printBtn.addEventListener('click', function () {
        var ids = Array.from(document.querySelectorAll('.row-check:checked'))
                       .map(function (cb) { return cb.value; });
        var bi = document.getElementById('batchInputs');
        if (bi) {
          bi.innerHTML = ids.map(function (id) {
            return '<input type="hidden" name="product_ids[]" value="' + id + '">';
          }).join('');
        }
        batchForm.submit();
      });
    }
  }

  /* ─── Escape HTML helper ──────────────────────────────── */
  function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
  }

  /* ─── Auto-submit filter selects ─────────────────────── */
  function initAutoFilterSubmit() {
    var form = document.getElementById('filterForm');
    if (!form) return;
    ['categoryFilter', 'statusFilter', 'typeFilter'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () { form.submit(); });
    });
  }

  /* ─── Bootstrap Tooltips ──────────────────────────────── */
  function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new bootstrap.Tooltip(el);
    });
  }

  /* ─── Init all ─────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initToggleStatus();
    initToggleFeatured();
    initDelete();
    initRestore();
    initLiveSearch();
    initBarcodeSearch();
    initBarcodeGenerate();
    initImagePreview();
    initMarkupCalc();
    initBulkActions();
    initAutoFilterSubmit();
    initTooltips();
  });

})(window.AAM || {});
