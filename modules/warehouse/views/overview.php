<?php
/**
 * Warehouse Overview Dashboard — Phase 10
 * @var array $warehouse @var array $kpis @var array $expiringSoon
 * @var array $lowStock  @var array $recentReceiving @var array $recentTransfers
 * @var int   $warehouseId @var string $userRole
 */
declare(strict_types=1);

$kpis            ??= [];
$expiringSoon    ??= [];
$lowStock        ??= [];
$recentReceiving ??= [];
$recentTransfers ??= [];
$warehouse       ??= ['name' => 'Warehouse'];

$f  = fn($v) => number_format((float)$v, 1);
$fi = fn($v) => number_format((int)$v);
?>

<!-- Banner -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#0d6efd 0%,#0dcaf0 100%);color:#fff;">
  <div class="card-body d-flex align-items-center gap-3 py-3">
    <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                justify-content-center flex-shrink-0" style="width:52px;height:52px;">
      <i class="bi bi-building fs-4"></i>
    </div>
    <div class="flex-grow-1">
      <h5 class="mb-0 fw-bold"><?= e($warehouse['name'] ?? 'Warehouse') ?></h5>
      <small class="opacity-75"><?= date('l, d F Y') ?> &nbsp;&middot;&nbsp; Warehouse Operations</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= url('warehouse/receiving') ?>" class="btn btn-success btn-sm fw-semibold">
        <i class="bi bi-inbox-fill me-1"></i>Receive Products
      </a>
      <a href="<?= url('transfers/create') ?>" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-right-circle me-1"></i>New Transfer
      </a>
      <a href="<?= url('warehouse/movements') ?>" class="btn btn-outline-light btn-sm">
        <i class="bi bi-clock-history me-1"></i>Stock History
      </a>
    </div>
  </div>
</div>

<!-- KPI Row 1 -->
<div class="row g-3 mb-3">
  <?php
  $kpiCards = [
    ['Total Stock (units)',   $f($kpis['total_stock']       ?? 0), 'bi-boxes',                'primary'],
    ['Products in Warehouse', $fi($kpis['total_products']   ?? 0), 'bi-box-seam',             'info'],
    ['Pending Receipts',      $fi($kpis['pending_receipts'] ?? 0), 'bi-inbox-fill',           'warning'],
    ['Pending Transfers',     $fi($kpis['pending_transfers']?? 0), 'bi-arrow-right-circle',   'warning'],
    ['Low Stock Items',       $fi($kpis['low_stock_count']  ?? 0), 'bi-exclamation-triangle', 'danger'],
    ['Open Discrepancies',    $fi($kpis['open_discrepancies']??0), 'bi-clipboard-x',          'danger'],
    ["Today's Received",      $f($kpis['today_received']    ?? 0), 'bi-box-arrow-in-down',    'success'],
    ["Today's Dispatched",    $f($kpis['today_dispatched']  ?? 0), 'bi-box-arrow-right',      'primary'],
  ];
  foreach ($kpiCards as [$label, $value, $icon, $color]): ?>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid var(--bs-<?= $color ?>)!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small"><?= $label ?></span>
          <div class="rounded bg-<?= $color ?> bg-opacity-10 p-1">
            <i class="bi <?= $icon ?> text-<?= $color ?>"></i>
          </div>
        </div>
        <div class="fw-bold fs-3 lh-1 text-<?= ($value !== '0' && ($color === 'danger' || $color === 'warning')) ? $color : '' ?>">
          <?= $value ?>
        </div>
        <div class="text-muted" style="font-size:.7rem;"><?= $label ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Alerts row -->
