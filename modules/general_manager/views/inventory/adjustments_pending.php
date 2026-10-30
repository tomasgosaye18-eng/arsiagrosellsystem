<?php
/**
 * GM — Pending Stock Adjustments
 * @var array $adjustments  @var array $pagination
 */
declare(strict_types=1);
$adjustments ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Adjustments — Awaiting Your Approval
  </h5>
  <a href="<?= url('gm/adjustments') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-list-ul me-1"></i>All Adjustments
  </a>
</div>

<?php if (empty($adjustments)): ?>
<div class="alert alert-success">
  <i class="bi bi-check-circle-fill me-2"></i>No pending adjustments. All caught up.
</div>
<?php else: ?>
<div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div class="small"><?= count($adjustments) ?> adjustment(s) are awaiting your approval. Inventory will NOT change until you approve.</div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Reference</th>
            <th>Product</th>
            <th>Location</th>
            <th>System Qty</th>
            <th>Physical Qty</th>
            <th>Variance</th>
            <th>Reason</th>
            <th>Requested By</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($adjustments as $adj):
            $variance = (float)($adj['variance'] ?? 0);
          ?>
          <tr class="table-warning-subtle">
            <td class="ps-3 fw-semibold small"><?= e($adj['adjustment_number']??'') ?></td>
            <td class="small">
              <?= e($adj['product_name']??'—') ?>
              <?php if ($adj['product_sku']): ?><br><span class="text-muted" style="font-size:.68rem;"><?= e($adj['product_sku']) ?></span><?php endif; ?>
            </td>
            <td class="small text-muted"><?= ucfirst($adj['location_type']??'') ?> #<?= (int)($adj['location_id']??0) ?></td>
            <td class="small"><?= number_format((float)($adj['system_quantity']??0),3) ?></td>
            <td class="small fw-bold"><?= number_format((float)($adj['physical_quantity']??0),3) ?></td>
            <td class="small fw-bold <?= $variance<0?'text-danger':($variance>0?'text-success':'text-muted') ?>">
              <?= $variance>=0?'+':'' ?><?= number_format($variance,3) ?>
            </td>
            <td class="small text-muted">
              <?= e(\AAM\Inventory\Models\StockAdjustmentModel::REASONS[$adj['reason']??''] ?? ($adj['reason']??'—')) ?>
            </td>
            <td class="small text-muted"><?= e($adj['requested_by_name']??'—') ?></td>
            <td class="small text-muted"><?= date('d/m/Y H:i',strtotime($adj['created_at']??'now')) ?></td>
            <td>
              <a href="<?= url('gm/adjustments/'.$adj['id']) ?>" class="btn btn-warning btn-sm fw-semibold">
                <i class="bi bi-check2-circle me-1"></i>Review
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
