<?php
/**
 * GM — Transfer Pipeline
 * @var array $transfers
 * @var array $pagination
 * @var array $filters
 * @var array $pipeline
 * @var array $pendingApprovals
 * @var array $warehouses
 * @var array $shops
 */
declare(strict_types=1);
$transfers       ??= [];
$pagination      ??= [];
$filters         ??= [];
$pipeline        ??= [];
$pendingApprovals??= [];
$warehouses      ??= [];
$shops           ??= [];
$n  = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
$dt = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '—';
$statusBadge = [
    'pending'                  => 'bg-warning text-dark',
    'approved'                 => 'bg-success',
    'in_transit'               => 'bg-info text-dark',
    'received'                 => 'bg-primary',
    'received_with_discrepancy'=> 'bg-danger',
    'discrepancy_pending'      => 'bg-danger',
    'gm_rejected'              => 'bg-secondary',
    'final_rejected'           => 'bg-dark',
    'cancelled'                => 'bg-secondary',
    'rejected'                 => 'bg-danger',
    'draft'                    => 'bg-light text-dark border',
];
?>
<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Transfer Pipeline</h5>
    <small class="text-muted">Full transfer visibility — General Manager</small>
  </div>
  <?php if (($pipeline['pending_approval'] ?? 0) > 0): ?>
  <a href="<?= url('gm/transfers/pending') ?>" class="btn btn-warning btn-sm fw-semibold">
    <i class="bi bi-hourglass-split me-1"></i><?= (int)$pipeline['pending_approval'] ?> Pending Approval
  </a>
  <?php endif; ?>
</div>

<!-- Pipeline KPI strip -->
<div class="row g-2 mb-4">
  <?php foreach ([
    ['Pending Approval', $pipeline['pending_approval'] ?? 0, 'warning', url('gm/transfers?status=pending')],
    ['Approved',         $pipeline['approved'] ?? 0,         'success', url('gm/transfers?status=approved')],
    ['In Transit',       $pipeline['in_transit'] ?? 0,       'info',    url('gm/transfers?status=in_transit')],
    ['W/ Discrepancy',   $pipeline['with_discrepancy'] ?? 0, 'danger',  url('gm/transfers?status=received_with_discrepancy')],
    ['Received / Month', $pipeline['received_this_month']??0,'secondary',url('gm/transfers')],
    ['Final Rejected',   ($pipeline['final_rejected_transfers'] ?? 0) + ($pipeline['final_rejected_receivings'] ?? 0), 'dark', url('gm/transfers?status=final_rejected')],
  ] as [$lbl,$cnt,$col,$href]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <a href="<?= $href ?>" class="card border-0 shadow-sm text-decoration-none h-100" style="border-top:3px solid var(--bs-<?= $col ?>)!important;">
      <div class="card-body text-center py-3">
        <div class="fw-bold fs-4 text-<?= $col ?>"><?= $n($cnt) ?></div>
        <div class="text-muted small"><?= $lbl ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="" class="row g-2 align-items-end">
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['pending','approved','in_transit','received','discrepancy_pending','gm_rejected','received_with_discrepancy','final_rejected','rejected','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= \AAM\Warehouse\Models\TransferModel::statusLabel($s) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="warehouse_id" class="form-select form-select-sm">
          <option value="">All Warehouses</option>
          <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>" <?= (int)($filters['warehouse_id'] ?? 0) === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="shop_id" class="form-select form-select-sm">
          <option value="">All Shops</option>
          <?php foreach ($shops as $sh): ?>
          <option value="<?= $sh['id'] ?>" <?= (int)($filters['shop_id'] ?? 0) === (int)$sh['id'] ? 'selected' : '' ?>><?= e($sh['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>"></div>
      <div class="col-md-2"><input type="date" name="date_to"   class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>"></div>
      <div class="col-md-2 d-flex gap-1">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search…" value="<?= e($filters['search'] ?? '') ?>">
        <button class="btn btn-primary btn-sm px-2"><i class="bi bi-search"></i></button>
        <a href="<?= url('gm/transfers') ?>" class="btn btn-outline-secondary btn-sm px-2"><i class="bi bi-x"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Transfer table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($transfers)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right d-block fs-3 mb-2 opacity-25"></i><small>No transfers match the selected filters.</small></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Transfer #</th><th>From</th><th>To</th>
            <th>Date</th><th>Requested By</th><th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transfers as $t): ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('transfers/'.$t['id']) ?>" class="text-decoration-none"><?= e($t['transfer_number'] ?? '#'.$t['id']) ?></a>
            </td>
            <td class="small text-muted"><?= e($t['warehouse_name'] ?? '—') ?></td>
            <td class="small"><?= e($t['shop_name'] ?? '—') ?></td>
            <td class="small"><?= $dt($t['transfer_date'] ?? $t['created_at'] ?? null) ?></td>
            <td class="small text-muted"><?= e($t['initiated_by_name'] ?? '—') ?></td>
            <td><span class="badge <?= $statusBadge[$t['status'] ?? ''] ?? 'bg-secondary' ?> small"><?= ucfirst(str_replace('_',' ',$t['status'] ?? '')) ?></span></td>
            <td class="text-end pe-3">
              <a href="<?= url('transfers/'.$t['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">Detail</a>
              <?php if (($t['status'] ?? '') === 'pending'): ?>
              <form method="POST" action="<?= url('gm/transfers/'.$t['id'].'/approve') ?>" class="d-inline" onsubmit="return confirm('Approve?')">
                <?php csrf_input(); ?>
                <button class="btn btn-success btn-sm py-0 px-2 ms-1"><i class="bi bi-check-lg"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if (($pagination['last_page'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">Showing page <?= $pagination['current_page'] ?> of <?= $pagination['last_page'] ?> (<?= $pagination['total'] ?> total)</small>
      <div class="d-flex gap-1">
        <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
        <a href="?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>"
           class="btn btn-sm <?= $p === $pagination['current_page'] ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
