<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/response_helpers.php
 * Purpose  : HTTP response procedural helper functions.
 *            Used in AJAX endpoint files and controllers.
 * =========================================================
 */

declare(strict_types=1);

if (defined('AAM_RESPONSE_HELPERS_LOADED')) return;
define('AAM_RESPONSE_HELPERS_LOADED', true);

/**
 * Send a JSON success response and terminate.
 *
 * @param mixed        $data
 * @param string       $message
 * @param int          $status
 */
function json_success(mixed $data = null, string $message = 'Success', int $status = 200): void
{
    \AAM\Helpers\Response::success($data, $message, $status);
}

/**
 * Send a JSON error response and terminate.
 *
 * @param string             $message
 * @param int                $status
 * @param array<mixed>       $errors
 */
function json_error(string $message = 'Error', int $status = 400, array $errors = []): void
{
    \AAM\Helpers\Response::error($message, $status, $errors);
}

/**
 * Redirect to a URL.
 */
function redirect_to(string $url, int $code = 302): void
{
    \AAM\Helpers\Response::redirect($url, $code);
}

/**
 * Send a 404 JSON response.
 */
function json_not_found(string $message = 'Not found.'): void
{
    \AAM\Helpers\Response::notFound($message);
}

/**
 * Send a 403 JSON response.
 */
function json_forbidden(string $message = 'Access denied.'): void
{
    \AAM\Helpers\Response::forbidden($message);
}
