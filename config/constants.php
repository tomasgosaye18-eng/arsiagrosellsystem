<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : config/constants.php
 * Purpose  : Application-wide constants.
 *            Defines all fixed values used throughout
 *            the system. No class — pure constants.
 * =========================================================
 */

declare(strict_types=1);

// ─── Application Identity ─────────────────────────────────────────────────────
define('AAM_APP_NAME',    'Arsi Agri-Manage');
define('AAM_SHORT_NAME',  'AAM');
define('AAM_VERSION',     '1.0.0');
define('AAM_ORG',         'Arsi University');
define('AAM_CURRENCY',    'ETB');
define('AAM_LOCALE',      'en_ET');

// ─── User Role Names (must match roles.name in DB) ───────────────────────────
define('ROLE_ADMIN',            'administrator');
define('ROLE_FARM_MANAGER',     'farm_manager');
define('ROLE_WAREHOUSE_MANAGER','warehouse_manager');
define('ROLE_SALESPERSON',      'salesperson');
define('ROLE_GENERAL_MANAGER',  'general_manager');
define('ROLE_CUSTOMER',         'customer');          // Customer ecommerce role

// ─── Role IDs (must match roles.id seeded in DB) ─────────────────────────────
define('ROLE_ID_ADMIN',             1);
define('ROLE_ID_FARM_MANAGER',      2);
define('ROLE_ID_WAREHOUSE_MANAGER', 3);
define('ROLE_ID_SALESPERSON',       5);
define('ROLE_ID_GENERAL_MANAGER',   6);
define('ROLE_ID_CUSTOMER',          7);               // Customer ecommerce role

// ─── HTTP Status Codes ────────────────────────────────────────────────────────
define('HTTP_OK',                   200);
define('HTTP_CREATED',              201);
define('HTTP_ACCEPTED',             202);
define('HTTP_NO_CONTENT',           204);
define('HTTP_MOVED_PERMANENTLY',    301);
define('HTTP_FOUND',                302);
define('HTTP_NOT_MODIFIED',         304);
define('HTTP_BAD_REQUEST',          400);
define('HTTP_UNAUTHORIZED',         401);
define('HTTP_FORBIDDEN',            403);
define('HTTP_NOT_FOUND',            404);
define('HTTP_METHOD_NOT_ALLOWED',   405);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_TOO_MANY_REQUESTS',    429);
define('HTTP_INTERNAL_SERVER_ERROR',500);
define('HTTP_SERVICE_UNAVAILABLE',  503);

// ─── Session Keys ─────────────────────────────────────────────────────────────
define('SESSION_USER_ID',       'aam_user_id');
define('SESSION_USER_ROLE',     'aam_user_role');
define('SESSION_USER_NAME',     'aam_user_name');
define('SESSION_USER_DATA',     'aam_user_data');
define('SESSION_CSRF_TOKEN',    'aam_csrf_token');
define('SESSION_FLASH',         'aam_flash');
define('SESSION_LOCATION_TYPE', 'aam_location_type');
define('SESSION_LOCATION_ID',   'aam_location_id');
define('SESSION_LAST_ACTIVITY', 'aam_last_activity');
define('SESSION_LOGIN_TIME',    'aam_login_time');
define('SESSION_IP',            'aam_ip');
define('SESSION_USER_AGENT',    'aam_user_agent');

// ─── Flash Message Types ─────────────────────────────────────────────────────
define('FLASH_SUCCESS', 'success');
define('FLASH_ERROR',   'error');
define('FLASH_WARNING', 'warning');
define('FLASH_INFO',    'info');

// ─── Inventory Status Values ─────────────────────────────────────────────────
define('BATCH_STATUS_ACTIVE',               'active');
define('BATCH_STATUS_PARTIALLY_DISPATCHED', 'partially_dispatched');
define('BATCH_STATUS_FULLY_DISPATCHED',     'fully_dispatched');
define('BATCH_STATUS_DEPLETED',             'depleted');
define('BATCH_STATUS_EXPIRED',              'expired');
define('BATCH_STATUS_QUARANTINE',           'quarantine');

