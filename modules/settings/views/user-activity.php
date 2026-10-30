<?php
/**
 * =========================================================
 * USER ACTIVITY VIEW
 * =========================================================
 * File    : modules/settings/views/user-activity.php
 * Purpose : Login history + activity log in separate tabs.
 *           System Administrator only.
 * =========================================================
 */
declare(strict_types=1);

$activeTab = $_GET['tab'] ?? 'logins';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-activity me-2 text-primary"></i>User Activity
    </h4>
    <p class="text-muted small mb-0">Monitor login history, user actions, and live sessions.</p>
  </div>
</div>

<!-- ── Summary Stats ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #0d6efd!important;">
      <div class="fw-bold fs-3 text-primary"><?= number_format($stats['total_logins_today']) ?></div>
      <div class="small text-muted">Logins Today</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #dc3545!important;">
      <div class="fw-bold fs-3 <?= $stats['failed_logins_today'] > 0 ? 'text-danger' : 'text-muted' ?>">
        <?= number_format($stats['failed_logins_today']) ?>
      </div>
      <div class="small text-muted">Failed Logins Today</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #198754!important;">
      <div class="fw-bold fs-3 text-success"><?= number_format($stats['active_users_today']) ?></div>
      <div class="small text-muted">Active Users Today</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #fd7e14!important;">
      <div class="fw-bold fs-3" style="color:#fd7e14;"><?= number_format($stats['total_actions_today']) ?></div>
      <div class="small text-muted">Actions Today</div>
    </div>
  </div>
</div>

