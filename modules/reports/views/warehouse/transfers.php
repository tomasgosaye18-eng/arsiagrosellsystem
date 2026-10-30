<?php
/**
 * Transfer Report — AAM Warehouse Reports
 * @var array $rows @var array $pagination @var array $summary
 * @var array $filters @var array $shops @var array $products @var array $warehouse
 */
declare(strict_types=1);
$rows    ??= [];
$filters ??= [];
$shops   ??= [];
$products??= [];

use AAM\Warehouse\Models\TransferModel;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url('reports/warehouse') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <span class="fw-bold fs-5"><i class="bi bi-arrow-right-circle me-2 text-info"></i>Transfer Report</span>
    <small class="text-muted ms-2"><?= e($warehouse['name'] ?? '') ?></small>
  </div>
  <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<!-- Summary -->
<div class="row g-2 mb-3">
  <?php foreach ([
    ['Total Transfers', $summary['total_transfers'] ?? 0, 'secondary'],
    ['Total Qty',       number_format((float)($summary['total_qty']??0),1), 'primary'],
    ['Completed',       $summary['completed'] ?? 0, 'success'],
    ['Pending',         $summary['pending']   ?? 0, 'warning'],
    ['Cancelled',       $summary['cancelled'] ?? 0, 'secondary'],
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
      <div class="col-12 col-md-2">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Transfer #" value="<?= e($filters['search']??'') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="shop_id" class="form-select form-select-sm">
          <option value="">All Shops</option>
          <?php foreach ($shops as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($filters['shop_id']??0)==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
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
          <?php foreach (['draft','pending','approved','in_transit','received','partially_received','discrepancy','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= TransferModel::statusLabel($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']??'') ?>"></div>
      <div class="col-6 col-md-2"><input type="date" name="date_to"   class="form-control form-control-sm" value="<?= e($filters['date_to']??'') ?>"></div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('reports/warehouse/transfers') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
      <div class="text-center py-5 text-muted small">No transfer records found for selected filters.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Transfer #</th><th>Shop</th><th>Items</th><th>Total Qty</th>
            <th>Requested By</th><th>Transfer Date</th><th>Received Date</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $badge = TransferModel::statusBadge($r['status']);
            $label = TransferModel::statusLabel($r['status']);
          ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('transfers/'.$r['id']) ?>" class="text-decoration-none"><?= e($r['transfer_number']) ?></a>
            </td>
            <td class="small"><?= e($r['shop_name']??'—') ?></td>
            <td class="small text-muted"><?= (int)($r['total_items']??0) ?></td>
            <td class="small fw-semibold"><?= number_format((float)$r['total_quantity'],1) ?></td>
            <td class="small text-muted"><?= e($r['initiated_by_name']??'—') ?></td>
            <td class="small"><?= $r['transfer_date']?date('d/m/Y',strtotime($r['transfer_date'])):'—' ?></td>
            <td class="small"><?= $r['received_date']?date('d/m/Y',strtotime($r['received_date'])):'—' ?></td>
            <td class="pe-3"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
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
