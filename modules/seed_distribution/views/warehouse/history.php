<?php
declare(strict_types=1);
$warehouse ??= [];
$result    ??= ['data' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
$filters   ??= [];
$seedProds ??= [];

$statusColors = [
    'pending'   => 'warning',
    'approved'  => 'info',
    'released'  => 'primary',
    'completed' => 'success',
    'cancelled' => 'secondary',
    'rejected'  => 'danger',
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-secondary"></i>Distribution History</h4>
    <p class="text-muted small mb-0"><?= e($warehouse['name'] ?? '') ?> — All seed distributions</p>
  </div>
  <a href="<?= url('seed-distribution/new') ?>" class="btn btn-success btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Distribution
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Search ref, recipient, location…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <select name="product_id" class="form-select form-select-sm">
          <option value="">All Products</option>
          <?php foreach ($seedProds as $sp): ?>
          <option value="<?= $sp['id'] ?>"
                  <?= ($filters['product_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>>
            <?= e($sp['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['pending','approved','released','completed','cancelled','rejected'] as $s): ?>
          <option value="<?= $s ?>"
                  <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= ucfirst($s) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<?php if (empty($result['data'])): ?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No distributions found.</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Ref #</th>
            <th>Date</th>
            <th>Product</th>
            <th>Grade</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Total</th>
            <th>Type</th>
            <th>Recipient</th>
            <th>Status</th>
            <th>Issued By</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['data'] as $row): ?>
          <?php
          $sc        = $statusColors[$row['status']] ?? 'secondary';
          $unitPrc   = (float)($row['unit_price']   ?? 0);
          $totalAmt  = (float)($row['total_amount'] ?? 0);
          $canReceipt = in_array($row['status'], ['released','completed'], true);
          ?>
          <tr>
            <td>
              <code class="text-<?= $sc ?>">
                <?= e($row['distribution_number']) ?>
              </code>
            </td>
            <td class="small text-nowrap">
              <?= $row['distribution_date']
                ? e(date('d/m/Y', strtotime($row['distribution_date'])))
                : '—' ?>
            </td>
            <td class="small"><?= e($row['product_name']) ?></td>
            <td>
              <?= $row['grade_name']
                ? '<span class="badge bg-secondary-subtle text-dark border small">'.e($row['grade_name']).'</span>'
                : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end small text-nowrap">
              <?= number_format((float)$row['quantity'], 2) ?>
              <span class="text-muted"><?= e($row['unit_abbr'] ?? '') ?></span>
            </td>
            <td class="text-end small text-nowrap">
              <?= $unitPrc > 0
                ? 'ETB '.number_format($unitPrc, 2)
                : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end small fw-semibold text-nowrap">
              <?= $totalAmt > 0
                ? 'ETB '.number_format($totalAmt, 2)
                : '<span class="text-muted">—</span>' ?>
            </td>
            <td>
              <?php if ($row['distribution_type'] === 'warehouse_pickup'): ?>
              <span class="badge bg-info-subtle text-info border border-info-subtle small">
                <i class="bi bi-building me-1"></i>Pickup
              </span>
              <?php else: ?>
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                <i class="bi bi-geo-alt me-1"></i>Field
              </span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($row['recipient_name'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle">
                <?= ucfirst($row['status']) ?>
              </span>
            </td>
            <td class="small text-muted">
              <?= e($row['created_by_name'] ?? '—') ?>
            </td>
            <td class="text-nowrap">
              <a href="<?= url('seed-distribution/' . $row['id']) ?>"
                 class="btn btn-sm btn-outline-secondary" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if ($canReceipt): ?>
              <a href="<?= url('seed-distribution/' . $row['id'] . '/receipt') ?>"
                 class="btn btn-sm btn-outline-success ms-1" target="_blank" title="Receipt">
                <i class="bi bi-receipt"></i>
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Financial totals footer -->
    <?php
    $grandTotal = array_sum(array_column($result['data'], 'total_amount'));
    $grandQty   = array_sum(array_column($result['data'], 'quantity'));
    ?>
    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span class="text-muted small">
        Showing <?= $result['from'] ?>–<?= $result['to'] ?> of <?= $result['total'] ?> records
      </span>
      <span class="small fw-semibold">
        Page Total:
        <span class="text-muted me-3"><?= number_format($grandQty, 2) ?> units</span>
        <span class="text-success">ETB <?= number_format($grandTotal, 2) ?></span>
      </span>
    </div>
  </div>
</div>

<?php if ($result['totalPages'] > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm">
    <?php for ($p = 1; $p <= $result['totalPages']; $p++): ?>
    <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>">
      <a class="page-link"
         href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>">
        <?= $p ?>
      </a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
