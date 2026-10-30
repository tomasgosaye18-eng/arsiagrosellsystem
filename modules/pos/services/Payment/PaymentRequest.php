<?php
/**
 * =========================================================
 * File    : modules/pos/services/Payment/PaymentRequest.php
 * Purpose : Value object returned by MobilePaymentInterface::initiate()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services\Payment;

/**
 * Immutable result of a payment initiation.
 * Status is always PENDING — initiation never means paid.
 */
final class PaymentRequest
{
    public function __construct(
        /** Always 'pending' after initiation */
        public readonly string $status,

        /** Payment reference (AUPOS-YYYYMMDD-NNNNNN) */
        public readonly string $reference,

        /** Provider name for display */
        public readonly string $providerName,

        /**
         * QR payload or payment deep-link to show in the UI.
         * For CBE Birr / Telebirr: official format when configured.
         * When provider not configured: a placeholder showing the reference.
         */
        public readonly string $qrPayload,

        /**
         * Display hint for the QR modal.
         * e.g. 'Scan with CBE Birr mobile app'
         */
        public readonly string $displayHint,

        /**
         * Provider transaction ID (if the provider returned one immediately).
         * Used for faster verification lookups.
         */
        public readonly string $providerTxnId = '',

        /**
         * Raw provider response — stored server-side only, never sent to client.
         */
        public readonly array $rawResponse = [],

        /**
         * Whether the provider is operating in real API mode or
         * in pending-only dev mode (no real API configured).
         */
        public readonly bool $isLive = false,
    ) {}

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
