<?php
declare(strict_types=1);
$result         ??= ['data' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
$filters        ??= [];
$seedProds      ??= [];
$seedWarehouses ??= [];

$statusColors = ['pending'=>'warning','approved'=>'info','released'=>'primary','completed'=>'success','cancelled'=>'secondary','rejected'=>'danger'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-secondary"></i>Distribution History</h4>
    <p class="text-muted small mb-0">All seed distribution records</p>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search…" value="<?= e($filters['search'] ?? '') ?>"></div>
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
          <option value="">All Products</option>
          <?php foreach ($seedProds as $sp): ?>
          <option value="<?= $sp['id'] ?>" <?= ($filters['product_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= e($sp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['pending','approved','released','completed','cancelled','rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>"></div>
      <div class="col-md-1"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>"></div>
      <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100">Filter</button></div>
      <div class="col-md-1"><a href="<?= url('gm/seed-distribution/history') ?>" class="btn btn-sm btn-outline-secondary w-100">Clear</a></div>
    </form>
  </div>
</div>

<?php if (empty($result['data'])): ?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No records found.</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Ref #</th><th>Date</th><th>Warehouse</th><th>Product</th><th>Grade</th><th class="text-end">Qty</th><th>Unit</th><th>Type</th><th>Destination</th><th>Recipient</th><th>Status</th><th>Approved By</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($result['data'] as $row):
            $sc = $statusColors[$row['status']] ?? 'secondary';
          ?>
          <tr>
            <td><code class="text-<?= $sc ?> small"><?= e($row['distribution_number']) ?></code></td>
            <td class="small"><?= $row['distribution_date'] ? e(date('d/m/Y', strtotime($row['distribution_date']))) : '—' ?></td>
            <td class="small"><?= e($row['warehouse_name'] ?? '—') ?></td>
            <td class="small fw-semibold"><?= e($row['product_name']) ?></td>
            <td><?= $row['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border small">'.e($row['grade_name']).'</span>' : '—' ?></td>
            <td class="text-end small"><?= number_format((float)$row['quantity'], 2) ?></td>
            <td class="small text-muted"><?= e($row['unit_abbr'] ?? '') ?></td>
            <td class="small"><?= $row['distribution_type'] === 'warehouse_pickup' ? '<i class="bi bi-building me-1"></i>Pickup' : '<i class="bi bi-geo-alt me-1"></i>Field' ?></td>
            <td class="small text-muted"><?= e(substr($row['destination_location'] ?? '', 0, 25)) ?></td>
            <td class="small"><?= e(substr($row['recipient_name'] ?? '—', 0, 20)) ?></td>
            <td><span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle small"><?= ucfirst($row['status']) ?></span></td>
            <td class="small text-muted"><?= e($row['approved_by_name'] ?? '—') ?></td>
            <td><a href="<?= url('gm/seed-distribution/' . $row['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 text-muted small border-top">Showing <?= $result['from'] ?>–<?= $result['to'] ?> of <?= $result['total'] ?></div>
  </div>
</div>

<?php if ($result['totalPages'] > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for ($p = 1; $p <= $result['totalPages']; $p++): ?>
  <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>">
    <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
  </li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php endif; ?>
