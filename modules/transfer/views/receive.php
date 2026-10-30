<?php
/**
 * Transfer Receiving Form — Destination Shop (Salesperson)
 * Handles both fresh receiving (in_transit) and resubmission (gm_rejected).
 *
 * @var array  $transfer          Full transfer with items
 * @var array  $warehouse
 * @var string $today
 * @var string $userRole
 * @var bool   $isResubmit        True when resubmitting after GM rejection
 * @var array  $prevDiscrepancies Previous discrepancy records (on resubmit)
 * @var string $gmRejectionReason GM's rejection reason (on resubmit)
 */
declare(strict_types=1);

use AAM\Warehouse\Models\TransferModel;

$transfer          ??= [];
$items             = $transfer['items'] ?? [];
$isResubmit        ??= false;
$prevDiscrepancies ??= [];
$gmRejectionReason ??= '';
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= url('transfers/incoming') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Incoming
  </a>
  <div>
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-<?= $isResubmit ? 'arrow-repeat' : 'check2-circle' ?> me-2 text-<?= $isResubmit ? 'warning' : 'success' ?>"></i>
      <?= $isResubmit ? 'Re-submit Receiving' : 'Confirm Receipt' ?>
      — <?= e($transfer['transfer_number'] ?? '') ?>
    </h5>
    <small class="text-muted">
      From: <strong><?= e($transfer['warehouse_name'] ?? '—') ?></strong>
      &rarr; To: <strong><?= e($transfer['shop_name'] ?? '—') ?></strong>
    </small>
  </div>
</div>

<?php if ($isResubmit && $gmRejectionReason): ?>
<!-- GM rejection reason banner -->
<div class="alert alert-danger d-flex gap-2 align-items-start mb-3 small">
  <i class="bi bi-x-circle-fill flex-shrink-0 mt-1 fs-5"></i>
  <div>
    <div class="fw-bold mb-1">General Manager rejected your previous submission</div>
    <div><?= e($gmRejectionReason) ?></div>
    <div class="mt-1 text-muted">Please review the quantities and reason below, correct as needed, then re-submit.</div>
  </div>
</div>
<?php endif; ?>

<!-- Transfer summary cards -->
<div class="row g-3 mb-3">
  <?php
  $status = $transfer['status'] ?? '';
  $cards = [
    ['Transfer #',   $transfer['transfer_number'] ?? '—',                                              'secondary'],
    ['Status',       TransferModel::statusLabel($status),                                              TransferModel::statusBadge($status)],
    ['Dispatched',   $transfer['transfer_date'] ? date('d/m/Y', strtotime($transfer['transfer_date'])) : '—', 'info'],
    ['Items',        count($items),                                                                    'dark'],
  ];
  foreach ($cards as [$lbl, $val, $col]): ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-6"><?= e($val) ?></div>
        <div class="text-muted small"><?= $lbl ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Instructions -->
<div class="alert alert-<?= $isResubmit ? 'warning' : 'success' ?> d-flex align-items-center gap-2 mb-3 small">
  <i class="bi bi-<?= $isResubmit ? 'exclamation-triangle' : 'shield-check' ?> flex-shrink-0"></i>
  <div>
    <?php if ($isResubmit): ?>
      Review and correct the received quantities below.
      <strong>A reason is required for any quantity difference.</strong>
      When correct, click <em>Re-submit for GM Approval</em>.
    <?php else: ?>
      Enter the <strong>actual quantities you physically received</strong>.
      If any quantity differs from what was sent, a <strong>reason is required</strong>
      and the discrepancy will be sent to the General Manager for approval
      before shop inventory is updated.
    <?php endif; ?>
  </div>
</div>