// ─── Dispatch Status Values ──────────────────────────────────────────────────
define('DISPATCH_PENDING',            'pending');
define('DISPATCH_IN_TRANSIT',         'in_transit');
define('DISPATCH_RECEIVED',           'received');
define('DISPATCH_PARTIALLY_RECEIVED', 'partially_received');
define('DISPATCH_CANCELLED',          'cancelled');

// ─── Transfer Status Values ──────────────────────────────────────────────────
define('TRANSFER_DRAFT',                    'draft');
define('TRANSFER_APPROVED',                 'approved');
define('TRANSFER_IN_TRANSIT',               'in_transit');
define('TRANSFER_RECEIVED',                 'received');
define('TRANSFER_PARTIALLY_RECEIVED',       'partially_received');
define('TRANSFER_RECEIVED_WITH_DISCREPANCY','received_with_discrepancy');
define('TRANSFER_CANCELLED',                'cancelled');

// ─── Sale Status Values ───────────────────────────────────────────────────────
define('SALE_PENDING',            'pending');
define('SALE_COMPLETED',          'completed');
define('SALE_VOIDED',             'voided');
define('SALE_RETURNED',           'returned');
define('SALE_PARTIALLY_RETURNED', 'partially_returned');

// ─── Payment Method Codes (must match payment_methods.code in DB) ─────────────
define('PAYMENT_METHOD_CASH',     'cash');
define('PAYMENT_METHOD_CBE_BIRR', 'cbe_birr');
define('PAYMENT_METHOD_TELEBIRR', 'telebirr');

// ─── Payment Status Values ────────────────────────────────────────────────────
define('PAYMENT_STATUS_PENDING',   'pending');
define('PAYMENT_STATUS_PAID',      'paid');
define('PAYMENT_STATUS_FAILED',    'failed');
define('PAYMENT_STATUS_CANCELLED', 'cancelled');
define('PAYMENT_STATUS_EXPIRED',   'expired');

// ─── File Upload Limits ──────────────────────────────────────────────────────
define('UPLOAD_MAX_SIZE_BYTES',     10 * 1024 * 1024); // 10 MB
define('UPLOAD_IMAGE_MAX_WIDTH',    2048);
define('UPLOAD_IMAGE_MAX_HEIGHT',   2048);
define('UPLOAD_PRODUCT_IMAGE_DIR',  PUBLIC_PATH . '/uploads/products');
define('UPLOAD_PROFILE_IMAGE_DIR',  PUBLIC_PATH . '/uploads/profiles');
define('UPLOAD_SHOP_IMAGE_DIR',     PUBLIC_PATH . '/uploads/shops');
define('UPLOAD_RECEIPTS_DIR',       UPLOADS_PATH . '/receipts');

// ─── Pagination ───────────────────────────────────────────────────────────────
define('PAGINATION_DEFAULT',  20);
define('PAGINATION_REPORTS',  50);
define('PAGINATION_AUDIT',    50);
define('PAGINATION_MAX',      200);

// ─── Date / Time Formats ─────────────────────────────────────────────────────
define('DATE_FORMAT_DISPLAY',   'd/m/Y');
define('DATE_FORMAT_INPUT',     'Y-m-d');
define('DATETIME_FORMAT',       'd/m/Y H:i');
define('TIME_FORMAT',           'H:i');
define('DATE_FORMAT_LOG',       'Y-m-d H:i:s');

