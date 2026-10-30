<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Middleware/CustomerAuthMiddleware.php
 * Purpose  : Customer-facing authentication middleware.
 *            Guards protected customer routes (checkout,
 *            orders, account) against unauthenticated access.
 *
 *            KEY DIFFERENCE from AuthMiddleware:
 *              - Redirects to /customer/auth/login (not /login)
 *              - Accepts ANY authenticated role (customer OR
 *                internal staff) — shared session architecture
 *
 *            Authentication and authorization are SEPARATE:
 *              - This middleware only checks: is anyone logged in?
 *              - Role restrictions (customer-only vs. internal)
 *                are handled by RoleMiddleware on internal routes.
 *
 * DOES NOT MODIFY: AuthMiddleware, SessionManager, or any
 * existing internal management route.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Middleware;

use AAM\Core\SessionManager;
use AAM\Core\Logger;
use AAM\Helpers\Response;

class CustomerAuthMiddleware
{
    /**
     * Handle the incoming request.
     *
     * Allows through: authenticated users with ROLE_CUSTOMER only.
     * Blocks:        unauthenticated guests → redirect to /customer/auth/login
     * Blocks:        internal staff roles  → redirect to their own dashboard
     *
     * @param array<string,mixed> $params    Route parameters
     * @param callable            $next      Next handler in the pipeline
     */
    public function handle(array $params, callable $next): void
    {
        if (!SessionManager::isAuthenticated()) {
            Logger::notice('Unauthenticated customer route access attempt', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            if ($this->isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Authentication required.',
                ], HTTP_UNAUTHORIZED);
                return;
            }

            // Preserve the intended URL (max 2048 chars) so the customer is
            // returned here after successful Sign In / Sign Up.
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strlen($requestUri) <= 2048) {
                SessionManager::set('intended_url', $requestUri);
            }

            SessionManager::set(SESSION_FLASH, [
                'type'    => FLASH_WARNING,
                'message' => 'Please sign in to continue.',
            ]);

            $appUrl = rtrim((string)\AAM\Config\Application::get('url', ''), '/');
            header('Location: ' . $appUrl . '/customer/auth/login');
            exit;
        }

        // Role guard: only ROLE_CUSTOMER may access customer-protected routes.
        // Internal staff (warehouse manager, admin, etc.) must use their own portal.
        $role = SessionManager::userRole();
        if ($role !== \ROLE_CUSTOMER) {
            if ($this->isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Access denied.',
                ], HTTP_FORBIDDEN);
                return;
            }

            // Redirect internal staff to their own dashboard
            $appUrl = rtrim((string)\AAM\Config\Application::get('url', ''), '/');
            header('Location: ' . $appUrl . '/dashboard');
            exit;
        }

        // Prevent the browser from caching authenticated pages.
        // This ensures that pressing the Back button after logout
        // will NOT restore a cached copy of a protected customer page.
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        }

        $next();
    }

    /**
     * Detect AJAX requests.
     * Matches existing pattern used throughout the codebase.
     */
    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
