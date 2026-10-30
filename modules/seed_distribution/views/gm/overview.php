<?php
declare(strict_types=1);
$kpis           ??= [];
$bySeed         ??= [];
$recent         ??= [];
$seedStock      ??= [];
$seedWarehouses ??= [];
$seedProducts   ??= [];
$filters        ??= [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-flower1 me-2 text-success"></i>Seed Distribution Overview</h4>
    <p class="text-muted small mb-0">Arsi University Improved Seed Distribution Program</p>
  </div>
  <a href="<?= url('gm/seed-distribution/pending') ?>" class="btn btn-warning btn-sm">
    <i class="bi bi-hourglass-split me-1"></i>Pending Approvals
    <?php if (!empty($kpis['pending_count']) && $kpis['pending_count'] > 0): ?>
    <span class="badge bg-dark ms-1"><?= (int)$kpis['pending_count'] ?></span>
    <?php endif; ?>
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2">
        <select name="warehouse_id" class="form-select form-select-sm">
          <option value="">All Warehouses</option>
          <?php foreach ($seedWarehouses as $wh): ?>
          <option value="<?= $wh['id'] ?>" <?= ($filters['warehouse_id'] ?? '') == $wh['id'] ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="product_id" class="form-select form-select-sm">
          <option value="">All Seed Products</option>
          <?php foreach ($seedProducts as $sp): ?>
          <option value="<?= $sp['id'] ?>" <?= ($filters['product_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= e($sp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>" placeholder="From date">
      </div>
      <div class="col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>" placeholder="To date">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
      </div>
      <div class="col-md-1">
        <a href="<?= url('gm/seed-distribution') ?>" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3 col-xl-2">
    <div class="card border-0 shadow-sm h-100 text-center">
      <div class="card-body py-3">
        <div class="h4 fw-bold text-primary mb-1"><?= (int)($kpis['total_distributions'] ?? 0) ?></div>
        <div class="small text-muted">Total Distributions</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2">
    <div class="card border-0 shadow-sm h-100 text-center">
      <div class="card-body py-3">
        <div class="h4 fw-bold text-warning mb-1"><?= (int)($kpis['pending_count'] ?? 0) ?></div>
        <div class="small text-muted">Pending Approval</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2">
    <div class="card border-0 shadow-sm h-100 text-center">
      <div class="card-body py-3">
        <div class="h4 fw-bold text-info mb-1"><?= (int)($kpis['approved_count'] ?? 0) ?></div>
        <div class="small text-muted">Approved</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2">
    <div class="card border-0 shadow-sm h-100 text-center">
      <div class="card-body py-3">
        <div class="h4 fw-bold text-success mb-1"><?= (int)($kpis['completed_count'] ?? 0) ?></div>
        <div class="small text-muted">Completed</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2">
    <div class="card border-0 shadow-sm h-100 text-center">
      <div class="card-body py-3">
        <div class="h4 fw-bold text-dark mb-1"><?= number_format((float)($kpis['total_quantity'] ?? 0), 0) ?></div>
        <div class="small text-muted">Total Qty Distributed</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2">
    <div class="card border-0 shadow-sm h-100 text-center">
      <div class="card-body py-3">
        <div class="h4 fw-bold text-danger mb-1"><?= (int)(($kpis['cancelled_count'] ?? 0) + ($kpis['rejected_count'] ?? 0)) ?></div>
        <div class="small text-muted">Cancelled / Rejected</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- By Seed Summary -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header border-0 bg-white">
        <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2 text-success"></i>Distributions by Seed</h6>
      </div>
      <div class="card-body p-0">
        <?php if (empty($bySeed)): ?>
        <p class="text-muted p-3 mb-0 small">No distribution data available.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr><th>Product</th><th>Grade</th><th class="text-end">Count</th><th class="text-end">Total Qty</th></tr>
            </thead>
            <tbody>
              <?php foreach ($bySeed as $row): ?>
              <tr>
                <td class="small"><?= e($row['product_name']) ?></td>
                <td><?= $row['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border small">'.e($row['grade_name']).'</span>' : '—' ?></td>
                <td class="text-end small"><?= (int)$row['distribution_count'] ?></td>
                <td class="text-end fw-semibold small"><?= number_format((float)$row['total_quantity'], 2) ?> <?= e($row['unit_abbr'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Seed Stock -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header border-0 bg-white d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0"><i class="bi bi-boxes me-2 text-primary"></i>Current Seed Stock</h6>
        <a href="<?= url('gm/seed-distribution/stock') ?>" class="btn btn-outline-primary btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($seedStock)): ?>
        <p class="text-muted p-3 mb-0 small">No seed stock data available.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr><th>Warehouse</th><th>Product</th><th class="text-end">Available</th></tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($seedStock, 0, 8) as $row): ?>
              <?php $avail = (float)$row['available_quantity']; ?>
              <tr>
                <td class="small text-muted"><?= e($row['warehouse_name']) ?></td>
                <td class="small"><?= e($row['product_name']) ?></td>
                <td class="text-end small <?= $avail > 0 ? 'text-success' : 'text-danger' ?> fw-semibold">
                  <?= number_format($avail, 2) ?> <?= e($row['unit_abbr'] ?? '') ?>
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

  <!-- Recent Distributions -->
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header border-0 bg-white d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Distributions</h6>
        <a href="<?= url('gm/seed-distribution/distributions') ?>" class="btn btn-outline-secondary btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recent)): ?>
        <p class="text-muted p-3 mb-0 small">No recent distributions.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Ref #</th><th>Product</th><th>Grade</th><th>Warehouse</th>
                <th class="text-end">Qty</th><th>Recipient</th><th>Date</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sc = ['pending'=>'warning','approved'=>'info','released'=>'primary','completed'=>'success','cancelled'=>'secondary','rejected'=>'danger'];
              foreach ($recent as $r):
                $c = $sc[$r['status']] ?? 'secondary';
              ?>
              <tr>
                <td><code class="text-<?= $c ?> small"><?= e($r['distribution_number']) ?></code></td>
                <td class="small"><?= e($r['product_name']) ?></td>
                <td><?= $r['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border small">'.e($r['grade_name']).'</span>' : '—' ?></td>
                <td class="small text-muted"><?= e($r['warehouse_name'] ?? '—') ?></td>
                <td class="text-end small"><?= number_format((float)$r['quantity'], 2) ?></td>
                <td class="small"><?= e($r['recipient_name'] ?? '—') ?></td>
                <td class="small"><?= $r['distribution_date'] ? e(date('d/m/Y', strtotime($r['distribution_date']))) : '—' ?></td>
                <td><span class="badge bg-<?= $c ?>-subtle text-<?= $c ?> border border-<?= $c ?>-subtle small"><?= ucfirst($r['status']) ?></span></td>
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
