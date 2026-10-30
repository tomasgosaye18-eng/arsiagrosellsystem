<?php
/**
 * View: modules/admin/views/audit/index.php
 * Purpose: Business Operational Audit Trail
 *          Enterprise-grade timeline/table with full filters,
 *          export, search, sort, pagination, and detail view.
 *
 * @var \AAM\Core\View  $view
 * @var array           $logs
 * @var int             $total
 * @var int             $page
 * @var int             $totalPages
 * @var int             $perPage
 * @var array           $filters
 * @var array           $moduleMeta
 * @var array           $actionColor
 * @var array           $filterOptions
 */
declare(strict_types=1);

/** Returns Bootstrap badge colour for an action string */
$actionBadge = function(string $action) use ($actionColor): string {
    foreach ($actionColor as $keyword => $color) {
        if (str_contains(strtolower($action), $keyword)) {
            return $color;
        }
    }
    return 'secondary';
};

/** Returns Bootstrap badge colour for a module */
$moduleBadge = function(string $module) use ($moduleMeta): string {
    return $moduleMeta[strtolower($module)]['color'] ?? 'secondary';
};

/** Location label from a log row */
$locationLabel = function(array $log): string {
    if (!empty($log['farm_name']))      return '<i class="bi bi-tree-fill text-success me-1"></i>' . e($log['farm_name']);
    if (!empty($log['warehouse_name'])) return '<i class="bi bi-building-fill text-primary me-1"></i>' . e($log['warehouse_name']);
    if (!empty($log['shop_name']))      return '<i class="bi bi-shop text-warning me-1"></i>' . e($log['shop_name']);
    return '<span class="text-muted">—</span>';
};

$activeFilters = array_filter($filters, fn($v) => $v !== '' && $v !== 0);
$exportBase    = url('admin/audit-logs/export') . '?' . http_build_query(array_filter($filters, fn($v) => $v !== '' && $v !== 0));
?>

<!-- ── Page Header ──────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-1">
      <i class="bi bi-clipboard2-data-fill me-2 text-primary"></i>Business Audit Trail
    </h4>
    <p class="text-muted small mb-0">
      Track who performed what operational action across the AAM system
      &nbsp;·&nbsp;
      <span class="fw-semibold text-dark"><?= number_format($total) ?></span> records
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= $exportBase ?>&format=excel" class="btn btn-outline-success btn-sm">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
    </a>
    <a href="<?= $exportBase ?>&format=pdf" class="btn btn-outline-danger btn-sm" target="_blank">
      <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
    </a>
  </div>
</div>

