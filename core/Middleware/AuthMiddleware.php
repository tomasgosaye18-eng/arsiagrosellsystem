<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Middleware/AuthMiddleware.php
 * Purpose  : Authentication middleware.
 *            Ensures the request comes from a logged-in user.
 *            Redirects to login if not authenticated.
 *            Handles AJAX 401 responses separately.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Middleware;

use AAM\Core\SessionManager;
use AAM\Core\Logger;
use AAM\Helpers\Response;

class AuthMiddleware
{
    /**
     * Handle the incoming request.
     *
     * @param array<string,mixed> $params    Route parameters
     * @param callable            $next      Next handler in the pipeline
     */
    public function handle(array $params, callable $next): void
    {
        if (!SessionManager::isAuthenticated()) {
            Logger::notice('Unauthenticated access attempt', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            if ($this->isAjax()) {
                Response::json(['success' => false, 'message' => 'Authentication required.'], HTTP_UNAUTHORIZED);
                return;
            }

            // Store intended URL so we can redirect after login
            SessionManager::set('intended_url', $_SERVER['REQUEST_URI'] ?? '');
            SessionManager::set(SESSION_FLASH, [
                'type'    => FLASH_WARNING,
                'message' => 'Please log in to continue.',
            ]);

            header('Location: ' . \AAM\Config\Application::get('url', '') . '/login');
            exit;
        }

        // Check if user must change password
        $userData = SessionManager::get(SESSION_USER_DATA, []);
        if (
            !empty($userData['must_change_password']) &&
            !str_contains($_SERVER['REQUEST_URI'] ?? '', '/change-password')
        ) {
            header('Location: ' . \AAM\Config\Application::get('url', '') . '/change-password');
            exit;
        }

        // Prevent the browser from caching authenticated pages.
        // This ensures that pressing the Back button after logout
        // will NOT restore a cached copy of a protected page.
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        }

        $next();
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
