<?php
/**
 * GM — Transfer Receiving Discrepancies Pending Review
 *
 * @var array $pending   Rows from DiscrepancyModel::getPendingTransferGMReview()
 */
declare(strict_types=1);
$pending ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-shield-exclamation me-2 text-warning"></i>
    Transfer Receiving Discrepancies — Pending Your Review
    <?php if (!empty($pending)): ?>
    <span class="badge bg-danger ms-2"><?= count($pending) ?></span>
    <?php endif; ?>
  </h5>
  <a href="<?= url('gm/transfers') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>All Transfers
  </a>
</div>

<div class="alert alert-warning d-flex gap-2 align-items-start small mb-3">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
  <div>
    The Salesperson at each shop reported a <strong>quantity discrepancy</strong> when receiving
    a warehouse transfer. You must <strong>Approve</strong> or <strong>Reject</strong> each one.<br>
    <strong>Shop inventory is blocked until you act.</strong>
    <ul class="mb-0 mt-1">
      <li><strong>Approve</strong> — shop inventory updated with the actual received quantity only. Missing qty recorded as loss.</li>
      <li><strong>Reject</strong> — salesperson must review and re-submit with corrected data.</li>
    </ul>
  </div>
</div>

<?php if (empty($pending)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-check2-circle display-4 d-block mb-2 text-success opacity-50"></i>
    <p class="mb-0 fw-semibold">No transfer discrepancies pending your review.</p>
  </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Transfer #</th>
            <th>Warehouse</th>
            <th>Shop</th>
            <th>Transfer Date</th>
            <th class="text-end">Sent</th>
            <th class="text-end">Received</th>
            <th class="text-end text-danger">Missing</th>
            <th>Lines</th>
            <th>Received By</th>
            <th class="pe-3 text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $row):
            $totalSent     = (float)$row['total_sent'];
            $totalReceived = (float)$row['total_received'];
            $totalMissing  = (float)$row['total_missing'];
            $missingPct    = $totalSent > 0
                ? round(($totalMissing / $totalSent) * 100, 1)
                : 0;
          ?>
          <tr>
            <td class="ps-3 fw-semibold small">
              <?= e($row['transfer_number']) ?>
            </td>
            <td class="small fw-semibold"><?= e($row['warehouse_name'] ?? '—') ?></td>
            <td class="small">
              <?= e($row['shop_name'] ?? '—') ?>
              <?php if (!empty($row['shop_location'])): ?>
              <div class="text-muted" style="font-size:.7rem;"><?= e($row['shop_location']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small text-muted">
              <?= $row['transfer_date'] ? date('d/m/Y', strtotime($row['transfer_date'])) : '—' ?>
            </td>
            <td class="small fw-bold text-primary text-end">
              <?= number_format($totalSent, 3) ?>
            </td>
            <td class="small fw-bold text-success text-end">
              <?= number_format($totalReceived, 3) ?>
            </td>
            <td class="small fw-bold text-danger text-end">
              <?= number_format($totalMissing, 3) ?>
              <?php if ($missingPct > 0): ?>
              <div style="font-size:.68rem;" class="text-muted">(<?= $missingPct ?>%)</div>
              <?php endif; ?>
            </td>
            <td class="small">
              <span class="badge bg-warning text-dark"><?= (int)$row['discrepancy_count'] ?></span>
            </td>
            <td class="small text-muted"><?= e($row['received_by_name'] ?? '—') ?></td>
            <td class="pe-3 text-end">
              <a href="<?= url('gm/transfers/' . $row['transfer_id'] . '/discrepancy-review') ?>"
                 class="btn btn-warning btn-sm">
                <i class="bi bi-eye me-1"></i>Review
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
