<?php
/**
 * View: modules/admin/views/audit/show.php
 * Purpose: Business Audit Trail — single record detail
 *
 * @var array $log
 * @var array $moduleMeta
 * @var array $actionColor
 */
declare(strict_types=1);

$old = !empty($log['old_values']) ? json_decode($log['old_values'], true) : null;
$new = !empty($log['new_values']) ? json_decode($log['new_values'], true) : null;
$dt  = new \DateTime($log['action_at'] ?? 'now');

$module  = strtolower($log['module'] ?? '');
$mColor  = $moduleMeta[$module]['color'] ?? 'secondary';
$mIcon   = $moduleMeta[$module]['icon']  ?? 'bi-circle';
$action  = strtolower($log['action_type'] ?? '');
$aColor  = 'secondary';
foreach ($actionColor as $kw => $c) {
    if (str_contains($action, $kw)) { $aColor = $c; break; }
}

$user     = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: ($log['username'] ?? 'System');
$location = $log['farm_name'] ?? $log['warehouse_name'] ?? $log['shop_name'] ?? null;
?>

<!-- ── Header ───────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-1">
      <i class="bi bi-clipboard2-data-fill me-2 text-primary"></i>Audit Record
      <span class="text-muted fw-normal fs-5">#<?= (int)$log['id'] ?></span>
    </h4>
    <p class="text-muted small mb-0">Business Audit Trail &rsaquo; Detail View</p>
  </div>
  <a href="<?= url('admin/audit-logs') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Audit Trail
  </a>
</div>

<!-- ── Action Banner ─────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-<?= $aColor ?>">
  <div class="card-body d-flex flex-wrap gap-4 align-items-center py-3">

    <div class="text-center" style="min-width:80px;">
      <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1 bg-<?= $mColor ?>-subtle"
           style="width:52px;height:52px;">
        <i class="bi <?= $mIcon ?> fs-4 text-<?= $mColor ?>"></i>
      </div>
      <span class="badge bg-<?= $mColor ?>-subtle text-<?= $mColor ?> border border-<?= $mColor ?>-subtle px-2">
        <?= e(ucfirst($module)) ?>
      </span>
    </div>

    <div class="flex-grow-1">
      <div class="mb-1">
        <span class="badge bg-<?= $aColor ?>-subtle text-<?= $aColor ?> border border-<?= $aColor ?>-subtle px-3 py-2 fs-6 text-uppercase fw-bold">
          <?= e($log['action_type'] ?? '—') ?>
        </span>
      </div>
      <?php if (!empty($log['description'])): ?>
      <p class="mb-0 text-dark"><?= e($log['description']) ?></p>
      <?php endif; ?>
    </div>

    <div class="text-end">
      <div class="fw-bold text-dark"><?= $dt->format('M j, Y') ?></div>
      <div class="text-muted small"><?= $dt->format('h:i:s A') ?></div>
    </div>

  </div>
</div>

