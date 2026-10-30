<?php
/**
 * Transfer Confirmation — Phase 10
 * @var array $transfer @var array $warehouse @var string $today @var string $userRole
 */
declare(strict_types=1);
$transfer ??= [];
$items    = $transfer['items'] ?? [];
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= url('transfers/' . ($transfer['id'] ?? '')) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-check2-circle me-2 text-success"></i>
      Confirm Receipt — <?= e($transfer['transfer_number'] ?? '') ?>
    </h5>
    <small class="text-muted">
      From: <?= e($transfer['warehouse_name'] ?? '—') ?>
      &rarr; To: <?= e($transfer['shop_name'] ?? '—') ?>
    </small>
  </div>
</div>

<div class="alert alert-info d-flex align-items-center gap-2 mb-3 small">
  <i class="bi bi-info-circle-fill flex-shrink-0"></i>
  <div>Enter the <strong>actual quantities received</strong>. Variances will be automatically recorded as discrepancies.
  Shop inventory will be updated when you submit.</div>
</div>

<form method="POST" action="<?= url('transfers/' . ($transfer['id'] ?? '') . '/confirm') ?>" id="confirmForm">
  <?php csrf_input(); ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold small">
      <i class="bi bi-list-check me-2 text-success"></i>Products
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Product</th>
              <th>Batch</th>
              <th>Qty Sent</th>
              <th class="text-success">Qty Received *</th>
              <th>Variance</th>
              <th class="pe-3">Discrepancy Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <input type="hidden" name="item_ids[]" value="<?= (int)$item['id'] ?>">
              <td class="ps-3">
                <div class="fw-semibold small"><?= e($item['product_name'] ?? '—') ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= e($item['product_sku'] ?? '') ?></div>
              </td>
              <td class="small"><?= e($item['batch_number'] ?? '—') ?></td>
              <td class="small fw-bold text-primary">
                <?= number_format((float)$item['quantity_sent'], 3) ?> <?= e($item['unit_abbr'] ?? '') ?>
              </td>
              <td>
                <input type="number" name="quantity_received[]"
                       class="form-control form-control-sm qty-recv" style="min-width:100px;"
                       step="0.001" min="0"
                       value="<?= number_format((float)$item['quantity_sent'], 3, '.', '') ?>"
                       data-sent="<?= (float)$item['quantity_sent'] ?>"
                       required>
              </td>
              <td class="small variance-cell text-muted">—</td>
              <td class="pe-3 dis-col" style="display:none;">
                <input type="text" name="discrepancy_reasons[]"
                       class="form-control form-control-sm"
                       placeholder="Reason…" style="min-width:150px;">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label small fw-semibold">Notes (optional)</label>
      <textarea name="notes" class="form-control form-control-sm" rows="2"
                placeholder="Additional notes for this confirmation…"></textarea>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= url('transfers/' . ($transfer['id'] ?? '')) ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-success" id="submitBtn">
      <i class="bi bi-check2-circle me-1"></i>Confirm &amp; Update Shop Inventory
    </button>
  </div>
</form>

<?php $view->section('scripts'); ?>
<script>
(function () {
  document.querySelectorAll('.qty-recv').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var row  = inp.closest('tr');
      var sent = parseFloat(inp.dataset.sent) || 0;
      var recv = parseFloat(inp.value) || 0;
      var var_ = sent - recv;
      var vc   = row.querySelector('.variance-cell');
      var dc   = row.querySelector('.dis-col');
      if (Math.abs(var_) > 0.001) {
        var pct = sent > 0 ? ((Math.abs(var_) / sent) * 100).toFixed(1) : 0;
        vc.innerHTML = '<span class="badge bg-danger">' +
          (var_ > 0 ? '-' : '+') + Math.abs(var_).toFixed(3) + ' (' + pct + '%)</span>';
        if (dc) dc.style.display = '';
      } else {
        vc.innerHTML = '<span class="text-success"><i class="bi bi-check2"></i></span>';
        if (dc) dc.style.display = 'none';
      }
    });
    inp.dispatchEvent(new Event('input'));
  });
  document.getElementById('confirmForm').addEventListener('submit', function () {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Confirming…';
  });
})();
</script>
<?php $view->endSection(); ?>
