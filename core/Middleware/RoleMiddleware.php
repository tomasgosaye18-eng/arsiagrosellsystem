<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Middleware/RoleMiddleware.php
 * Purpose  : Role-based access control middleware.
 *            Checks that the authenticated user has one of
 *            the allowed roles for the requested route.
 *            Returns 403 if role check fails.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Middleware;

use AAM\Core\SessionManager;
use AAM\Core\View;
use AAM\Core\Logger;
use AAM\Helpers\Response;

class RoleMiddleware
{
    /** @var string[] Allowed role names */
    private array $allowedRoles;

    /**
     * @param string[] $allowedRoles Role names that may access this route
     */
    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function handle(array $params, callable $next): void
    {
        $userRole = SessionManager::userRole();

        if (!$userRole || !in_array($userRole, $this->allowedRoles, true)) {
            Logger::notice('Access denied — insufficient role', [
                'user_role'     => $userRole ?? 'none',
                'required_roles' => $this->allowedRoles,
                'uri'           => $_SERVER['REQUEST_URI'] ?? '',
            ]);

            if ($this->isAjax()) {
                Response::json(['success' => false, 'message' => 'Access denied.'], HTTP_FORBIDDEN);
                return;
            }

            http_response_code(HTTP_FORBIDDEN);
            $view = new View();
            $view->renderError(403, 'Access Denied', 'You do not have permission to access this page.');
            exit;
        }

        $next();
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
