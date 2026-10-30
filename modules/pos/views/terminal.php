<?php
/**
 * POS Terminal — Phase 12 + Payment Methods
 * @var array  $shop         @var int    $shopId
 * @var array|null $activeShift  @var array  $categories
 * @var array  $lowStock     @var array  $dayTotals
 * @var string $csrfToken    @var string $userRole
 * @var array  $paymentMethods  Active payment methods from system config
 *                              Shape: { cash: [...], mobile_banking: [...] }
 */
declare(strict_types=1);
$shop           ??= [];
$categories     ??= [];
$lowStock       ??= [];
$dayTotals      ??= [];
$activeShift    ??= null;
$paymentMethods ??= ['cash' => [], 'mobile_banking' => []];

// Determine which payment method types are active
$hasCash         = !empty($paymentMethods['cash']);
$hasMobileBanking= !empty($paymentMethods['mobile_banking']);
// Safety fallback: if NOTHING is configured at all (table missing / pre-migration),
// allow cash through so POS is not completely blocked.
// Do NOT inject cash if admin has explicitly deactivated it.
$fallbackCashOnly = false;
if (!$hasCash && !$hasMobileBanking) {
    $fallbackCashOnly = true;
    $hasCash = true;
    $paymentMethods['cash'] = [['id' => 0, 'code' => 'cash', 'name' => 'Cash', 'provider_code' => null]];
}
?>