<?php if (!empty($expiringSoon) || !empty($lowStock)): ?>
<div class="row g-3 mb-3">
  <?php if (!empty($expiringSoon)): ?>
  <div class="col-12">
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2">
      <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
      <strong><?= count($expiringSoon) ?> product(s) expiring within 7 days.</strong>
      <a href="<?= url('warehouse/inventory?status=expiring_soon') ?>" class="ms-auto btn btn-warning btn-sm py-0 px-2">View</a>
    </div>
  </div>
  <?php endif; ?>
  <?php if (!empty($lowStock)): ?>
  <div class="col-12">
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
      <strong><?= count($lowStock) ?> product(s) below minimum stock level.</strong>
      <a href="<?= url('warehouse/inventory?status=low_stock') ?>" class="ms-auto btn btn-danger btn-sm py-0 px-2">View</a>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Check for transfers awaiting GM discrepancy approval (dispatched by this WM, now discrepancy_pending)
$db2 = \AAM\Core\Database::getInstance();
$discrepancyPendingTransfers = $db2->fetchAll(
    "SELECT id, transfer_number, shop_id,
            (SELECT name FROM shops WHERE id = wt.shop_id) AS shop_name
     FROM warehouse_transfers wt
     WHERE warehouse_id = :wid AND status = 'discrepancy_pending'
     ORDER BY updated_at DESC LIMIT 10",
    ['wid' => $warehouseId ?? 0]
);
?>
<?php if (!empty($discrepancyPendingTransfers)): ?>
<div class="alert alert-danger d-flex align-items-start gap-3 mb-3">
  <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
  <div class="flex-grow-1">
    <div class="fw-bold">
      <?= count($discrepancyPendingTransfers) ?> transfer<?= count($discrepancyPendingTransfers) > 1 ? 's' : '' ?>
      pending General Manager discrepancy approval
    </div>
    <div class="small mt-1">
      The destination shop reported a quantity discrepancy on these transfers.
      Shop inventory is blocked until the GM approves.
      <?php foreach ($discrepancyPendingTransfers as $dt): ?>
      <a href="<?= url('transfers/' . $dt['id']) ?>"
         class="badge bg-danger text-white text-decoration-none ms-1">
        <?= e($dt['transfer_number']) ?> → <?= e($dt['shop_name'] ?? '') ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<div class="row g-3">
  <!-- Recent Receivings -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small"><i class="bi bi-inbox-fill me-2 text-success"></i>Recent Receivings</span>
        <a href="<?= url('warehouse/receiving') ?>" class="btn btn-outline-success btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentReceiving)): ?>
          <div class="text-center py-4 text-muted small">No receiving records yet.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3 small">Number</th>
                <th class="small">Farm</th>
                <th class="small">Received</th>
                <th class="small pe-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentReceiving as $r):
                $badge = \AAM\Warehouse\Models\ReceivingModel::statusBadge($r['status']);
                $label = \AAM\Warehouse\Models\ReceivingModel::statusLabel($r['status']);
              ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <a href="<?= url('warehouse/receiving/' . $r['id']) ?>"><?= e($r['receiving_number']) ?></a>
                </td>
                <td class="small text-muted"><?= e($r['farm_name'] ?? '—') ?></td>
                <td class="small"><?= e($r['total_received'] ?? '0') ?></td>
                <td class="pe-3"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Transfers -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small"><i class="bi bi-arrow-right-circle me-2 text-primary"></i>Recent Transfers</span>
        <a href="<?= url('transfers') ?>" class="btn btn-outline-primary btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentTransfers)): ?>
          <div class="text-center py-4 text-muted small">No transfers yet.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3 small">Number</th>
                <th class="small">Shop</th>
                <th class="small">Qty</th>
                <th class="small pe-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentTransfers as $t):
                $badge = \AAM\Warehouse\Models\TransferModel::statusBadge($t['status']);
                $label = \AAM\Warehouse\Models\TransferModel::statusLabel($t['status']);
              ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <a href="<?= url('transfers/' . $t['id']) ?>"><?= e($t['transfer_number']) ?></a>
                </td>
                <td class="small text-muted"><?= e($t['shop_name'] ?? '—') ?></td>
                <td class="small"><?= number_format((float)$t['total_quantity'], 1) ?></td>
                <td class="pe-3"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
