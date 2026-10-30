<?php
/**
 * =========================================================
 * AAM — Farm Module — Harvest List
 * =========================================================
 * @var \AAM\Core\View $view
 * @var array          $farm
 * @var array          $harvests
 * @var array          $pagination
 * @var array          $filters
 * @var array          $products
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

use AAM\Farm\Models\HarvestBatchModel;

$farmId  = (int)$farm['id'];
$isAdmin = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);

$statusOptions = [
    HarvestBatchModel::STATUS_ACTIVE               => 'Active',
    HarvestBatchModel::STATUS_PARTIALLY_DISPATCHED => 'Partially Dispatched',
    HarvestBatchModel::STATUS_FULLY_DISPATCHED     => 'Fully Dispatched',
    HarvestBatchModel::STATUS_DEPLETED             => 'Depleted',
    HarvestBatchModel::STATUS_EXPIRED              => 'Expired',
    HarvestBatchModel::STATUS_QUARANTINE           => 'Quarantine',
];
$qualityOptions = ['A' => 'Grade A', 'B' => 'Grade B', 'C' => 'Grade C', 'Ungraded' => 'Ungraded'];
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-basket2-fill me-2 text-success"></i>Harvest Records
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('farms') ?>">Farms</a></li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId) ?>"><?= e($farm['name']) ?></a>
        </li>
        <li class="breadcrumb-item active">Harvest</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('farms/' . $farmId . '/harvests/create') ?>"
     class="btn btn-success btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Record Harvest
  </a>
</div>

<?php $view->flash(); ?>

<!-- ── Filters ──────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="<?= url('farms/' . $farmId . '/harvests') ?>"
          class="row g-2 align-items-end">
      <div class="col-md-3">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="search" class="form-control" name="search"
                 value="<?= e($filters['search'] ?? '') ?>"
                 placeholder="Batch ID, product…" autocomplete="off">
        </div>
      </div>
      <?php if (!empty($products)): ?>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="product_id">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>"
            <?= (string)($filters['product_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="status">
          <option value="">All Status</option>
          <?php foreach ($statusOptions as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>>
            <?= $v ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="quality">
          <option value="">All Grades</option>
          <?php foreach ($qualityOptions as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters['quality'] ?? '') === $k ? 'selected' : '' ?>>
            <?= $v ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <div class="input-group input-group-sm">
          <input type="date" class="form-control" name="date_from"
                 value="<?= e($filters['date_from'] ?? '') ?>">
          <input type="date" class="form-control" name="date_to"
                 value="<?= e($filters['date_to'] ?? '') ?>">
        </div>
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <?php if (array_filter($filters)): ?>
        <a href="<?= url('farms/' . $farmId . '/harvests') ?>"
           class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ── Table ─────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($harvests)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-basket2 display-4 d-block mb-3 opacity-25"></i>
      <h6 class="fw-semibold">No harvest records found</h6>
      <?php if (array_filter($filters)): ?>
      <a href="<?= url('farms/' . $farmId . '/harvests') ?>"
         class="btn btn-outline-secondary btn-sm mt-2">Clear Filters</a>
      <?php else: ?>
      <a href="<?= url('farms/' . $farmId . '/harvests/create') ?>"
         class="btn btn-success btn-sm mt-2">
        <i class="bi bi-plus-lg me-1"></i>Record First Harvest
      </a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Harvest ID</th>
            <th>Product</th>
            <th class="text-end">Initial Qty</th>
            <th class="text-end">Available</th>
            <th>Date</th>
            <th>Quality</th>
            <th>Status</th>
            <th>Recorded By</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($harvests as $h):
            $sc = HarvestBatchModel::getStatusBadgeClass($h['status'] ?? '');
            $sl = HarvestBatchModel::getStatusLabel($h['status'] ?? '');
            $qc = HarvestBatchModel::getQualityBadgeClass($h['quality_grade'] ?? '');
            $isEditable = HarvestBatchModel::isEditable($h['status'] ?? '');
          ?>
          <tr>
            <td class="ps-3">
              <a href="<?= url('harvests/' . (int)$h['id']) ?>"
                 class="text-decoration-none fw-semibold font-monospace small">
                <?= e($h['batch_number'] ?? "HARV-{$h['id']}") ?>
              </a>
            </td>
            <td class="small">
              <div class="fw-semibold"><?= e($h['product_name'] ?? '—') ?></div>
              <?php if (!empty($h['product_sku'])): ?>
              <div class="text-muted font-monospace" style="font-size:.68rem;"><?= e($h['product_sku']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end small fw-semibold">
              <?= number_format((float)($h['initial_quantity'] ?? 0), 2) ?>
              <span class="text-muted"><?= e($h['unit_abbr'] ?? '') ?></span>
            </td>
            <td class="text-end small">
              <?php
              $rem = (float)($h['remaining_quantity'] ?? 0);
              $init = (float)($h['initial_quantity'] ?? 1);
              $pct  = $init > 0 ? round($rem / $init * 100) : 0;
              ?>
              <span class="<?= $rem <= 0 ? 'text-secondary' : ($pct < 30 ? 'text-danger fw-bold' : 'text-success fw-bold') ?>">
                <?= number_format($rem, 2) ?>
              </span>
              <span class="text-muted"><?= e($h['unit_abbr'] ?? '') ?></span>
              <div class="progress mt-1" style="height:3px;">
                <div class="progress-bar bg-<?= $pct < 30 ? 'danger' : 'success' ?>"
                     style="width:<?= $pct ?>%"></div>
              </div>
            </td>
            <td class="small text-muted">
              <?= !empty($h['harvest_date']) ? date('d M Y', strtotime($h['harvest_date'])) : '—' ?>
            </td>
            <td>
              <span class="badge bg-<?= $qc ?>-subtle text-<?= $qc ?> small">
                <?= e($h['quality_grade'] ?? '—') ?>
              </span>
            </td>
            <td>
              <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> small"><?= $sl ?></span>
            </td>
            <td class="small text-muted">
              <?= e($h['recorded_by_name'] ?? '—') ?>
            </td>
            <td class="text-end pe-3">
              <div class="d-flex justify-content-end gap-1">
                <a href="<?= url('harvests/' . (int)$h['id']) ?>"
                   class="btn btn-sm btn-outline-info" title="View">
                  <i class="bi bi-eye-fill"></i>
                </a>
                <?php if ($isEditable): ?>
                <a href="<?= url('harvests/' . (int)$h['id'] . '/edit') ?>"
                   class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil-fill"></i>
                </a>
                <?php endif; ?>
                <?php if ($h['status'] === HarvestBatchModel::STATUS_ACTIVE && (float)$h['remaining_quantity'] > 0): ?>
                <a href="<?= url('farms/' . $farmId . '/dispatches/create') ?>"
                   class="btn btn-sm btn-outline-success" title="Prepare Dispatch">
                  <i class="bi bi-truck"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Footer / Pagination -->
    <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-3 border-top gap-2">
      <div class="text-muted small">
        Showing <strong><?= count($harvests) ?></strong>
        of <strong><?= $pagination['total'] ?></strong> records
      </div>
      <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($pg = 1; $pg <= $pagination['totalPages']; $pg++): ?>
          <li class="page-item <?= $pg === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
            <a class="page-link"
               href="?page=<?= $pg ?>&<?= http_build_query(array_filter($filters)) ?>">
              <?= $pg ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
