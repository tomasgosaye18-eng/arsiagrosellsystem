<?php
/**
 * GM — Receiving Discrepancies Pending Review
 * @var array $pending
 */
declare(strict_types=1);
$pending ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-shield-check me-2 text-warning"></i>
    Receiving Discrepancies — Pending Your Review
    <?php if (!empty($pending)): ?>
    <span class="badge bg-danger ms-2"><?= count($pending) ?></span>
    <?php endif; ?>
  </h5>
  <a href="<?= url('gm/warehouses/discrepancies') ?>" class="btn btn-outline-secondary btn-sm">
    All Discrepancies
  </a>
</div>

<div class="alert alert-warning d-flex gap-2 align-items-start small mb-3">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
  <div>
    These receiving records have a <strong>quantity discrepancy</strong> reported by a
    Warehouse Manager. You must review each one and either
    <strong>Approve</strong> (allow the warehouse to receive the actual lower quantity)
    or <strong>Reject</strong> (return for correction).
    <br>Warehouse inventory is <strong>blocked</strong> until you act.
  </div>
</div>

<?php if (empty($pending)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-check2-circle display-4 d-block mb-2 text-success opacity-50"></i>
    <p class="mb-0">No discrepancies pending your review.</p>
  </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Receiving #</th>
            <th>Dispatch #</th>
            <th>Farm</th>
            <th>Warehouse</th>
            <th>Dispatch Date</th>
            <th>Expected</th>
            <th>Actual</th>
            <th>Missing</th>
            <th>Discrepancy Lines</th>
            <th>Received By</th>
            <th class="pe-3 text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $row): ?>
          <tr>
            <td class="ps-3 fw-semibold small">
              <?= e($row['receiving_number']) ?>
            </td>
            <td class="small"><?= e($row['dispatch_number'] ?? '—') ?></td>
            <td class="small fw-semibold"><?= e($row['farm_name'] ?? '—') ?></td>
            <td class="small"><?= e($row['warehouse_name'] ?? '—') ?></td>
            <td class="small"><?= $row['dispatch_date'] ? date('d/m/Y', strtotime($row['dispatch_date'])) : '—' ?></td>
            <td class="small fw-bold text-primary"><?= number_format((float)$row['total_expected'], 3) ?></td>
            <td class="small fw-bold text-success"><?= number_format((float)$row['total_received'], 3) ?></td>
            <td class="small fw-bold text-danger"><?= number_format((float)$row['total_missing'], 3) ?></td>
            <td class="small"><span class="badge bg-warning text-dark"><?= (int)$row['discrepancy_count'] ?></span></td>
            <td class="small text-muted"><?= e($row['received_by_name'] ?? '—') ?></td>
            <td class="pe-3 text-end">
              <a href="<?= url('gm/warehouses/receiving/' . $row['receiving_id'] . '/discrepancy-review') ?>"
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
