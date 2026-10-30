<?php
/**
 * =========================================================
 * AAM — Farm Module — Farm Details
 * =========================================================
 * @var \AAM\Core\View $view
 * @var array          $farm
 * @var array          $stats
 * @var string         $tab
 * @var array          $recentHarvests
 * @var array          $recentDispatches
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

$farmId   = (int)$farm['id'];
$isAdmin  = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
$fmt      = fn(mixed $v): string => is_numeric($v) ? number_format((float)$v, 1) : ($v ?? '—');

$statusBadge = static fn(string $s, string $l): string =>
    "<span class=\"badge rounded-pill bg-{$s}-subtle text-{$s}\">{$l}</span>";
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-flower1 me-2 text-success"></i><?= e($farm['name']) ?>
      <?php if (!(int)$farm['is_active']): ?>
      <span class="badge bg-secondary ms-2 fs-6">Inactive</span>
      <?php endif; ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('farms') ?>">Farms</a></li>
        <li class="breadcrumb-item active"><?= e($farm['name']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('farms/' . $farmId . '/harvests/create') ?>"
       class="btn btn-success btn-sm">
      <i class="bi bi-basket-fill me-1"></i>Record Harvest
    </a>
    <a href="<?= url('farms/' . $farmId . '/dispatches/create') ?>"
       class="btn btn-outline-primary btn-sm">
      <i class="bi bi-truck me-1"></i>Create Dispatch
    </a>
    <?php if ($isAdmin): ?>
    <a href="<?= url('admin/organization/farms/' . $farmId . '/edit') ?>"
       class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-pencil me-1"></i>Edit Farm
    </a>
    <?php endif; ?>
  </div>
</div>

<?php $view->flash(); ?>

<!-- ── Farm Header Card ──────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3"
     style="background:linear-gradient(135deg,#198754 0%,#20c997 100%);color:#fff;">
  <div class="card-body py-3 px-4">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="d-flex align-items-center gap-3 mb-2">
          <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:48px;height:48px;">
            <i class="bi bi-flower1 fs-4"></i>
          </div>
          <div>
            <div class="small opacity-75">Farm Code</div>
            <div class="fw-bold font-monospace"><?= e($farm['farm_code'] ?? ('FARM-' . str_pad((string)$farmId, 3, '0', STR_PAD_LEFT))) ?></div>
          </div>
        </div>
        <?php if (!empty($farm['location'])): ?>
        <div class="small opacity-90">
          <i class="bi bi-geo-alt me-1"></i><?= e($farm['location']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($farm['farm_type'])): ?>
        <div class="small opacity-90 mt-1">
          <i class="bi bi-tag me-1"></i><?= e($farm['farm_type']) ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="col-md-6">
        <?php if (!empty($farm['manager_name'])): ?>
        <div class="small opacity-75 mb-1">Manager</div>
        <div class="fw-semibold"><?= e($farm['manager_name']) ?></div>
        <?php if (!empty($farm['manager_phone'])): ?>
        <div class="small opacity-90">
          <i class="bi bi-telephone me-1"></i><?= e($farm['manager_phone']) ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($farm['area_hectares'])): ?>
        <div class="small opacity-90 mt-1">
          <i class="bi bi-rulers me-1"></i><?= e($farm['area_hectares']) ?> ha
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── KPI Summary ───────────────────────────────────────── -->
<div class="row g-2 mb-3">
  <?php
  $kpiItems = [
    [number_format((float)($stats['today_qty'] ?? 0), 1),   "Today's Harvest",     'success', 'bi-basket-fill'],
    [number_format((float)($stats['monthly_qty'] ?? 0), 1), 'Monthly Production',  'info',    'bi-calendar-check'],
    [$stats['pending_dispatch'] ?? 0,                        'Pending Dispatch',   'warning', 'bi-hourglass-split'],
    [$stats['dispatched_total'] ?? 0,                        'Dispatched (Year)',  'primary', 'bi-truck'],
  ];
  foreach ($kpiItems as [$val, $lbl, $clr, $ico]): ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100"
         style="border-top:3px solid var(--bs-<?= $clr ?>)!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted small"><?= $lbl ?></span>
          <i class="bi <?= $ico ?> text-<?= $clr ?>"></i>
        </div>
        <div class="fw-bold fs-4 text-<?= $clr ?>"><?= $val ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Tabs ──────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3" id="farmTabs">
  <?php
  $tabs = [
    ['overview',  'Overview',           'bi-speedometer2'],
    ['harvests',  'Harvest',            'bi-basket2-fill'],
    ['dispatches','Dispatch',           'bi-truck'],
  ];
  foreach ($tabs as [$slug, $label, $icon]):
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab === $slug ? 'active' : '' ?>"
       href="<?= url('farms/' . $farmId) ?>?tab=<?= $slug ?>">
      <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- ── Tab Content ───────────────────────────────────────── -->

<?php if ($tab === 'overview'): ?>
<!-- OVERVIEW -->
<div class="row g-3">
  <!-- Recent Harvests -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
          <i class="bi bi-basket me-2 text-success"></i>Recent Harvests
        </span>
        <a href="<?= url('farms/' . $farmId . '/harvests') ?>"
           class="btn btn-outline-success btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentHarvests)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-basket d-block fs-3 mb-2 opacity-25"></i>
            <small>No harvest records yet.</small>
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3 small">Batch / Product</th>
                <th class="small">Qty</th>
                <th class="small">Date</th>
                <th class="small">Status</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recentHarvests as $h):
              $sc = \AAM\Farm\Models\HarvestBatchModel::getStatusBadgeClass($h['status'] ?? '');
              $sl = \AAM\Farm\Models\HarvestBatchModel::getStatusLabel($h['status'] ?? '');
            ?>
            <tr>
              <td class="ps-3 small fw-semibold">
                <?= e($h['product_name'] ?? '—') ?>
                <?php if (!empty($h['batch_number'])): ?>
                <div class="text-muted font-monospace" style="font-size:.68rem;"><?= e($h['batch_number']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small">
                <?= number_format((float)($h['initial_quantity'] ?? 0), 1) ?>
                <span class="text-muted"><?= e($h['unit_abbr'] ?? '') ?></span>
              </td>
              <td class="small text-muted">
                <?= !empty($h['harvest_date']) ? date('d M Y', strtotime($h['harvest_date'])) : '—' ?>
              </td>
              <td>
                <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> small"><?= $sl ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Dispatches -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
          <i class="bi bi-truck me-2 text-primary"></i>Recent Dispatches
        </span>
        <a href="<?= url('farms/' . $farmId . '/dispatches') ?>"
           class="btn btn-outline-primary btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentDispatches)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-truck d-block fs-3 mb-2 opacity-25"></i>
            <small>No dispatches yet.</small>
          </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($recentDispatches as $d):
            $ds  = \AAM\Farm\Models\FarmDispatchModel::getStatusBadgeClass($d['status'] ?? '');
            $dl  = \AAM\Farm\Models\FarmDispatchModel::getStatusLabel($d['status'] ?? '');
          ?>
          <a href="<?= url('dispatches/' . (int)$d['id']) ?>"
             class="list-group-item list-group-item-action py-2">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="small fw-semibold font-monospace"><?= e($d['dispatch_number']) ?></div>
                <div class="text-muted" style="font-size:.7rem;">
                  <?= !empty($d['dispatch_date']) ? date('d M Y', strtotime($d['dispatch_date'])) : '—' ?>
                  · <?= e($d['warehouse_name'] ?? '—') ?>
                </div>
              </div>
              <span class="badge bg-<?= $ds ?>-subtle text-<?= $ds ?> small"><?= $dl ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'harvests'): ?>
<!-- HARVEST TAB: quick redirect link -->
<div class="text-center py-4">
  <i class="bi bi-basket2-fill display-5 text-success d-block mb-3 opacity-50"></i>
  <h6>Harvest Records</h6>
  <a href="<?= url('farms/' . $farmId . '/harvests') ?>"
     class="btn btn-success">
    <i class="bi bi-arrow-right-circle me-1"></i>View All Harvest Records
  </a>
</div>

<?php elseif ($tab === 'dispatches'): ?>
<!-- DISPATCH TAB: quick redirect link -->
<div class="text-center py-4">
  <i class="bi bi-truck display-5 text-primary d-block mb-3 opacity-50"></i>
  <h6>Dispatch Records</h6>
  <a href="<?= url('farms/' . $farmId . '/dispatches') ?>"
     class="btn btn-primary">
    <i class="bi bi-arrow-right-circle me-1"></i>View All Dispatches
  </a>
</div>
<?php endif; ?>
