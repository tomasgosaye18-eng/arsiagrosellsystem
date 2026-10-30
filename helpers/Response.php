<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/Response.php
 * Purpose  : Standardized HTTP response helper.
 *            Sends JSON responses for AJAX endpoints.
 *            Handles redirects and content-type headers.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Helpers;

class Response
{
    /**
     * Send a JSON response and terminate.
     *
     * @param array<mixed> $data
     * @param int          $statusCode
     */
    public static function json(array $data, int $statusCode = HTTP_OK): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send a standardized success JSON response.
     *
     * @param mixed        $data
     * @param string       $message
     * @param int          $statusCode
     * @param array<mixed> $meta
     */
    public static function success(
        mixed  $data       = null,
        string $message    = 'Success',
        int    $statusCode = HTTP_OK,
        array  $meta       = []
    ): void {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send a standardized error JSON response.
     *
     * @param string               $message
     * @param int                  $statusCode
     * @param array<string,mixed>  $errors
     */
    public static function error(
        string $message    = 'An error occurred',
        int    $statusCode = HTTP_BAD_REQUEST,
        array  $errors     = []
    ): void {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send a paginated JSON response.
     *
     * @param array<mixed> $data
     * @param int          $total
     * @param int          $page
     * @param int          $perPage
     */
    public static function paginated(array $data, int $total, int $page, int $perPage): void
    {
        self::json([
            'success'    => true,
            'data'       => $data,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / max($perPage, 1)),
                'from'        => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to'          => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Send a plain-text response.
     */
    public static function text(string $content, int $statusCode = HTTP_OK): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $content;
        exit;
    }

    /**
     * Send a redirect header.
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        $url = str_replace(["\r", "\n"], '', $url);
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * Send a 404 Not Found JSON response.
     */
    public static function notFound(string $message = 'Resource not found.'): void
    {
        self::error($message, HTTP_NOT_FOUND);
    }

    /**
     * Send a 403 Forbidden JSON response.
     */
    public static function forbidden(string $message = 'Access denied.'): void
    {
        self::error($message, HTTP_FORBIDDEN);
    }

    /**
     * Send a 401 Unauthorized JSON response.
     */
    public static function unauthorized(string $message = 'Authentication required.'): void
    {
        self::error($message, HTTP_UNAUTHORIZED);
    }

    /**
     * Send a 422 Validation Error JSON response.
     *
     * @param array<string, string[]> $errors
     */
    public static function validationError(array $errors, string $message = 'Validation failed.'): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Set a header (does not terminate).
     */
    public static function header(string $name, string $value): void
    {
        if (!headers_sent()) {
            header("{$name}: {$value}");
        }
    }
}
