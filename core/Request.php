<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Request.php
 * Purpose  : HTTP Request wrapper.
 *            Sanitizes all input access.
 *            Provides typed accessors for GET, POST, FILES.
 *            Never exposes raw $_GET/$_POST directly.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Security\Sanitizer;

class Request
{
    // ─── GET Parameters ───────────────────────────────────────────────────────

    /**
     * Get a sanitized GET parameter.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($_GET[$key])) {
            return $default;
        }
        return Sanitizer::clean($_GET[$key]);
    }

    /**
     * Get all sanitized GET parameters.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return Sanitizer::cleanArray($_GET);
    }

    // ─── POST Parameters ─────────────────────────────────────────────────────

    /**
     * Get a sanitized POST parameter.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        if (!isset($_POST[$key])) {
            return $default;
        }
        return Sanitizer::clean($_POST[$key]);
    }

    /**
     * Get all sanitized POST parameters.
     *
     * @return array<string, mixed>
     */
    public function postAll(): array
    {
        return Sanitizer::cleanArray($_POST);
    }

    /**
     * Get raw POST value without sanitization.
     * Use ONLY when you need exact values (e.g., passwords).
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function rawPost(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    // ─── JSON Body ────────────────────────────────────────────────────────────

    /**
     * Parse and return JSON request body (for AJAX JSON POST).
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $body = file_get_contents('php://input');
        if (!$body) {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? Sanitizer::cleanArray($data) : [];
    }

    // ─── File Uploads ─────────────────────────────────────────────────────────

    /**
     * Get an uploaded file info array.
     *
     * @param string $key
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    /**
     * Check if an uploaded file exists and has no upload error.
     */
    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    }

    // ─── Request Meta ─────────────────────────────────────────────────────────

    /**
     * Get the HTTP request method.
     */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Check if request method is POST.
     */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Check if request was made via AJAX (XMLHttpRequest).
     */
    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Check if request expects JSON response (Accept: application/json).
     */
    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    /**
     * Get the client IP address.
     */
    public function ip(): string
    {
        // Check for load balancer / proxy headers
        $candidates = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        $raw = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Normalize IPv6 loopback to IPv4 loopback for display
        if ($raw === '::1') {
            return '127.0.0.1';
        }

        return $raw;
    }

    /**
     * Get the User-Agent string.
     */
    public function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }

    /**
     * Get the full request URI.
     */
    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get the request path without query string.
     */
    public function path(): string
    {
        return parse_url($this->uri(), PHP_URL_PATH) ?? '/';
    }

    /**
     * Get a header value.
     *
     * @param string $name   Header name (case-insensitive)
     * @param string $default
     */
    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }
}