<!-- ── Filter Panel ─────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-funnel me-2 text-primary"></i>Filters</span>
    <?php if (!empty($activeFilters)): ?>
    <a href="<?= url('admin/audit-logs') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-x-circle me-1"></i>Clear All
    </a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" action="<?= url('admin/audit-logs') ?>" id="auditFilterForm">

      <!-- Row 1: Search + Date Range -->
      <div class="row g-2 mb-2">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
              <i class="bi bi-search text-muted"></i>
            </span>
            <input type="search" class="form-control border-start-0" name="search"
                   value="<?= e($filters['search']) ?>"
                   placeholder="Search user, action, entity, description…">
          </div>
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control" name="date_from"
                 value="<?= e($filters['date_from']) ?>" title="From date">
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control" name="date_to"
                 value="<?= e($filters['date_to']) ?>" title="To date">
        </div>
        <div class="col-md-2">
          <select class="form-select" name="module">
            <option value="">All Modules</option>
            <?php foreach ($filterOptions['modules'] as $mod): ?>
            <option value="<?= e($mod) ?>" <?= $filters['module'] === $mod ? 'selected' : '' ?>>
              <?= ucfirst(e($mod)) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="text" class="form-control" name="action_type"
                 value="<?= e($filters['action_type']) ?>"
                 placeholder="Action (e.g. created)">
        </div>
      </div>

      <!-- Row 2: User + Role + Farm + Warehouse + Shop -->
      <div class="row g-2">
        <div class="col-md-2">
          <select class="form-select" name="user_id">
            <option value="0">All Users</option>
            <?php foreach ($filterOptions['users'] as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)$filters['user_id'] === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="role_name">
            <option value="">All Roles</option>
            <?php foreach ($filterOptions['roles'] as $r): ?>
            <option value="<?= e($r['name']) ?>" <?= $filters['role_name'] === $r['name'] ? 'selected' : '' ?>>
              <?= e(ucwords(str_replace('_', ' ', $r['name']))) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="farm_id">
            <option value="0">All Farms</option>
            <?php foreach ($filterOptions['farms'] as $f): ?>
            <option value="<?= (int)$f['id'] ?>" <?= (int)$filters['farm_id'] === (int)$f['id'] ? 'selected' : '' ?>>
              <?= e($f['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="warehouse_id">
            <option value="0">All Warehouses</option>
            <?php foreach ($filterOptions['warehouses'] as $w): ?>
            <option value="<?= (int)$w['id'] ?>" <?= (int)$filters['warehouse_id'] === (int)$w['id'] ? 'selected' : '' ?>>
              <?= e($w['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="shop_id">
            <option value="0">All Shops</option>
            <?php foreach ($filterOptions['shops'] as $sh): ?>
            <option value="<?= (int)$sh['id'] ?>" <?= (int)$filters['shop_id'] === (int)$sh['id'] ? 'selected' : '' ?>>
              <?= e($sh['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-filter me-1"></i>Apply
          </button>
          <a href="<?= url('admin/audit-logs') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg"></i>
          </a>
        </div>
      </div>

    </form>
  </div>
</div>

<!-- ── Active Filter Chips ───────────────────────────────────────────────── -->
<?php if (!empty($activeFilters)): ?>
<div class="d-flex flex-wrap gap-1 mb-3">
  <?php foreach ($activeFilters as $key => $val): ?>
  <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
    <?= e(ucwords(str_replace('_', ' ', $key))) ?>: <?= e((string)$val) ?>
  </span>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Results Table ─────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($logs)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-clipboard2-x display-4 d-block mb-3 opacity-25"></i>
      <h5 class="fw-semibold">No audit records found</h5>
      <p class="small">Try adjusting your filters or date range.</p>
    </div>
    <?php else: ?>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 audit-table">
        <thead class="table-light border-bottom">
          <tr>
            <th class="ps-3" style="width:130px;">Date &amp; Time</th>
            <th style="width:150px;">User</th>
            <th style="width:110px;">Module</th>
            <th style="width:160px;">Action</th>
            <th>Entity / Record</th>
            <th>Description</th>
            <th style="width:140px;">Location</th>
            <th class="text-end pe-3" style="width:60px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log):
            $dt     = new \DateTime($log['action_at'] ?? 'now');
            $module = strtolower($log['module'] ?? '');
            $action = strtolower($log['action_type'] ?? '');
            $mColor = $moduleBadge($module);
            $aColor = $actionBadge($action);
            $user   = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: ($log['username'] ?? 'System');
          ?>
          <tr>
            <!-- Date/Time -->
            <td class="ps-3 text-nowrap">
              <div class="small fw-semibold text-dark"><?= $dt->format('M j, Y') ?></div>
              <div class="small text-muted"><?= $dt->format('H:i:s') ?></div>
            </td>

            <!-- User + Role -->
            <td>
              <div class="small fw-semibold text-dark"><?= e($user) ?></div>
              <?php if (!empty($log['role_name'])): ?>
              <div class="small text-muted"><?= e(ucwords(str_replace('_',' ', $log['role_name']))) ?></div>
              <?php endif; ?>
            </td>

            <!-- Module -->
            <td>
              <span class="badge bg-<?= $mColor ?>-subtle text-<?= $mColor ?> border border-<?= $mColor ?>-subtle px-2">
                <?php $mIcon = $moduleMeta[$module]['icon'] ?? 'bi-circle'; ?>
                <i class="bi <?= $mIcon ?> me-1"></i><?= e(ucfirst($module)) ?>
              </span>
            </td>

            <!-- Action -->
            <td>
              <span class="badge bg-<?= $aColor ?>-subtle text-<?= $aColor ?> border border-<?= $aColor ?>-subtle px-2 text-uppercase small">
                <?= e($log['action_type'] ?? '—') ?>
              </span>
            </td>

            <!-- Entity / Record -->
            <td>
              <?php if (!empty($log['entity_label'])): ?>
              <div class="small fw-semibold"><?= e($log['entity_label']) ?></div>
              <?php endif; ?>
              <?php if (!empty($log['entity_type'])): ?>
              <div class="small text-muted"><?= e(ucfirst($log['entity_type'])) ?>
                <?= $log['record_id'] ? ' #' . (int)$log['record_id'] : '' ?>
              </div>
              <?php endif; ?>
            </td>

            <!-- Description -->
            <td class="small text-muted" style="max-width:240px;">
              <?= e($log['description'] ?? '—') ?>
            </td>

            <!-- Location -->
            <td class="small">
              <?= $locationLabel($log) ?>
            </td>

            <!-- Detail -->
            <td class="text-end pe-3">
              <a href="<?= url('admin/audit-logs/' . (int)$log['id']) ?>"
                 class="btn btn-sm btn-outline-secondary"
                 title="View details">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-3 py-3 border-top d-flex justify-content-between align-items-center">
      <small class="text-muted">
        Showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?>
        of <?= number_format($total) ?> records
      </small>
      <?php
        $qs    = array_filter($filters, fn($v) => $v !== '' && $v !== 0);
        $pager = new \AAM\Core\Pagination($total, $perPage, $page, url('admin/audit-logs') . '?' . http_build_query($qs));
        echo $pager->render();
      ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
