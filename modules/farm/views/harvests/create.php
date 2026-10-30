<?php
/**
 * =========================================================
 * AAM — Farm Module — Record Harvest
 * =========================================================
 * @var \AAM\Core\View $view
 * @var array          $farm
 * @var array          $products
 * @var array          $units
 * @var string         $nextBatch
 * @var string         $today
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

$farmId   = (int)$farm['id'];
$oldInput = $oldInput ?? [];
$old      = fn(string $k, string $d = '') => e($oldInput[$k] ?? $d);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-basket-fill me-2 text-success"></i>Record Harvest
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('farms') ?>">Farms</a></li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId) ?>"><?= e($farm['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId . '/harvests') ?>">Harvest</a>
        </li>
        <li class="breadcrumb-item active">Record</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('farms/' . $farmId . '/harvests') ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php $view->flash(); ?>

<form method="POST" action="<?= url('farms/' . $farmId . '/harvests') ?>"
      id="harvestForm" novalidate>
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="row g-3">

    <!-- ── LEFT ──────────────────────────────────────────── -->
    <div class="col-lg-8">

      <!-- Farm Context Banner -->
      <div class="alert alert-success d-flex align-items-center gap-3 py-2 mb-3">
        <i class="bi bi-flower1 fs-4 flex-shrink-0"></i>
        <div>
          <div class="fw-semibold"><?= e($farm['name']) ?></div>
          <?php if (!empty($farm['location'])): ?>
          <small class="opacity-75"><?= e($farm['location']) ?></small>
          <?php endif; ?>
        </div>
      </div>

      <!-- Core Harvest Info -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-info-circle me-2 text-success"></i>Harvest Information
          </h6>
        </div>
        <div class="card-body p-3">
          <div class="row g-3">

            <!-- Batch Number -->
            <div class="col-md-5">
              <label class="form-label fw-semibold">
                Batch Number <span class="text-muted fw-normal small">(auto-generated)</span>
              </label>
              <input type="text" class="form-control font-monospace <?= $view->isInvalid('batch_number') ?>"
                     name="batch_number" value="<?= $old('batch_number', $nextBatch) ?>"
                     maxlength="50" autocomplete="off"
                     placeholder="e.g. HARV-2026-0001">
              <?php $view->fieldError('batch_number'); ?>
              <div class="form-text">Leave blank to auto-generate.</div>
            </div>

            <!-- Product -->
            <div class="col-md-7">
              <label class="form-label fw-semibold">
                Product <span class="text-danger">*</span>
              </label>
              <select class="form-select <?= $view->isInvalid('product_id') ?>"
                      name="product_id" id="productSelect" required>
                <option value="">— Select Product —</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>"
                        data-perishable="<?= (int)$p['is_perishable'] ?>"
                        data-shelf="<?= (int)($p['shelf_life_days'] ?? 0) ?>"
                  <?= ($oldInput['product_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                  <?= e($p['name']) ?>
                  <?php if (!empty($p['sku'])): ?>(<?= e($p['sku']) ?>)<?php endif; ?>
                </option>
                <?php endforeach; ?>
              </select>
              <?php $view->fieldError('product_id'); ?>
            </div>

            <!-- Quantity -->
            <div class="col-md-5">
              <label class="form-label fw-semibold">
                Quantity <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="number" class="form-control <?= $view->isInvalid('initial_quantity') ?>"
                       name="initial_quantity" id="qtyInput"
                       value="<?= $old('initial_quantity', '') ?>"
                       min="0.001" step="0.001" required
                       placeholder="0.000">
                <select class="form-select" name="unit_id" id="unitSelect"
                        style="max-width:130px;" required>
                  <option value="">Unit</option>
                  <?php foreach ($units as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"
                    <?= ($oldInput['unit_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                    <?= e($u['name']) ?> (<?= e($u['abbreviation']) ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php $view->fieldError('initial_quantity'); ?>
              <?php $view->fieldError('unit_id'); ?>
            </div>

            <!-- Quality Grade -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">
                Quality Grade <span class="text-danger">*</span>
              </label>
              <select class="form-select <?= $view->isInvalid('quality_grade') ?>"
                      name="quality_grade" required>
                <option value="">— Select Grade —</option>
                <option value="A" <?= ($oldInput['quality_grade'] ?? '') === 'A' ? 'selected' : '' ?>>
                  Grade A — Good
                </option>
                <option value="B" <?= ($oldInput['quality_grade'] ?? '') === 'B' ? 'selected' : '' ?>>
                  Grade B — Acceptable
                </option>
                <option value="C" <?= ($oldInput['quality_grade'] ?? '') === 'C' ? 'selected' : '' ?>>
                  Grade C — Rejected
                </option>
                <option value="Ungraded" <?= ($oldInput['quality_grade'] ?? '') === 'Ungraded' ? 'selected' : '' ?>>
                  Ungraded
                </option>
              </select>
              <?php $view->fieldError('quality_grade'); ?>
            </div>

            <!-- Dates -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">
                Production Date <span class="text-danger">*</span>
              </label>
              <input type="date" class="form-control <?= $view->isInvalid('production_date') ?>"
                     name="production_date"
                     value="<?= $old('production_date', $today) ?>"
                     max="<?= $today ?>" required>
              <?php $view->fieldError('production_date'); ?>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">
                Harvest Date <span class="text-danger">*</span>
              </label>
              <input type="date" class="form-control <?= $view->isInvalid('harvest_date') ?>"
                     name="harvest_date"
                     value="<?= $old('harvest_date', $today) ?>"
                     max="<?= $today ?>" required>
              <?php $view->fieldError('harvest_date'); ?>
            </div>

            <div class="col-md-4" id="expiryGroup">
              <label class="form-label fw-semibold">Expiry Date</label>
              <input type="date" class="form-control" name="expiry_date" id="expiryDate"
                     value="<?= $old('expiry_date') ?>"
                     min="<?= $today ?>">
              <div class="form-text" id="expiryHint">For perishable products.</div>
            </div>

            <!-- Notes -->
            <div class="col-12">
              <label class="form-label fw-semibold">Notes</label>
              <textarea class="form-control" name="notes" rows="2"
                        placeholder="Optional notes about this harvest…"><?= $old('notes') ?></textarea>
            </div>

          </div>
        </div>
      </div>

    </div><!-- /col-lg-8 -->

    <!-- ── RIGHT ─────────────────────────────────────────── -->
    <div class="col-lg-4">

      <!-- Summary Card -->
      <div class="card border-0 shadow-sm mb-3 border-start border-4 border-success">
        <div class="card-body">
          <h6 class="fw-semibold text-success mb-3">
            <i class="bi bi-clipboard-check me-2"></i>Harvest Summary
          </h6>
          <dl class="mb-0 small">
            <dt class="text-muted">Farm</dt>
            <dd class="fw-semibold"><?= e($farm['name']) ?></dd>
            <dt class="text-muted">Product</dt>
            <dd id="summaryProduct" class="fw-semibold">—</dd>
            <dt class="text-muted">Quantity</dt>
            <dd id="summaryQty" class="fw-bold text-success fs-5">—</dd>
            <dt class="text-muted">Batch</dt>
            <dd id="summaryBatch" class="font-monospace">—</dd>
          </dl>
        </div>
      </div>

      <!-- Quality Alert -->
      <div class="alert alert-warning d-none py-2" id="qualityAlert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Grade C (Rejected)</strong> harvest cannot be dispatched without
        authorized action.
      </div>

      <!-- Submit -->
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <button type="submit" class="btn btn-success w-100 mb-2" id="submitBtn">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner"></span>
            <i class="bi bi-check-circle-fill me-1"></i>Save Harvest Record
          </button>
          <a href="<?= url('farms/' . $farmId . '/harvests') ?>"
             class="btn btn-outline-secondary w-100">Cancel</a>
        </div>
      </div>

    </div><!-- /col-lg-4 -->
  </div><!-- /row -->
</form>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var productSelect = document.getElementById('productSelect');
  var qtyInput      = document.getElementById('qtyInput');
  var unitSelect    = document.getElementById('unitSelect');
  var expiryDate    = document.getElementById('expiryDate');
  var expiryHint    = document.getElementById('expiryHint');
  var qualityAlert  = document.getElementById('qualityAlert');
  var summaryProduct = document.getElementById('summaryProduct');
  var summaryQty     = document.getElementById('summaryQty');
  var summaryBatch   = document.getElementById('summaryBatch');
  var batchInput     = document.querySelector('[name="batch_number"]');
  var qualitySelect  = document.querySelector('[name="quality_grade"]');

  function updateSummary() {
    var productText = productSelect.options[productSelect.selectedIndex]?.text?.split('(')[0]?.trim() || '—';
    var qty         = qtyInput.value || '0';
    var unitText    = unitSelect.options[unitSelect.selectedIndex]?.text?.split('(')[1]?.replace(')','')?.trim() || '';
    var batch       = batchInput.value || '—';

    summaryProduct.textContent = productText;
    summaryQty.textContent     = qty + (unitText ? ' ' + unitText : '');
    summaryBatch.textContent   = batch;

    // Perishable product → suggest expiry
    var opt        = productSelect.options[productSelect.selectedIndex];
    var perishable = opt?.dataset?.perishable === '1';
    var shelfDays  = parseInt(opt?.dataset?.shelf || '0', 10);

    if (perishable && shelfDays > 0 && !expiryDate.value) {
      var d = new Date();
      d.setDate(d.getDate() + shelfDays);
      expiryDate.value = d.toISOString().split('T')[0];
      expiryHint.textContent = 'Suggested based on ' + shelfDays + '-day shelf life.';
    }
  }

  function checkQuality() {
    var grade = qualitySelect.value;
    if (grade === 'C') {
      qualityAlert.classList.remove('d-none');
    } else {
      qualityAlert.classList.add('d-none');
    }
  }

  productSelect?.addEventListener('change', updateSummary);
  qtyInput?.addEventListener('input', updateSummary);
  unitSelect?.addEventListener('change', updateSummary);
  batchInput?.addEventListener('input', updateSummary);
  qualitySelect?.addEventListener('change', checkQuality);

  updateSummary();
  checkQuality();

  // Form submit — disable button to prevent double submit
  document.getElementById('harvestForm')?.addEventListener('submit', function () {
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('spinner').classList.remove('d-none');
  });
})();
</script>
<?php $view->endSection(); ?>
