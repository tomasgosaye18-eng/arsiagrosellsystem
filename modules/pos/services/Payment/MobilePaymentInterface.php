<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — AU-AgroSale POS
 * =========================================================
 * File    : modules/pos/services/Payment/MobilePaymentInterface.php
 * Purpose : Provider-independent contract for mobile payment
 *           providers (CBE Birr, Telebirr, future providers).
 *
 * ARCHITECTURE
 *   POS Controller
 *     └── MobilePaymentService
 *           └── MobilePaymentInterface
 *                 ├── CbeBirrProvider
 *                 └── TelebirrProvider
 *
 * Adding a new provider:
 *   1. Implement MobilePaymentInterface
 *   2. Register in MobilePaymentService::getProvider()
 *   No other file needs to change.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services\Payment;

/**
 * Contract every mobile payment provider must satisfy.
 *
 * IMPORTANT — Implementation Rules:
 *   - initiate() creates a payment request at the provider.
 *   - initiate() returns a PaymentRequest with status=PENDING.
 *   - initiate() MUST NOT mark the payment as paid.
 *   - verify() checks actual payment status from the provider.
 *   - verify() MUST NOT fake/assume success when API is unavailable.
 *   - Credentials must NEVER be exposed to the client.
 */
interface MobilePaymentInterface
{
    /**
     * Provider identifier — must match the database ENUM value.
     * e.g. 'cbe_birr', 'telebirr'
     */
    public function getProviderCode(): string;

    /**
     * Human-readable provider name for UI display.
     * e.g. 'CBE Birr', 'Telebirr'
     */
    public function getProviderName(): string;

    /**
     * Check whether real API credentials are configured.
     * Returns false in development/staging when no credentials exist.
     * The MobilePaymentService uses this to decide whether to attempt
     * a real API call or leave the payment in PENDING state.
     */
    public function isConfigured(): bool;

    /**
     * Initiate a payment request with the provider.
     *
     * @param string $reference  Unique payment reference (AUPOS-YYYYMMDD-NNNNNN)
     * @param float  $amount     Exact sale total in ETB (server-side authoritative)
     * @param array  $metadata   Optional additional data (seller name, shop name, etc.)
     *
     * @return PaymentRequest    Always status=PENDING.
     *                           qr_payload contains what the UI should display.
     *                           raw_response is for server-side logging only.
     *
     * @throws PaymentProviderException  Only on unrecoverable configuration errors.
     *   Network failures / provider errors must be caught internally and returned
     *   as a PENDING result (the caller will handle the pending state).
     */
    public function initiate(string $reference, float $amount, array $metadata = []): PaymentRequest;

    /**
     * Verify the status of a previously initiated payment.
     *
     * @param string $reference  The payment reference to check
     * @param string $providerTxnId  Provider transaction ID (if available from initiate)
     *
     * @return PaymentVerification  Contains: status (PAID|FAILED|PENDING|EXPIRED),
     *                              verified amount, provider txn ID, raw response.
     *
     * @throws PaymentProviderException  On unrecoverable errors only.
     *   PENDING is returned when the provider is unreachable or API not configured.
     */
    public function verify(string $reference, string $providerTxnId = ''): PaymentVerification;
}
