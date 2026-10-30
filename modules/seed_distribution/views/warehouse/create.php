<?php
declare(strict_types=1);
$warehouse ??= [];
$seedStock ??= [];
$allGrades ??= [];
$prefillProduct = (int)($_GET['product_id'] ?? 0);
$prefillGrade   = (int)($_GET['seed_grade_id'] ?? 0);

// Build grades map: product_id => [grades]
// Build price map:  product_id => unit_price
$gradesMap = [];
$priceMap  = [];
foreach ($allGrades as $g) {
    $gradesMap[(int)$g['product_id']][] = $g;
}
foreach ($seedStock as $s) {
    $pid = (int)$s['product_id'];
    $priceMap[$pid] = (float)($s['unit_price'] ?? 0);
}
$gradesJson = json_encode($gradesMap, JSON_HEX_APOS | JSON_HEX_TAG);
$priceJson  = json_encode($priceMap,  JSON_HEX_APOS | JSON_HEX_TAG);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle-fill me-2 text-success"></i>New Seed Distribution</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('warehouse') ?>">Warehouse</a></li>
        <li class="breadcrumb-item"><a href="<?= url('seed-distribution/seeds') ?>">Available Seeds</a></li>
        <li class="breadcrumb-item active">New Distribution</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('seed-distribution/seeds') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<div id="formErrors" class="alert alert-danger d-none mb-3">
  <strong>Please fix:</strong>
  <ul class="mb-0 mt-1 ps-3" id="errorList"></ul>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <form id="seedDistForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <!-- Card: Seed Info -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-success bg-opacity-10 border-0">
          <span class="fw-semibold"><i class="bi bi-flower1 me-2 text-success"></i>Seed Information</span>
        </div>
        <div class="card-body p-4">

          <!-- Distribution Type -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Distribution Type <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="distribution_type"
                       id="typePickup" value="warehouse_pickup" checked>
                <label class="form-check-label" for="typePickup">
                  <i class="bi bi-building me-1"></i>Warehouse Pickup
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="distribution_type"
                       id="typeField" value="field_distribution">
                <label class="form-check-label" for="typeField">
                  <i class="bi bi-geo-alt me-1"></i>Field / Location Distribution
                </label>
              </div>
            </div>
          </div>

          <!-- Seed Product -->
          <div class="mb-3">
            <label for="product_id" class="form-label fw-semibold">Seed Product <span class="text-danger">*</span></label>
            <select class="form-select" id="product_id" name="product_id" required>
              <option value="">— Select seed product —</option>
              <?php foreach ($seedStock as $s): ?>
              <?php $hasPrice = (float)($s['unit_price'] ?? 0) > 0; ?>
              <option value="<?= (int)$s['product_id'] ?>"
                      data-unit-id="<?= (int)($s['unit_id'] ?? 0) ?>"
                      data-unit-abbr="<?= e($s['unit_abbr'] ?? '') ?>"
                      data-available="<?= (float)$s['available_quantity'] ?>"
                      data-unit-price="<?= (float)($s['unit_price'] ?? 0) ?>"
                      <?= $prefillProduct === (int)$s['product_id'] ? 'selected' : '' ?>>
                <?= e($s['product_name']) ?>
                (<?= number_format((float)$s['available_quantity'], 2) ?> <?= e($s['unit_abbr'] ?? '') ?>)
                <?= !$hasPrice ? '⚠ No price set' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Grade / Class -->
          <div class="mb-3" id="gradeSection">
            <label for="seed_grade_id" class="form-label fw-semibold">Grade / Class</label>
            <select class="form-select" id="seed_grade_id" name="seed_grade_id">
              <option value="">— Select grade (if applicable) —</option>
            </select>
            <div class="form-text">Select a grade/class for this seed product.</div>
          </div>

          <!-- Quantity + Date -->
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label for="quantity" class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" step="0.001" min="0.001" class="form-control"
                       id="quantity" name="quantity" placeholder="0.000" required>
                <span class="input-group-text" id="unitDisplay">units</span>
              </div>
              <div id="stockHint" class="form-text text-muted"></div>
            </div>
            <div class="col-md-6">
              <label for="distribution_date" class="form-label fw-semibold">Distribution Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="distribution_date" name="distribution_date"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <!-- ── Pricing Panel ─────────────────────────────────── -->
          <div id="pricingPanel" class="d-none mb-3">
            <div class="card border border-success-subtle bg-success-subtle rounded-2">
              <div class="card-body p-3">
                <p class="fw-semibold small mb-2 text-success-emphasis">
                  <i class="bi bi-tag-fill me-1"></i>GM-Configured Seed Price
                </p>
                <div class="row g-2 align-items-center">
                  <div class="col-sm-5">
                    <label class="form-label small text-muted mb-1">Unit Price</label>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">ETB</span>
                      <input type="text" class="form-control bg-white text-success fw-bold"
                             id="unitPriceDisplay" readonly tabindex="-1"
                             placeholder="—">
                      <span class="input-group-text" id="unitPriceUnit"></span>
                    </div>
                    <div class="form-text text-muted" style="font-size:.7rem;">
                      Set by General Manager — read-only
                    </div>
                  </div>
                  <div class="col-sm-2 text-center">
                    <span class="text-muted fw-bold fs-5">×</span>
                  </div>
                  <div class="col-sm-5">
                    <label class="form-label small text-muted mb-1">Total Amount</label>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">ETB</span>
                      <input type="text" class="form-control bg-white fw-bold fs-6 text-dark"
                             id="totalAmountDisplay" readonly tabindex="-1"
                             placeholder="0.00">
                    </div>
                    <div class="form-text text-muted" style="font-size:.7rem;">
                      Quantity × Unit Price
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- No-Price Warning -->
          <div id="noPriceWarning" class="d-none alert alert-warning py-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Price not configured.</strong>
            This seed has no GM-configured price. Distribution cannot proceed until
            the General Manager sets a price for this product.
          </div>

          <!-- Destination Location -->
          <div class="mb-3">
            <label for="destination_location" class="form-label fw-semibold">Destination / Location</label>
            <input type="text" class="form-control" id="destination_location" name="destination_location"
                   maxlength="500" placeholder="e.g. Bale Zone, Robe Town, Arsi Zone…">
            <div class="form-text">Where seed will be distributed / received.</div>
          </div>

        </div>
      </div>

      <!-- Card: Recipient -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-primary bg-opacity-10 border-0">
          <span class="fw-semibold"><i class="bi bi-person-fill me-2 text-primary"></i>Recipient Information</span>
        </div>
        <div class="card-body p-4">

          <div class="row g-3">
            <div class="col-md-6">
              <label for="recipient_name" class="form-label fw-semibold">Farmer / Recipient Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="recipient_name" name="recipient_name"
                     maxlength="200" placeholder="Full name" required>
            </div>
            <div class="col-md-6">
              <label for="recipient_phone" class="form-label fw-semibold">Phone</label>
              <input type="text" class="form-control" id="recipient_phone" name="recipient_phone"
                     maxlength="30" placeholder="09XXXXXXXX">
            </div>
            <div class="col-md-6">
              <label for="recipient_organization" class="form-label fw-semibold">Organization / Group</label>
              <input type="text" class="form-control" id="recipient_organization" name="recipient_organization"
                     maxlength="200" placeholder="Farmers cooperative, group name…">
            </div>
            <div class="col-md-6">
              <label for="program_name" class="form-label fw-semibold">Program / Purpose</label>
              <input type="text" class="form-control" id="program_name" name="program_name"
                     maxlength="200" placeholder="e.g. Wheat Improvement Program 2026">
            </div>
            <div class="col-12">
              <label for="recipient_notes" class="form-label fw-semibold">Recipient Notes</label>
              <textarea class="form-control" id="recipient_notes" name="recipient_notes"
                        rows="2" maxlength="500" placeholder="Additional recipient info…"></textarea>
            </div>
          </div>

        </div>
      </div>

      <!-- Notes -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
          <label for="notes" class="form-label fw-semibold">Notes</label>
          <textarea class="form-control" id="notes" name="notes"
                    rows="3" maxlength="1000" placeholder="Optional notes…"></textarea>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-4" id="submitBtn">
          <i class="bi bi-send-fill me-1"></i>Submit for Approval
        </button>
        <a href="<?= url('seed-distribution/seeds') ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Info panel -->
  <div class="col-lg-4">
    <div class="card border-0 bg-success-subtle mb-3">
      <div class="card-body p-3">
        <h6 class="fw-bold text-success mb-2"><i class="bi bi-info-circle me-1"></i>Workflow</h6>
        <ol class="small mb-0 ps-3">
          <li>Submit distribution → <strong>Pending</strong></li>
          <li>GM reviews and approves</li>
          <li>Approved → Warehouse releases seed</li>
          <li>Stock is deducted from inventory</li>
          <li>Distribution recorded in history</li>
        </ol>
      </div>
    </div>
    <div class="card border-0 border-warning bg-warning-subtle mb-3">
      <div class="card-body p-3">
        <h6 class="fw-bold text-warning-emphasis mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Stock Rules</h6>
        <ul class="small mb-0 ps-3">
          <li>Quantity must not exceed available stock</li>
          <li>Stock is deducted only after GM approval + release</li>
          <li>Negative inventory is not allowed</li>
        </ul>
      </div>
    </div>
    <div class="card border-0 border-info bg-info-subtle">
      <div class="card-body p-3">
        <h6 class="fw-bold text-info-emphasis mb-2"><i class="bi bi-tag-fill me-1"></i>Pricing</h6>
        <ul class="small mb-0 ps-3">
          <li>Unit price is set by the General Manager</li>
          <li>Price is shown automatically when you select a seed</li>
          <li>Total = Quantity × Unit Price</li>
          <li>The price is locked at submission time</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function(){
  'use strict';

  var gradesMap     = <?= $gradesJson ?>;
  var priceMap      = <?= $priceJson ?>;
  var prefillPid    = <?= $prefillProduct ?>;
  var prefillGid    = <?= $prefillGrade ?>;
  var currentPrice  = 0;

  var prodSel         = document.getElementById('product_id');
  var gradeSel        = document.getElementById('seed_grade_id');
  var unitDisp        = document.getElementById('unitDisplay');
  var stockHint       = document.getElementById('stockHint');
  var errBox          = document.getElementById('formErrors');
  var errList         = document.getElementById('errorList');
  var form            = document.getElementById('seedDistForm');
  var submitBtn       = document.getElementById('submitBtn');
  var pricingPanel    = document.getElementById('pricingPanel');
  var noPriceWarning  = document.getElementById('noPriceWarning');
  var unitPriceDisp   = document.getElementById('unitPriceDisplay');
  var unitPriceUnit   = document.getElementById('unitPriceUnit');
  var totalAmountDisp = document.getElementById('totalAmountDisplay');
  var qtyInput        = document.getElementById('quantity');

  function populateGrades(productId) {
    var grades = gradesMap[productId] || [];
    gradeSel.innerHTML = '<option value="">— Select grade (if applicable) —</option>';
    grades.forEach(function(g) {
      var opt = document.createElement('option');
      opt.value = g.id;
      opt.textContent = g.grade_name;
      if (g.id == prefillGid) opt.selected = true;
      gradeSel.appendChild(opt);
    });
  }

  function updateStockHint() {
    var sel = prodSel.options[prodSel.selectedIndex];
    if (!sel || !sel.value) {
      unitDisp.textContent = 'units';
      stockHint.textContent = '';
      return;
    }
    var abbr  = sel.dataset.unitAbbr || 'units';
    var avail = parseFloat(sel.dataset.available) || 0;
    unitDisp.textContent = abbr;
    stockHint.textContent = 'Available: ' + avail.toFixed(2) + ' ' + abbr;
    stockHint.className = avail > 0 ? 'form-text text-success' : 'form-text text-danger';
  }

  function updatePricing() {
    var sel = prodSel.options[prodSel.selectedIndex];
    if (!sel || !sel.value) {
      pricingPanel.classList.add('d-none');
      noPriceWarning.classList.add('d-none');
      currentPrice = 0;
      return;
    }

    var pid   = parseInt(sel.value, 10);
    var price = priceMap[pid] || 0;
    var abbr  = sel.dataset.unitAbbr || 'unit';

    currentPrice = price;

    if (price > 0) {
      pricingPanel.classList.remove('d-none');
      noPriceWarning.classList.add('d-none');
      unitPriceDisp.value = price.toFixed(2);
      unitPriceUnit.textContent = abbr;
      recalcTotal();
    } else {
      pricingPanel.classList.add('d-none');
      noPriceWarning.classList.remove('d-none');
    }
  }

  function recalcTotal() {
    if (currentPrice <= 0) {
      totalAmountDisp.value = '0.00';
      return;
    }
    var qty = parseFloat(qtyInput.value) || 0;
    var total = qty * currentPrice;
    totalAmountDisp.value = total.toFixed(2);
  }

  prodSel.addEventListener('change', function(){
    populateGrades(this.value);
    updateStockHint();
    updatePricing();
  });

  qtyInput.addEventListener('input', recalcTotal);
  qtyInput.addEventListener('change', recalcTotal);

  // Pre-fill
  if (prefillPid) {
    prodSel.value = prefillPid;
    populateGrades(prefillPid);
    updateStockHint();
    updatePricing();
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    errBox.classList.add('d-none'); errList.innerHTML = '';

    var errs = [];
    if (!prodSel.value) errs.push('Seed product is required.');

    // Price check before quantity check
    if (prodSel.value && currentPrice <= 0) {
      errs.push('Price has not been configured for this seed. Please contact the General Manager.');
    }

    var qty = parseFloat(qtyInput.value);
    if (!qty || qty <= 0) errs.push('Quantity must be greater than zero.');

    // Stock check
    if (prodSel.value && qty > 0) {
      var sel   = prodSel.options[prodSel.selectedIndex];
      var avail = parseFloat(sel.dataset.available) || 0;
      if (qty > avail) {
        errs.push('Quantity (' + qty.toFixed(3) + ') exceeds available stock (' + avail.toFixed(3) + ').');
      }
    }

    if (!document.getElementById('recipient_name').value.trim()) errs.push('Recipient name is required.');
    if (!document.getElementById('distribution_date').value) errs.push('Distribution date is required.');

    if (errs.length) {
      errs.forEach(function(m){ errList.innerHTML += '<li>' + m + '</li>'; });
      errBox.classList.remove('d-none');
      window.scrollTo(0,0);
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';

    fetch('<?= url('seed-distribution') ?>', {
      method: 'POST', credentials: 'same-origin',
      headers: {'X-Requested-With':'XMLHttpRequest'},
      body: new FormData(form)
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
      if (resp.success) {
        window.location.href = resp.data && resp.data.redirect
          ? resp.data.redirect
          : '<?= url('seed-distribution/pending') ?>';
      } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Submit for Approval';
        errList.innerHTML = '<li>' + (resp.message || 'Error occurred') + '</li>';
        errBox.classList.remove('d-none');
        window.scrollTo(0,0);
      }
    })
    .catch(function(){
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Submit for Approval';
      errList.innerHTML = '<li>Network error. Please try again.</li>';
      errBox.classList.remove('d-none');
    });
  });

})();
</script>
<?php $view->endSection(); ?>
