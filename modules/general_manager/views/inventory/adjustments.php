<?php
/**
 * GM — Stock Adjustments List
 * @var array  $adjustments  @var array  $pagination  @var array  $filters
 */
declare(strict_types=1);
$adjustments ??= [];
$filters     ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-sliders me-2 text-warning"></i>Stock Adjustments
  </h5>
  <a href="<?= url('gm/adjustments/pending') ?>" class="btn btn-warning btn-sm">
    <i class="bi bi-hourglass-split me-1"></i>Pending Approval
    <?php $pCount = (int)($pendingAdjCount ?? 0); if ($pCount > 0): ?>
    <span class="badge bg-danger ms-1"><?= $pCount ?></span>
    <?php endif; ?>
  </a>
</div>

<!-- Filters -->
<form method="GET" class="row g-2 mb-3">
  <div class="col-sm-3">
    <select name="status" class="form-select form-select-sm">
      <option value="">All Statuses</option>
      <option value="pending"  <?= ($filters['status']??'')==='pending'  ?'selected':'' ?>>Pending</option>
      <option value="applied"  <?= ($filters['status']??'')==='applied'  ?'selected':'' ?>>Applied</option>
      <option value="rejected" <?= ($filters['status']??'')==='rejected' ?'selected':'' ?>>Rejected</option>
    </select>
  </div>
  <div class="col-sm-3">
    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search…"
           value="<?= e($filters['search']??'') ?>">
  </div>
  <div class="col-sm-2">
    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']??'') ?>">
  </div>
  <div class="col-sm-2">
    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']??'') ?>">
  </div>
  <div class="col-sm-2 d-flex gap-1">
    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
    <a href="<?= url('gm/adjustments') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($adjustments)): ?>
      <div class="text-center py-5 text-muted small">No adjustments found.</div>
    <?php else: ?>
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
            <th>Status</th>
            <th>Requested By</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($adjustments as $adj):
            $variance = (float)($adj['variance'] ?? 0);
            $badge    = \AAM\Inventory\Models\StockAdjustmentModel::statusBadge($adj['status']??'');
            $label    = \AAM\Inventory\Models\StockAdjustmentModel::statusLabel($adj['status']??'');
          ?>
          <tr>
            <td class="ps-3 fw-semibold small"><?= e($adj['adjustment_number']??'') ?></td>
            <td class="small">
              <?= e($adj['product_name']??'—') ?>
              <?php if ($adj['product_sku']): ?><br><span class="text-muted" style="font-size:.68rem;"><?= e($adj['product_sku']) ?></span><?php endif; ?>
            </td>
            <td class="small text-muted"><?= ucfirst($adj['location_type']??'') ?> #<?= (int)($adj['location_id']??0) ?></td>
            <td class="small"><?= number_format((float)($adj['system_quantity']??0),3) ?></td>
            <td class="small"><?= number_format((float)($adj['physical_quantity']??0),3) ?></td>
            <td class="small fw-bold <?= $variance<0?'text-danger':($variance>0?'text-success':'text-muted') ?>">
              <?= $variance>=0?'+':'' ?><?= number_format($variance,3) ?>
            </td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <td class="small text-muted"><?= e($adj['requested_by_name']??'—') ?></td>
            <td class="small text-muted"><?= date('d/m/Y',strtotime($adj['created_at']??'now')) ?></td>
            <td>
              <a href="<?= url('gm/adjustments/'.$adj['id']) ?>" class="btn btn-outline-primary btn-sm">
                <?= ($adj['status']??'')==='pending' ? 'Review' : 'View' ?>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages']??1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?></small>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p=1; $p<=min($pagination['totalPages'],10); $p++): ?>
        <li class="page-item <?= $p==($pagination['page']??1)?'active':'' ?>">
          <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
