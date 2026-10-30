<?php
/**
 * Receiving Confirmation Form — Phase 10
 * @var array $dispatch @var array $warehouse @var int $warehouseId @var string $today
 */
declare(strict_types=1);
$dispatch  ??= [];
$warehouse ??= ['name' => 'Warehouse'];
$items     = $dispatch['items'] ?? [];
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= url('warehouse/receiving') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-check2-circle me-2 text-success"></i>
      Confirm Receiving — <?= e($dispatch['dispatch_number'] ?? '') ?>
    </h5>
    <small class="text-muted">
      From: <?= e($dispatch['farm_name'] ?? '—') ?>
      &middot; Dispatched: <?= $dispatch['dispatch_date'] ? date('d M Y', strtotime($dispatch['dispatch_date'])) : '—' ?>
      &middot; Sent by: <?= e($dispatch['initiated_by_name'] ?? '—') ?>
    </small>
  </div>
</div>

<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-info-circle-fill flex-shrink-0"></i>
  <div class="small">
    Enter the <strong>actual quantities received</strong> for each product.
    If the received quantity differs from the dispatched quantity, a <strong>discrepancy reason is required</strong>.
    The discrepancy will be submitted for <strong>General Manager review and approval</strong>
    before inventory is updated.
  </div>
</div>

<form method="POST" action="<?= url('warehouse/receiving/' . $dispatch['id'] . '/confirm') ?>"
      id="receivingForm">
  <?php csrf_input(); ?>

  <!-- Items -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold small">
      <i class="bi bi-list-check me-2 text-success"></i>Products to Receive
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Product</th>
              <th>Batch / Lot</th>
              <th>Production Date</th>
              <th>Expiry Date</th>
              <th>Qty Dispatched</th>
              <th class="text-success">Qty Received *</th>
              <th>Variance</th>
              <th class="pe-3">Discrepancy Reason</th>
            </tr>
          </thead>
          <tbody id="itemsBody">
            <?php foreach ($items as $i => $item): ?>
            <tr>
              <input type="hidden" name="item_ids[]"          value="<?= $i ?>">
              <input type="hidden" name="dispatch_item_ids[]" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="product_ids[]"       value="<?= (int)$item['product_id'] ?>">
              <input type="hidden" name="unit_ids[]"          value="<?= (int)($item['unit_id'] ?? 0) ?>">
              <input type="hidden" name="quantity_expected[]" value="<?= (float)$item['quantity_dispatched'] ?>">

              <td class="ps-3">
                <div class="fw-semibold small"><?= e($item['product_name'] ?? '—') ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= e($item['product_sku'] ?? '') ?></div>
              </td>
              <td>
                <input type="text" name="batch_numbers[]"
                       class="form-control form-control-sm" style="min-width:100px;"
                       value="<?= e($item['batch_number'] ?? '') ?>"
                       placeholder="Batch #">
              </td>
              <td>
                <input type="date" name="production_dates[]"
                       class="form-control form-control-sm" style="min-width:130px;"
                       value="<?= e($item['production_date'] ?? '') ?>">
              </td>
              <td>
                <input type="date" name="expiry_dates[]"
                       class="form-control form-control-sm expiry-date" style="min-width:130px;"
                       value="<?= e($item['expiry_date'] ?? '') ?>">
              </td>
              <td class="small fw-bold text-primary">
                <?= number_format((float)$item['quantity_dispatched'], 3) ?>
                <?= e($item['unit_abbr'] ?? '') ?>
              </td>
              <td>
                <input type="number" name="quantity_received[]"
                       class="form-control form-control-sm qty-received" style="min-width:100px;"
                       step="0.001" min="0"
                       value="<?= number_format((float)$item['quantity_dispatched'], 3, '.', '') ?>"
                       data-expected="<?= (float)$item['quantity_dispatched'] ?>"
                       required>
              </td>
              <td class="small variance-cell text-muted">—</td>
              <td class="pe-3 discrepancy-col" style="display:none;">
                <input type="text" name="discrepancy_reasons[]"
                       class="form-control form-control-sm"
                       placeholder="Reason for variance"
                       style="min-width:160px;">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Notes -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label small fw-semibold">Notes (optional)</label>
      <textarea name="notes" class="form-control form-control-sm" rows="2"
                placeholder="Additional notes for this receiving…"></textarea>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= url('warehouse/receiving') ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-success" id="submitBtn">
      <i class="bi bi-check2-circle me-1"></i>Confirm Receiving
    </button>
  </div>
</form>

<!-- Discrepancy workflow notice (shown by JS) -->
<div id="discrepancyNotice" class="alert alert-warning d-none mt-3 small">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <strong>Discrepancy Workflow:</strong>
  Because the received quantity differs from what was dispatched, a <strong>discrepancy reason
  is required</strong> for each affected line. Submitting will send this receiving for
  <strong>General Manager review and approval</strong>.
  Warehouse inventory will only be updated <em>after</em> the GM approves.
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  document.querySelectorAll('.qty-received').forEach(function (input) {
    input.addEventListener('input', function () {
      var row        = input.closest('tr');
      var expected   = parseFloat(input.dataset.expected) || 0;
      var received   = parseFloat(input.value) || 0;
      var variance   = expected - received;
      var varCell    = row.querySelector('.variance-cell');
      var disCol     = row.querySelector('.discrepancy-col');
      var disInput   = disCol ? disCol.querySelector('input') : null;
      var anyDiscrepancy = false;

      if (Math.abs(variance) > 0.001) {
        var pct = expected > 0 ? ((Math.abs(variance) / expected) * 100).toFixed(1) : 0;
        varCell.innerHTML = '<span class="badge bg-' + (variance > 0 ? 'danger' : 'warning') + '">' +
          (variance > 0 ? '-' : '+') + Math.abs(variance).toFixed(3) + ' (' + pct + '%)</span>';
        if (disCol) disCol.style.display = '';
        // Mark reason as REQUIRED when there is a variance
        if (disInput) { disInput.required = true; disInput.placeholder = 'Reason REQUIRED'; }
      } else {
        varCell.innerHTML = '<span class="text-success small"><i class="bi bi-check2"></i></span>';
        if (disCol) disCol.style.display = 'none';
        if (disInput) { disInput.required = false; disInput.placeholder = 'Reason for variance'; }
      }

      // Show workflow notice if any row has a discrepancy
      anyDiscrepancy = Array.from(document.querySelectorAll('.qty-received')).some(function(i) {
        var e2 = parseFloat(i.dataset.expected) || 0;
        var r2 = parseFloat(i.value) || 0;
        return Math.abs(e2 - r2) > 0.001;
      });
      var notice = document.getElementById('discrepancyNotice');
      if (notice) notice.classList.toggle('d-none', !anyDiscrepancy);

      // Update submit button label
      var btn = document.getElementById('submitBtn');
      if (btn) {
        if (anyDiscrepancy) {
          btn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Submit Discrepancy for GM Review';
          btn.className = 'btn btn-warning';
        } else {
          btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirm Receiving';
          btn.className = 'btn btn-success';
        }
      }
    });

    // Trigger once on load
    input.dispatchEvent(new Event('input'));
  });

  // Warn on expiry date in the past
  document.querySelectorAll('.expiry-date').forEach(function (input) {
    input.addEventListener('change', function () {
      var val = new Date(input.value);
      var now = new Date();
      if (val < now) {
        input.classList.add('is-invalid');
      } else {
        input.classList.remove('is-invalid');
      }
    });
  });

  document.getElementById('receivingForm').addEventListener('submit', function (e) {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';
  });
})();
</script>
<?php $view->endSection(); ?>
