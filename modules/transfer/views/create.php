<?php
/**
 * Create Transfer — Phase 10
 * @var array $products @var array $shops @var array $warehouse @var string $today
 */
declare(strict_types=1);
$products  ??= [];
$shops     ??= [];
$warehouse ??= ['name' => 'Warehouse'];
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= url('transfers') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-plus-circle me-2 text-primary"></i>Create Transfer
    </h5>
    <small class="text-muted">From: <?= e($warehouse['name'] ?? 'Warehouse') ?></small>
  </div>
</div>

<div class="alert alert-info d-flex align-items-center gap-2 mb-4 small">
  <i class="bi bi-info-circle-fill flex-shrink-0"></i>
  <div>
    Only products with <strong>available stock</strong> are shown.
    The system will automatically verify available quantities before creating the transfer.
  </div>
</div>

<form method="POST" action="<?= url('transfers') ?>" id="transferForm">
  <?php csrf_input(); ?>

  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <label class="form-label small fw-semibold">Destination Shop *</label>
      <select name="shop_id" class="form-select" required>
        <option value="">— Select Shop —</option>
        <?php foreach ($shops as $s): ?>
        <option value="<?= $s['id'] ?>">
          <?= e($s['name']) ?><?= !empty($s['location']) ? ' — ' . e($s['location']) : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label small fw-semibold">Notes</label>
      <input type="text" name="notes" class="form-control"
             placeholder="Optional transfer notes…">
    </div>
  </div>

  <!-- Product Lines -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold small"><i class="bi bi-box-seam me-2 text-primary"></i>Products to Transfer</span>
      <button type="button" class="btn btn-outline-primary btn-sm" id="addRow">
        <i class="bi bi-plus-lg me-1"></i>Add Product
      </button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle" id="itemsTable">
          <thead class="table-light">
            <tr>
              <th class="ps-3" style="min-width:220px;">Product *</th>
              <th style="min-width:120px;">Available Qty</th>
              <th style="min-width:130px;">Qty to Transfer *</th>
              <th style="min-width:120px;">Batch #</th>
              <th style="min-width:130px;">Expiry Date</th>
              <th class="pe-3 text-center" style="width:60px;">Remove</th>
            </tr>
          </thead>
          <tbody id="rowsBody">
            <tr id="row-0">
              <td class="ps-3">
                <select name="product_ids[]" class="form-select form-select-sm product-select" required
                        onchange="updateAvailable(this)">
                  <option value="">— Select Product —</option>
                  <?php foreach ($products as $p): ?>
                  <option value="<?= $p['product_id'] ?>"
                          data-available="<?= (float)$p['available_quantity'] ?>"
                          data-unit="<?= e($p['unit_abbr'] ?? '') ?>"
                          data-batch="<?= e($p['batch_number'] ?? '') ?>"
                          data-expiry="<?= e($p['expiry_date'] ?? '') ?>"
                          data-unit-id="<?= (int)($p['unit_id'] ?? 0) ?>">
                    <?= e($p['product_name']) ?>
                    (<?= number_format((float)$p['available_quantity'], 1) ?> <?= e($p['unit_abbr'] ?? '') ?> available)
                  </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="unit_ids[]" class="unit-id">
              </td>
              <td class="small available-display text-success fw-semibold">—</td>
              <td>
                <input type="number" name="quantities[]" class="form-control form-control-sm qty-input"
                       step="0.001" min="0.001" required placeholder="0.000">
                <div class="invalid-feedback qty-error small"></div>
              </td>
              <td>
                <input type="text" name="batch_numbers[]" class="form-control form-control-sm batch-input"
                       placeholder="Batch #">
              </td>
              <td>
                <input type="date" name="expiry_dates[]" class="form-control form-control-sm expiry-input">
              </td>
              <td class="pe-3 text-center">
                <button type="button" class="btn btn-outline-danger btn-sm remove-row py-0" disabled>
                  <i class="bi bi-x-lg"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= url('transfers') ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary" id="submitBtn">
      <i class="bi bi-check2-circle me-1"></i>Create Transfer
    </button>
  </div>
</form>

<!-- Product data JSON for JS -->
<script id="productData" type="application/json">
<?php
$productData = [];
foreach ($products as $p) {
    $productData[(int)$p['product_id']] = [
        'available' => (float)$p['available_quantity'],
        'unit'      => $p['unit_abbr']  ?? '',
        'batch'     => $p['batch_number'] ?? '',
        'expiry'    => $p['expiry_date']  ?? '',
        'unit_id'   => (int)($p['unit_id'] ?? 0),
    ];
}
echo json_encode($productData, JSON_HEX_TAG);
?>
</script>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var products = JSON.parse(document.getElementById('productData').textContent);
  var rowCount = 1;
  var productTemplate = document.querySelector('#row-0 select.product-select').innerHTML;

  window.updateAvailable = function (sel) {
    var row   = sel.closest('tr');
    var pid   = parseInt(sel.value, 10);
    var info  = products[pid] || null;
    var avail = row.querySelector('.available-display');
    var batch = row.querySelector('.batch-input');
    var exp   = row.querySelector('.expiry-input');
    var uid   = row.querySelector('.unit-id');

    if (info) {
      avail.textContent = info.available.toFixed(1) + ' ' + info.unit;
      if (info.batch) batch.value = info.batch;
      if (info.expiry) exp.value = info.expiry;
      if (uid) uid.value = info.unit_id;
      row.querySelector('.qty-input').max = info.available;
    } else {
      avail.textContent = '—';
    }
  };

  document.getElementById('addRow').addEventListener('click', function () {
    var tbody = document.getElementById('rowsBody');
    var id    = 'row-' + rowCount++;
    var tr    = document.createElement('tr');
    tr.id = id;
    tr.innerHTML = '<td class="ps-3">' +
      '<select name="product_ids[]" class="form-select form-select-sm product-select" required onchange="updateAvailable(this)">' +
      productTemplate + '</select>' +
      '<input type="hidden" name="unit_ids[]" class="unit-id"></td>' +
      '<td class="small available-display text-success fw-semibold">—</td>' +
      '<td><input type="number" name="quantities[]" class="form-control form-control-sm qty-input" step="0.001" min="0.001" required placeholder="0.000"><div class="invalid-feedback qty-error small"></div></td>' +
      '<td><input type="text" name="batch_numbers[]" class="form-control form-control-sm batch-input" placeholder="Batch #"></td>' +
      '<td><input type="date" name="expiry_dates[]" class="form-control form-control-sm expiry-input"></td>' +
      '<td class="pe-3 text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-row py-0"><i class="bi bi-x-lg"></i></button></td>';
    tbody.appendChild(tr);
    tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); });
  });

  // Enable remove for first row when more rows added
  document.getElementById('addRow').addEventListener('click', function () {
    document.querySelectorAll('.remove-row').forEach(function (b) { b.disabled = false; });
  });

  // Validate qty vs available on submit
  document.getElementById('transferForm').addEventListener('submit', function (e) {
    var valid = true;
    document.querySelectorAll('.qty-input').forEach(function (inp) {
      var max = parseFloat(inp.max) || 0;
      var val = parseFloat(inp.value) || 0;
      var err = inp.closest('tr').querySelector('.qty-error');
      if (max > 0 && val > max) {
        inp.classList.add('is-invalid');
        if (err) err.textContent = 'Exceeds available (' + max.toFixed(3) + ')';
        valid = false;
      } else {
        inp.classList.remove('is-invalid');
        if (err) err.textContent = '';
      }
    });
    if (!valid) { e.preventDefault(); return; }
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating…';
  });
})();
</script>
<?php $view->endSection(); ?>
