<?php
/**
 * AU-AgroSale — Phase 7 Checkout + Payment Diagnostic
 * =====================================================
 * Visit: http://localhost/arsiagro/app/check_checkout.php
 * DELETE this file after debugging.
 */
define('AAM_START_TIME',   microtime(true));
define('AAM_START_MEMORY', memory_get_usage());
define('ROOT_PATH',     __DIR__);
define('CONFIG_PATH',   ROOT_PATH . '/config');
define('CORE_PATH',     ROOT_PATH . '/core');
define('MODULES_PATH',  ROOT_PATH . '/modules');
define('HELPERS_PATH',  ROOT_PATH . '/helpers');
define('TEMPLATES_PATH',ROOT_PATH . '/templates');
define('STORAGE_PATH',  ROOT_PATH . '/storage');
define('LOGS_PATH',     ROOT_PATH . '/storage/logs');
define('CACHE_PATH',    ROOT_PATH . '/storage/cache');
define('UPLOADS_PATH',  ROOT_PATH . '/storage/uploads');
define('VENDOR_PATH',   ROOT_PATH . '/vendor');
define('PUBLIC_PATH',   ROOT_PATH . '/public');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('API_PATH',      ROOT_PATH . '/api');

require_once CORE_PATH . '/Autoloader.php';
\AAM\Core\Autoloader::register();
\AAM\Config\Environment::load();
\AAM\Config\Application::initialize();
\AAM\Core\SessionManager::start();
\AAM\Core\Database::initialize();

$db = \AAM\Core\Database::getInstance();

