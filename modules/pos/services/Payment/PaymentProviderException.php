<?php
/**
 * =========================================================
 * File    : modules/pos/services/Payment/PaymentProviderException.php
 * Purpose : Exception for unrecoverable payment provider errors.
 *           Never exposes credentials or internal stack traces.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services\Payment;

class PaymentProviderException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        // Sanitise message — never allow raw exception traces
        // or credential strings to propagate through this exception.
        parent::__construct($message, $code, $previous);
    }
}
