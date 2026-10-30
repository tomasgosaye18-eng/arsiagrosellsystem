<?php
/**
 * Discrepancy Report — AAM Warehouse Reports
 * @var array $rows @var array $pagination @var array $summary
 * @var array $filters @var array $products @var array $warehouse
 */
declare(strict_types=1);
$rows    ??= [];
$filters ??= [];
$products??= [];

use AAM\Warehouse\Models\DiscrepancyModel;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url('reports/warehouse') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <span class="fw-bold fs-5"><i class="bi bi-clipboard-x me-2 text-danger"></i>Discrepancy Report</span>
    <small class="text-muted ms-2"><?= e($warehouse['name'] ?? '') ?></small>
  </div>
  <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<!-- Summary -->
<div class="row g-2 mb-3">
  <?php foreach ([
    ['Total',         $summary['total']        ?? 0, 'secondary'],
    ['Open',          $summary['open_count']   ?? 0, 'danger'],
    ['Under Review',  $summary['under_review'] ?? 0, 'warning'],
    ['Resolved',      $summary['resolved']     ?? 0, 'success'],
    ['Total Variance',number_format((float)($summary['total_variance']??0),3), 'danger'],
  ] as [$l,$v,$c]): ?>
  <div class="col-6 col-md-2">
    <div class="card border-0 shadow-sm text-center py-2">
      <div class="fw-bold text-<?= $c ?>"><?= $v ?></div>
      <div class="text-muted" style="font-size:.72rem;"><?= $l ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3 d-print-none">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Discrepancy # or product…" value="<?= e($filters['search']??'') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="product_id" class="form-select form-select-sm">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($filters['product_id']??0)==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['open'=>'Open','under_review'=>'Under Review','resolved'=>'Resolved','rejected'=>'Rejected'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($filters['status']??'')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']??'') ?>"></div>
      <div class="col-6 col-md-2"><input type="date" name="date_to"   class="form-control form-control-sm" value="<?= e($filters['date_to']??'') ?>"></div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('reports/warehouse/discrepancies') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
      <div class="text-center py-5 text-muted small">No discrepancy records found for selected filters.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Dis. #</th><th>Reference</th><th>Product</th><th>Batch</th>
            <th>Expected</th><th>Actual</th><th>Variance</th><th>%</th>
            <th>Type</th><th>Reported By</th><th>Date</th>
            <th>Status</th><th class="pe-3">Resolved By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $badge = DiscrepancyModel::statusBadge($r['status']);
            $label = DiscrepancyModel::statusLabel($r['status']);
            $type  = DiscrepancyModel::typeLabel($r['discrepancy_type']);
          ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('warehouse/discrepancies/'.$r['id']) ?>" class="text-decoration-none"><?= e($r['discrepancy_number']) ?></a>
            </td>
            <td class="small text-muted"><?= ucfirst($r['reference_type']??'') ?> #<?= $r['reference_id'] ?></td>
            <td class="small"><?= e($r['product_name']??'—') ?></td>
            <td class="small text-muted"><?= e($r['batch_number']??'—') ?></td>
            <td class="small"><?= number_format((float)$r['expected_quantity'],3) ?></td>
            <td class="small text-danger"><?= number_format((float)$r['actual_quantity'],3) ?></td>
            <td class="small fw-bold text-danger"><?= number_format(abs((float)$r['variance_quantity']),3) ?></td>
            <td class="small"><?= number_format((float)$r['variance_percent'],1) ?>%</td>
            <td><span class="badge bg-secondary-subtle text-secondary small"><?= e($type) ?></span></td>
            <td class="small text-muted"><?= e($r['reported_by_name']??'—') ?></td>
            <td class="small"><?= $r['reported_at']?date('d/m/Y',strtotime($r['reported_at'])):'—' ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <td class="small text-muted pe-3"><?= e($r['resolved_by_name']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages']??1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top d-print-none">
      <small class="text-muted">Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?></small>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p=1;$p<=$pagination['totalPages'];$p++): ?>
        <li class="page-item <?= $p===($pagination['page']??1)?'active':'' ?>">
          <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