<div class="row g-4">

  <!-- ── Left: Who + Where ──────────────────────────────────────────────── -->
  <div class="col-lg-4">

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header fw-semibold small">
        <i class="bi bi-person-fill me-2 text-primary"></i>Performed By
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">User</span>
          <span class="small fw-semibold"><?= e($user) ?></span>
        </li>
        <?php if (!empty($log['username']) && $user !== $log['username']): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">Username</span>
          <code class="small"><?= e($log['username']) ?></code>
        </li>
        <?php endif; ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">Role</span>
          <span class="small"><?= e(ucwords(str_replace('_', ' ', $log['role_name'] ?? '—'))) ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">IP Address</span>
          <span class="small text-muted"><?= e($log['ip_address'] ?? '—') ?></span>
        </li>
      </ul>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header fw-semibold small">
        <i class="bi bi-geo-alt-fill me-2 text-warning"></i>Location
      </div>
      <ul class="list-group list-group-flush">
        <?php if (!empty($log['farm_name'])): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small"><i class="bi bi-tree-fill text-success me-1"></i>Farm</span>
          <span class="small fw-semibold"><?= e($log['farm_name']) ?></span>
        </li>
        <?php endif; ?>
        <?php if (!empty($log['warehouse_name'])): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small"><i class="bi bi-building-fill text-primary me-1"></i>Warehouse</span>
          <span class="small fw-semibold"><?= e($log['warehouse_name']) ?></span>
        </li>
        <?php endif; ?>
        <?php if (!empty($log['shop_name'])): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small"><i class="bi bi-shop text-warning me-1"></i>Shop</span>
          <span class="small fw-semibold"><?= e($log['shop_name']) ?></span>
        </li>
        <?php endif; ?>
        <?php if (empty($log['farm_name']) && empty($log['warehouse_name']) && empty($log['shop_name'])): ?>
        <li class="list-group-item py-2">
          <span class="text-muted small">No location context recorded</span>
        </li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-tag-fill me-2 text-secondary"></i>Record
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">Module</span>
          <span class="badge bg-<?= $mColor ?>-subtle text-<?= $mColor ?> border border-<?= $mColor ?>-subtle">
            <?= e(ucfirst($module)) ?>
          </span>
        </li>
        <?php if (!empty($log['entity_type'])): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">Entity Type</span>
          <span class="small"><?= e(ucfirst($log['entity_type'])) ?></span>
        </li>
        <?php endif; ?>
        <?php if (!empty($log['entity_label'])): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">Entity</span>
          <span class="small fw-semibold"><?= e($log['entity_label']) ?></span>
        </li>
        <?php endif; ?>
        <?php if ($log['record_id']): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">Record ID</span>
          <code class="small">#<?= (int)$log['record_id'] ?></code>
        </li>
        <?php endif; ?>
        <?php if (!empty($log['table_affected'])): ?>
        <li class="list-group-item d-flex justify-content-between py-2">
          <span class="text-muted small">Table</span>
          <code class="small"><?= e($log['table_affected']) ?></code>
        </li>
        <?php endif; ?>
      </ul>
    </div>

  </div>

  <!-- ── Right: Changes ────────────────────────────────────────────────── -->
  <div class="col-lg-8">

    <?php if ($old || $new): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header fw-semibold small">
        <i class="bi bi-arrow-left-right me-2 text-warning"></i>Data Changes
      </div>
      <div class="card-body">
        <?php
        // Attempt a clean field-by-field comparison
        $allKeys = array_unique(array_merge(array_keys($old ?? []), array_keys($new ?? [])));
        ?>
        <?php if (!empty($allKeys)): ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th class="small text-muted fw-semibold">Field</th>
                <th class="small text-danger fw-semibold">Previous Value</th>
                <th class="small text-success fw-semibold">New Value</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allKeys as $k): ?>
              <?php
                $prev = $old[$k] ?? null;
                $next = $new[$k] ?? null;
                $changed = $prev !== $next;
              ?>
              <tr class="<?= $changed ? '' : 'opacity-50' ?>">
                <td><code class="small"><?= e($k) ?></code></td>
                <td class="small <?= $changed ? 'text-danger' : 'text-muted' ?>">
                  <?= $prev !== null ? e((string)$prev) : '<span class="text-muted fst-italic">—</span>' ?>
                </td>
                <td class="small <?= $changed ? 'text-success fw-semibold' : 'text-muted' ?>">
                  <?= $next !== null ? e((string)$next) : '<span class="text-muted fst-italic">—</span>' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="row g-3">
          <?php if ($old): ?>
          <div class="col-md-6">
            <h6 class="text-danger small fw-bold mb-2">
              <i class="bi bi-dash-circle me-1"></i>Before
            </h6>
            <pre class="bg-light p-3 rounded small border" style="font-size:.75rem;max-height:300px;overflow-y:auto;"><?= e(json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
          </div>
          <?php endif; ?>
          <?php if ($new): ?>
          <div class="col-md-6">
            <h6 class="text-success small fw-bold mb-2">
              <i class="bi bi-plus-circle me-1"></i>After
            </h6>
            <pre class="bg-light p-3 rounded small border" style="font-size:.75rem;max-height:300px;overflow-y:auto;"><?= e(json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body text-center py-4 text-muted">
        <i class="bi bi-journal-x fs-3 d-block mb-2 opacity-25"></i>
        <p class="small mb-0">No before/after data recorded for this action.</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Timeline position hint -->
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-clock-history me-2 text-muted"></i>Timestamp
      </div>
      <div class="card-body">
        <div class="row g-3 text-center">
          <div class="col-4">
            <div class="text-muted small">Date</div>
            <div class="fw-bold"><?= $dt->format('F j, Y') ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted small">Time</div>
            <div class="fw-bold"><?= $dt->format('h:i:s A') ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted small">Day</div>
            <div class="fw-bold"><?= $dt->format('l') ?></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
