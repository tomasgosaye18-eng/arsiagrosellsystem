<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Exceptions/Exceptions.php
 * Purpose  : All custom application exceptions.
 *            Grouped in one file for simplicity.
 *            ErrorHandler uses these to determine HTTP codes.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Exceptions;

// ─── 404 Not Found ───────────────────────────────────────────────────────────
class NotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'The requested resource was not found.', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// ─── 403 Forbidden ───────────────────────────────────────────────────────────
class ForbiddenException extends \RuntimeException
{
    public function __construct(string $message = 'Access denied.', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// ─── 401 Unauthorized ────────────────────────────────────────────────────────
class UnauthorizedException extends \RuntimeException
{
    public function __construct(string $message = 'Authentication required.', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// ─── 422 Validation Error ─────────────────────────────────────────────────────
class ValidationException extends \RuntimeException
{
    /** @var array<string, string[]> */
    private array $errors;

    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(array $errors, string $message = 'Validation failed.', int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    /** @return array<string, string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

// ─── Database Exception ───────────────────────────────────────────────────────
class DatabaseException extends \RuntimeException
{
    public function __construct(string $message = 'A database error occurred.', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// ─── Upload Exception ─────────────────────────────────────────────────────────
class UploadException extends \RuntimeException
{
    public function __construct(string $message = 'File upload failed.', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
