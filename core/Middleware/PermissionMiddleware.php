<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Middleware/PermissionMiddleware.php
 * Purpose  : Granular permission middleware.
 *            Checks whether the authenticated user's role
 *            has a specific permission code in the DB.
 *            Uses request-scoped cache (static array) to
 *            avoid repeated DB queries within one request,
 *            while ensuring permission changes take effect
 *            on next login (no stale session cache).
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Middleware;

use AAM\Core\SessionManager;
use AAM\Core\Database;
use AAM\Core\View;
use AAM\Core\Logger;
use AAM\Helpers\Response;

class PermissionMiddleware
{
    /**
     * Request-scoped cache: role_id → string[]
     * Cleared on each new HTTP request automatically (static in PHP process).
     *
     * @var array<int, string[]>
     */
    private static array $requestCache = [];

    /** @var string Required permission code */
    private string $requiredPermission;

    public function __construct(string $requiredPermission)
    {
        $this->requiredPermission = $requiredPermission;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function handle(array $params, callable $next): void
    {
        $userId = SessionManager::userId();

        if (!$userId) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'message' => 'Unauthenticated.'], HTTP_UNAUTHORIZED);
                return;
            }
            $appUrl = rtrim((string) \AAM\Config\Application::get('url', ''), '/');
            header('Location: ' . $appUrl . '/login');
            exit;
        }

        $permissions = $this->getPermissionsForCurrentUser($userId);

        if (!in_array($this->requiredPermission, $permissions, true)) {
            Logger::notice('Permission denied', [
                'user_id'    => $userId,
                'permission' => $this->requiredPermission,
                'uri'        => $_SERVER['REQUEST_URI'] ?? '',
            ]);

            if ($this->isAjax()) {
                Response::json(['success' => false, 'message' => 'Permission denied.'], HTTP_FORBIDDEN);
                return;
            }

            http_response_code(HTTP_FORBIDDEN);
            $view = new View();
            $view->renderError(403, 'Permission Denied', 'You do not have permission to perform this action.');
            exit;
        }

        $next();
    }

    /**
     * Get all permission codes for the current user's role.
     * Uses a request-scoped static cache (per role_id) so the DB
     * is only queried once per request even with multiple middleware checks.
     * Permission changes take effect after the user's next login.
     *
     * @return string[]
     */
    private function getPermissionsForCurrentUser(int $userId): array
    {
        $userData = SessionManager::get(SESSION_USER_DATA, []);
        $roleId   = isset($userData['role_id']) ? (int)$userData['role_id'] : null;

        if (!$roleId) {
            return [];
        }

        // Use request-scoped cache keyed by role_id
        if (isset(self::$requestCache[$roleId])) {
            return self::$requestCache[$roleId];
        }

        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll(
                "SELECT p.code
                 FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = :role_id AND p.is_active = 1",
                ['role_id' => $roleId]
            );

            $permissions = array_column($rows, 'code');
            self::$requestCache[$roleId] = $permissions;

            return $permissions;
        } catch (\Throwable $e) {
            Logger::error('Failed to load permissions: ' . $e->getMessage());
            return [];
        }
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
