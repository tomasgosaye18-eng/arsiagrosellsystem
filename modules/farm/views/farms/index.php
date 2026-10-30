<?php
/**
 * =========================================================
 * AAM — Farm Module — All Farms
 * =========================================================
 * File     : modules/farm/views/farms/index.php
 * @var \AAM\Core\View $view
 * @var array          $farms
 * @var array          $filters
 * @var string[]       $farmTypes
 * @var array|null     $stats      (admin/GM only)
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

// $isAdmin: controls admin-only actions (register/edit/delete farm entities)
// GM can VIEW farms but cannot register/edit/delete them — that is System Admin territory.
$isAdmin    = in_array($userRole ?? '', [ROLE_ADMIN], true);
$canViewAll = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);

$statusBadge = static fn(int $active): string =>
    $active
    ? '<span class="badge bg-success-subtle text-success rounded-pill">Active</span>'
    : '<span class="badge bg-secondary-subtle text-secondary rounded-pill">Inactive</span>';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-flower1 me-2 text-success"></i>All Farms
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Farms</li>
      </ol>
    </nav>
  </div>
  <?php if ($isAdmin): ?>
  <a href="<?= url('admin/organization/farms/create') ?>" class="btn btn-success btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Register Farm
  </a>
  <?php endif; ?>
</div>

<?php $view->flash(); ?>

<!-- ── Stats Strip (visible to Admin and GM — business overview) ───────────── -->
<?php if ($canViewAll && $stats): ?>
<div class="row g-2 mb-3">
  <?php
  $statItems = [
    [$stats['total'],   'Total Farms',        'bi-flower1',        'primary'],
    [$stats['active'],  'Active',             'bi-check-circle',   'success'],
    [$stats['pending'], 'Pending Dispatches', 'bi-hourglass-split','warning'],
    [$stats['batches'], 'Active Batches',     'bi-basket2',        'info'],
  ];
  foreach ($statItems as [$val, $lbl, $ico, $clr]): ?>
  <div class="col-6 col-sm-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi <?= $ico ?> text-<?= $clr ?> fs-5"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)$val ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= $lbl ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Filters ──────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="<?= url('farms') ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="search" class="form-control" name="search"
                 value="<?= e($filters['search'] ?? '') ?>"
                 placeholder="Search farms, location…">
        </div>
      </div>
      <?php if (!empty($farmTypes)): ?>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="farm_type">
          <option value="">All Types</option>
          <?php foreach ($farmTypes as $ft): ?>
          <option value="<?= e($ft) ?>" <?= ($filters['farm_type'] ?? '') === $ft ? 'selected' : '' ?>>
            <?= e($ft) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="status">
          <option value="">All Status</option>
          <option value="1" <?= ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <?php if (array_filter($filters)): ?>
        <a href="<?= url('farms') ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ── Farm Cards ────────────────────────────────────────── -->
<?php if (empty($farms)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-flower1 display-4 d-block mb-3 opacity-25"></i>
    <h6 class="fw-semibold">No farms found</h6>
    <?php if (array_filter($filters)): ?>
    <a href="<?= url('farms') ?>" class="btn btn-outline-secondary btn-sm mt-2">Clear Filters</a>
    <?php else: ?>
    <p class="small mb-2">No farms have been registered yet.</p>
    <?php if ($isAdmin): ?>
    <a href="<?= url('admin/organization/farms/create') ?>" class="btn btn-success btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Register First Farm
    </a>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($farms as $farm): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #198754!important;">
      <div class="card-body">

        <!-- Farm header -->
        <div class="d-flex align-items-start justify-content-between mb-2">
          <div class="flex-grow-1 overflow-hidden me-2">
            <h6 class="fw-bold mb-0 text-truncate">
              <a href="<?= url('farms/' . (int)$farm['id']) ?>"
                 class="text-decoration-none text-dark">
                <?= e($farm['name']) ?>
              </a>
            </h6>
            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
              <?php if (!empty($farm['farm_code'])): ?>
              <span class="font-monospace text-muted" style="font-size:.68rem;">
                <?= e($farm['farm_code']) ?>
              </span>
              <?php endif; ?>
              <?php if (!empty($farm['farm_type'])): ?>
              <span class="badge bg-success-subtle text-success small">
                <?= e($farm['farm_type']) ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
          <?= $statusBadge((int)$farm['is_active']) ?>
        </div>

        <!-- Location -->
        <?php if (!empty($farm['location'])): ?>
        <div class="small text-muted mb-2">
          <i class="bi bi-geo-alt me-1"></i><?= e($farm['location']) ?>
        </div>
        <?php endif; ?>

        <!-- Manager -->
        <?php if (!empty($farm['manager_name'])): ?>
        <div class="small text-muted mb-3">
          <i class="bi bi-person me-1"></i><?= e($farm['manager_name']) ?>
          <?php if (!empty($farm['manager_phone'])): ?>
          &nbsp;·&nbsp;<?= e($farm['manager_phone']) ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Stats row -->
        <div class="row g-2 mb-3">
          <div class="col-4 text-center">
            <div class="fw-bold text-success">
              <?= number_format((float)($farm['active_batches'] ?? 0)) ?>
            </div>
            <div class="text-muted" style="font-size:.68rem;">Active Batches</div>
          </div>
          <div class="col-4 text-center border-start border-end">
            <div class="fw-bold text-warning">
              <?= number_format((float)($farm['pending_dispatches'] ?? 0)) ?>
            </div>
            <div class="text-muted" style="font-size:.68rem;">Pending Dispatch</div>
          </div>
          <div class="col-4 text-center">
            <div class="fw-bold text-primary" style="font-size:.9rem;">
              <?= number_format((float)($farm['total_available_qty'] ?? 0), 1) ?>
            </div>
            <div class="text-muted" style="font-size:.68rem;">Available Qty</div>
          </div>
        </div>

        <!-- Last activity -->
        <?php if (!empty($farm['last_harvest_at'])): ?>
        <div class="text-muted mb-3" style="font-size:.72rem;">
          <i class="bi bi-clock me-1"></i>Last activity:
          <?= date('d M Y', strtotime($farm['last_harvest_at'])) ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="d-flex gap-2 flex-wrap">
          <a href="<?= url('farms/' . (int)$farm['id']) ?>"
             class="btn btn-outline-success btn-sm flex-grow-1">
            <i class="bi bi-eye me-1"></i>View
          </a>
          <a href="<?= url('farms/' . (int)$farm['id'] . '/harvests') ?>"
             class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-basket2"></i>
          </a>
          <a href="<?= url('farms/' . (int)$farm['id'] . '/dispatches') ?>"
             class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-truck"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
