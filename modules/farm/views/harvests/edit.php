<?php
/**
 * =========================================================
 * AAM — Farm Module — Edit Harvest
 * =========================================================
 * @var \AAM\Core\View $view
 * @var array          $harvest
 * @var array          $farm
 * @var array          $products
 * @var array          $units
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

$harvestId = (int)$harvest['id'];
$farmId    = (int)$farm['id'];
$oldInput  = $oldInput ?? $harvest;
$old       = fn(string $k, mixed $d = '') => e($oldInput[$k] ?? $d);
$dispatched = (float)$harvest['initial_quantity'] - (float)$harvest['remaining_quantity'];
?>

<!-- ── Header ───────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-pencil-fill me-2 text-primary"></i>
      Edit Harvest — <?= e($harvest['batch_number'] ?? "#{$harvestId}") ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId . '/harvests') ?>">Harvest</a>
        </li>
        <li class="breadcrumb-item">
          <a href="<?= url('harvests/' . $harvestId) ?>"><?= e($harvest['batch_number'] ?? $harvestId) ?></a>
        </li>
        <li class="breadcrumb-item active">Edit</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('harvests/' . $harvestId) ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php $view->flash(); ?>

<?php if ($dispatched > 0): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3 py-2">
  <i class="bi bi-info-circle-fill flex-shrink-0"></i>
  <div class="small">
    <strong><?= number_format($dispatched, 2) ?></strong>
    <?= e($harvest['unit_abbr'] ?? '') ?> already dispatched.
    Initial quantity must remain ≥ <?= number_format($dispatched, 2) ?>.
  </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= url('harvests/' . $harvestId . '/update') ?>"
      id="editForm" novalidate>
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-info-circle me-2 text-primary"></i>Update Harvest Details
          </h6>
        </div>
        <div class="card-body p-3">
          <div class="row g-3">

            <!-- Batch Number (read-only) -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Batch Number</label>
              <input type="text" class="form-control-plaintext font-monospace fw-bold"
                     value="<?= e($harvest['batch_number'] ?? '') ?>" readonly>
            </div>

            <!-- Product (read-only after creation) -->
            <div class="col-md-8">
              <label class="form-label fw-semibold">Product</label>
              <input type="text" class="form-control-plaintext fw-semibold"
                     value="<?= e($harvest['product_name'] ?? '') ?>" readonly>
            </div>

            <!-- Quantity -->
            <div class="col-md-5">
              <label class="form-label fw-semibold">
                Quantity <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="number"
                       class="form-control <?= $view->isInvalid('initial_quantity') ?>"
                       name="initial_quantity"
                       value="<?= $old('initial_quantity') ?>"
                       min="<?= $dispatched > 0 ? $dispatched : '0.001' ?>"
                       step="0.001" required>
                <select class="form-select" name="unit_id" style="max-width:130px;">
                  <?php foreach ($units as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"
                    <?= ($oldInput['unit_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                    <?= e($u['abbreviation']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php $view->fieldError('initial_quantity'); ?>
            </div>

            <!-- Quality -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Quality Grade <span class="text-danger">*</span></label>
              <select class="form-select" name="quality_grade" required>
                <?php foreach (['A'=>'Grade A', 'B'=>'Grade B', 'C'=>'Grade C (Rejected)', 'Ungraded'=>'Ungraded'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $old('quality_grade') === $k ? 'selected' : '' ?>>
                  <?= $v ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Dates -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Production Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="production_date"
                     value="<?= $old('production_date') ?>" required>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">Harvest Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="harvest_date"
                     value="<?= $old('harvest_date') ?>" required>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">Expiry Date</label>
              <input type="date" class="form-control" name="expiry_date"
                     value="<?= $old('expiry_date') ?>">
            </div>

            <!-- Notes -->
            <div class="col-12">
              <label class="form-label fw-semibold">Notes</label>
              <textarea class="form-control" name="notes"
                        rows="2"><?= $old('notes') ?></textarea>
            </div>

          </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg me-1"></i>Save Changes
          </button>
          <a href="<?= url('harvests/' . $harvestId) ?>"
             class="btn btn-outline-secondary px-4">Cancel</a>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm border-start border-4 border-warning">
        <div class="card-body">
          <h6 class="fw-semibold text-warning mb-2">
            <i class="bi bi-shield-exclamation me-2"></i>Edit Restrictions
          </h6>
          <ul class="small mb-0 ps-3">
            <li>Batch number cannot be changed.</li>
            <li>Product cannot be changed.</li>
            <li>Quantity must be ≥ already dispatched.</li>
            <li>Only <strong>Active</strong> records are editable.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</form>