<form method="POST"
      action="<?= url('transfers/' . ($transfer['id'] ?? '') . '/receive') ?>"
      id="receiveForm">
  <?php csrf_input(); ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold small">
      <i class="bi bi-list-check me-2 text-success"></i>
      Products to Receive
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Product</th>
              <th>Batch / Expiry</th>
              <th>Sent Qty</th>
              <th class="text-success fw-semibold">
                Received Qty <span class="text-danger">*</span>
              </th>
              <th>Missing</th>
              <th class="pe-3">
                Reason for Difference
                <span class="text-danger fw-bold">*</span>
                <small class="text-muted fw-normal">(required if qty differs)</small>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item):
              $qtySent = (float)$item['quantity_sent'];
              // Pre-fill previous received qty on resubmit
              $prevQty = (float)($item['quantity_received'] ?? $qtySent);
            ?>
            <tr class="receive-row" data-sent="<?= $qtySent ?>">
              <input type="hidden" name="item_ids[]" value="<?= (int)$item['id'] ?>">
              <td class="ps-3">
                <div class="fw-semibold small"><?= e($item['product_name'] ?? '—') ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= e($item['product_sku'] ?? '') ?></div>
              </td>
              <td class="small">
                <?php if ($item['batch_number']): ?>
                <div><?= e($item['batch_number']) ?></div>
                <?php endif; ?>
                <?php if ($item['expiry_date']): ?>
                <div class="text-muted"><?= date('d/m/Y', strtotime($item['expiry_date'])) ?></div>
                <?php endif; ?>
                <?php if (!$item['batch_number'] && !$item['expiry_date']): ?>—<?php endif; ?>
              </td>
              <td class="small fw-bold text-primary">
                <?= number_format($qtySent, 3) ?> <?= e($item['unit_abbr'] ?? '') ?>
              </td>
              <td style="min-width:130px;">
                <input type="number"
                       name="quantity_received[]"
                       class="form-control form-control-sm qty-recv"
                       step="0.001" min="0"
                       max="<?= $qtySent * 2 ?>"
                       value="<?= number_format($isResubmit ? $prevQty : $qtySent, 3, '.', '') ?>"
                       data-sent="<?= $qtySent ?>"
                       data-unit="<?= e($item['unit_abbr'] ?? '') ?>"
                       required>
              </td>
              <td class="small variance-cell text-muted">—</td>
              <td class="pe-3" style="min-width:200px;">
                <input type="text"
                       name="discrepancy_reasons[]"
                       class="form-control form-control-sm discrepancy-reason"
                       placeholder="Required if quantity differs…"
                       value="">
                <div class="invalid-feedback">Reason is required when quantity differs from sent.</div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Summary row (shown when discrepancy detected) -->
  <div id="discrepancySummary" class="card border-danger border-0 shadow-sm mb-3 d-none">
    <div class="card-body p-3">
      <div class="row g-3 text-center">
        <div class="col-4">
          <div class="text-muted small">Total Sent</div>
          <div class="fw-bold text-primary fs-6" id="sumSent">—</div>
        </div>
        <div class="col-4">
          <div class="text-muted small">Total Received</div>
          <div class="fw-bold text-success fs-6" id="sumReceived">—</div>
        </div>
        <div class="col-4">
          <div class="text-muted small">Total Missing</div>
          <div class="fw-bold text-danger fs-6" id="sumMissing">—</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Notes -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label small fw-semibold">Notes (optional)</label>
      <textarea name="notes" class="form-control form-control-sm" rows="2"
                placeholder="Any notes about the condition of goods received…"></textarea>
    </div>
  </div>

  <!-- Discrepancy warning — shown by JS when quantities differ -->
  <div id="discrepancyWarning" class="alert alert-warning d-none mb-3 small">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Discrepancy detected.</strong>
    The received quantities differ from what was dispatched.<br>
    <strong>Shop inventory will NOT be updated until the General Manager reviews and approves.</strong>
    Make sure the reason for each variance is filled in, then submit for GM approval.
  </div>

  <!-- Normal match info — shown by JS when all quantities match -->
  <div id="matchInfo" class="alert alert-success d-none mb-3 small">
    <i class="bi bi-check2-circle me-2"></i>
    All quantities match. <strong>Shop inventory will be updated immediately upon confirmation.</strong>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= url('transfers/incoming') ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-success d-none" id="btnConfirmFull">
      <i class="bi bi-check2-circle me-1"></i>Confirm Receipt &amp; Update Shop Inventory
    </button>
    <button type="submit" class="btn btn-warning d-none" id="btnSubmitDiscrepancy">
      <i class="bi bi-send me-1"></i>
      <?= $isResubmit ? 'Re-submit' : 'Submit' ?> for GM Approval
    </button>
  </div>
