<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/security_helpers.php
 * Purpose  : Security-related procedural helper functions.
 *            Used in view templates and controllers.
 * =========================================================
 */

declare(strict_types=1);

if (defined('AAM_SECURITY_HELPERS_LOADED')) return;
define('AAM_SECURITY_HELPERS_LOADED', true);

/**
 * Output the CSRF meta tag (for AJAX).
 * Usage in <head>: <?php csrf_meta(); ?>
 */
function csrf_meta(): void
{
    echo \AAM\Security\CSRF::meta();
}

/**
 * Check if the current user has a given permission code.
 * Uses session-cached permission list.
 */
function can(string $permission): bool
{
    $permissions = \AAM\Core\SessionManager::get('aam_permissions');

    if (!is_array($permissions)) {
        // Not loaded yet — load from DB
        $userId = \AAM\Core\SessionManager::userId();
        $userData = \AAM\Core\SessionManager::get(SESSION_USER_DATA, []);
        $roleId   = $userData['role_id'] ?? null;

        if (!$userId || !$roleId) {
            return false;
        }

        try {
            $rows = \AAM\Core\Database::getInstance()->fetchAll(
                "SELECT p.code FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = :rid",
                ['rid' => $roleId]
            );
            $permissions = array_column($rows, 'code');
            \AAM\Core\SessionManager::set('aam_permissions', $permissions);
        } catch (\Throwable $e) {
            return false;
        }
    }

    return in_array($permission, (array) $permissions, true);
}

/**
 * Sanitize a value for safe HTML display.
 * Alias for the global e() function.
 */
function sanitize(mixed $value): string
{
    return \AAM\Security\Sanitizer::escape($value);
}

/**
 * Generate a cryptographically secure random token string.
 */
function random_token(int $length = 32): string
{
    return bin2hex(random_bytes((int) ceil($length / 2)));
}
