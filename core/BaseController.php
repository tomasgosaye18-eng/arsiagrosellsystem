<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/BaseController.php
 * Purpose  : Reusable base controller.
 *            All module controllers extend this class.
 *            Provides: view rendering, redirects, JSON
 *            responses, flash messages, input access,
 *            validation, and CSRF verification.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;
use AAM\Security\CSRF;
use AAM\Helpers\Validator;
use AAM\Helpers\Response;

abstract class BaseController
{
    /** @var View View renderer instance */
    protected View $view;

    /** @var Request HTTP request wrapper */
    protected Request $request;

    public function __construct()
    {
        $this->view    = new View();
        $this->request = new Request();
    }

    // ─── View Rendering ───────────────────────────────────────────────────────

    /**
     * Render a view file inside the master layout.
     *
     * @param string               $viewPath  Dot-notation: 'admin.dashboard'
     *                                         resolves to modules/admin/views/dashboard.php
     * @param array<string, mixed> $data      Variables to pass to the view
     * @param string               $layout    Layout template name (default: 'master')
     */
    protected function render(string $viewPath, array $data = [], string $layout = 'master'): void
    {
        $this->view->render($viewPath, $data, $layout);
    }

    /**
     * Render a partial view fragment (no layout wrapper).
     * Useful for AJAX partial page loads.
     *
     * @param string               $viewPath
     * @param array<string, mixed> $data
     */
    protected function renderPartial(string $viewPath, array $data = []): void
    {
        $this->view->renderPartial($viewPath, $data);
    }

    // ─── Redirect ─────────────────────────────────────────────────────────────

    /**
     * Redirect to a URL.
     *
     * @param string $url        Absolute URL or relative path
     * @param int    $statusCode HTTP redirect code (301 or 302)
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        // Ensure URL is safe (no header injection)
        $url = str_replace(["\r", "\n"], '', $url);

        // Prepend app URL for relative paths (with or without leading slash)
        if (!str_starts_with($url, 'http')) {
            $appUrl = rtrim((string) Application::get('url', ''), '/');
            $url = $appUrl . '/' . ltrim($url, '/');
        }

        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * Redirect back to the previous page (Referer).
     * Falls back to dashboard if no Referer.
     */
    protected function redirectBack(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $appUrl  = (string) Application::get('url', '');

        // Only redirect to same-origin URLs for security
        if ($referer && str_starts_with($referer, $appUrl)) {
            $this->redirect($referer);
        } else {
            $this->redirect($fallback);
        }
    }

    /**
     * Redirect with a flash message.
     *
     * @param string $url
     * @param string $type    FLASH_SUCCESS | FLASH_ERROR | FLASH_WARNING | FLASH_INFO
     * @param string $message
     */
    protected function redirectWith(string $url, string $type, string $message): void
    {
        $this->flash($type, $message);
        $this->redirect($url);
    }

    // ─── JSON Response ────────────────────────────────────────────────────────