<?php $view->section('styles'); ?>
<style>
/* ── POS Layout ─────────────────────────────── */
.pos-wrap{display:flex;gap:0;height:calc(100vh - var(--aam-topbar-h,56px) - 88px);min-height:500px;}
.pos-products{flex:1 1 0;min-width:0;overflow-y:auto;padding:12px 10px 12px 0;}
.pos-cart{width:340px;flex-shrink:0;display:flex;flex-direction:column;
  background:var(--theme-card,#fff);
  border:1px solid var(--theme-border,#dee2e6);border-radius:8px;overflow:hidden;}
/* Product cards */
.prod-card{cursor:pointer;border:1.5px solid var(--theme-card-border,#e2e8f0);border-radius:8px;padding:10px;
  background:var(--theme-card,#fff);color:var(--theme-text,#212529);
  transition:border-color .15s,box-shadow .15s;user-select:none;}
.prod-card:hover{border-color:#0d6efd;box-shadow:0 2px 8px rgba(13,110,253,.15);}
.prod-card.out-stock{opacity:.5;cursor:not-allowed;pointer-events:none;}
.no-price-card:hover{border-color:#ffc107!important;box-shadow:0 2px 6px rgba(255,193,7,.2)!important;}
.prod-card .prod-price{font-size:.85rem;font-weight:700;color:#0d6efd;}
.prod-card .prod-qty{font-size:.72rem;color:var(--theme-text-muted,#6c757d);}
.prod-card .prod-name{font-size:.82rem;font-weight:600;line-height:1.25;margin-bottom:2px;}
/* Cart */
.cart-items{flex:1 1 0;overflow-y:auto;padding:8px;}
.cart-row{display:flex;align-items:center;gap:6px;padding:6px 0;
  border-bottom:1px solid var(--theme-border-light,#f0f0f0);}
.cart-row:last-child{border-bottom:none;}
.cart-item-name{flex:1 1 0;font-size:.8rem;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cart-qty-ctrl{display:flex;align-items:center;gap:3px;}
.cart-qty-ctrl button{width:22px;height:22px;border:1px solid var(--theme-border,#dee2e6);border-radius:4px;
  background:var(--theme-card-header,#f8f9fa);color:var(--theme-text,#212529);
  font-size:.75rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;}
.cart-qty-ctrl button:hover{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.cart-qty-input{width:42px;text-align:center;border:1px solid var(--theme-input-border,#dee2e6);
  border-radius:4px;font-size:.8rem;padding:1px 3px;
  background:var(--theme-input-bg,#fff);color:var(--theme-input-text,#212529);}
.cart-subtotal{font-size:.78rem;font-weight:600;white-space:nowrap;min-width:56px;text-align:right;}
.cart-remove{width:20px;height:20px;border:none;background:transparent;color:#dc3545;
  cursor:pointer;padding:0;border-radius:3px;font-size:.7rem;}
.cart-remove:hover{background:#dc3545;color:#fff;}
/* Cart footer */
.cart-footer{border-top:1px solid var(--theme-border,#dee2e6);padding:10px;}
.total-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:4px;}
.total-row.grand{font-weight:700;font-size:1.05rem;border-top:1px solid var(--theme-border,#dee2e6);padding-top:6px;margin-top:4px;}
/* Category pills */
.cat-pill{cursor:pointer;border:1.5px solid var(--theme-border,#dee2e6);border-radius:20px;padding:4px 14px;
  font-size:.78rem;background:var(--theme-card,#fff);color:var(--theme-text,#212529);
  transition:all .12s;white-space:nowrap;}
.cat-pill:hover,.cat-pill.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}
/* Search box */
.pos-search{position:relative;}
.pos-search input{border-radius:8px;padding-right:2.4rem;}
.pos-search .clear-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);
  background:none;border:none;color:var(--theme-text-muted,#6c757d);cursor:pointer;font-size:.85rem;padding:0;}
/* Shift badge */
.shift-indicator{display:inline-flex;align-items:center;gap:6px;font-size:.75rem;padding:4px 10px;
  border-radius:20px;font-weight:600;}
</style>
<?php $view->endSection(); ?>

<?php
// ── Day summary bar ──────────────────────────────────────
$txns  = (int)($dayTotals['total_transactions'] ?? 0);
$sales = (float)($dayTotals['total_sales'] ?? 0);
$items = (int)($dayTotals['total_items_sold'] ?? 0);
?>

<!-- ── Top bar ───────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <h6 class="mb-0 fw-bold"><i class="bi bi-cart3 me-1 text-primary"></i><?= e($shop['name'] ?? 'Shop') ?></h6>
    <?php if ($activeShift): ?>
    <span class="shift-indicator bg-success bg-opacity-10 text-success border border-success border-opacity-25">
      <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
      Shift Open · <?= date('H:i', strtotime($activeShift['opened_at'])) ?>
    </span>
    <?php else: ?>
    <span class="shift-indicator bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
      <i class="bi bi-pause-circle me-1"></i>No Open Shift
      <a href="<?= url('pos/shift') ?>" class="ms-1 text-warning text-decoration-none small">(Manage)</a>
    </span>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <span class="badge bg-primary bg-opacity-10 text-primary small">Today: <?= $txns ?> sales · ETB <?= number_format($sales, 2) ?></span>
    <a href="<?= url('pos/history') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>History</a>
    <a href="<?= url('pos/shift') ?>"  class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge me-1"></i>Shift</a>
  </div>
</div>

<?php if (!empty($lowStock)): ?>
<div class="alert alert-warning alert-dismissible py-2 mb-2 small" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-1"></i>
  <strong>Low stock:</strong>
  <?= implode(', ', array_map(fn($p) => e($p['product_name']) . ' (' . number_format((float)$p['available_quantity'], 1) . ')', array_slice($lowStock, 0, 4))) ?>
  <?php if (count($lowStock) > 4): ?> and <?= count($lowStock)-4 ?> more.<?php endif; ?>
  <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ── Main POS layout ───────────────────────────────────── -->
<div class="pos-wrap">

  <!-- ════════ LEFT: Products ════════ -->
  <div class="pos-products">

    <!-- Search + Category Filter -->
    <div class="d-flex gap-2 mb-2 align-items-center flex-wrap">
      <div class="pos-search flex-grow-1" style="max-width:340px;">
        <input type="text" id="prodSearch" class="form-control form-control-sm"
               placeholder="Search product name, SKU, barcode…" autocomplete="off">
        <button class="clear-btn" id="clearSearch" title="Clear"><i class="bi bi-x"></i></button>
      </div>
      <div class="d-flex gap-1 flex-wrap" id="catFilter">
        <button class="cat-pill active" data-cat="0">All</button>
        <?php foreach ($categories as $cat): ?>
        <button class="cat-pill" data-cat="<?= $cat['id'] ?>"><?= e($cat['name']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Product Grid -->
    <div id="prodGrid" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-5 g-2">
      <div class="col-12 text-center py-4 text-muted" id="prodLoading">
        <i class="bi bi-arrow-clockwise spin me-1"></i>Loading products…
      </div>
    </div>
    <div id="prodEmpty" class="text-center py-5 text-muted d-none">
      <i class="bi bi-search display-6 d-block mb-2 opacity-25"></i>
      No products found for this search.
    </div>
  </div>

  <!-- ════════ RIGHT: Cart ════════ -->
  <div class="pos-cart ms-2">

    <!-- Cart header -->
    <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center bg-light">
      <span class="fw-semibold small"><i class="bi bi-cart-fill me-1 text-primary"></i>Cart</span>
      <button class="btn btn-outline-danger btn-sm py-0 px-2" id="clearCart" title="Clear cart">
        <i class="bi bi-trash3 me-1"></i><span class="small">Clear</span>
      </button>
    </div>

    <!-- Cart items -->
    <div class="cart-items" id="cartItems">
      <div class="text-center text-muted py-4 small" id="cartEmpty">
        <i class="bi bi-cart display-6 d-block mb-2 opacity-25"></i>
        Cart is empty.<br>Click a product to add.
      </div>
    </div>

    <!-- Cart totals -->
    <div class="cart-footer">
      <div class="total-row"><span class="text-muted">Subtotal</span><span id="cartSubtotal">ETB 0.00</span></div>
      <div class="total-row grand"><span>TOTAL</span><span id="cartTotal" class="text-primary">ETB 0.00</span></div>

      <!-- ── Payment Method ─────────────────────────────── -->
      <div class="mt-2 mb-2">
        <label class="form-label small fw-semibold mb-1">Payment Method</label>
        <div class="d-flex gap-3 flex-wrap" id="paymentMethodRadios">

          <?php if ($hasCash): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="paymentMethod"
                   id="pmCash" value="cash" checked>
            <label class="form-check-label small" for="pmCash">
              <i class="bi bi-cash-coin me-1 text-success"></i>Cash
            </label>
          </div>
          <?php endif; ?>

          <?php if ($hasMobileBanking): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="paymentMethod"
                   id="pmMobile" value="mobile">
            <label class="form-check-label small" for="pmMobile">
              <i class="bi bi-phone-fill me-1 text-primary"></i>Mobile Banking
            </label>
          </div>
          <?php endif; ?>

          <?php if (!$hasCash && !$hasMobileBanking): ?>
          <span class="small text-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>No active payment methods. Contact System Administrator.
          </span>
          <?php endif; ?>

        </div>

        <!-- Mobile provider selector (hidden until Mobile Banking selected) -->
        <?php if ($hasMobileBanking): ?>
        <div id="mobileProviderWrap" class="mt-2 d-none">
          <label class="form-label small fw-semibold mb-1">Provider</label>
          <div class="d-flex gap-3 flex-wrap" id="mobileProviderRadios">
            <?php foreach ($paymentMethods['mobile_banking'] as $idx => $mp): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="mobileProvider"
                     id="prov_<?= e($mp['code']) ?>"
                     value="<?= e($mp['provider_code'] ?? $mp['code']) ?>"
                     <?= $idx === 0 ? 'checked' : '' ?>>
              <label class="form-check-label small" for="prov_<?= e($mp['code']) ?>">
                <?php if ($mp['code'] === 'cbe_birr'): ?>
                  <i class="bi bi-bank me-1 text-primary"></i>
                <?php else: ?>
                  <i class="bi bi-telephone-fill me-1 text-warning"></i>
                <?php endif; ?>
                <?= e($mp['name']) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div id="mobileProviderWrap" class="mt-2 d-none">
          <!-- No active mobile providers — placeholder div kept for JS reference -->
        </div>
        <?php endif; ?>
      </div>

      <!-- Cash received input (shown only for Cash) -->
      <div class="mt-2 mb-2" id="cashInputWrap">
        <label class="form-label small fw-semibold mb-1">Cash Received (ETB)</label>
        <input type="number" id="cashReceived" class="form-control form-control-sm" min="0" step="0.01"
               placeholder="0.00" style="font-size:1.05rem;font-weight:700;">
        <div class="d-flex justify-content-between mt-1">
          <small class="text-muted">Change:</small>
          <small id="changeDisplay" class="fw-bold text-success">—</small>
        </div>
      </div>

      <!-- Complete Sale -->
      <button class="btn btn-success w-100 fw-bold" id="completeSaleBtn" disabled
              <?php if (!$activeShift): ?>title="Open a shift first to process sales"<?php endif; ?>>
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php if (!$activeShift): ?>Open Shift to Sell<?php else: ?>Complete Sale<?php endif; ?>
      </button>
      <div id="saleErrorMsg" class="alert alert-danger py-2 small mt-2 d-none" role="alert"></div>
    </div>
  </div>
</div>

<!-- ── Payment Confirm Modal (Cash) ─────────────────────────── -->
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold" id="payModalLabel"><i class="bi bi-cash-coin me-2 text-success"></i>Confirm Payment</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm mb-2">
            <tbody id="modalItemsBody"></tbody>
          </table>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between fw-bold fs-5">
          <span>TOTAL</span><span id="modalTotal" class="text-primary"></span>
        </div>
        <div class="d-flex justify-content-between mt-1">
          <span class="text-muted small">Cash Received</span><span id="modalCash" class="small fw-semibold"></span>
        </div>
        <div class="d-flex justify-content-between mt-1">
          <span class="text-muted small">Change</span><span id="modalChange" class="small fw-bold text-success"></span>
        </div>
        <textarea id="saleNotes" class="form-control form-control-sm mt-2" rows="2" placeholder="Notes (optional)"></textarea>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-success fw-bold w-100" id="confirmSaleBtn">
          <i class="bi bi-check2-circle me-2"></i>Confirm & Complete Sale
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Mobile Payment QR Modal ───────────────────────────────── -->
<div class="modal fade" id="mobilePayModal" tabindex="-1" aria-labelledby="mobilePayModalLabel"
     aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
    <div class="modal-content">
      <div class="modal-header border-0 pb-1">
        <h6 class="modal-title fw-bold" id="mobilePayModalLabel">
          <i class="bi bi-phone-fill me-2 text-primary"></i>
          <span id="mpModalProviderName">Mobile Payment</span>
        </h6>
        <!-- Close only allowed if PENDING — JS controls this -->
        <button type="button" class="btn-close" id="mpModalCloseBtn" aria-label="Close"></button>
      </div>
      <div class="modal-body px-3 py-2">

        <!-- Sale summary -->
        <div class="mb-2">
          <div class="table-responsive">
            <table class="table table-sm table-borderless mb-1" style="font-size:.8rem;">
              <tbody id="mpModalItemsBody"></tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between fw-bold" style="font-size:1.05rem;border-top:1px solid #dee2e6;padding-top:6px;">
            <span>TOTAL</span>
            <span id="mpModalTotal" class="text-primary"></span>
          </div>
        </div>

        <!-- Payment info box -->
        <div class="rounded border p-2 mb-3" style="background:var(--theme-card-header,#f8f9fa);font-size:.8rem;">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Provider</span>
            <strong id="mpInfoProvider"></strong>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Reference</span>
            <code id="mpInfoReference" style="font-size:.78rem;"></code>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Status</span>
            <span id="mpInfoStatus"></span>
          </div>
        </div>

        <!-- QR / payment display area -->
        <div id="mpQrWrap" class="text-center mb-3">
          <!-- QR canvas rendered by qrcode.js -->
          <div id="mpQrCanvas" class="d-inline-block p-2 bg-white rounded border" style="min-width:180px;min-height:180px;"></div>
          <div id="mpDisplayHint" class="small text-muted mt-2"></div>
        </div>

        <!-- Development notice (shown when provider is not live) -->
        <div id="mpDevNotice" class="alert alert-warning py-2 small d-none" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          <strong>Development Mode:</strong> Payment provider API is not yet connected.
          Payment will remain <strong>PENDING</strong> until official API credentials are configured.
          <br><em>Do not use manual verification as a production payment confirmation.</em>
        </div>

        <!-- Status message / spinner -->
        <div id="mpStatusMsg" class="text-center small text-muted d-none">
          <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
          Checking payment status…
        </div>

        <!-- Error message -->
        <div id="mpErrorMsg" class="alert alert-danger py-2 small d-none" role="alert"></div>

        <!-- Expiry countdown -->
        <div class="text-center small text-muted mt-1" id="mpExpiryWrap" style="display:none!important;">
          Expires in: <strong id="mpExpiryCountdown"></strong>
        </div>
      </div>

      <div class="modal-footer border-0 pt-0 flex-column gap-2">
        <!-- Check Payment button (manual verify) -->
        <button class="btn btn-primary w-100 fw-semibold" id="mpCheckBtn">
          <i class="bi bi-arrow-clockwise me-2"></i>Check Payment Status
        </button>

        <!-- DEV ONLY manual verify — clearly labelled, hidden in production -->
        <?php if (\AAM\Config\Application::get('env', 'production') !== 'production'): ?>
        <div class="w-100" id="mpDevVerifyWrap">
          <div class="text-center small text-warning fw-semibold mb-1">
            ⚠ DEVELOPMENT ONLY — Not a real payment confirmation
          </div>
          <button class="btn btn-outline-warning w-100 btn-sm" id="mpDevMarkPaidBtn">
            <i class="bi bi-bug me-1"></i>[DEV] Simulate Payment Received
          </button>
        </div>
        <?php endif; ?>

        <!-- Cancel button -->
        <button class="btn btn-outline-secondary w-100 btn-sm" id="mpCancelBtn">
          <i class="bi bi-x-circle me-1"></i>Cancel Payment
        </button>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/qrcode.min.js') ?>"></script>
<script>
// ── POS Terminal initialisation ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function posTerminalInit() {
(function() {
'use strict';
const APP_URL    = '<?= rtrim(e(\AAM\Config\Application::get('url','')), '/') ?>';
const CSRF_TOKEN = '<?= e($csrfToken) ?>';
const SHOP_ID    = <?= (int)$shopId ?>;

// ── State ────────────────────────────────────────────────
let products     = [];
let cart         = {};
let activeCatId  = 0;
let searchTimer  = null;

// Mobile payment state
let mpPaymentId    = null;
let mpPollInterval = null;
let mpExpiresAt    = null;
let mpCountdownInt = null;

// ── DOM refs ─────────────────────────────────────────────
const $grid         = document.getElementById('prodGrid');
const $loading      = document.getElementById('prodLoading');
const $empty        = document.getElementById('prodEmpty');
const $cartItems    = document.getElementById('cartItems');
const $cartEmpty    = document.getElementById('cartEmpty');
const $cartSubtotal = document.getElementById('cartSubtotal');
const $cartTotal    = document.getElementById('cartTotal');
const $cashInput    = document.getElementById('cashReceived');
const $changeDisp   = document.getElementById('changeDisplay');
const $saleBtn      = document.getElementById('completeSaleBtn');
const $saleErr      = document.getElementById('saleErrorMsg');
const $clearCart    = document.getElementById('clearCart');
const $search       = document.getElementById('prodSearch');
const $cashWrap     = document.getElementById('cashInputWrap');
const $mobWrap      = document.getElementById('mobileProviderWrap');

// ── Format currency ────────────────────────────────────────
function fmt(n) { return 'ETB ' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

// ── Load products ──────────────────────────────────────────
function loadProducts() {
    $loading.classList.remove('d-none');
    $grid.querySelectorAll('.prod-item-col').forEach(e => e.remove());
    $empty.classList.add('d-none');

    const q   = encodeURIComponent($search.value.trim());
    const cat = activeCatId;
    const url = APP_URL + '/api/pos/products?q=' + q + '&category_id=' + cat + '&shop_id=' + SHOP_ID;

    fetch(url, {method:'GET', credentials:'same-origin',
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(resp => {
            $loading.classList.add('d-none');
            if (!resp || resp.success === false) {
                $empty.textContent = (resp && resp.message) ? resp.message : 'No products available.';
                $empty.classList.remove('d-none'); return;
            }
            products = resp.data ?? (Array.isArray(resp) ? resp : []);
            if (products.length === 0) {
                $empty.textContent = 'No products available in this shop.';
                $empty.classList.remove('d-none');
            } else { renderProducts(); }
        })
        .catch(err => {
            $loading.classList.add('d-none');
            $empty.textContent = 'Could not load products (' + err.message + '). Please refresh.';
            $empty.classList.remove('d-none');
        });
}

// ── Render product grid ───────────────────────────────────
function renderProducts() {
    $grid.querySelectorAll('.prod-item-col').forEach(e => e.remove());
    if (!products.length) { $empty.classList.remove('d-none'); return; }
    $empty.classList.add('d-none');
    products.forEach(p => {
        const inCart  = cart[p.product_id];
        const priceOk = (p.price_available === true || p.price_available === 1);
        const col     = document.createElement('div');
        col.className = 'col prod-item-col';
        if (!priceOk) {
            col.innerHTML = `<div class="prod-card h-100 no-price-card pos-no-price" data-pid="${p.product_id}"
                    title="No approved selling price." style="cursor:not-allowed;opacity:.65;border-color:#ffc107;">
                <div class="prod-name">${escHtml(p.product_name)}</div>
                <div class="text-muted" style="font-size:.68rem;">${escHtml(p.product_sku??'')}</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div>
                        <div class="text-warning fw-semibold" style="font-size:.72rem;"><i class="bi bi-tag-fill me-1"></i>Price Not Set</div>
                        <div class="prod-qty">Stock: ${parseFloat(p.available_quantity).toFixed(1)} ${escHtml(p.unit_abbr??'')}</div>
                    </div>
                    <i class="bi bi-lock-fill text-warning" style="font-size:1rem;"></i>
                </div></div>`;
        } else {
            col.innerHTML = `<div class="prod-card h-100" data-pid="${p.product_id}">
                <div class="prod-name">${escHtml(p.product_name)}</div>
                <div class="text-muted" style="font-size:.68rem;">${escHtml(p.product_sku??'')}</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div>
                        <div class="prod-price">${fmt(p.current_price)}<span class="text-muted fw-normal" style="font-size:.7rem;"> / ${escHtml(p.unit_abbr??'unit')}</span></div>
                        <div class="prod-qty">Stock: ${parseFloat(p.available_quantity).toFixed(1)} ${escHtml(p.unit_abbr??'')}</div>
                    </div>
                    ${inCart ? `<span class="badge bg-primary" style="font-size:.7rem;">${inCart.qty}</span>` : '<i class="bi bi-plus-circle text-primary" style="font-size:1.1rem;"></i>'}
                </div></div>`;
            col.querySelector('.prod-card').addEventListener('click', () => addToCart(p));
        }
        $grid.appendChild(col);
    });
}

function escHtml(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(s ?? '')));
    return d.innerHTML;
}

// ── Cart operations ───────────────────────────────────────
function addToCart(product) {
    const pid = product.product_id;
    const priceOk = (product.price_available === true || product.price_available === 1);
    if (!priceOk) { showSaleError('Sale blocked: "' + product.product_name + '" has no approved selling price.'); return; }
    if (cart[pid]) {
        const newQty = cart[pid].qty + 1;
        if (newQty > parseFloat(product.available_quantity)) { showSaleError('Max available stock reached for ' + product.product_name); return; }
        cart[pid].qty = newQty;
    } else {
        cart[pid] = {item: product, qty: 1};
    }
    renderCart(); renderProducts();
}

function removeFromCart(pid) { delete cart[pid]; renderCart(); renderProducts(); }

function updateQty(pid, delta) {
    if (!cart[pid]) return;
    const newQty = cart[pid].qty + delta;
    if (newQty <= 0) { removeFromCart(pid); return; }
    const max = parseFloat(cart[pid].item.available_quantity);
    if (newQty > max) { showSaleError('Exceeds available stock (' + max.toFixed(1) + ')'); return; }
    cart[pid].qty = newQty; renderCart();
}

function setQty(pid, val) {
    if (!cart[pid]) return;
    const qty = parseFloat(val);
    if (isNaN(qty) || qty <= 0) { removeFromCart(pid); return; }
    const max = parseFloat(cart[pid].item.available_quantity);
    if (qty > max) { showSaleError('Exceeds available stock (' + max.toFixed(1) + ')'); return; }
    cart[pid].qty = qty; renderCart();
}

function clearSaleError() { $saleErr.classList.add('d-none'); }
function showSaleError(msg) { $saleErr.textContent = msg; $saleErr.classList.remove('d-none'); }

// ── Render cart ───────────────────────────────────────────
function renderCart() {
    clearSaleError();
    const keys = Object.keys(cart);
    $cartEmpty.style.display = keys.length ? 'none' : 'block';
    $cartItems.querySelectorAll('.cart-row').forEach(e => e.remove());
    let subtotal = 0;
    keys.forEach(pid => {
        const {item, qty} = cart[pid];
        const sub = qty * parseFloat(item.current_price);
        subtotal += sub;
        const row = document.createElement('div');
        row.className = 'cart-row';
        row.innerHTML = `
            <div class="cart-item-name" title="${escHtml(item.product_name)}">${escHtml(item.product_name)}</div>
            <div class="cart-qty-ctrl">
                <button class="dec" data-pid="${pid}">−</button>
                <input type="number" class="cart-qty-input" value="${qty}" min="0.001" max="${item.available_quantity}" step="0.001" data-pid="${pid}">
                <button class="inc" data-pid="${pid}">+</button>
            </div>
            <div class="cart-subtotal">${fmt(sub)}</div>
            <button class="cart-remove" data-pid="${pid}" title="Remove"><i class="bi bi-x"></i></button>`;
        row.querySelector('.dec').addEventListener('click', () => updateQty(pid, -1));
        row.querySelector('.inc').addEventListener('click', () => updateQty(pid, +1));
        row.querySelector('.cart-remove').addEventListener('click', () => removeFromCart(pid));
        row.querySelector('.cart-qty-input').addEventListener('change', e => setQty(pid, e.target.value));
        $cartItems.appendChild(row);
    });
    $cartSubtotal.textContent = fmt(subtotal);
    $cartTotal.textContent    = fmt(subtotal);
    updateChange(subtotal);
    <?php if ($activeShift): ?>
    $saleBtn.disabled = keys.length === 0;
    <?php else: ?>
    $saleBtn.disabled = true;
    <?php endif; ?>
}

// ── Payment method toggle ─────────────────────────────────
function getSelectedPaymentMethod() {
    const r = document.querySelector('input[name="paymentMethod"]:checked');
    if (r) return r.value;
    // No radio checked — infer from what's available
    if (document.getElementById('pmMobile')) return 'mobile';
    return 'cash';
}
function getSelectedProvider() {
    const r = document.querySelector('input[name="mobileProvider"]:checked');
    // Fallback to first available mobile provider code from PHP-rendered radios
    if (!r) {
        const first = document.querySelector('input[name="mobileProvider"]');
        return first ? first.value : 'cbe_birr';
    }
    return r.value;
}

document.querySelectorAll('input[name="paymentMethod"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const isMobile = getSelectedPaymentMethod() === 'mobile';
        if ($cashWrap) $cashWrap.classList.toggle('d-none', isMobile);
        if ($mobWrap)  $mobWrap.classList.toggle('d-none', !isMobile);
        clearSaleError();
    });
});

// If Mobile Banking radio does not exist (deactivated), hide the wrapper entirely
(function() {
    const mobRadio = document.getElementById('pmMobile');
    if (!mobRadio && $mobWrap) {
        $mobWrap.classList.add('d-none');
    }

    // Auto-check the first available payment method radio on load
    const firstRadio = document.querySelector('input[name="paymentMethod"]');
    if (firstRadio && !document.querySelector('input[name="paymentMethod"]:checked')) {
        firstRadio.checked = true;
        // Trigger the change event to update UI state
        firstRadio.dispatchEvent(new Event('change'));
    }

    // If only mobile banking available, show provider selector immediately
    const isMobileOnly = document.getElementById('pmMobile') &&
                         !document.getElementById('pmCash');
    if (isMobileOnly) {
        if ($cashWrap) $cashWrap.classList.add('d-none');
        if ($mobWrap)  $mobWrap.classList.remove('d-none');
    }

    // If only cash available, hide mobile wrapper
    const isCashOnly = document.getElementById('pmCash') &&
                       !document.getElementById('pmMobile');
    if (isCashOnly) {
        if ($mobWrap) $mobWrap.classList.add('d-none');
    }
})();

// ── Cash / change ─────────────────────────────────────────
function getTotal() {
    let t = 0;
    Object.values(cart).forEach(({item,qty}) => t += qty * parseFloat(item.current_price));
    return t;
}

function updateChange(total) {
    const cash   = parseFloat($cashInput.value) || 0;
    const total2 = total ?? getTotal();
    if (cash >= total2 && total2 > 0) {
        $changeDisp.textContent = fmt(cash - total2);
        $changeDisp.className = 'fw-bold text-success';
    } else if (cash > 0 && cash < total2) {
        $changeDisp.textContent = '− ' + fmt(total2 - cash) + ' short';
        $changeDisp.className = 'fw-bold text-danger';
    } else {
        $changeDisp.textContent = '—';
        $changeDisp.className = 'fw-bold text-muted';
    }
}
$cashInput.addEventListener('input', () => updateChange(null));

// ── Quick-cash buttons ────────────────────────────────────
const quickAmounts = [50,100,200,500];
const $footer = document.querySelector('.cart-footer');
const $qRow = document.createElement('div');
$qRow.className = 'd-flex gap-1 mb-2 flex-wrap';
quickAmounts.forEach(a => {
    const b = document.createElement('button');
    b.className = 'btn btn-outline-secondary btn-sm py-0 px-2';
    b.style.fontSize = '.72rem';
    b.textContent = a;
    b.addEventListener('click', () => { $cashInput.value = a; updateChange(null); });
    $qRow.appendChild(b);
});
const $cashInputParent = $cashInput.closest('.mt-2') || $cashInput.parentElement;
if ($cashInputParent && $cashInputParent.parentElement === $footer) {
    $footer.insertBefore($qRow, $cashInputParent);
} else {
    const $saleBtn2 = $footer.querySelector('#completeSaleBtn');
    if ($saleBtn2) $footer.insertBefore($qRow, $saleBtn2);
    else $footer.appendChild($qRow);
}

// ── Complete sale button ──────────────────────────────────
$saleBtn.addEventListener('click', () => {
    clearSaleError();
    if (Object.keys(cart).length === 0) { showSaleError('Cart is empty.'); return; }
    <?php if (!$activeShift): ?>
    showSaleError('No active shift. Please open a shift before processing sales.');
    return;
    <?php endif; ?>

    const method = getSelectedPaymentMethod();

    if (method === 'mobile') {
        // ── Mobile payment flow ──────────────────────────
        // Guard: confirm a provider is actually selected
        const provider = getSelectedProvider();
        if (!provider) {
            showSaleError('Please select a mobile banking provider (CBE Birr or Telebirr).');
            return;
        }
        openMobilePayFlow();
    } else {
        // ── Cash flow ────────────────────────────────────
        // Guard: Cash radio must exist (i.e. Cash is ACTIVE in system config)
        if (!document.getElementById('pmCash')) {
            showSaleError('Cash payment is currently inactive. Please select Mobile Banking.');
            return;
        }
        const total = getTotal();
        const cash  = parseFloat($cashInput.value) || 0;
        if (cash < total) { showSaleError('Cash received must be at least ETB ' + total.toFixed(2)); return; }
        openCashConfirmModal(total, cash);
    }
});

// ── Build modal item rows ─────────────────────────────────
function buildItemRows() {
    let rows = '';
    Object.values(cart).forEach(({item,qty}) => {
        const sub = qty * parseFloat(item.current_price);
        rows += `<tr>
            <td class="small">${escHtml(item.product_name)}</td>
            <td class="text-end small">${qty.toFixed(3)} ${escHtml(item.unit_abbr??'')} × ${fmt(item.current_price)}/${escHtml(item.unit_abbr??'unit')}</td>
            <td class="text-end small fw-semibold">${fmt(sub)}</td></tr>`;
    });
    return rows;
}

// ── Cash confirm modal ────────────────────────────────────
function openCashConfirmModal(total, cash) {
    document.getElementById('modalItemsBody').innerHTML = buildItemRows();
    document.getElementById('modalTotal').textContent  = fmt(total);
    document.getElementById('modalCash').textContent   = fmt(cash);
    document.getElementById('modalChange').textContent = fmt(cash - total);
    new bootstrap.Modal(document.getElementById('payModal')).show();
}

// ── Confirm cash sale ─────────────────────────────────────
document.getElementById('confirmSaleBtn').addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
    const cartItems = Object.values(cart).map(({item,qty}) => ({
        product_id: item.product_id, quantity: qty, unit_price: parseFloat(item.current_price),
    }));
    const body = new FormData();
    body.append('csrf_token',   CSRF_TOKEN);
    body.append('cart_items',   JSON.stringify(cartItems));
    body.append('cash_received',parseFloat($cashInput.value).toString());
    body.append('notes',        document.getElementById('saleNotes').value);

    fetch(APP_URL + '/pos/sale', {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
                cart = {}; renderCart(); $cashInput.value = '';
                window.location.href = APP_URL + '/pos/receipt/' + resp.data.sale_id;
            } else {
                bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
                showSaleError(resp.message ?? 'Sale failed. Please try again.');
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Confirm & Complete Sale';
            }
        })
        .catch(() => {
            bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
            showSaleError('Network error. Please check connection and try again.');
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Confirm & Complete Sale';
        });
});

// ═══════════════════════════════════════════════════════════
// MOBILE PAYMENT FLOW
// ═══════════════════════════════════════════════════════════

// ── Open mobile pay flow ──────────────────────────────────
function openMobilePayFlow() {
    const provider = getSelectedProvider();
    const total    = getTotal();

    // Reset modal state
    mpPaymentId    = null;
    mpExpiresAt    = null;
    clearMobilePolling();

    document.getElementById('mpModalProviderName').textContent = provider === 'cbe_birr' ? 'CBE Birr' : 'Telebirr';
    document.getElementById('mpModalItemsBody').innerHTML = buildItemRows();
    document.getElementById('mpModalTotal').textContent   = fmt(total);
    document.getElementById('mpInfoProvider').textContent = provider === 'cbe_birr' ? 'CBE Birr' : 'Telebirr';
    document.getElementById('mpInfoReference').textContent = '—';
    setMpStatus('pending');
    document.getElementById('mpQrCanvas').innerHTML = '<div class="text-muted small p-4">Generating payment…<br><span class="spinner-border spinner-border-sm mt-2"></span></div>';
    document.getElementById('mpDisplayHint').textContent = '';
    document.getElementById('mpDevNotice').classList.add('d-none');
    document.getElementById('mpErrorMsg').classList.add('d-none');
    document.getElementById('mpStatusMsg').classList.add('d-none');

    // Show modal (static backdrop — prevent accidental close)
    new bootstrap.Modal(document.getElementById('mobilePayModal')).show();

    // Initiate payment on server
    const cartItems = Object.values(cart).map(({item,qty}) => ({
        product_id: item.product_id, quantity: qty,
    }));
    const body = new FormData();
    body.append('csrf_token',  CSRF_TOKEN);
    body.append('cart_items',  JSON.stringify(cartItems));
    body.append('provider',    provider);
    body.append('notes',       '');

    fetch(APP_URL + '/pos/payment/mobile', {
        method: 'POST', body, credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp.success) {
            showMobileError(resp.message ?? 'Failed to initiate payment.');
            return;
        }
        const data = resp.data;
        mpPaymentId = data.payment_id;
        mpExpiresAt = new Date(data.expires_at.replace(' ', 'T'));

        document.getElementById('mpInfoReference').textContent = data.reference;
        document.getElementById('mpDisplayHint').textContent   = data.display_hint || '';

        // Show dev notice if provider not live
        if (!data.is_live) {
            document.getElementById('mpDevNotice').classList.remove('d-none');
        }

        // Render QR code
        renderMobileQR(data.qr_payload, data.provider_name);

        // Start expiry countdown
        startExpiryCountdown();

        // Start polling
        startMobilePolling();
    })
    .catch(err => {
        showMobileError('Network error initiating payment: ' + err.message);
    });
}

// ── Render QR ─────────────────────────────────────────────
function renderMobileQR(payload, providerName) {
    const $canvas = document.getElementById('mpQrCanvas');
    $canvas.innerHTML = '';

    if (typeof QRCode === 'undefined') {
        // Fallback: show the reference text
        $canvas.innerHTML = '<div class="p-3 text-muted small">QR library not loaded.<br><code>' + escHtml(payload.substring(0,60)) + '</code></div>';
        return;
    }

    try {
        new QRCode($canvas, {
            text:   payload,
            width:  180,
            height: 180,
            colorDark:  '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
        // Add provider label below QR
        const label = document.createElement('div');
        label.className = 'small fw-semibold mt-1 text-primary';
        label.textContent = providerName;
        $canvas.appendChild(label);
    } catch(e) {
        $canvas.innerHTML = '<div class="p-2 text-muted small">Could not render QR.<br><code>' + escHtml(payload.substring(0,80)) + '</code></div>';
    }
}

// ── Payment status badge ──────────────────────────────────
function setMpStatus(status) {
    const el = document.getElementById('mpInfoStatus');
    const map = {
        pending:   '<span class="badge bg-warning text-dark">PENDING</span>',
        paid:      '<span class="badge bg-success">PAID</span>',
        failed:    '<span class="badge bg-danger">FAILED</span>',
        cancelled: '<span class="badge bg-secondary">CANCELLED</span>',
        expired:   '<span class="badge bg-secondary">EXPIRED</span>',
    };
    el.innerHTML = map[status] || '<span class="badge bg-secondary">' + escHtml(status.toUpperCase()) + '</span>';
}

function showMobileError(msg) {
    const el = document.getElementById('mpErrorMsg');
    el.textContent = msg;
    el.classList.remove('d-none');
    document.getElementById('mpStatusMsg').classList.add('d-none');
}

// ── Polling ───────────────────────────────────────────────
function startMobilePolling() {
    clearMobilePolling();
    mpPollInterval = setInterval(pollPaymentStatus, 5000); // every 5 seconds
}

function clearMobilePolling() {
    if (mpPollInterval) { clearInterval(mpPollInterval); mpPollInterval = null; }
}

function pollPaymentStatus() {
    if (!mpPaymentId) return;
    fetch(APP_URL + '/api/pos/payment/status?payment_id=' + mpPaymentId, {
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp.success) return;
        handlePaymentStatusUpdate(resp.data);
    })
    .catch(() => {}); // Silent poll failures — don't show error on every tick
}

function handlePaymentStatusUpdate(data) {
    setMpStatus(data.status);

    if (data.status === 'paid' && data.sale_id) {
        clearMobilePolling();
        clearExpiryCountdown();
        // Payment confirmed — navigate to receipt
        onMobilePaymentSuccess(data.sale_id);
    } else if (['failed','cancelled','expired'].includes(data.status)) {
        clearMobilePolling();
        clearExpiryCountdown();
        onMobilePaymentFailed(data.status);
    }
    // pending: continue polling
}

// ── Manual check button ───────────────────────────────────
document.getElementById('mpCheckBtn').addEventListener('click', function() {
    if (!mpPaymentId) return;
    const $btn = this;
    $btn.disabled = true;
    $btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking…';
    document.getElementById('mpStatusMsg').classList.remove('d-none');
    document.getElementById('mpErrorMsg').classList.add('d-none');

    const body = new FormData();
    body.append('csrf_token',  CSRF_TOKEN);
    body.append('payment_id',  mpPaymentId);

    fetch(APP_URL + '/pos/payment/verify', {
        method: 'POST', body, credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(resp => {
        document.getElementById('mpStatusMsg').classList.add('d-none');
        $btn.disabled = false;
        $btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Check Payment Status';

        if (resp.success) {
            handlePaymentStatusUpdate(resp.data);
        } else {
            showMobileError(resp.message ?? 'Verification failed. Please retry.');
        }
    })
    .catch(() => {
        document.getElementById('mpStatusMsg').classList.add('d-none');
        $btn.disabled = false;
        $btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Check Payment Status';
        showMobileError('Network error. Please check connection and retry.');
    });
});

// ── Cancel button ─────────────────────────────────────────
document.getElementById('mpCancelBtn').addEventListener('click', function() {
    if (!confirm('Cancel this payment? The customer will need to initiate a new payment.')) return;
    clearMobilePolling();
    clearExpiryCountdown();

    if (!mpPaymentId) {
        bootstrap.Modal.getInstance(document.getElementById('mobilePayModal')).hide();
        return;
    }

    const body = new FormData();
    body.append('csrf_token',  CSRF_TOKEN);
    body.append('payment_id',  mpPaymentId);

    fetch(APP_URL + '/pos/payment/cancel', {
        method: 'POST', body, credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(resp => {
        setMpStatus('cancelled');
        bootstrap.Modal.getInstance(document.getElementById('mobilePayModal')).hide();
        showSaleError('Mobile payment cancelled. You can try again or use cash.');
    })
    .catch(() => {
        bootstrap.Modal.getInstance(document.getElementById('mobilePayModal')).hide();
    });
});

// ── Modal close button ────────────────────────────────────
document.getElementById('mpModalCloseBtn').addEventListener('click', function() {
    // Only allow close when payment is not in a terminal state that would auto-redirect
    clearMobilePolling();
    clearExpiryCountdown();
    if (mpPaymentId) {
        // Ask to cancel
        if (confirm('Close and cancel this payment?')) {
            const body = new FormData();
            body.append('csrf_token', CSRF_TOKEN);
            body.append('payment_id', mpPaymentId);
            fetch(APP_URL + '/pos/payment/cancel', {
                method:'POST', body, credentials:'same-origin',
                headers:{'X-Requested-With':'XMLHttpRequest'}
            }).catch(()=>{});
        } else {
            // Restart polling
            startMobilePolling();
            startExpiryCountdown();
            return;
        }
    }
    bootstrap.Modal.getInstance(document.getElementById('mobilePayModal')).hide();
});

// ── DEV: Simulate payment (development only) ──────────────
const $devBtn = document.getElementById('mpDevMarkPaidBtn');
if ($devBtn) {
    $devBtn.addEventListener('click', function() {
        if (!mpPaymentId) return;
        if (!confirm('[DEV] Simulate a payment received event?\n\nThis is ONLY for development testing.\nDo NOT use in production.')) return;

        const body = new FormData();
        body.append('csrf_token',  CSRF_TOKEN);
        body.append('payment_id',  mpPaymentId);

        // Call verify — in dev mode the backend will check the provider
        // which returns PENDING (no API configured). The dev simulation
        // should be done at the service layer if needed.
        // For now this just triggers a regular verify call so devs can
        // test the flow up to the PENDING verification step.
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Simulating…';

        fetch(APP_URL + '/pos/payment/verify', {
            method:'POST', body, credentials:'same-origin',
            headers:{'X-Requested-With':'XMLHttpRequest'}
        })
        .then(r => r.json())
        .then(resp => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-bug me-1"></i>[DEV] Simulate Payment Received';
            if (resp.success) {
                handlePaymentStatusUpdate(resp.data);
                if (resp.data.status === 'pending') {
                    showMobileError('[DEV] Provider not configured — payment remains PENDING. Connect real API credentials to verify payments.');
                }
            } else {
                showMobileError(resp.message ?? 'Simulation failed.');
            }
        })
        .catch(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-bug me-1"></i>[DEV] Simulate Payment Received';
        });
    });
}

// ── Payment success ───────────────────────────────────────
function onMobilePaymentSuccess(saleId) {
    clearMobilePolling();
    clearExpiryCountdown();
    setMpStatus('paid');
    document.getElementById('mpCheckBtn').disabled = true;
    document.getElementById('mpCancelBtn').disabled = true;
    document.getElementById('mpQrCanvas').innerHTML =
        '<div class="p-3 text-success"><i class="bi bi-check-circle-fill" style="font-size:3rem;"></i><div class="mt-2 fw-bold">Payment Confirmed!</div></div>';
    document.getElementById('mpDisplayHint').textContent = 'Redirecting to receipt…';

    setTimeout(() => {
        cart = {}; renderCart();
        const modal = bootstrap.Modal.getInstance(document.getElementById('mobilePayModal'));
        if (modal) modal.hide();
        window.location.href = APP_URL + '/pos/receipt/' + saleId;
    }, 1500);
}

// ── Payment failed / cancelled / expired ──────────────────
function onMobilePaymentFailed(status) {
    clearMobilePolling();
    clearExpiryCountdown();
    setMpStatus(status);
    document.getElementById('mpQrCanvas').innerHTML =
        '<div class="p-3 text-danger"><i class="bi bi-x-circle-fill" style="font-size:3rem;"></i>' +
        '<div class="mt-2 fw-bold">' + status.charAt(0).toUpperCase() + status.slice(1) + '</div></div>';
    document.getElementById('mpCheckBtn').disabled = true;

    const messages = {
        failed:    'Payment was declined. Please try again or use cash.',
        expired:   'Payment expired. Please start a new payment.',
        cancelled: 'Payment was cancelled.',
    };
    showMobileError(messages[status] || 'Payment could not be completed.');
}

// ── Expiry countdown ──────────────────────────────────────
function startExpiryCountdown() {
    clearExpiryCountdown();
    const $wrap = document.getElementById('mpExpiryWrap');
    const $cd   = document.getElementById('mpExpiryCountdown');
    if (!mpExpiresAt) return;
    $wrap.style.display = '';

    function tick() {
        const remaining = Math.max(0, Math.floor((mpExpiresAt - Date.now()) / 1000));
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        $cd.textContent = m + ':' + String(s).padStart(2,'0');
        if (remaining <= 0) {
            clearExpiryCountdown();
            clearMobilePolling();
            onMobilePaymentFailed('expired');
        }
    }
    tick();
    mpCountdownInt = setInterval(tick, 1000);
}

function clearExpiryCountdown() {
    if (mpCountdownInt) { clearInterval(mpCountdownInt); mpCountdownInt = null; }
    const $wrap = document.getElementById('mpExpiryWrap');
    if ($wrap) $wrap.style.display = 'none';
}

// ── Clean up when mobile modal is hidden ──────────────────
document.getElementById('mobilePayModal').addEventListener('hidden.bs.modal', function() {
    clearMobilePolling();
    clearExpiryCountdown();
});

// ── Clear cart ────────────────────────────────────────────
$clearCart.addEventListener('click', () => {
    if (Object.keys(cart).length === 0) return;
    if (!confirm('Clear all items from the cart?')) return;
    cart = {}; renderCart(); renderProducts();
});

// ── Search ────────────────────────────────────────────────
$search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadProducts, 280);
});
document.getElementById('clearSearch').addEventListener('click', () => {
    $search.value = ''; loadProducts();
});

// ── Category filter ───────────────────────────────────────
document.getElementById('catFilter').addEventListener('click', e => {
    const pill = e.target.closest('.cat-pill');
    if (!pill) return;
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    activeCatId = parseInt(pill.dataset.cat ?? '0');
    loadProducts();
});

// ── Barcode focus ─────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    if (e.key.length === 1) { $search.focus(); }
});

// ── Initial load ──────────────────────────────────────────
loadProducts();
})();
}); // DOMContentLoaded
</script>
<?php $view->endSection(); ?>
