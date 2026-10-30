<?php
declare(strict_types=1);
$allGrades    ??= [];
$seedProducts ??= [];
$allProducts  ??= [];

// Group grades by product
$gradesByProd = [];
foreach ($allGrades as $g) {
    $gradesByProd[(int)$g['product_id']][] = $g;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-box-seam-fill me-2 text-success"></i>Seed Products & Grades</h4>
    <p class="text-muted small mb-0">Manage seed products and configurable grade/class classifications</p>
  </div>
</div>

<!-- Seed Products Section -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header border-0 bg-white d-flex align-items-center justify-content-between">
    <h6 class="fw-bold mb-0"><i class="bi bi-flower1 me-2 text-success"></i>Seed Products</h6>
    <span class="badge bg-success"><?= count($seedProducts) ?> active</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Product</th><th>SKU</th><th>Unit</th><th>Category</th><th>Status</th><th>Grades</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($allProducts as $p): ?>
          <tr>
            <td class="fw-semibold"><?= e($p['name']) ?></td>
            <td class="font-monospace small text-muted"><?= e($p['sku'] ?? '') ?></td>
            <td class="small"><?= e($p['unit_abbr'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($p['category_name'] ?? '—') ?></td>
            <td>
              <?php if ($p['is_seed']): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-flower1 me-1"></i>Seed Product</span>
              <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary border">Regular</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php $grades = $gradesByProd[(int)$p['id']] ?? []; ?>
              <?php if (!empty($grades)): ?>
                <?php foreach ($grades as $g): ?>
                <span class="badge bg-secondary-subtle text-dark border me-1"><?= e($g['grade_name']) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
              <span class="text-muted fst-italic">—</span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-<?= $p['is_seed'] ? 'danger' : 'success' ?> toggle-seed-btn"
                      data-product-id="<?= (int)$p['id'] ?>"
                      data-is-seed="<?= (int)$p['is_seed'] ?>"
                      title="<?= $p['is_seed'] ? 'Remove seed flag' : 'Mark as seed product' ?>">
                <?= $p['is_seed'] ? '<i class="bi bi-x-circle me-1"></i>Remove Seed' : '<i class="bi bi-flower1 me-1"></i>Mark as Seed' ?>
              </button>
              <?php if ($p['is_seed']): ?>
              <button class="btn btn-sm btn-outline-primary ms-1 add-grade-btn"
                      data-product-id="<?= (int)$p['id'] ?>"
                      data-product-name="<?= e($p['name']) ?>">
                <i class="bi bi-plus-lg me-1"></i>Add Grade
              </button>
              <a href="<?= url('gm/products/' . (int)$p['id'] . '/pricing') ?>"
                 class="btn btn-sm btn-outline-warning ms-1" title="Manage Price">
                <i class="bi bi-tag me-1"></i>Price
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Grades Section -->
<div class="card border-0 shadow-sm">
  <div class="card-header border-0 bg-white">
    <h6 class="fw-bold mb-0"><i class="bi bi-tags-fill me-2 text-primary"></i>Configured Grades / Classes</h6>
  </div>
  <div class="card-body p-0">
    <?php if (empty($allGrades)): ?>
    <p class="text-muted p-3 mb-0 small">No grades configured yet. Mark products as seed and add grades above.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Product</th><th>Grade Name</th><th>Code</th><th>Description</th><th>Order</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($allGrades as $g): ?>
          <tr>
            <td class="small fw-semibold"><?= e($g['product_name'] ?? '') ?></td>
            <td><span class="badge bg-secondary-subtle text-dark border"><?= e($g['grade_name']) ?></span></td>
            <td class="font-monospace small text-muted"><?= e($g['grade_code']) ?></td>
            <td class="small text-muted"><?= e($g['description'] ?? '—') ?></td>
            <td class="small"><?= (int)$g['display_order'] ?></td>
            <td>
              <?php if ($g['is_active']): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle small">Active</span>
              <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary border small">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-secondary toggle-grade-btn"
                      data-grade-id="<?= (int)$g['id'] ?>">
                <?= $g['is_active'] ? '<i class="bi bi-pause-fill"></i>' : '<i class="bi bi-play-fill"></i>' ?>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Grade Modal -->
<div class="modal fade" id="addGradeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="bi bi-tags-fill me-2"></i>Add Grade — <span id="gradeProductName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addGradeForm">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="product_id" id="gradeProductId">
          <div class="mb-3">
            <label class="form-label fw-semibold">Grade Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="grade_name" placeholder="e.g. Pre-basic, Certified, 15 KG Package" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Grade Code</label>
            <input type="text" class="form-control font-monospace" name="grade_code"
                   placeholder="e.g. PRE_BASIC (auto-generated if blank)">
            <div class="form-text">Unique code for this grade. Auto-generated from name if left blank.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" name="description" rows="2" maxlength="300"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Display Order</label>
            <input type="number" class="form-control" name="display_order" value="0" min="0" style="max-width:100px;">
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="saveGradeBtn">
            <i class="bi bi-check-lg me-1"></i>Save Grade
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function(){
  'use strict';

  var gradeModal = new bootstrap.Modal(document.getElementById('addGradeModal'));

  // Open add grade modal
  document.querySelectorAll('.add-grade-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.getElementById('gradeProductId').value    = this.dataset.productId;
      document.getElementById('gradeProductName').textContent = this.dataset.productName;
      gradeModal.show();
    });
  });

  // Save grade
  document.getElementById('addGradeForm').addEventListener('submit', function(e){
    e.preventDefault();
    var btn = document.getElementById('saveGradeBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    fetch('<?= url('gm/seed-distribution/grades') ?>', {
      method: 'POST', credentials: 'same-origin',
      headers: {'X-Requested-With':'XMLHttpRequest'},
      body: new FormData(this)
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Grade';
      if (resp.success) { gradeModal.hide(); location.reload(); }
      else alert(resp.message || 'Save failed');
    });
  });

  // Toggle grade active
  document.querySelectorAll('.toggle-grade-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = this.dataset.gradeId;
      var fd = new FormData();
      fd.append('csrf_token', '<?= csrf_token() ?>');
      fetch('<?= url('gm/seed-distribution/grades') ?>/' + id + '/toggle', {
        method: 'POST', credentials: 'same-origin',
        headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(resp){
        if (resp.success) location.reload();
        else alert(resp.message || 'Failed');
      });
    });
  });

  // Toggle is_seed
  document.querySelectorAll('.toggle-seed-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id     = this.dataset.productId;
      var isSeed = parseInt(this.dataset.isSeed);
      if (!confirm(isSeed ? 'Remove seed flag from this product?' : 'Mark this product as a seed product?')) return;
      var fd = new FormData();
      fd.append('csrf_token', '<?= csrf_token() ?>');
      fetch('<?= url('gm/seed-distribution/products') ?>/' + id + '/toggle-seed', {
        method: 'POST', credentials: 'same-origin',
        headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(resp){
        if (resp.success) location.reload();
        else alert(resp.message || 'Failed');
      });
    });
  });
})();
</script>
<?php $view->endSection(); ?>
