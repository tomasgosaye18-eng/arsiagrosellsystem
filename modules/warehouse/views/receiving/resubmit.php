<?php
/**
 * Warehouse Receiving — Resubmit Discrepancy After GM Rejection
 *
 * @var array  $receiving      Full receiving record with items
 * @var array  $warehouse
 * @var array  $discrepancies  Existing discrepancy records (showing GM rejection reason)
 * @var string $userRole
 */
declare(strict_types=1);

use AAM\Warehouse\Models\ReceivingModel;
use AAM\Warehouse\Models\DiscrepancyModel;

$receiving     ??= [];
$discrepancies ??= [];
$items          = $receiving['items'] ?? [];
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= url('warehouse/receiving/' . ($receiving['id'] ?? '')) ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-arrow-repeat me-2 text-warning"></i>
      Resubmit Discrepancy — <?= e($receiving['receiving_number'] ?? '') ?>
    </h5>
    <small class="text-muted">
      Dispatch: <strong><?= e($receiving['dispatch_number'] ?? '—') ?></strong>
      &middot; Farm: <strong><?= e($receiving['farm_name'] ?? '—') ?></strong>
      &middot; Warehouse: <strong><?= e($warehouse['name'] ?? '—') ?></strong>
    </small>
  </div>
</div>

<!-- GM rejection reasons -->
<?php foreach ($discrepancies as $dis):
  if (empty($dis['gm_notes'])) continue;
?>
<div class="alert alert-danger d-flex gap-2 align-items-start mb-3 small">
  <i class="bi bi-x-circle-fill fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <div class="fw-bold mb-1">
      General Manager rejected: <?= e($dis['product_name'] ?? '') ?>
    </div>
    <div><?= nl2br(e($dis['gm_notes'])) ?></div>
    <?php if (!empty($dis['gm_reviewed_by_name'])): ?>
    <div class="text-muted mt-1">
      Rejected by: <?= e($dis['gm_reviewed_by_name']) ?>
      <?= $dis['gm_reviewed_at'] ? '— ' . date('d/m/Y H:i', strtotime($dis['gm_reviewed_at'])) : '' ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="alert alert-warning d-flex gap-2 align-items-start mb-3 small">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
  <div>
    Review the General Manager's rejection reason above, correct the received quantities and
    reasons as needed, then resubmit. The GM will be notified again to review.
    <strong>A reason is required for any quantity that still differs from the dispatched amount.</strong>
  </div>
</div>

<form method="POST"
      action="<?= url('warehouse/receiving/' . ($receiving['id'] ?? '') . '/resubmit') ?>"
      id="resubmitForm">
  <?php csrf_input(); ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold small">
      <i class="bi bi-list-check me-2 text-warning"></i>
      Receiving Items — Correct Quantities
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Product</th>
              <th>Batch</th>
              <th>Expected (Dispatched)</th>
              <th class="text-success fw-semibold">Actual Received <span class="text-danger">*</span></th>
              <th>Variance</th>
              <th class="pe-3">
                Reason <span class="text-danger fw-bold">*</span>
                <small class="text-muted fw-normal">(required if qty differs)</small>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item):
              $expected    = (float)$item['quantity_expected'];
              $prevReceived= (float)$item['quantity_received'];

              // Find previous discrepancy reason for this product (if any)
              $prevReason = '';
              foreach ($discrepancies as $dis) {
                  if ((int)$dis['product_id'] === (int)$item['product_id']) {
                      $prevReason = $dis['reason'] ?? '';
                      break;
                  }
              }
            ?>
            <tr class="recv-row" data-expected="<?= $expected ?>">
              <input type="hidden" name="receiving_item_ids[]" value="<?= (int)$item['id'] ?>">
              <td class="ps-3">
                <div class="fw-semibold small"><?= e($item['product_name'] ?? '—') ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= e($item['product_sku'] ?? '') ?></div>
              </td>
              <td class="small text-muted"><?= e($item['batch_number'] ?? '—') ?></td>
              <td class="small fw-bold text-primary">
                <?= number_format($expected, 3) ?> <?= e($item['unit_abbr'] ?? '') ?>
              </td>
              <td style="min-width:130px;">
                <input type="number"
                       name="quantity_received[]"
                       class="form-control form-control-sm qty-recv"
                       step="0.001" min="0"
                       value="<?= number_format($prevReceived, 3, '.', '') ?>"
                       data-expected="<?= $expected ?>"
                       required>
              </td>
              <td class="small variance-cell text-muted">—</td>
              <td class="pe-3" style="min-width:220px;">
                <input type="text"
                       name="discrepancy_reasons[]"
                       class="form-control form-control-sm discrepancy-reason"
                       placeholder="Required if qty differs…"
                       value="<?= e($prevReason) ?>">
                <div class="invalid-feedback">Reason required when quantity differs.</div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Summary -->
  <div id="discrepancySummary" class="card border-danger border-0 shadow-sm mb-3 d-none">
    <div class="card-body py-2">
      <div class="row g-2 text-center small">
        <div class="col-4">
          <div class="text-muted">Total Expected</div>
          <div class="fw-bold text-primary" id="sumExpected">—</div>
        </div>
        <div class="col-4">
          <div class="text-muted">Total Received</div>
          <div class="fw-bold text-success" id="sumReceived">—</div>
        </div>
        <div class="col-4">
          <div class="text-muted">Still Missing</div>
          <div class="fw-bold text-danger" id="sumMissing">—</div>
        </div>
      </div>
    </div>
  </div>

  <div id="discrepancyNotice" class="alert alert-warning d-none mb-3 small">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Discrepancy still present.</strong>
    This will be sent back to the General Manager for review.
    Warehouse inventory will only be updated after GM approval.
  </div>

  <div id="matchNotice" class="alert alert-success d-none mb-3 small">
    <i class="bi bi-check2-circle me-2"></i>
    <strong>All quantities now match.</strong>
    Submitting this will immediately complete the receiving and update warehouse inventory.
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= url('warehouse/receiving/' . ($receiving['id'] ?? '')) ?>"
       class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-warning" id="submitBtn">
      <i class="bi bi-send me-1"></i>Resubmit for GM Review
    </button>
  </div>
