<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Middleware/CsrfMiddleware.php
 * Purpose  : CSRF protection middleware.
 *            Verifies _token on all state-changing requests
 *            (POST, PUT, DELETE).
 *            GET requests are always allowed through.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Middleware;

use AAM\Security\CSRF;
use AAM\Core\Logger;
use AAM\Helpers\Response;

class CsrfMiddleware
{
    /** HTTP methods that require CSRF verification */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    /**
     * @param array<string,mixed> $params
     */
    public function handle(array $params, callable $next): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($method, self::PROTECTED_METHODS, true)) {
            $next();
            return;
        }

        // Get token from POST body or header (header for AJAX)
        // Support both '_token' (CSRF::field()) and 'csrf_token' (forms using csrf_token() helper)
        $token = $_POST['csrf_token']
            ?? $_POST['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? '';

        if (!CSRF::verify((string) $token)) {
            Logger::security('CSRF token verification failed', [
                'method' => $method,
                'uri'    => $_SERVER['REQUEST_URI'] ?? '',
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            if ($this->isAjax()) {
                Response::json(['success' => false, 'message' => 'Invalid security token.'], HTTP_FORBIDDEN);
                return;
            }

            http_response_code(HTTP_FORBIDDEN);
            die('Invalid or expired security token. Please go back and try again.');
        }

        $next();
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
