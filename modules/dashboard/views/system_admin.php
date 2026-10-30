<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : modules/dashboard/views/system_admin.php
 * Purpose  : System Administrator exclusive dashboard.
 *            Shows: users, locked accounts, roles, audit
 *            trail, system settings, login activity.
 *            NO operational/business content.
 * =========================================================
 *
 * @var \AAM\Core\View      $view
 * @var string              $userName
 * @var array<string,mixed> $kpis
 * @var array               $recentAudit
 * @var array               $recentLogins
 * @var array               $lockedUsers
 */
declare(strict_types=1);

$kpis         ??= [];
$recentAudit  ??= [];
$recentLogins ??= [];
$lockedUsers  ??= [];

$fmt = fn(mixed $v): string => is_numeric($v) ? number_format((float)$v) : ($v ?? '—');
?>

<!-- ── Welcome Banner ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);">
      <div class="card-body d-flex align-items-center gap-3 py-3 text-white">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:52px;height:52px;background:rgba(255,193,7,.2);border:2px solid rgba(255,193,7,.4);">
          <i class="bi bi-shield-fill-check fs-4" style="color:#ffc107;"></i>
        </div>
        <div class="flex-grow-1">
          <h5 class="mb-0 fw-bold">System Administration</h5>
          <small class="opacity-75">Welcome, <?= e($userName) ?> &mdash; <?= date('l, d F Y') ?></small>
        </div>
        <div class="d-none d-md-flex gap-2 flex-wrap">
          <a href="<?= url('admin/users/create') ?>" class="btn btn-warning btn-sm fw-semibold">
            <i class="bi bi-person-plus me-1"></i>Add User
          </a>
          <a href="<?= url('settings/backups') ?>" class="btn btn-outline-light btn-sm">
            <i class="bi bi-shield-check me-1"></i>Settings
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SYSTEM HEALTH KPIs
     ══════════════════════════════════════════════════════════ -->
<div class="row g-2 mb-3">
  <div class="col-12">
    <p class="text-muted small fw-semibold text-uppercase mb-0" style="letter-spacing:.06em;">
      <i class="bi bi-shield-fill me-1" style="color:#ffc107;"></i>System Health
    </p>
  </div>
</div>

<div class="row g-3 mb-4">

  <!-- Total Users -->
  <div class="col-6 col-sm-4 col-xl-2">
    <a href="<?= url('admin/users') ?>" class="card border-0 shadow-sm text-decoration-none h-100"
       style="border-top:3px solid #0d6efd!important;">
      <div class="card-body text-center py-3 px-2">
        <i class="bi bi-people-fill text-primary fs-3 d-block mb-1"></i>
        <div class="fw-bold fs-4"><?= $fmt($kpis['total_users'] ?? 0) ?></div>
        <div class="small fw-semibold text-dark">Total Users</div>
        <div class="text-muted" style="font-size:.7rem;"><?= $fmt($kpis['active_users'] ?? 0) ?> active</div>
      </div>
    </a>
  </div>

  <!-- Locked Accounts -->
  <div class="col-6 col-sm-4 col-xl-2">
    <a href="<?= url('admin/users?status=locked') ?>" class="card border-0 shadow-sm text-decoration-none h-100"
       style="border-top:3px solid #dc3545!important;">
      <div class="card-body text-center py-3 px-2">
        <i class="bi bi-lock-fill text-danger fs-3 d-block mb-1"></i>
        <div class="fw-bold fs-4 <?= ($kpis['locked_users'] ?? 0) > 0 ? 'text-danger' : '' ?>">
          <?= $fmt($kpis['locked_users'] ?? 0) ?>
        </div>
        <div class="small fw-semibold text-dark">Locked</div>
        <div class="text-muted" style="font-size:.7rem;">Accounts locked</div>
      </div>
    </a>
  </div>

  <!-- Farms -->
  <div class="col-6 col-sm-4 col-xl-2">
    <a href="<?= url('admin/organization/farms') ?>" class="card border-0 shadow-sm text-decoration-none h-100"
       style="border-top:3px solid #198754!important;">
      <div class="card-body text-center py-3 px-2">
        <i class="bi bi-flower1 text-success fs-3 d-block mb-1"></i>
        <div class="fw-bold fs-4"><?= $fmt($kpis['total_farms'] ?? 0) ?></div>
        <div class="small fw-semibold text-dark">Farms</div>
        <div class="text-muted" style="font-size:.7rem;">Registered</div>
      </div>
    </a>
  </div>

  <!-- Warehouses -->
  <div class="col-6 col-sm-4 col-xl-2">
    <a href="<?= url('admin/organization/warehouses') ?>" class="card border-0 shadow-sm text-decoration-none h-100"
       style="border-top:3px solid #0dcaf0!important;">
      <div class="card-body text-center py-3 px-2">
        <i class="bi bi-building text-info fs-3 d-block mb-1"></i>
        <div class="fw-bold fs-4"><?= $fmt($kpis['total_warehouses'] ?? 0) ?></div>
        <div class="small fw-semibold text-dark">Warehouses</div>
        <div class="text-muted" style="font-size:.7rem;">Registered</div>
      </div>
    </a>
  </div>

  <!-- Shops -->
  <div class="col-6 col-sm-4 col-xl-2">
    <a href="<?= url('admin/organization/shops') ?>" class="card border-0 shadow-sm text-decoration-none h-100"
       style="border-top:3px solid #fd7e14!important;">
      <div class="card-body text-center py-3 px-2">
        <i class="bi bi-shop fs-3 d-block mb-1" style="color:#fd7e14;"></i>
        <div class="fw-bold fs-4"><?= $fmt($kpis['active_shops'] ?? 0) ?></div>
        <div class="small fw-semibold text-dark">Shops</div>
        <div class="text-muted" style="font-size:.7rem;">Active</div>
      </div>
    </a>
  </div>

  <!-- Products -->
  <div class="col-6 col-sm-4 col-xl-2">
    <a href="<?= url('admin/products') ?>" class="card border-0 shadow-sm text-decoration-none h-100"
       style="border-top:3px solid #6f42c1!important;">
      <div class="card-body text-center py-3 px-2">
        <i class="bi bi-box-seam-fill fs-3 d-block mb-1" style="color:#6f42c1;"></i>
        <div class="fw-bold fs-4"><?= $fmt($kpis['total_products'] ?? 0) ?></div>
        <div class="small fw-semibold text-dark">Products</div>
        <div class="text-muted" style="font-size:.7rem;">In catalog</div>
      </div>
    </a>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     ALERTS — Locked Accounts
     ══════════════════════════════════════════════════════════ -->
