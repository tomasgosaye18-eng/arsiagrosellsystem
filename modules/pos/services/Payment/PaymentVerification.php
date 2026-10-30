<?php
/**
 * =========================================================
 * File    : modules/pos/services/Payment/PaymentVerification.php
 * Purpose : Value object returned by MobilePaymentInterface::verify()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services\Payment;

/**
 * Result of a payment verification check.
 *
 * status values:
 *   paid      — provider confirmed successful payment
 *   pending   — not yet paid (still waiting)
 *   failed    — payment was rejected / declined by provider
 *   expired   — payment TTL elapsed without completion
 *   cancelled — payment was cancelled (by customer or seller)
 */
final class PaymentVerification
{
    public function __construct(
        /** paid | pending | failed | expired | cancelled */
        public readonly string $status,

        /** Amount confirmed by the provider (0 if not paid/unknown) */
        public readonly float $verifiedAmount,

        /** Provider-assigned transaction ID */
        public readonly string $providerTxnId,

        /** Human-readable message for logging / UI (no sensitive data) */
        public readonly string $message,

        /** Raw provider response — server-side only, never sent to client */
        public readonly array $rawResponse = [],
    ) {}

    public function isPaid(): bool    { return $this->status === 'paid';    }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isFailed(): bool  { return $this->status === 'failed' || $this->status === 'expired' || $this->status === 'cancelled'; }
}