// ─── Notification Types (must match notification_types.type_code) ─────────────
define('NOTIF_LOW_STOCK',           'low_stock');
define('NOTIF_OUT_OF_STOCK',        'out_of_stock');
define('NOTIF_EXPIRED_PRODUCT',     'expired_product');
define('NOTIF_NEAR_EXPIRY',         'near_expiry');
define('NOTIF_TRANSFER_PENDING',    'transfer_pending');
define('NOTIF_TRANSFER_RECEIVED',   'transfer_received');
define('NOTIF_DISPATCH_RECEIVED',   'dispatch_received');
define('NOTIF_DISPATCH_PENDING',    'dispatch_pending');
define('NOTIF_DISPATCH_APPROVED',   'dispatch_approved');
define('NOTIF_DISPATCH_REJECTED',   'dispatch_rejected');
define('NOTIF_DISCREPANCY',         'discrepancy');
define('NOTIF_ADJUSTMENT_PENDING',  'adjustment_pending');
define('NOTIF_ADJUSTMENT_APPROVED', 'adjustment_approved');
define('NOTIF_ADJUSTMENT_REJECTED', 'adjustment_rejected');
define('NOTIF_SYSTEM_ANNOUNCEMENT', 'system_announcement');
define('NOTIF_USER_MESSAGE',        'user_message');
define('NOTIF_BACKUP_COMPLETED',    'backup_completed');
define('NOTIF_BACKUP_FAILED',       'backup_failed');
define('NOTIF_ACCOUNT_LOCKED',      'account_locked');

// ─── Customer Order Notification Types (Phase 5) ─────────────────────────────
define('NOTIF_CUSTOMER_ORDER_PLACED',  'customer_order_placed');
define('NOTIF_CUSTOMER_ORDER_PAID',    'customer_order_paid');
define('NOTIF_CUSTOMER_ORDER_READY',   'customer_order_ready');
define('NOTIF_CUSTOMER_ORDER_DONE',    'customer_order_completed');
define('NOTIF_SHOP_NEW_ORDER',         'shop_new_customer_order');

// ─── Log Levels ───────────────────────────────────────────────────────────────
// AAM_LOG_ prefix avoids collision with PHP built-in LOG_* syslog integer constants
define('AAM_LOG_DEBUG',     'debug');
define('AAM_LOG_INFO',      'info');
define('AAM_LOG_NOTICE',    'notice');
define('AAM_LOG_WARNING',   'warning');
define('AAM_LOG_ERROR',     'error');
define('AAM_LOG_CRITICAL',  'critical');
define('AAM_LOG_ALERT',     'alert');
define('AAM_LOG_EMERGENCY', 'emergency');

// Aliases — used throughout the codebase as LOG_DEBUG etc.
// We define our own string versions under the AAM_ prefix and alias them.
// The if(!defined) guard means PHP's integer LOG_* won't be overwritten,
// but our string aliases are separate constants.
define('AAM_LOG_LEVEL_DEBUG',    'debug');
define('AAM_LOG_LEVEL_INFO',     'info');
define('AAM_LOG_LEVEL_NOTICE',   'notice');
define('AAM_LOG_LEVEL_WARNING',  'warning');
define('AAM_LOG_LEVEL_ERROR',    'error');
define('AAM_LOG_LEVEL_CRITICAL', 'critical');
define('AAM_LOG_LEVEL_ALERT',    'alert');
define('AAM_LOG_LEVEL_EMERGENCY','emergency');

// ─── Location Types ──────────────────────────────────────────────────────────
define('LOCATION_FARM',      'farm');
define('LOCATION_WAREHOUSE', 'warehouse');
define('LOCATION_SHOP',      'shop');

// ─── Quality Grades ──────────────────────────────────────────────────────────
define('GRADE_A',        'A');
define('GRADE_B',        'B');
define('GRADE_C',        'C');
define('GRADE_UNGRADED', 'Ungraded');

// ─── Adjustment Types ────────────────────────────────────────────────────────
define('ADJUSTMENT_INCREASE',  'increase');
define('ADJUSTMENT_DECREASE',  'decrease');
define('ADJUSTMENT_RECOUNT',   'recount');
define('ADJUSTMENT_WRITE_OFF', 'write_off');
define('ADJUSTMENT_RETURN',    'return');