<!-- ── Online Users (last 15 min) ───────────────────────── -->
<?php if (!empty($onlineUsers)): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header fw-semibold small d-flex align-items-center gap-2">
    <span class="d-inline-block rounded-circle bg-success" style="width:8px;height:8px;box-shadow:0 0 6px #198754;"></span>
    Recently Active
    <span class="badge bg-success ms-1"><?= count($onlineUsers) ?></span>
    <span class="text-muted fw-normal ms-1" style="font-size:.75rem;">(last 15 minutes)</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">User</th>
            <th>Role</th>
            <th class="pe-3">Last Seen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($onlineUsers as $u): ?>
          <tr>
            <td class="ps-3 small">
              <span class="fw-semibold"><?= e($u['username']) ?></span>
              <span class="text-muted ms-1"><?= e($u['full_name']) ?></span>
            </td>
            <td class="small text-muted"><?= e($u['role_name'] ?? '—') ?></td>
            <td class="small text-muted pe-3">
              <?= date('d/m/Y H:i:s', strtotime($u['last_seen'])) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Tabs ──────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">

  <!-- Tab Nav -->
  <div class="card-header bg-white border-bottom px-3 pt-3 pb-0">
    <ul class="nav nav-tabs card-header-tabs" id="activityTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'logins' ? 'active' : '' ?> d-flex align-items-center gap-2"
                id="tab-logins"
                data-bs-toggle="tab"
                data-bs-target="#pane-logins"
                type="button" role="tab"
                aria-controls="pane-logins"
                aria-selected="<?= $activeTab === 'logins' ? 'true' : 'false' ?>">
          <i class="bi bi-box-arrow-in-right text-success"></i>
          Login History
          <span class="badge bg-secondary"><?= number_format($totalLogins) ?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'activity' ? 'active' : '' ?> d-flex align-items-center gap-2"
                id="tab-activity"
                data-bs-toggle="tab"
                data-bs-target="#pane-activity"
                type="button" role="tab"
                aria-controls="pane-activity"
                aria-selected="<?= $activeTab === 'activity' ? 'true' : 'false' ?>">
          <i class="bi bi-clock-history text-primary"></i>
          Activity Log
          <span class="badge bg-secondary"><?= count($recentActivity) ?></span>
        </button>
      </li>
    </ul>
  </div>

  <div class="tab-content" id="activityTabContent">

    <!-- ═══════════════════════════════════════
         TAB 1 — LOGIN HISTORY
         ═══════════════════════════════════════ -->
    <div class="tab-pane fade <?= $activeTab === 'logins' ? 'show active' : '' ?>"
         id="pane-logins" role="tabpanel" aria-labelledby="tab-logins">

      <!-- Filters -->
      <div class="px-3 py-3 border-bottom">
        <form method="GET" action="<?= url('settings/user-activity') ?>" class="row g-2 align-items-end">
          <input type="hidden" name="tab" value="logins">
          <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Username</label>
            <input type="text" name="user" class="form-control form-control-sm"
                   value="<?= e($filterUser) ?>" placeholder="Search username…">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Date</label>
            <input type="date" name="date" class="form-control form-control-sm"
                   value="<?= e($filterDate) ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm px-4">
              <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <a href="<?= url('settings/user-activity') ?>?tab=logins" class="btn btn-outline-secondary btn-sm ms-1">
              <i class="bi bi-x-circle me-1"></i>Clear
            </a>
          </div>
        </form>
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Username</th>
              <th>Full Name</th>
              <th>Role</th>
              <th>Status</th>
              <th>IP Address</th>
              <th>Browser / OS</th>
              <th>Fail Reason</th>
              <th class="pe-3">Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logins)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4 small">No login records found.</td></tr>
            <?php else: ?>
            <?php foreach ($logins as $l):
              $success = (bool)($l['was_successful'] ?? 0); ?>
            <tr>
              <td class="ps-3 small fw-semibold"><?= e($l['username_attempted'] ?? '—') ?></td>
              <td class="small text-muted"><?= e($l['full_name'] ?? '—') ?></td>
              <td class="small text-muted"><?= e($l['role_name'] ?? '—') ?></td>
              <td class="small">
                <span class="badge <?= $success ? 'bg-success' : 'bg-danger' ?>">
                  <?= $success ? 'Success' : 'Failed' ?>
                </span>
              </td>
              <td class="small text-muted font-monospace"><?= e($l['ip_address'] ?? '—') ?></td>
              <td class="small text-muted">
                <?= e($l['browser'] ?? '—') ?>
                <?php if (!empty($l['os_name'])): ?>
                <span class="text-secondary">/ <?= e($l['os_name']) ?></span>
                <?php endif; ?>
              </td>
              <td class="small text-danger"><?= e($l['failure_reason'] ?? '') ?></td>
              <td class="small text-muted pe-3 text-nowrap">
                <?= !empty($l['attempted_at']) ? date('d/m/Y H:i', strtotime($l['attempted_at'])) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="small text-muted">
          Page <?= $currentPage ?> of <?= $totalPages ?> · <?= number_format($totalLogins) ?> records
        </span>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php if ($currentPage > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= http_build_query(['tab' => 'logins', 'user' => $filterUser, 'date' => $filterDate, 'page' => $currentPage - 1]) ?>">
                &laquo;
              </a>
            </li>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(['tab' => 'logins', 'user' => $filterUser, 'date' => $filterDate, 'page' => $p]) ?>">
                <?= $p ?>
              </a>
            </li>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= http_build_query(['tab' => 'logins', 'user' => $filterUser, 'date' => $filterDate, 'page' => $currentPage + 1]) ?>">
                &raquo;
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>

    </div><!-- /pane-logins -->


    <!-- ═══════════════════════════════════════
         TAB 2 — ACTIVITY LOG
         ═══════════════════════════════════════ -->
    <div class="tab-pane fade <?= $activeTab === 'activity' ? 'show active' : '' ?>"
         id="pane-activity" role="tabpanel" aria-labelledby="tab-activity">

      <!-- Filters -->
      <div class="px-3 py-3 border-bottom">
        <form method="GET" action="<?= url('settings/user-activity') ?>" class="row g-2 align-items-end">
          <input type="hidden" name="tab" value="activity">
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Username</label>
            <input type="text" name="user" class="form-control form-control-sm"
                   value="<?= e($filterUser) ?>" placeholder="Search username…">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Action Type</label>
            <select name="action" class="form-select form-select-sm">
              <option value="">— All Actions —</option>
              <?php foreach ($actionTypes as $at): ?>
              <option value="<?= e($at['action']) ?>" <?= $filterAction === $at['action'] ? 'selected' : '' ?>>
                <?= e($at['action']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Date</label>
            <input type="date" name="date" class="form-control form-control-sm"
                   value="<?= e($filterDate) ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm px-4">
              <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <a href="<?= url('settings/user-activity') ?>?tab=activity" class="btn btn-outline-secondary btn-sm ms-1">
              <i class="bi bi-x-circle me-1"></i>Clear
            </a>
          </div>
        </form>
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-3">User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Description</th>
              <th>IP Address</th>
              <th class="pe-3">Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentActivity)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4 small">No activity records found.</td></tr>
            <?php else: ?>
            <?php foreach ($recentActivity as $act):
              $actionColor = match(true) {
                str_contains(strtolower($act['action'] ?? ''), 'delete') => 'bg-danger',
                str_contains(strtolower($act['action'] ?? ''), 'create') => 'bg-success',
                str_contains(strtolower($act['action'] ?? ''), 'update') => 'bg-primary',
                str_contains(strtolower($act['action'] ?? ''), 'login')  => 'bg-info',
                default => 'bg-secondary',
              };
            ?>
            <tr>
              <td class="ps-3 small">
                <span class="fw-semibold"><?= e($act['username'] ?? '—') ?></span>
                <div class="text-muted" style="font-size:.72rem;"><?= e($act['full_name'] ?? '') ?></div>
              </td>
              <td class="small text-muted"><?= e($act['role_name'] ?? '—') ?></td>
              <td class="small">
                <span class="badge <?= $actionColor ?>"><?= e($act['action'] ?? '—') ?></span>
              </td>
              <td class="small text-muted" style="max-width:300px;">
                <span class="d-inline-block text-truncate" style="max-width:280px;"
                      title="<?= e($act['description'] ?? '') ?>">
                  <?= e($act['description'] ?? '—') ?>
                </span>
              </td>
              <td class="small text-muted font-monospace"><?= e($act['ip_address'] ?? '—') ?></td>
              <td class="small text-muted pe-3 text-nowrap">
                <?= !empty($act['created_at']) ? date('d/m/Y H:i', strtotime($act['created_at'])) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div><!-- /pane-activity -->

  </div><!-- /tab-content -->
</div><!-- /card -->
