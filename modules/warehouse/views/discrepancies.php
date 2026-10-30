<?php
/**
 * Discrepancies — Phase 10
 * @var array $discrepancies @var array $pagination @var array $filters @var array $warehouse
 */
declare(strict_types=1);
$discrepancies ??= [];
$filters       ??= [];
$userRole      ??= '';
$canResolve    = in_array($userRole, [ROLE_WAREHOUSE_MANAGER, ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-clipboard-x me-2 text-danger"></i>Discrepancies
  </h5>
  <a href="<?= url('warehouse') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-speedometer2 me-1"></i>Overview
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Discrepancy # or product…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['open','under_review','resolved','rejected','gm_rejected','final_rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= \AAM\Warehouse\Models\DiscrepancyModel::statusLabel($s) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('warehouse/discrepancies') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($discrepancies)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-clipboard-check display-6 d-block mb-2 opacity-25"></i>
        <p class="mb-0">No discrepancies found.</p>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Dis. #</th>
            <th>Type</th>
            <th>Product</th>
            <th>Reference</th>
            <th>Expected</th>
            <th>Actual</th>
            <th>Variance</th>
            <th>Reported By</th>
            <th>Date</th>
            <th>Status</th>
            <?php if ($canResolve): ?><th class="pe-3 text-end">Action</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($discrepancies as $d):
            $badge     = \AAM\Warehouse\Models\DiscrepancyModel::statusBadge($d['status']);
            $label     = \AAM\Warehouse\Models\DiscrepancyModel::statusLabel($d['status']);
            $typeLabel = \AAM\Warehouse\Models\DiscrepancyModel::typeLabel($d['discrepancy_type']);
            // Transfer discrepancies are GM-workflow only — WM cannot manually resolve them
            $isTransfer    = ($d['reference_type'] ?? '') === 'transfer';
            $canResolveRow = $canResolve && !$isTransfer && in_array($d['status'], ['open','under_review'], true);
          ?>
          <tr>
            <td class="ps-3 small fw-semibold"><?= e($d['discrepancy_number']) ?></td>
            <td><span class="badge bg-secondary-subtle text-secondary small"><?= e($typeLabel) ?></span></td>
            <td class="small"><?= e($d['product_name'] ?? '—') ?></td>
            <td class="small text-muted">
              <?= ucfirst($d['reference_type'] ?? '') ?> #<?= $d['reference_id'] ?>
            </td>
            <td class="small"><?= number_format((float)$d['expected_quantity'], 3) ?></td>
            <td class="small text-danger"><?= number_format((float)$d['actual_quantity'], 3) ?></td>
            <td class="small fw-bold text-danger">
              <?= number_format((float)$d['variance_quantity'], 3) ?>
              <span class="text-muted">(<?= number_format((float)$d['variance_percent'], 1) ?>%)</span>
            </td>
            <td class="small text-muted"><?= e($d['reported_by_name'] ?? '—') ?></td>
            <td class="small"><?= $d['reported_at'] ? date('d/m/Y', strtotime($d['reported_at'])) : '—' ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <?php if ($canResolveRow): ?>
            <td class="pe-3 text-end">
              <a href="<?= url('warehouse/discrepancies/' . $d['id']) ?>"
                 class="btn btn-outline-secondary btn-sm py-0">View</a>
              <button type="button" class="btn btn-sm btn-outline-success py-0"
                      data-bs-toggle="modal"
                      data-bs-target="#resolveModal"
                      data-id="<?= $d['id'] ?>"
                      data-num="<?= e($d['discrepancy_number']) ?>">
                Resolve
              </button>
            </td>
            <?php else: ?>
            <td class="pe-3 text-end">
              <a href="<?= url('warehouse/discrepancies/' . $d['id']) ?>"
                 class="btn btn-outline-secondary btn-sm py-0">View</a>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?> records
      </small>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
        <li class="page-item <?= $p === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($canResolve): ?>
<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="resolveForm">
      <?php csrf_input(); ?>
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title" id="resolveModalLabel">Update Discrepancy Status</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Discrepancy: <strong id="disNum"></strong></p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">New Status *</label>
            <select name="status" class="form-select" required>
              <option value="under_review">Under Review</option>
              <option value="resolved">Resolved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Resolution Notes</label>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="Describe the resolution or investigation findings…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Update Status</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
var resolveModal = document.getElementById('resolveModal');
resolveModal.addEventListener('show.bs.modal', function (event) {
  var btn = event.relatedTarget;
  var id  = btn.getAttribute('data-id');
  var num = btn.getAttribute('data-num');
  document.getElementById('disNum').textContent = num;
  document.getElementById('resolveForm').action =
    '<?= url('warehouse/discrepancies') ?>/' + id + '/status';
});
</script>
<?php $view->endSection(); ?>
<?php endif; ?>