<?php if (($kpis['locked_users'] ?? 0) > 0): ?>
<div class="alert alert-danger d-flex align-items-center justify-content-between py-2 mb-4" role="alert">
  <div class="d-flex align-items-center gap-2">
    <i class="bi bi-lock-fill flex-shrink-0"></i>
    <span class="small">
      <strong><?= $kpis['locked_users'] ?></strong> account(s) are currently locked and need attention.
    </span>
  </div>
  <a href="<?= url('admin/users?status=locked') ?>" class="btn btn-danger btn-sm py-0 px-2 flex-shrink-0 ms-2">
    Unlock
  </a>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">

  <!-- ── Locked Users Table ────────────────────────────── -->
  <?php if (!empty($lockedUsers)): ?>
  <div class="col-12">
    <div class="card border-0 shadow-sm border-start border-danger border-4">
      <div class="card-header d-flex justify-content-between align-items-center bg-danger bg-opacity-10">
        <span class="fw-semibold small text-danger">
          <i class="bi bi-lock-fill me-2"></i>Locked Accounts
          <span class="badge bg-danger ms-1"><?= count($lockedUsers) ?></span>
        </span>
        <a href="<?= url('admin/users?status=locked') ?>" class="btn btn-outline-danger btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Username</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Failed Attempts</th>
                <th class="pe-3">Locked At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lockedUsers as $lu): ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <a href="<?= url('admin/users/' . $lu['id']) ?>"><?= e($lu['username']) ?></a>
                </td>
                <td class="small"><?= e($lu['full_name']) ?></td>
                <td class="small text-muted"><?= e($lu['role_name']) ?></td>
                <td class="small text-danger fw-semibold"><?= (int)$lu['failed_login_attempts'] ?></td>
                <td class="small text-muted pe-3">
                  <?= $lu['locked_at'] ? date('d/m/Y H:i', strtotime($lu['locked_at'])) : '—' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════
     QUICK ACTIONS + SYSTEM CONFIG
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <!-- Quick Actions -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small">
        <i class="bi bi-grid-fill me-2 text-warning"></i>Quick Actions
      </div>
      <div class="card-body pb-2">
        <p class="text-muted mb-2" style="font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">
          User Management
        </p>
        <div class="row g-2 mb-3">
          <?php
          $userLinks = [
            [url('admin/users/create'), 'bi-person-plus-fill',  'Add User',    'primary'],
            [url('admin/users'),        'bi-people-fill',        'All Users',   'secondary'],
            [url('admin/users?status=locked'), 'bi-lock-fill',  'Locked',      'danger'],
            [url('admin/roles'),        'bi-person-badge-fill',  'Roles',       'info'],
          ];
          foreach ($userLinks as [$href, $icon, $label, $color]): ?>
          <div class="col-6">
            <a href="<?= $href ?>"
               class="btn btn-outline-<?= $color ?> w-100 d-flex flex-column align-items-center py-2 gap-1">
              <i class="bi <?= $icon ?> fs-5"></i>
              <span style="font-size:.68rem;font-weight:600;"><?= $label ?></span>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="text-muted mb-2" style="font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">
          System Config
        </p>
        <div class="row g-2">
          <?php
          $sysLinks = [
            [url('settings/backups'),     'bi-shield-check',          'Security',    'warning'],
            [url('settings/backups'),     'bi-cloud-arrow-down-fill', 'Backup',      'success'],
          ];
          foreach ($sysLinks as [$href, $icon, $label, $color]): ?>
          <div class="col-6">
            <a href="<?= $href ?>"
               class="btn btn-outline-<?= $color ?> w-100 d-flex flex-column align-items-center py-2 gap-1">
              <i class="bi <?= $icon ?> fs-5"></i>
              <span style="font-size:.68rem;font-weight:600;"><?= $label ?></span>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Organization Quick Links -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small">
        <i class="bi bi-diagram-3-fill me-2 text-info"></i>Organization
      </div>
      <div class="card-body pb-2">
        <p class="text-muted mb-2" style="font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">
          Manage Locations
        </p>
        <div class="row g-2 mb-3">
          <?php
          $orgLinks = [
            [url('admin/organization'),             'bi-speedometer2',     'Overview',    'primary'],
            [url('admin/organization/farms'),        'bi-flower1',          'Farms',       'success'],
            [url('admin/organization/warehouses'),   'bi-building',         'Warehouses',  'info'],
            [url('admin/organization/shops'),        'bi-shop',             'Shops',       'warning'],
            [url('admin/organization/assignments'),  'bi-person-badge-fill','Assignments', 'secondary'],
          ];
          foreach ($orgLinks as [$href, $icon, $label, $color]): ?>
          <div class="col-4">
            <a href="<?= $href ?>"
               class="btn btn-outline-<?= $color ?> w-100 d-flex flex-column align-items-center py-2 gap-1">
              <i class="bi <?= $icon ?> fs-5"></i>
              <span style="font-size:.65rem;font-weight:600;"><?= $label ?></span>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="text-muted mb-2" style="font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">
          Product Catalog
        </p>
        <div class="row g-2">
          <?php
          $prodLinks = [
            [url('admin/products'),    'bi-box-seam-fill',   'Products',   'dark'],
            [url('admin/categories'),  'bi-tags-fill',       'Categories', 'primary'],
            [url('admin/units'),       'bi-rulers',          'Units',      'secondary'],
          ];
          foreach ($prodLinks as [$href, $icon, $label, $color]): ?>
          <div class="col-4">
            <a href="<?= $href ?>"
               class="btn btn-outline-<?= $color ?> w-100 d-flex flex-column align-items-center py-2 gap-1">
              <i class="bi <?= $icon ?> fs-5"></i>
              <span style="font-size:.65rem;font-weight:600;"><?= $label ?></span>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     RECENT LOGINS
     ══════════════════════════════════════════════════════════ -->
<?php if (!empty($recentLogins)): ?>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-box-arrow-in-right me-2 text-success"></i>Recent Login Activity
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>IP Address</th>
                <th class="pe-3">Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentLogins as $login): ?>
              <?php $success = (bool)($login['was_successful'] ?? 0); ?>
              <tr>
                <td class="ps-3 small fw-semibold"><?= e($login['username_attempted'] ?? $login['username'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($login['role_name'] ?? '—') ?></td>
                <td class="small">
                  <span class="badge <?= $success ? 'bg-success' : 'bg-danger' ?>">
                    <?= $success ? 'Success' : 'Failed' ?>
                  </span>
                </td>
                <td class="small text-muted"><?= e($login['ip_address'] ?? '—') ?></td>
                <td class="small text-muted pe-3">
                  <?= !empty($login['attempted_at']) ? date('d/m/Y H:i', strtotime($login['attempted_at'])) : '—' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
