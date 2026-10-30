<?php
/**
 * Inventory — New Stock Adjustment Form — Phase 11
 * @var array  $products     @var array  $reasons      @var string $locationType
 * @var int    $locationId   @var string $locationName @var string $userRole
 */
declare(strict_types=1);
$products ??= [];
$reasons  ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-sliders me-2 text-warning"></i>
    New Stock Adjustment — <?= e($locationName) ?>
  </h5>
  <a href="<?= url('inventory/adjustments') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-4">
      <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
      <div class="small">
        Submit this adjustment request for General Manager review.
        Once the GM approves, the inventory quantity will be updated and
        a stock movement will be recorded automatically.
        <strong>Inventory is never changed directly — all changes require GM approval.</strong>
      </div>
    </div>

    <form method="POST" action="<?= url('inventory/adjustments') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="lt"  value="<?= e($locationType) ?>">
      <input type="hidden" name="lid" value="<?= (int)$locationId ?>">

      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold small">Product <span class="text-danger">*</span></label>
          <select name="product_id" id="productSelect" class="form-select" required
                  onchange="fillSystemQty(this)">
            <option value="">— Select product —</option>
            <?php foreach ($products as $p): ?>
            <option value="<?= $p['product_id'] ?>"
                    data-qty="<?= (float)$p['current_quantity'] ?>"
                    data-unit="<?= e($p['unit_abbr'] ?? '') ?>"
                    data-unit-id="<?= (int)($p['unit_id'] ?? 0) ?>">
              <?= e($p['product_name']) ?>
              (<?= number_format((float)$p['current_quantity'],3) ?> <?= e($p['unit_abbr']??'') ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-3">
          <label class="form-label fw-semibold small">System Quantity</label>
          <input type="text" id="systemQty" class="form-control bg-light"
                 readonly placeholder="Auto-filled" tabindex="-1">
          <input type="hidden" name="unit_id" id="unitIdField">
        </div>

        <div class="col-6 col-md-3">
          <label class="form-label fw-semibold small">Physical Quantity <span class="text-danger">*</span></label>
          <input type="number" name="physical_quantity" id="physQty"
                 class="form-control" step="0.001" min="0" required
                 placeholder="Enter actual count" onchange="calcVariance()">
        </div>

        <div class="col-6 col-md-3">
          <label class="form-label fw-semibold small">Variance</label>
          <input type="text" id="varianceDisplay" class="form-control bg-light"
                 readonly placeholder="Calculated" tabindex="-1">
        </div>

        <div class="col-6 col-md-3">
          <label class="form-label fw-semibold small">Batch Number</label>
          <input type="text" name="batch_number" class="form-control"
                 placeholder="Optional" maxlength="60">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold small">Reason <span class="text-danger">*</span></label>
          <select name="reason" class="form-select" required>
            <option value="">— Select reason —</option>
            <?php foreach ($reasons as $code => $label): ?>
            <option value="<?= $code ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold small">Notes</label>
          <textarea name="notes" class="form-control" rows="3"
                    placeholder="Additional details (optional)"></textarea>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-warning fw-semibold">
            <i class="bi bi-send me-1"></i>Submit Adjustment Request
          </button>
          <a href="<?= url('inventory/adjustments') ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function fillSystemQty(sel) {
    const opt = sel.options[sel.selectedIndex];
    const qty = parseFloat(opt.dataset.qty || 0);
    const unit = opt.dataset.unit || '';
    document.getElementById('systemQty').value = qty.toFixed(3) + (unit ? ' ' + unit : '');
    document.getElementById('unitIdField').value = opt.dataset.unitId || '';
    calcVariance();
}
function calcVariance() {
    const sel   = document.getElementById('productSelect');
    const opt   = sel.options[sel.selectedIndex];
    const sys   = parseFloat(opt.dataset.qty || 0);
    const phys  = parseFloat(document.getElementById('physQty').value || 0);
    const v     = phys - sys;
    const vd    = document.getElementById('varianceDisplay');
    vd.value    = (v >= 0 ? '+' : '') + v.toFixed(3);
    vd.className = 'form-control bg-light ' +
        (v < 0 ? 'text-danger' : v > 0 ? 'text-success' : 'text-muted');
}
</script>