    /**
     * Send a JSON success response.
     *
     * @param mixed                $data    Response payload
     * @param string               $message Human-readable success message
     * @param int                  $status  HTTP status code
     * @param array<string, mixed> $meta    Additional metadata
     */
    protected function jsonSuccess(
        mixed  $data    = null,
        string $message = 'Success',
        int    $status  = HTTP_OK,
        array  $meta    = []
    ): void {
        Response::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], $status);
    }

    /**
     * Send a JSON error response.
     *
     * @param string               $message
     * @param int                  $status
     * @param array<string, mixed> $errors  Validation errors keyed by field
     */
    protected function jsonError(
        string $message = 'An error occurred',
        int    $status  = HTTP_BAD_REQUEST,
        array  $errors  = []
    ): void {
        Response::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Send raw JSON response.
     *
     * @param array<mixed> $data
     * @param int          $status
     */
    protected function json(array $data, int $status = HTTP_OK): void
    {
        Response::json($data, $status);
    }

    // ─── Flash Messages ───────────────────────────────────────────────────────

    /**
     * Set a flash message in the session.
     * Flash messages are read once and then cleared.
     *
     * @param string $type    FLASH_SUCCESS | FLASH_ERROR | FLASH_WARNING | FLASH_INFO
     * @param string $message
     */
    protected function flash(string $type, string $message): void
    {
        SessionManager::set(SESSION_FLASH, [
            'type'    => $type,
            'message' => $message,
        ]);
    }

    // ─── Input Access ─────────────────────────────────────────────────────────

    /**
     * Get a value from POST data, sanitized.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $this->request->post($key, $default);
    }

    /**
     * Get all POST data, sanitized.
     *
     * @return array<string, mixed>
     */
    protected function inputAll(): array
    {
        return $this->request->postAll();
    }

    /**
     * Get a URL parameter from GET, sanitized.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $this->request->get($key, $default);
    }

    /**
     * Get an uploaded file.
     *
     * @param string $key
     * @return array<string,mixed>|null
     */
    protected function file(string $key): ?array
    {
        return $this->request->file($key);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    /**
     * Validate input data against rules.
     * Returns true if valid, or redirects back with errors on failure.
     *
     * @param array<string, mixed>          $data
     * @param array<string, string|array>   $rules
     * @param bool                          $returnErrors  If true, return errors instead of redirecting
     * @return true|array<string, string[]>
     */
    protected function validate(array $data, array $rules, bool $returnErrors = false): true|array
    {
        $validator = new Validator($data, $rules);

        if ($validator->passes()) {
            return true;
        }

        $errors = $validator->errors();

        if ($returnErrors) {
            return $errors;
        }

        // Store errors in session and redirect back
        SessionManager::set('validation_errors', $errors);
        SessionManager::set('old_input', $data);
        $this->redirectBack();

        return $errors; // unreachable but satisfies static analysis
    }

    // ─── CSRF ─────────────────────────────────────────────────────────────────

    /**
     * Verify CSRF token for state-changing requests.
     * Terminates with 403 if invalid.
     */
    protected function verifyCsrf(): void
    {
        // Support both 'csrf_token' (forms using csrf_token() helper) and '_token' (CSRF::field())
        $token = $this->input('csrf_token', '') ?: $this->input('_token', '');
        if (!CSRF::verify($token)) {
            Logger::warning('CSRF token mismatch', [
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            ]);
            http_response_code(HTTP_FORBIDDEN);
            die('Invalid or expired security token. Please go back and try again.');
        }
    }

    // ─── Authentication Helpers ───────────────────────────────────────────────

    /**
     * Get the currently authenticated user ID.
     */
    protected function userId(): ?int
    {
        $id = SessionManager::get(SESSION_USER_ID);
        return $id !== null ? (int) $id : null;
    }

    /**
     * Get the current user's role name.
     */
    protected function userRole(): ?string
    {
        return SessionManager::get(SESSION_USER_ROLE);
    }

    /**
     * Get full current user data array from session.
     *
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        return SessionManager::get(SESSION_USER_DATA);
    }

    /**
     * Check if the current user has a specific role.
     */
    protected function hasRole(string $role): bool
    {
        return $this->userRole() === $role;
    }

    /**
     * Require authentication — redirect to login if not authenticated.
     */
    protected function requireAuth(): void
    {
        if ($this->userId() === null) {
            $this->flash(FLASH_WARNING, 'Please log in to continue.');
            $this->redirect('/login');
        }
    }

    /**
     * Require a specific role — abort with 403 if wrong role.
     */
    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        if (!in_array($this->userRole(), $roles, true)) {
            http_response_code(HTTP_FORBIDDEN);
            $this->view->renderError(403, 'Access Denied', 'You do not have permission to access this page.');
            exit;
        }
    }

    // ─── Old Input / Validation Error Helpers ────────────────────────────────

    /**
     * Get old input value (populated after failed validation).
     */
    protected function old(string $key, mixed $default = ''): mixed
    {
        $old = SessionManager::get('old_input', []);
        return $old[$key] ?? $default;
    }

    /**
     * Get validation errors for a field.
     *
     * @return string[]
     */
    protected function errors(string $field): array
    {
        $errors = SessionManager::get('validation_errors', []);
        return $errors[$field] ?? [];
    }

    /**
     * Check if a field has validation errors.
     */
    protected function hasError(string $field): bool
    {
        return !empty($this->errors($field));
    }
}