echo '<style>
body{font-family:monospace;padding:20px;background:#111;color:#eee;font-size:13px;line-height:1.6}
.ok{color:#4caf50}.err{color:#f44336}.warn{color:#ff9800}.info{color:#64b5f6}
h2{color:#66bb6a;border-bottom:1px solid #333;padding:5px 0;margin:20px 0 8px}
h3{color:#aaa;margin:12px 0 4px;font-size:12px;text-transform:uppercase;letter-spacing:.05em}
</style>';

function ok($msg)   { echo '<div class="ok">  ✓ ' . htmlspecialchars($msg) . '</div>'; }
function err($msg)  { echo '<div class="err"> ✗ ' . htmlspecialchars($msg) . '</div>'; }
function warn($msg) { echo '<div class="warn">⚠ ' . htmlspecialchars($msg) . '</div>'; }
function info($msg) { echo '<div class="info">ℹ ' . htmlspecialchars($msg) . '</div>'; }

echo '<h2>PHASE 7 — Mobile Banking / QR Payment Diagnostic</h2>';

/* ─── 1. REQUIRED TABLES ─────────────────────────────────────── */
echo '<h2>1. Required Tables</h2>';
$tables = [
    'customer_orders'              => 'Customer pickup orders',
    'customer_order_items'         => 'Order line items',
    'customer_order_status_changes'=> 'Order status audit trail',
    'payment_methods'              => 'Payment method registry',
    'mobile_payments'              => 'Mobile payment attempts',
    'shop_inventory'               => 'Shop stock levels',
    'sales'                        => 'Completed sales',
    'sale_items'                   => 'Sale line items',
    'products'                     => 'Products catalogue',
    'shops'                        => 'Pickup shops',
];
foreach ($tables as $table => $desc) {
    try {
        $r = $db->fetchOne("SELECT COUNT(*) AS c FROM `$table`");
        ok("$table — {$r['c']} rows — $desc");
    } catch (\Throwable $e) {
        err("$table MISSING — $desc — " . $e->getMessage());
    }
}

/* ─── 2. PAYMENT METHODS ─────────────────────────────────────── */
echo '<h2>2. Payment Methods</h2>';
try {
    $methods = $db->fetchAll(
        'SELECT id, code, name, COALESCE(display_name, name) AS display_name,
                type, is_active, provider_code
         FROM payment_methods ORDER BY sort_order'
    );
    if (!$methods) {
        err('NO payment methods — run: 2026_08_16_000037_payment_methods.sql');
    }
    foreach ($methods as $m) {
        $fn = $m['is_active'] ? 'ok' : 'warn';
        $fn("#{$m['id']} {$m['code']} | display: \"{$m['display_name']}\" | type: {$m['type']} | active: {$m['is_active']} | provider: " . ($m['provider_code'] ?? '—'));
    }

    // Phase 7: check display_name column exists
    try {
        $db->fetchOne("SELECT display_name FROM payment_methods LIMIT 1");
        ok('payment_methods.display_name column exists (Phase 7)');
    } catch (\Throwable $e) {
        err('payment_methods.display_name MISSING — run migration 042 (Phase 7)');
    }
} catch (\Throwable $e) {
    err('payment_methods: ' . $e->getMessage());
}

/* ─── 3. PAYMENT REFERENCE FORMAT ────────────────────────────── */
echo '<h2>3. Payment Reference Column</h2>';
try {
    $col = $db->fetchOne(
        "SELECT CHARACTER_MAXIMUM_LENGTH AS len
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'customer_orders'
           AND COLUMN_NAME  = 'payment_reference'"
    );
    if ($col && (int)$col['len'] >= 40) {
        ok("customer_orders.payment_reference is VARCHAR({$col['len']}) — supports AUS-PAY-YYYYMMDD-XXXXXX format ✓");
    } else {
        err("customer_orders.payment_reference is too short (length={$col['len']}) — run migration 042");
    }
} catch (\Throwable $e) {
    err('Could not check payment_reference column: ' . $e->getMessage());
}

/* ─── 4. SESSION STATE ───────────────────────────────────────── */
echo '<h2>4. Session State</h2>';
$shopId   = (int)($_SESSION['ca_selected_shop_id']   ?? 0);
$shopName = $_SESSION['ca_selected_shop_name']        ?? '';
$cart     = $_SESSION['ca_cart']                      ?? [];
$pending  = $_SESSION['ca_pending_payment']           ?? [];

$shopId   ? ok("ca_selected_shop_id = $shopId ($shopName)") : warn('ca_selected_shop_id not set — no shop selected');
count($cart) ? ok('ca_cart = ' . count($cart) . ' item(s)') : warn('ca_cart is empty');

foreach ($cart as $item) {
    $s = $item['status'] ?? 'ok';
    ($s === 'ok' ? 'ok' : 'warn')("  └ {$item['product_name']} qty={$item['quantity']} price={$item['price']} status=$s");
}

if ($pending) {
    ok('ca_pending_payment present — order_id=' . ($pending['order_id'] ?? '?') . ' ref=' . ($pending['payment_reference'] ?? '?'));
    // Validate reference format
    $ref = $pending['payment_reference'] ?? '';
    if (preg_match('/^AUS-PAY-\d{8}-[A-Z0-9]{6}$/', $ref)) {
        ok("Payment reference format valid: $ref");
    } elseif ($ref) {
        warn("Payment reference format unexpected (legacy?): $ref — new orders will use AUS-PAY-YYYYMMDD-XXXXXX");
    }
} else {
    info('ca_pending_payment not set — no payment in progress');
}

/* ─── 5. PRODUCT AVAILABILITY ────────────────────────────────── */
echo '<h2>5. Product Availability at Selected Shop</h2>';
if ($shopId && $cart) {
    foreach ($cart as $item) {
        $pid = (int)$item['product_id'];
        $qty = (float)$item['quantity'];
        try {
            $row = $db->fetchOne(
                "SELECT p.name, p.unit_price, p.is_active, p.product_status,
                        COALESCE(si.available_quantity,0) AS avail
                 FROM products p
                 LEFT JOIN shop_inventory si ON si.product_id=p.id AND si.shop_id=:sid
                 WHERE p.id=:pid LIMIT 1",
                ['sid' => $shopId, 'pid' => $pid]
            );
            if (!$row)                                err("#$pid not found in products table");
            elseif (!$row['is_active'])               err("{$row['name']}: is_active=0");
            elseif ($row['product_status'] !== 'active') err("{$row['name']}: product_status={$row['product_status']}");
            elseif (!$row['unit_price'])              err("{$row['name']}: unit_price is NULL/0 — no selling price set");
            elseif ($qty > $row['avail'])             err("{$row['name']}: need $qty but only {$row['avail']} in shop");
            else                                     ok("{$row['name']}: price={$row['unit_price']} avail={$row['avail']} need=$qty ✓");
        } catch (\Throwable $e) { err("#$pid: " . $e->getMessage()); }
    }
} else {
    info('Select a shop and add cart items to test availability');
}

/* ─── 6. MOBILE PAYMENT INTEGRITY ───────────────────────────── */
echo '<h2>6. Recent Mobile Payments (Last 5)</h2>';
try {
    $mps = $db->fetchAll(
        "SELECT mp.id, mp.payment_reference, mp.provider, mp.amount, mp.status,
                mp.expires_at, mp.customer_order_id,
                co.order_reference, co.order_status
         FROM mobile_payments mp
         LEFT JOIN customer_orders co ON co.id = mp.customer_order_id
         ORDER BY mp.created_at DESC LIMIT 5"
    );
    if (!$mps) {
        info('No mobile payments yet');
    }
    foreach ($mps as $mp) {
        $expired = strtotime($mp['expires_at']) < time() ? ' [EXPIRED]' : '';
        $line = "#{$mp['id']} {$mp['payment_reference']} | {$mp['provider']} | ETB{$mp['amount']} | {$mp['status']}{$expired}";
        if ($mp['order_reference']) {
            $line .= " | order={$mp['order_reference']} ({$mp['order_status']})";
        }
        $fn = $mp['status'] === 'paid' ? 'ok' : ($mp['status'] === 'pending' ? 'info' : 'warn');
        $fn($line);
    }

    // Idempotency check: look for any paid mobile_payment with no linked sale_id
    $orphans = $db->fetchOne(
        "SELECT COUNT(*) AS c FROM mobile_payments WHERE status='paid' AND sale_id IS NULL"
    );
    if ((int)$orphans['c'] > 0) {
        warn("Found {$orphans['c']} paid mobile_payment(s) with no sale_id — possible orphan records");
    } else {
        ok('No orphaned paid mobile_payments (all paid records have a sale_id)');
    }
} catch (\Throwable $e) {
    err('mobile_payments: ' . $e->getMessage());
}

/* ─── 7. ROUTES ─────────────────────────────────────────────── */
echo '<h2>7. Routes</h2>';
$r = file_get_contents(CORE_PATH . '/routes.php');
$checks = [
    'customer/checkout/place'       => 'CheckoutController@place',
    'customer/checkout/verify'      => 'CheckoutController@verify',
    'customer/checkout/payment/{id}'=> 'CheckoutController@payment',
    'customer/checkout/success'     => 'CheckoutController@success',
    'customer/checkout/cancel'      => 'CheckoutController@cancel',
    'customer/payment/status'       => 'PaymentController@status',
    'customer/payment/dev-confirm'  => 'PaymentController@devConfirm',
];
foreach ($checks as $route => $handler) {
    if (str_contains($r, $handler)) {
        ok("$route → $handler");
    } else {
        err("MISSING: $route → $handler");
    }
}

/* ─── 8. PERFORMANCE INDEXES ─────────────────────────────────── */
echo '<h2>8. Performance Indexes (Phase 7)</h2>';
$indexes = [
    ['customer_orders',  'idx_co_id_session'],
    ['mobile_payments',  'idx_mp_status_order'],
    ['mobile_payments',  'idx_mp_customer_order_id'],
    ['customer_orders',  'idx_co_session'],
    ['customer_orders',  'idx_co_order_status'],
];
foreach ($indexes as [$table, $idx]) {
    try {
        $exists = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i",
            ['t' => $table, 'i' => $idx]
        );
        (int)$exists['c'] > 0 ? ok("$table.$idx") : warn("$table.$idx MISSING — run migration 042");
    } catch (\Throwable $e) { warn("$idx: " . $e->getMessage()); }
}

/* ─── 9. ENVIRONMENT ─────────────────────────────────────────── */
echo '<h2>9. Environment</h2>';
$env = defined('APP_ENV') ? APP_ENV : getenv('APP_ENV');
info("APP_ENV = $env");
if ($env === 'production') {
    ok('Production mode — dev simulate button is DISABLED');
} else {
    warn("Development mode — dev simulate button is ENABLED (expected for local testing)");
}

$cbeMerchant = getenv('CBE_BIRR_MERCHANT_ID');
$cbePKey     = getenv('CBE_BIRR_API_KEY');
$telAppId    = getenv('TELEBIRR_APP_ID');
$telAppKey   = getenv('TELEBIRR_APP_KEY');

$cbeMerchant ? ok('CBE_BIRR_MERCHANT_ID is set') : warn('CBE_BIRR_MERCHANT_ID not set — CBE Birr runs in dev/pending mode (expected)');
$cbePKey     ? ok('CBE_BIRR_API_KEY is set')     : warn('CBE_BIRR_API_KEY not set — CBE Birr runs in dev/pending mode (expected)');
$telAppId    ? ok('TELEBIRR_APP_ID is set')       : warn('TELEBIRR_APP_ID not set — Telebirr runs in dev/pending mode (expected)');
$telAppKey   ? ok('TELEBIRR_APP_KEY is set')      : warn('TELEBIRR_APP_KEY not set — Telebirr runs in dev/pending mode (expected)');

/* ─── 10. RECENT ERRORS ──────────────────────────────────────── */
echo '<h2>10. Recent Checkout/Payment Errors (app log)</h2>';
try {
    $logFile = LOGS_PATH . '/app.log';
    if (file_exists($logFile)) {
        $lines  = file($logFile);
        $recent = array_filter(
            array_slice($lines, -100),
            fn($l) => preg_match('/checkout|payment|verify|mobile_pay|CheckoutService|MobilePayment/i', $l)
        );
        $recent = array_slice($recent, -10);
        if ($recent) {
            foreach ($recent as $l) err(trim($l));
        } else {
            ok('No recent checkout/payment errors in log');
        }
    } else {
        info('Log file not found at: ' . $logFile);
    }
} catch (\Throwable $e) {
    warn('Cannot read log: ' . $e->getMessage());
}

echo '<br><p style="color:#555;font-size:11px">DELETE check_checkout.php after debugging.</p>';
