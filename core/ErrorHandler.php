<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/ErrorHandler.php
 * Purpose  : Global error and exception handler.
 *            Converts PHP errors to exceptions.
 *            Renders error pages based on environment.
 *            Logs all errors and exceptions.
 *            Prevents any sensitive info leakage in prod.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;
use AAM\Config\Environment;

class ErrorHandler
{
    /**
     * Register all error and exception handlers.
     * Call once during application bootstrap.
     */
    public static function register(): void
    {
        // Convert all PHP errors to ErrorException
        set_error_handler([self::class, 'handleError']);

        // Catch uncaught exceptions
        set_exception_handler([self::class, 'handleException']);

        // Catch fatal errors (parse errors, out-of-memory, etc.)
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * PHP error handler — converts errors to ErrorException.
     *
     * @throws \ErrorException
     */
    public static function handleError(
        int    $errno,
        string $errstr,
        string $errfile = '',
        int    $errline = 0
    ): bool {
        // Respect the @ error-suppression operator
        if (!(error_reporting() & $errno)) {
            return false;
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Global uncaught exception handler.
     */
    public static function handleException(\Throwable $e): void
    {
        // Determine HTTP status code from exception type
        $statusCode = self::resolveStatusCode($e);

        // Log the exception
        self::logException($e, $statusCode);

        // Send HTTP response code
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        // Clear any partial output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Render appropriate error response
        if (self::isAjaxRequest()) {
            self::renderJsonError($e, $statusCode);
        } else {
            self::renderHtmlError($e, $statusCode);
        }
    }

    /**
     * Shutdown handler to catch fatal errors.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        if (!in_array($error['type'], $fatal, true)) {
            return;
        }

        $exception = new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        );

        self::handleException($exception);
    }

    // ─── Status Code Resolution ───────────────────────────────────────────────

    /**
     * Determine the HTTP status code for an exception.
     */
    private static function resolveStatusCode(\Throwable $e): int
    {
        return match(true) {
            $e instanceof \AAM\Exceptions\NotFoundException       => HTTP_NOT_FOUND,
            $e instanceof \AAM\Exceptions\ForbiddenException      => HTTP_FORBIDDEN,
            $e instanceof \AAM\Exceptions\UnauthorizedException   => HTTP_UNAUTHORIZED,
            $e instanceof \AAM\Exceptions\ValidationException     => HTTP_UNPROCESSABLE_ENTITY,
            $e instanceof \InvalidArgumentException               => HTTP_BAD_REQUEST,
            default                                              => HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    // ─── Logging ─────────────────────────────────────────────────────────────

    /**
     * Log an exception with full context.
     */
    private static function logException(\Throwable $e, int $statusCode): void
    {
        $context = [
            'type'    => get_class($e),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'status'  => $statusCode,
            'url'     => $_SERVER['REQUEST_URI'] ?? '',
            'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        if (Environment::isDevelopment() || Environment::isDebug()) {
            $context['trace'] = $e->getTraceAsString();
        }

        $level = $statusCode >= 500 ? 'critical' : 'error';
        Logger::log($level, $e->getMessage(), $context);
    }

    // ─── Response Rendering ──────────────────────────────────────────────────

    /**
     * Render a JSON error response (for AJAX requests).
     */
    private static function renderJsonError(\Throwable $e, int $statusCode): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $response = ['success' => false, 'message' => self::getPublicMessage($e, $statusCode)];

        if (Environment::isDevelopment() || Environment::isDebug()) {
            $response['debug'] = [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTrace(),
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Render an HTML error page.
     */
    private static function renderHtmlError(\Throwable $e, int $statusCode): void
    {
        $title   = self::getStatusTitle($statusCode);
        $message = self::getPublicMessage($e, $statusCode);

        // Try to use the view system's error template
        $errorTemplate = TEMPLATES_PATH . '/errors/' . $statusCode . '.php';
        $defaultTemplate = TEMPLATES_PATH . '/errors/default.php';

        if (file_exists($errorTemplate)) {
            $debug   = (Environment::isDevelopment() || Environment::isDebug()) ? $e : null;
            extract(['code' => $statusCode, 'title' => $title, 'message' => $message, 'debug' => $debug]);
            require $errorTemplate;
            return;
        }

        if (file_exists($defaultTemplate)) {
            $debug = (Environment::isDevelopment() || Environment::isDebug()) ? $e : null;
            extract(['code' => $statusCode, 'title' => $title, 'message' => $message, 'debug' => $debug]);
            require $defaultTemplate;
            return;
        }

        // Absolute fallback HTML
        $safeMessage = htmlspecialchars($message);
        $debugInfo   = '';

        if (Environment::isDevelopment() || Environment::isDebug()) {
            $debugInfo = '<pre style="background:#f8f8f8;padding:1rem;margin-top:1rem;overflow:auto;font-size:12px;">'
                . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString())
                . '</pre>';
        }

        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$statusCode} — {$title} | AAM</title>
            <style>
                body { font-family: -apple-system, sans-serif; margin: 0; background: #f5f5f5; color: #333; }
                .container { max-width: 700px; margin: 80px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 20px rgba(0,0,0,.08); }
                h1 { color: #dc3545; margin: 0 0 1rem; font-size: 2rem; }
                p { line-height: 1.6; }
                a { color: #0d6efd; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>{$statusCode} — {$title}</h1>
                <p>{$safeMessage}</p>
                <p><a href="/">Return to Home</a></p>
                {$debugInfo}
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Return a public-safe error message based on environment.
     */
    private static function getPublicMessage(\Throwable $e, int $statusCode): string
    {
        if (Environment::isDevelopment() || Environment::isDebug()) {
            return $e->getMessage();
        }

        return match($statusCode) {
            HTTP_NOT_FOUND            => 'The page you requested could not be found.',
            HTTP_FORBIDDEN            => 'You do not have permission to access this resource.',
            HTTP_UNAUTHORIZED         => 'Please log in to continue.',
            HTTP_UNPROCESSABLE_ENTITY => 'The submitted data is invalid.',
            HTTP_SERVICE_UNAVAILABLE  => 'The service is temporarily unavailable. Please try again later.',
            default                   => 'An unexpected error occurred. Please try again or contact the administrator.',
        };
    }

    /**
     * Return a human-readable title for an HTTP status code.
     */
    private static function getStatusTitle(int $code): string
    {
        return match($code) {
            HTTP_BAD_REQUEST            => 'Bad Request',
            HTTP_UNAUTHORIZED           => 'Unauthorized',
            HTTP_FORBIDDEN              => 'Access Denied',
            HTTP_NOT_FOUND              => 'Page Not Found',
            HTTP_METHOD_NOT_ALLOWED     => 'Method Not Allowed',
            HTTP_UNPROCESSABLE_ENTITY   => 'Validation Error',
            HTTP_TOO_MANY_REQUESTS      => 'Too Many Requests',
            HTTP_SERVICE_UNAVAILABLE    => 'Service Unavailable',
            default                     => 'Internal Server Error',
        };
    }

    /**
     * Check if the current request is an AJAX request.
     */
    private static function isAjaxRequest(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
