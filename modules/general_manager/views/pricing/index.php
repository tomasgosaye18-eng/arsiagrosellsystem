<?php
/**
 * =========================================================
 * GENERAL MANAGER — Product Pricing Management
 * =========================================================
 * File    : modules/general_manager/views/pricing/index.php
 * Route   : GET /gm/products/{id}/pricing
 * Access  : ROLE_GENERAL_MANAGER only
 *
 * @var array  $product   Product data (product_id, product_name, unit_price, …)
 * @var array  $history   Price history records
 * @var array  $stats     Price statistics (total_changes, increases, decreases)
 */
declare(strict_types=1);

$product ??= [];
$history ??= [];
$stats   ??= [];
$pid = (int)($product['product_id'] ?? 0);
?>

<!-- ── Breadcrumb & Header ──────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0">
      <i class="bi bi-tag-fill me-2 text-success"></i>Product Pricing
    </h5>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('gm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('gm/products') ?>">Products</a></li>
        <li class="breadcrumb-item active">Pricing — <?= e($product['product_name'] ?? '') ?></li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('gm/products') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Products
  </a>
</div>

<!-- ── Product Info Bar ─────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body py-3 px-4">
    <div class="row align-items-center g-3">
      <div class="col-md-6">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:48px;height:48px;">
            <i class="bi bi-box-seam-fill text-primary fs-5"></i>
          </div>
          <div>
            <div class="fw-bold fs-6"><?= e($product['product_name'] ?? '—') ?></div>
            <div class="text-muted small">
              SKU: <span class="font-monospace"><?= e($product['sku'] ?? '—') ?></span>
              <?php if (!empty($product['category_name'])): ?>
               · <?= e($product['category_name']) ?>
              <?php endif; ?>
              <?php if (!empty($product['unit_name'])): ?>
               · per <?= e($product['unit_abbr'] ?? $product['unit_name']) ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="row g-2 text-center">
          <div class="col-4">
            <div class="border rounded p-2">
              <div class="text-muted" style="font-size:.72rem;">Current Price</div>
              <?php $currentPrice = (float)($product['unit_price'] ?? 0); ?>
              <?php if ($currentPrice > 0): ?>
              <div class="fw-bold text-success fs-5" id="currentPriceDisplay">
                ETB <?= number_format($currentPrice, 2) ?>
              </div>
              <?php else: ?>
              <div class="fw-bold text-warning fs-6" id="currentPriceDisplay">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>Not Set
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2">
              <div class="text-muted" style="font-size:.72rem;">Price Changes</div>
              <div class="fw-bold text-info fs-5"><?= (int)($stats['total_changes'] ?? 0) ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2">
              <div class="text-muted" style="font-size:.72rem;">POS Status</div>
              <?php if ($currentPrice > 0): ?>
              <div class="fw-bold text-success" style="font-size:.82rem;">
                <i class="bi bi-check-circle-fill me-1"></i>Sellable
              </div>
              <?php else: ?>
              <div class="fw-bold text-danger" style="font-size:.82rem;">
                <i class="bi bi-x-circle-fill me-1"></i>Blocked
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ((float)($product['unit_price'] ?? 0) <= 0): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start mb-4" role="alert">
  <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0 text-warning"></i>
  <div>
    <strong>No selling price set.</strong>
    This product is currently <strong>blocked from POS sales</strong>.
    Set a selling price below to make it available in the POS terminal.
    The price will take effect immediately after saving.
  </div>
</div>
<?php endif; ?>

<!-- ── Update Price Panel ────────────────────────────────── -->
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm border-start border-4 border-success">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-bold mb-0">
          <i class="bi bi-pencil-square me-2 text-success"></i>Set Selling Price
        </h6>
      </div>
      <div class="card-body p-4">

        <div class="mb-3 p-3 bg-light rounded">
          <div class="text-muted small mb-1">Current Selling Price</div>
          <?php $currentPrice2 = (float)($product['unit_price'] ?? 0); ?>
          <?php if ($currentPrice2 > 0): ?>
          <div class="fw-bold fs-4 text-success" id="currentPriceLarge">
            ETB <?= number_format($currentPrice2, 2) ?>
          </div>
          <?php else: ?>
          <div class="fw-bold fs-5 text-danger" id="currentPriceLarge">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>Not Set — Product cannot be sold
          </div>
          <?php endif; ?>
          <div class="text-muted small">
            per <strong><?= e($product['unit_abbr'] ?? $product['unit_name'] ?? 'unit') ?></strong>
            <?php if (!empty($product['unit_name']) && !empty($product['unit_abbr']) && $product['unit_name'] !== $product['unit_abbr']): ?>
            (<?= e($product['unit_name']) ?>)
            <?php endif; ?>
          </div>
        </div>

        <form id="priceUpdateForm">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="mb-3">
            <label for="newPriceInput" class="form-label fw-semibold">
              New Selling Price
              <span class="text-success">(ETB per <?= e($product['unit_abbr'] ?? $product['unit_name'] ?? 'unit') ?>)</span>
              <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text fw-semibold">ETB</span>
              <input type="number"
                     id="newPriceInput"
                     name="new_price"
                     class="form-control form-control-lg"
                     step="0.01"
                     min="0"
                     placeholder="e.g. 85.00"
                     autocomplete="off"
                     aria-describedby="priceHelp pricePreview">
              <span class="input-group-text text-muted">/ <?= e($product['unit_abbr'] ?? $product['unit_name'] ?? 'unit') ?></span>
            </div>
            <div class="invalid-feedback" id="err_new_price"></div>
            <small id="priceHelp" class="text-muted">
              Price per <?= e($product['unit_name'] ?? $product['unit_abbr'] ?? 'unit') ?>.
              POS will calculate: Quantity × this price.
            </small>
          </div>

          <!-- Live change preview -->
          <div id="pricePreview" class="alert alert-info py-2 small d-none" role="status" aria-live="polite">
            <i class="bi bi-info-circle me-1"></i>
            <span id="pricePreviewText"></span>
          </div>

          <div class="mb-3">
            <label for="reasonInput" class="form-label fw-semibold">
              Reason for Change
              <span class="text-muted small fw-normal">(optional)</span>
            </label>
            <textarea id="reasonInput"
                      name="reason"
                      class="form-control"
                      rows="2"
                      maxlength="255"
                      placeholder="e.g. Market price adjustment, Seasonal pricing…"></textarea>
          </div>

          <button type="submit" class="btn btn-success w-100 fw-bold py-2" id="priceSubmitBtn">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="priceSpinner" aria-hidden="true"></span>
            <i class="bi bi-check-circle-fill me-2" id="priceSubmitIcon"></i>
            Save Price
          </button>
          <div id="priceSuccessMsg" class="alert alert-success mt-2 py-2 small d-none" role="alert"></div>
          <div id="priceErrorMsg"   class="alert alert-danger  mt-2 py-2 small d-none" role="alert"></div>
        </form>

        <hr class="my-3">
        <div class="text-muted small">
          <i class="bi bi-shield-lock-fill me-1 text-success"></i>
          Only the General Manager can update selling prices.
          The new price will be used immediately in the POS terminal.
        </div>
      </div>
    </div>
  </div><!-- /col-lg-5 -->

  <!-- ── Price History ──────────────────────────────────── -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
          <i class="bi bi-clock-history me-2 text-secondary"></i>Price History
        </h6>
        <span class="badge bg-secondary"><?= count($history) ?> record<?= count($history) !== 1 ? 's' : '' ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($history)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-graph-up display-5 d-block mb-2 opacity-20"></i>
          <div class="small fw-semibold">No price history yet</div>
          <div class="small">The first price change will appear here.</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">#</th>
                <th class="text-end">Old Price</th>
                <th class="text-end">New Price</th>
                <th class="text-end">Change</th>
                <th>Changed By</th>
                <th>Date</th>
                <th>Reason</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $i => $ph):
                $diff    = (float)($ph['price_difference'] ?? ($ph['new_price'] - $ph['old_price']));
                $pct     = $ph['percentage_change'] ?? null;
                $isUp    = $diff >= 0;
                $icon    = $isUp ? 'bi-arrow-up-right text-success' : 'bi-arrow-down-right text-danger';
                $diffCls = $isUp ? 'text-success' : 'text-danger';
              ?>
              <tr>
                <td class="ps-3 text-muted small"><?= $i + 1 ?></td>
                <td class="text-end font-monospace small text-muted">
                  <?= $ph['old_price'] > 0
                      ? 'ETB ' . number_format((float)$ph['old_price'], 2)
                      : '<span class="text-muted fst-italic">Initial</span>' ?>
                </td>
                <td class="text-end font-monospace small fw-semibold">
                  ETB <?= number_format((float)$ph['new_price'], 2) ?>
                </td>
                <td class="text-end small">
                  <i class="bi <?= $icon ?>"></i>
                  <span class="<?= $diffCls ?> fw-semibold">
                    <?= $isUp ? '+' : '' ?><?= number_format($diff, 2) ?>
                    <?php if ($pct !== null): ?>
                    <small class="text-muted fw-normal">(<?= $isUp ? '+' : '' ?><?= number_format((float)$pct, 1) ?>%)</small>
                    <?php endif; ?>
                  </span>
                </td>
                <td class="small text-muted">
                  <?= e($ph['changed_by_username'] ?? $ph['changed_by_name'] ?? '—') ?>
                </td>
                <td class="small text-muted" style="white-space:nowrap;">
                  <?php
                    $dt = $ph['changed_at'] ?? '';
                    echo $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
                  ?>
                </td>
                <td class="small text-muted" style="max-width:140px;"
                    title="<?= e($ph['reason'] ?? '') ?>">
                  <?= e(mb_strimwidth($ph['reason'] ?? '—', 0, 40, '…')) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /col-lg-7 -->
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var APP_URL    = '<?= rtrim(e(\AAM\Config\Application::get('url','')), '/') ?>';
  var productId  = <?= (int)$pid ?>;
  var oldPrice   = <?= number_format((float)($product['unit_price'] ?? 0), 2, '.', '') ?>;
  var unitAbbr   = '<?= addslashes($product['unit_abbr'] ?? $product['unit_name'] ?? 'unit') ?>';

  var form        = document.getElementById('priceUpdateForm');
  var input       = document.getElementById('newPriceInput');
  var submitBtn   = document.getElementById('priceSubmitBtn');
  var spinner     = document.getElementById('priceSpinner');
  var submitIcon  = document.getElementById('priceSubmitIcon');
  var successMsg  = document.getElementById('priceSuccessMsg');
  var errorMsg    = document.getElementById('priceErrorMsg');
  var preview     = document.getElementById('pricePreview');
  var previewText = document.getElementById('pricePreviewText');
  var errNewPrice = document.getElementById('err_new_price');

  // ── Live price change preview ──────────────────────────
  input.addEventListener('input', function () {
    clearMessages();
    var newPrice = parseFloat(this.value);
    if (isNaN(newPrice) || this.value === '') {
      preview.classList.add('d-none');
      return;
    }
    var diff = newPrice - oldPrice;
    var pct  = oldPrice > 0 ? ((diff / oldPrice) * 100).toFixed(1) : null;
    var dir  = diff >= 0 ? 'increase' : 'decrease';
    var sign = diff >= 0 ? '+' : '';

    previewText.innerHTML =
      'Price will change from <strong>ETB ' + oldPrice.toFixed(2) + '/' + unitAbbr + '</strong> to ' +
      '<strong>ETB ' + newPrice.toFixed(2) + '/' + unitAbbr + '</strong> ' +
      '(<span class="' + (diff >= 0 ? 'text-success' : 'text-danger') + '">' +
      sign + diff.toFixed(2) +
      (pct !== null ? ' / ' + sign + pct + '%' : '') +
      ' ' + dir + '</span>).';
    preview.classList.remove('d-none');
  });

  // ── Form submission ────────────────────────────────────
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearMessages();

    var newPrice = parseFloat(input.value);
    if (isNaN(newPrice)) {
      showFieldError('new_price', 'Please enter a valid price.');
      input.classList.add('is-invalid');
      input.focus();
      return;
    }

    setLoading(true);

    var fd = new FormData(form);

    fetch(APP_URL + '/gm/products/' + productId + '/pricing', {
      method:      'POST',
      credentials: 'same-origin',
      headers:     {'X-Requested-With': 'XMLHttpRequest'},
      body:        fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      setLoading(false);
      if (resp.success) {
        // Update displayed current price
        oldPrice = parseFloat(resp.data.new_price);
        var formatted = 'ETB ' + oldPrice.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var cpDisplay = document.getElementById('currentPriceDisplay');
        var cpLarge   = document.getElementById('currentPriceLarge');
        if (cpDisplay) {
            cpDisplay.className = 'fw-bold text-success fs-5';
            cpDisplay.textContent = formatted;
        }
        if (cpLarge) {
            cpLarge.className = 'fw-bold fs-4 text-success';
            cpLarge.textContent = formatted;
        }

        successMsg.textContent = resp.message + ' The POS terminal will use this price immediately.';
        successMsg.classList.remove('d-none');
        preview.classList.add('d-none');
        input.value = '';

        // Reload price history table after short delay
        setTimeout(function () { location.reload(); }, 1500);
      } else {
        if (resp.errors && resp.errors.new_price) {
          showFieldError('new_price', resp.errors.new_price);
          input.classList.add('is-invalid');
        }
        errorMsg.textContent = resp.message || 'Price update failed. Please try again.';
        errorMsg.classList.remove('d-none');
      }
    })
    .catch(function () {
      setLoading(false);
      errorMsg.textContent = 'Network error. Please check your connection and try again.';
      errorMsg.classList.remove('d-none');
    });
  });

  function setLoading(loading) {
    submitBtn.disabled = loading;
    spinner.classList.toggle('d-none', !loading);
    submitIcon.classList.toggle('d-none', loading);
  }

  function clearMessages() {
    successMsg.classList.add('d-none');
    errorMsg.classList.add('d-none');
    input.classList.remove('is-invalid');
    errNewPrice.textContent = '';
  }

  function showFieldError(field, message) {
    var el = document.getElementById('err_' + field);
    if (el) { el.textContent = message; el.style.display = 'block'; }
  }
})();
</script>
<?php $view->endSection(); ?>
