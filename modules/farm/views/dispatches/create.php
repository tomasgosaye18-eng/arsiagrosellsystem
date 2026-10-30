<?php
/**
 * =========================================================
 * AAM — Farm Module — Create Dispatch
 * =========================================================
 * @var \AAM\Core\View $view
 * @var array          $farm
 * @var array          $dispatchable   Harvest batches available
 * @var array          $warehouses     Available warehouses
 * @var string         $nextDispatch
 * @var string         $today
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

use AAM\Farm\Models\HarvestBatchModel;

$farmId   = (int)$farm['id'];
$oldInput = $oldInput ?? [];
$old      = fn(string $k, string $d = '') => e($oldInput[$k] ?? $d);
?>

<!-- ── Header ───────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-truck me-2 text-primary"></i>Create Dispatch
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('farms') ?>">Farms</a></li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId) ?>"><?= e($farm['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId . '/dispatches') ?>">Dispatch</a>
        </li>
        <li class="breadcrumb-item active">Create</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('farms/' . $farmId . '/dispatches') ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php $view->flash(); ?>

<!-- ── Rule 6 Note ───────────────────────────────────────── -->
<div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3">
  <i class="bi bi-info-circle-fill flex-shrink-0"></i>
  <div class="small">
    Farm dispatches can only be sent to the <strong>Central Warehouse</strong>.
    This is a Farm → Warehouse transfer, not a shop delivery.
  </div>
</div>

<form method="POST" action="<?= url('farms/' . $farmId . '/dispatches') ?>"
      id="dispatchForm" novalidate>
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="row g-3">

    <!-- ── LEFT — Dispatch Header ────────────────────────── -->
    <div class="col-lg-8">

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-info-circle me-2 text-primary"></i>Dispatch Information
          </h6>
        </div>
        <div class="card-body p-3">
          <div class="row g-3">

            <!-- Dispatch Number -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Dispatch Number</label>
              <input type="text" class="form-control font-monospace"
                     name="dispatch_number"
                     value="<?= $old('dispatch_number', $nextDispatch) ?>"
                     placeholder="DSP-YYYY-NNNN" maxlength="30">
              <div class="form-text">Leave blank to auto-generate.</div>
            </div>

            <!-- Destination Warehouse (RULE 6) -->
            <div class="col-md-8">
              <label class="form-label fw-semibold">
                Destination <span class="text-danger">*</span>
              </label>
              <select class="form-select <?= $view->isInvalid('warehouse_id') ?>"
                      name="warehouse_id" required>
                <option value="">— Select Warehouse —</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= (int)$w['id'] ?>"
                  <?= ($oldInput['warehouse_id'] ?? '') == $w['id'] ? 'selected' : '' ?>>
                  <?= e($w['name']) ?>
                  <?php if (!empty($w['location'])): ?> — <?= e($w['location']) ?><?php endif; ?>
                </option>
                <?php endforeach; ?>
              </select>
              <?php $view->fieldError('warehouse_id'); ?>
            </div>

            <!-- Dispatch Date -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">
                Dispatch Date <span class="text-danger">*</span>
              </label>
              <input type="date" class="form-control <?= $view->isInvalid('dispatch_date') ?>"
                     name="dispatch_date"
                     value="<?= $old('dispatch_date', $today) ?>"
                     required>
              <?php $view->fieldError('dispatch_date'); ?>
            </div>

            <!-- Expected Arrival -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Expected Arrival</label>
              <input type="date" class="form-control"
                     name="expected_arrival_date"
                     value="<?= $old('expected_arrival_date') ?>"
                     min="<?= $today ?>">
            </div>

            <!-- Notes -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Notes</label>
              <input type="text" class="form-control" name="notes"
                     value="<?= $old('notes') ?>"
                     maxlength="255" placeholder="Optional notes…">
            </div>

          </div>
        </div>
      </div>

      <!-- Harvest Batch Selection -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-basket me-2 text-success"></i>
            Select Harvest Batches
          </h6>
          <span class="badge bg-success-subtle text-success">
            <?= count($dispatchable) ?> eligible
          </span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">
                    <input type="checkbox" class="form-check-input" id="selectAllBatches">
                  </th>
                  <th>Batch / Product</th>
                  <th class="text-end">Available</th>
                  <th>Quality</th>
                  <th>Harvest Date</th>
                  <th class="text-end pe-3" style="min-width:140px;">
                    Dispatch Qty
                  </th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dispatchable as $i => $b):
                  $qc = HarvestBatchModel::getQualityBadgeClass($b['quality_grade'] ?? '');
                  $rem = (float)$b['remaining_quantity'];
                ?>
                <tr class="batch-row">
                  <td class="ps-3">
                    <input type="checkbox" class="form-check-input batch-check"
                           data-index="<?= $i ?>"
                           data-max="<?= $rem ?>"
                           id="check_<?= $i ?>">
                  </td>
                  <td class="small">
                    <div class="fw-semibold"><?= e($b['product_name'] ?? '—') ?></div>
                    <div class="text-muted font-monospace" style="font-size:.68rem;">
                      <?= e($b['batch_number'] ?? '') ?>
                    </div>
                  </td>
                  <td class="text-end small fw-bold text-success">
                    <?= number_format($rem, 2) ?>
                    <span class="text-muted fw-normal"><?= e($b['unit_abbr'] ?? '') ?></span>
                  </td>
                  <td>
                    <span class="badge bg-<?= $qc ?>-subtle text-<?= $qc ?> small">
                      <?= e($b['quality_grade'] ?? '—') ?>
                    </span>
                  </td>
                  <td class="small text-muted">
                    <?= !empty($b['harvest_date']) ? date('d M Y', strtotime($b['harvest_date'])) : '—' ?>
                  </td>
                  <td class="text-end pe-3">
                    <!-- Hidden fields — populated by checkbox interaction -->
                    <input type="hidden" name="batch_ids[]" value="<?= (int)$b['id'] ?>"
                           class="batch-id-input" disabled>
                    <input type="number"
                           class="form-control form-control-sm text-end qty-input"
                           name="quantities[]"
                           id="qty_<?= $i ?>"
                           min="0.001"
                           max="<?= $rem ?>"
                           step="0.001"
                           value="<?= $rem ?>"
                           placeholder="0.000"
                           style="max-width:120px;margin-left:auto;"
                           disabled
                           data-index="<?= $i ?>">
                    <div class="invalid-feedback text-end"
                         id="err_qty_<?= $i ?>"></div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div id="noSelection" class="text-center py-3 text-muted small
                                       border-top d-none">
            <i class="bi bi-exclamation-circle me-1"></i>
            Select at least one harvest batch to dispatch.
          </div>
        </div>
      </div>

    </div><!-- /col-lg-8 -->

    <!-- ── RIGHT — Summary ───────────────────────────────── -->
    <div class="col-lg-4">

      <!-- Dispatch Summary -->
      <div class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
        <div class="card-body">
          <h6 class="fw-semibold text-primary mb-3">
            <i class="bi bi-truck me-2"></i>Dispatch Summary
          </h6>
          <dl class="small mb-0">
            <dt class="text-muted">From (Farm)</dt>
            <dd class="fw-semibold">
              <i class="bi bi-flower1 me-1 text-success"></i><?= e($farm['name']) ?>
            </dd>
            <dt class="text-muted">To (Warehouse)</dt>
            <dd id="summaryDest" class="fw-semibold">—</dd>
            <dt class="text-muted">Batches Selected</dt>
            <dd id="summaryBatches" class="fw-bold text-primary">0</dd>
            <dt class="text-muted">Total Quantity</dt>
            <dd id="summaryTotalQty" class="fw-bold fs-5 text-success">0.000</dd>
          </dl>
        </div>
      </div>

      <!-- Flow Reminder -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
          <div class="small fw-semibold text-muted mb-2">Supply Chain Flow</div>
          <?php
          $flow = [
            ['bi-flower1','success','FARM','Source'],
            ['bi-truck','primary','DISPATCH','This step'],
            ['bi-building','secondary','WAREHOUSE','Destination'],
          ];
          foreach ($flow as $k => [$ico, $clr, $stage, $note]):
          ?>
          <div class="d-flex align-items-center gap-2 <?= $k > 0 ? 'mt-1' : '' ?>">
            <i class="bi <?= $ico ?> text-<?= $clr ?> fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1">
              <div class="small fw-bold"><?= $stage ?></div>
              <div class="text-muted" style="font-size:.68rem;"><?= $note ?></div>
            </div>
            <?php if ($k < 2): ?><i class="bi bi-chevron-down text-muted small"></i><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Submit -->
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100 mb-2" id="submitBtn">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner"></span>
            <i class="bi bi-send-fill me-1"></i>Create Dispatch
          </button>
          <a href="<?= url('farms/' . $farmId . '/dispatches') ?>"
             class="btn btn-outline-secondary w-100">Cancel</a>
        </div>
      </div>

    </div><!-- /col-lg-4 -->
  </div><!-- /row -->
</form>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var checks       = document.querySelectorAll('.batch-check');
  var summaryBatches = document.getElementById('summaryBatches');
  var summaryTotalQty = document.getElementById('summaryTotalQty');
  var summaryDest   = document.getElementById('summaryDest');
  var warehouseSelect = document.querySelector('[name="warehouse_id"]');
  var noSelection   = document.getElementById('noSelection');
  var selectAll     = document.getElementById('selectAllBatches');

  function updateSummary() {
    var count = 0;
    var total = 0;

    checks.forEach(function (chk) {
      if (chk.checked) {
        var idx = chk.dataset.index;
        var qtyInput = document.getElementById('qty_' + idx);
        count++;
        total += parseFloat(qtyInput?.value || '0');
      }
    });

    summaryBatches.textContent  = count;
    summaryTotalQty.textContent = total.toFixed(3);
    noSelection.classList.toggle('d-none', count > 0);
  }

  function toggleBatch(chk) {
    var idx       = chk.dataset.index;
    var qtyInput  = document.getElementById('qty_' + idx);
    var batchId   = document.querySelector('input.batch-id-input[name="batch_ids[]"]:nth-of-type(' + (parseInt(idx)+1) + ')');
    // Re-query by row
    var row       = chk.closest('tr');
    var idInput   = row.querySelector('.batch-id-input');

    if (chk.checked) {
      if (idInput)  idInput.disabled  = false;
      if (qtyInput) qtyInput.disabled = false;
    } else {
      if (idInput)  idInput.disabled  = true;
      if (qtyInput) qtyInput.disabled = true;
    }
    updateSummary();
  }

  checks.forEach(function (chk) {
    chk.addEventListener('change', function () { toggleBatch(this); });
  });

  document.querySelectorAll('.qty-input').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var max  = parseFloat(this.max);
      var val  = parseFloat(this.value);
      var errEl = document.getElementById('err_qty_' + this.dataset.index);
      if (val > max + 0.0001) {
        this.classList.add('is-invalid');
        if (errEl) errEl.textContent = 'Exceeds available qty (' + max.toFixed(2) + ')';
      } else {
        this.classList.remove('is-invalid');
        if (errEl) errEl.textContent = '';
      }
      updateSummary();
    });
  });

  selectAll?.addEventListener('change', function () {
    checks.forEach(function (chk) {
      chk.checked = selectAll.checked;
      toggleBatch(chk);
    });
  });

  warehouseSelect?.addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    summaryDest.textContent = opt.text || '—';
  });

  // Form validation before submit
  document.getElementById('dispatchForm')?.addEventListener('submit', function (e) {
    var anySelected = [...checks].some(c => c.checked);
    if (!anySelected) {
      e.preventDefault();
      noSelection.classList.remove('d-none');
      noSelection.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    // Check for qty > max
    var hasError = document.querySelectorAll('.qty-input.is-invalid').length > 0;
    if (hasError) {
      e.preventDefault();
      return;
    }

    document.getElementById('submitBtn').disabled = true;
    document.getElementById('spinner').classList.remove('d-none');
  });

  updateSummary();
})();
</script>
<?php $view->endSection(); ?>