</form>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var rows         = document.querySelectorAll('.receive-row');
  var warnEl       = document.getElementById('discrepancyWarning');
  var matchEl      = document.getElementById('matchInfo');
  var summaryEl    = document.getElementById('discrepancySummary');
  var btnFull      = document.getElementById('btnConfirmFull');
  var btnDis       = document.getElementById('btnSubmitDiscrepancy');
  var sumSentEl    = document.getElementById('sumSent');
  var sumRecvEl    = document.getElementById('sumReceived');
  var sumMissEl    = document.getElementById('sumMissing');

  function formatQty(n) {
    return n.toFixed(3);
  }

  function updateRow(row) {
    var inp    = row.querySelector('.qty-recv');
    var vc     = row.querySelector('.variance-cell');
    var rInput = row.querySelector('.discrepancy-reason');
    var sent   = parseFloat(inp.dataset.sent) || 0;
    var recv   = parseFloat(inp.value)        || 0;
    var diff   = sent - recv;

    if (Math.abs(diff) > 0.001) {
      var pct  = sent > 0 ? ((Math.abs(diff) / sent) * 100).toFixed(1) + '%' : '—';
      var sign = diff > 0 ? '−' : '+';
      vc.innerHTML =
        '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">' +
        sign + Math.abs(diff).toFixed(3) + ' (' + pct + ')</span>';
      if (rInput) {
        rInput.setAttribute('required', 'required');
        rInput.classList.add('is-required-active');
      }
    } else {
      vc.innerHTML = '<span class="text-success small"><i class="bi bi-check2 me-1"></i>OK</span>';
      if (rInput) {
        rInput.removeAttribute('required');
        rInput.classList.remove('is-required-active');
      }
    }
  }

  function recalcTotals() {
    var hasDisc    = false;
    var totalSent  = 0;
    var totalRecv  = 0;

    rows.forEach(function (row) {
      var inp  = row.querySelector('.qty-recv');
      var sent = parseFloat(inp.dataset.sent) || 0;
      var recv = parseFloat(inp.value)        || 0;
      totalSent += sent;
      totalRecv += recv;
      if (Math.abs(sent - recv) > 0.001) hasDisc = true;
    });

    var missing = totalSent - totalRecv;

    // Update summary panel
    if (hasDisc) {
      sumSentEl.textContent    = formatQty(totalSent);
      sumRecvEl.textContent    = formatQty(totalRecv);
      sumMissEl.textContent    = formatQty(Math.max(0, missing));
      summaryEl.classList.remove('d-none');
    } else {
      summaryEl.classList.add('d-none');
    }

    // Toggle banners and buttons
    warnEl.classList.toggle('d-none', !hasDisc);
    matchEl.classList.toggle('d-none', hasDisc);
    btnFull.classList.toggle('d-none', hasDisc);
    btnDis.classList.toggle('d-none', !hasDisc);
  }

  // Initialise
  rows.forEach(function (row) {
    updateRow(row);
    row.querySelector('.qty-recv').addEventListener('input', function () {
      updateRow(row);
      recalcTotals();
    });
  });
  recalcTotals();

  // Form submission guard
  document.getElementById('receiveForm').addEventListener('submit', function (e) {
    // Validate that every row with a variance has a reason
    var valid = true;
    rows.forEach(function (row) {
      var inp    = row.querySelector('.qty-recv');
      var rInput = row.querySelector('.discrepancy-reason');
      var sent   = parseFloat(inp.dataset.sent) || 0;
      var recv   = parseFloat(inp.value)        || 0;
      if (Math.abs(sent - recv) > 0.001) {
        if (!rInput || rInput.value.trim() === '') {
          rInput.classList.add('is-invalid');
          valid = false;
        } else {
          rInput.classList.remove('is-invalid');
        }
      }
    });

    if (!valid) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    // Confirm before submitting discrepancy
    var hasDisc = !btnFull.classList.contains('d-none') === false;
    if (hasDisc) {
      if (!confirm(
        'You are submitting a receiving discrepancy for GM approval.\n' +
        'Shop inventory will NOT be updated until the GM approves.\n\n' +
        'Continue?'
      )) {
        e.preventDefault();
        return;
      }
    }

    // Disable buttons to prevent double-submit
    btnFull.disabled = true;
    btnDis.disabled  = true;
    btnFull.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Confirming…';
    btnDis.innerHTML  = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting…';
  });
})();
</script>
<?php $view->endSection(); ?>
