<?php
/**
 * =========================================================
 * AAM — Farm Module — Harvest Detail
 * =========================================================
 * @var \AAM\Core\View $view
 * @var array          $harvest
 * @var array          $dispatches
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

use AAM\Farm\Models\HarvestBatchModel;

$isAdmin   = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
$farmId    = (int)$harvest['farm_id'];
$sc        = HarvestBatchModel::getStatusBadgeClass($harvest['status'] ?? '');
$sl        = HarvestBatchModel::getStatusLabel($harvest['status'] ?? '');
$qc        = HarvestBatchModel::getQualityBadgeClass($harvest['quality_grade'] ?? '');
$isEditable = HarvestBatchModel::isEditable($harvest['status'] ?? '');

$rem   = (float)($harvest['remaining_quantity'] ?? 0);
$init  = (float)($harvest['initial_quantity'] ?? 1);
$disp  = $init - $rem;
$pct   = $init > 0 ? round($rem / $init * 100) : 0;
?>

<!-- ── Header ───────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-basket-fill me-2 text-success"></i>
      <?= e($harvest['batch_number'] ?? "Harvest #{$harvest['id']}") ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('farms') ?>">Farms</a></li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId) ?>"><?= e($harvest['farm_name'] ?? '') ?></a>
        </li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId . '/harvests') ?>">Harvest</a>
        </li>
        <li class="breadcrumb-item active"><?= e($harvest['batch_number'] ?? $harvest['id']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($isEditable): ?>
    <a href="<?= url('harvests/' . (int)$harvest['id'] . '/edit') ?>"
       class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <?php endif; ?>
    <?php if ($rem > 0 && $harvest['quality_grade'] !== 'C'): ?>
    <a href="<?= url('farms/' . $farmId . '/dispatches/create') ?>"
       class="btn btn-success btn-sm">
      <i class="bi bi-truck me-1"></i>Create Dispatch
    </a>
    <?php endif; ?>
    <?php if ($isAdmin && $harvest['status'] === HarvestBatchModel::STATUS_ACTIVE && $disp === 0.0): ?>
    <form method="POST" action="<?= url('harvests/' . (int)$harvest['id'] . '/cancel') ?>"
          onsubmit="return confirm('Cancel this harvest batch?')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <button type="submit" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-x-circle me-1"></i>Cancel
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php $view->flash(); ?>

<!-- ── Grade C Warning ──────────────────────────────────── -->
<?php if ($harvest['quality_grade'] === 'C'): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
  <div>
    <strong>Rejected Harvest.</strong>
    This batch is Grade C. It cannot be dispatched without authorized action.
  </div>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- ── Detail Card ─────────────────────────────────────── -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header">
        <span class="fw-semibold small">
          <i class="bi bi-info-circle me-2 text-success"></i>Harvest Details
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php
          $fields = [
            ['Farm',           $harvest['farm_name'] ?? '—'],
            ['Product',        $harvest['product_name'] ?? '—'],
            ['Batch Number',   $harvest['batch_number'] ?? '—'],
            ['Production Date',!empty($harvest['production_date']) ? date('d M Y', strtotime($harvest['production_date'])) : '—'],
            ['Harvest Date',   !empty($harvest['harvest_date'])    ? date('d M Y', strtotime($harvest['harvest_date']))    : '—'],
            ['Expiry Date',    !empty($harvest['expiry_date'])     ? date('d M Y', strtotime($harvest['expiry_date']))     : 'N/A'],
            ['Recorded By',    $harvest['recorded_by_name'] ?? '—'],
            ['Created',        !empty($harvest['created_at'])      ? date('d M Y H:i', strtotime($harvest['created_at']))  : '—'],
          ];
          foreach ($fields as [$label, $value]):
          ?>
          <div class="col-sm-6">
            <div class="text-muted small"><?= $label ?></div>
            <div class="fw-semibold small"><?= e($value) ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if (!empty($harvest['notes'])): ?>
        <hr>
        <div class="text-muted small mb-1">Notes</div>
        <div class="small"><?= nl2br(e($harvest['notes'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Quantity Card ───────────────────────────────────── -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header">
        <span class="fw-semibold small">
          <i class="bi bi-bar-chart-fill me-2 text-success"></i>Quantity Status
        </span>
      </div>
      <div class="card-body">
        <!-- Status + Quality badges -->
        <div class="d-flex gap-2 mb-3">
          <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?>"><?= $sl ?></span>
          <span class="badge bg-<?= $qc ?>-subtle text-<?= $qc ?>">
            Grade <?= e($harvest['quality_grade'] ?? '—') ?>
          </span>
        </div>

        <!-- Quantity breakdown -->
        <div class="row g-2 text-center mb-3">
          <div class="col-4">
            <div class="fw-bold fs-5 text-dark"><?= number_format($init, 2) ?></div>
            <div class="text-muted" style="font-size:.7rem;">
              Initial<br><span class="text-muted"><?= e($harvest['unit_abbr'] ?? '') ?></span>
            </div>
          </div>
          <div class="col-4 border-start border-end">
            <div class="fw-bold fs-5 text-warning"><?= number_format($disp, 2) ?></div>
            <div class="text-muted" style="font-size:.7rem;">Dispatched</div>
          </div>
          <div class="col-4">
            <div class="fw-bold fs-5 <?= $rem > 0 ? 'text-success' : 'text-secondary' ?>">
              <?= number_format($rem, 2) ?>
            </div>
            <div class="text-muted" style="font-size:.7rem;">Available</div>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="progress mb-1" style="height:8px;">
          <div class="progress-bar bg-success"
               style="width:<?= $pct ?>%"
               role="progressbar"
               aria-valuenow="<?= $pct ?>"
               aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="text-muted small text-end"><?= $pct ?>% available</div>
      </div>
    </div>

    <!-- Traceability -->
    <div class="card border-0 shadow-sm">
      <div class="card-header">
        <span class="fw-semibold small">
          <i class="bi bi-diagram-2 me-2 text-success"></i>Traceability
        </span>
      </div>
      <div class="card-body p-0">
        <?php
        $trace = [
          ['FARM',    'bi-flower1', 'success', $harvest['farm_name'] ?? '—'],
          ['HARVEST', 'bi-basket',  'success', $harvest['batch_number'] ?? '—'],
          ['DISPATCH','bi-truck',   'primary', count($dispatches) . ' dispatch(es)'],
          ['WAREHOUSE','bi-building','secondary','Central Warehouse'],
        ];
        foreach ($trace as $i => [$stage, $ico, $clr, $desc]):
        ?>
        <div class="d-flex align-items-center gap-2 px-3 py-2
                    <?= $i < 3 ? 'border-bottom' : '' ?>">
          <div class="rounded-circle bg-<?= $clr ?> bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:28px;height:28px;">
            <i class="bi <?= $ico ?> text-<?= $clr ?>" style="font-size:.75rem;"></i>
          </div>
          <div class="flex-grow-1">
            <div class="small fw-bold"><?= $stage ?></div>
            <div class="text-muted" style="font-size:.7rem;"><?= e($desc) ?></div>
          </div>
          <?php if ($i < 3): ?>
          <i class="bi bi-arrow-down text-muted small"></i>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Dispatch History ──────────────────────────────────── -->
<?php if (!empty($dispatches)): ?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header">
    <span class="fw-semibold small">
      <i class="bi bi-truck me-2 text-primary"></i>Dispatch History
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-3 small">Dispatch #</th>
          <th class="small">Date</th>
          <th class="small text-end">Qty Dispatched</th>
          <th class="small">Destination</th>
          <th class="small">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dispatches as $d):
          $ds = \AAM\Farm\Models\FarmDispatchModel::getStatusBadgeClass($d['status'] ?? '');
          $dl = \AAM\Farm\Models\FarmDispatchModel::getStatusLabel($d['status'] ?? '');
        ?>
        <tr>
          <td class="ps-3 small font-monospace fw-semibold">
            <?= e($d['dispatch_number'] ?? '—') ?>
          </td>
          <td class="small text-muted">
            <?= !empty($d['dispatch_date']) ? date('d M Y', strtotime($d['dispatch_date'])) : '—' ?>
          </td>
          <td class="small text-end fw-semibold">
            <?= number_format((float)($d['quantity_dispatched'] ?? 0), 2) ?>
          </td>
          <td class="small"><?= e($d['warehouse_name'] ?? '—') ?></td>
          <td>
            <span class="badge bg-<?= $ds ?>-subtle text-<?= $ds ?> small"><?= $dl ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