</form>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';
  var rows       = document.querySelectorAll('.recv-row');
  var summaryEl  = document.getElementById('discrepancySummary');
  var warnEl     = document.getElementById('discrepancyNotice');
  var matchEl    = document.getElementById('matchNotice');
  var submitBtn  = document.getElementById('submitBtn');
  var sumExpEl   = document.getElementById('sumExpected');
  var sumRecvEl  = document.getElementById('sumReceived');
  var sumMissEl  = document.getElementById('sumMissing');

  function updateRow(row) {
    var inp     = row.querySelector('.qty-recv');
    var vc      = row.querySelector('.variance-cell');
    var rInput  = row.querySelector('.discrepancy-reason');
    var exp     = parseFloat(inp.dataset.expected) || 0;
    var recv    = parseFloat(inp.value) || 0;
    var diff    = exp - recv;

    if (Math.abs(diff) > 0.001) {
      var pct = exp > 0 ? ((Math.abs(diff) / exp) * 100).toFixed(1) : 0;
      vc.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">'
        + (diff > 0 ? '−' : '+') + Math.abs(diff).toFixed(3) + ' (' + pct + '%)</span>';
      if (rInput) rInput.setAttribute('required', 'required');
    } else {
      vc.innerHTML = '<span class="text-success small"><i class="bi bi-check2 me-1"></i>OK</span>';
      if (rInput) rInput.removeAttribute('required');
    }
  }

  function recalc() {
    var hasDisc = false;
    var totExp  = 0, totRecv = 0;
    rows.forEach(function(row) {
      var inp  = row.querySelector('.qty-recv');
      var exp  = parseFloat(inp.dataset.expected) || 0;
      var recv = parseFloat(inp.value) || 0;
      totExp  += exp;
      totRecv += recv;
      if (Math.abs(exp - recv) > 0.001) hasDisc = true;
    });

    if (hasDisc) {
      sumExpEl.textContent  = totExp.toFixed(3);
      sumRecvEl.textContent = totRecv.toFixed(3);
      sumMissEl.textContent = Math.max(0, totExp - totRecv).toFixed(3);
      summaryEl.classList.remove('d-none');
      warnEl.classList.remove('d-none');
      matchEl.classList.add('d-none');
      submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Resubmit for GM Review';
      submitBtn.className = 'btn btn-warning';
    } else {
      summaryEl.classList.add('d-none');
      warnEl.classList.add('d-none');
      matchEl.classList.remove('d-none');
      submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirm — Complete Receiving';
      submitBtn.className = 'btn btn-success';
    }
  }

  rows.forEach(function(row) {
    updateRow(row);
    row.querySelector('.qty-recv').addEventListener('input', function() {
      updateRow(row);
      recalc();
    });
  });
  recalc();

  document.getElementById('resubmitForm').addEventListener('submit', function(e) {
    var valid = true;
    rows.forEach(function(row) {
      var inp    = row.querySelector('.qty-recv');
      var rInput = row.querySelector('.discrepancy-reason');
      var exp    = parseFloat(inp.dataset.expected) || 0;
      var recv   = parseFloat(inp.value) || 0;
      if (Math.abs(exp - recv) > 0.001 && rInput && rInput.value.trim() === '') {
        rInput.classList.add('is-invalid');
        valid = false;
      } else if (rInput) {
        rInput.classList.remove('is-invalid');
      }
    });
    if (!valid) { e.preventDefault(); return; }
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting…';
  });
})();
</script>
<?php $view->endSection(); ?>
