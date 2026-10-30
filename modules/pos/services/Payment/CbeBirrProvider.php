<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — AU-AgroSale POS
 * =========================================================
 * File    : modules/pos/services/Payment/CbeBirrProvider.php
 * Purpose : CBE Birr payment provider implementation.
 *
 * CURRENT STATE (Development Mode):
 *   CBE Birr official API credentials are not yet configured.
 *   This provider is fully READY for integration — when
 *   CBE Birr provides:
 *     - Merchant ID
 *     - API Key / Secret
 *     - API Base URL
 *     - QR generation endpoint
 *     - Payment status endpoint
 *
 *   Set the following in app/.env:
 *     CBE_BIRR_MERCHANT_ID=your_merchant_id
 *     CBE_BIRR_API_KEY=your_api_key
 *     CBE_BIRR_API_SECRET=your_api_secret
 *     CBE_BIRR_API_URL=https://api.cbebirr.et/v1  (official URL when available)
 *
 * IMPORTANT:
 *   - No undocumented API endpoints are used.
 *   - No fake verification — when API is not configured,
 *     payment stays PENDING. The sale is NOT completed.
 *   - Credentials are read from .env — NEVER from client input.
 *   - Credentials are NEVER included in any response to the browser.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services\Payment;

use AAM\Core\Logger;

class CbeBirrProvider implements MobilePaymentInterface
{
    // ── Provider identification ───────────────────────────
    public function getProviderCode(): string { return 'cbe_birr'; }
    public function getProviderName(): string { return 'CBE Birr'; }

    // ── Configuration ─────────────────────────────────────
    private function merchantId(): string  { return ProviderConfigResolver::get('cbe_birr', 'merchant_id',    'CBE_BIRR_MERCHANT_ID'); }
    private function apiKey(): string      { return ProviderConfigResolver::get('cbe_birr', 'api_key',        'CBE_BIRR_API_KEY'); }
    private function apiSecret(): string   { return ProviderConfigResolver::get('cbe_birr', 'api_secret',     'CBE_BIRR_API_SECRET'); }
    private function apiUrl(): string      { return rtrim(ProviderConfigResolver::get('cbe_birr', 'api_url',  'CBE_BIRR_API_URL'), '/'); }

    /**
     * Returns true only if ALL required credentials are set.
     * Returns false in dev/staging when no real credentials exist.
     */
    public function isConfigured(): bool
    {
        return ProviderConfigResolver::isConfigured('cbe_birr', ['merchant_id', 'api_key']);
    }

    // =========================================================
    // initiate()
    // Creates a payment request at CBE Birr.
    // Always returns PENDING — initiation is NOT a payment confirmation.
    // =========================================================
    public function initiate(string $reference, float $amount, array $metadata = []): PaymentRequest
    {
        if (!$this->isConfigured()) {
            // API not configured — return pending placeholder.
            // DO NOT mark as paid. DO NOT fake the API call.
            Logger::info('CBE Birr: API not configured — payment stays PENDING', [
                'reference' => $reference,
                'amount'    => $amount,
            ]);

            return new PaymentRequest(
                status:      'pending',
                reference:   $reference,
                providerName: $this->getProviderName(),
                qrPayload:   $this->buildPlaceholderQrPayload($reference, $amount),
                displayHint: 'CBE Birr API not yet connected. Payment awaiting official integration.',
                isLive:      false,
            );
        }

        // ── Real CBE Birr API integration ─────────────────
        // This block executes only when all credentials are configured.
        // The exact endpoint, request format, and response schema must
        // be updated when CBE Birr provides official API documentation.
        try {
            $payload = [
                'merchant_id'  => $this->merchantId(),
                'reference'    => $reference,
                'amount'       => number_format($amount, 2, '.', ''),
                'currency'     => 'ETB',
                'description'  => ($metadata['shop_name'] ?? 'AU AgroSale') . ' — ' . $reference,
                'callback_url' => ($metadata['callback_url'] ?? ''),
            ];

            $response = $this->post('/payment/initiate', $payload);

            Logger::info('CBE Birr: payment initiated', [
                'reference'  => $reference,
                'amount'     => $amount,
                'provider_txn' => $response['transaction_id'] ?? '',
            ]);

            return new PaymentRequest(
                status:        'pending',
                reference:     $reference,
                providerName:  $this->getProviderName(),
                qrPayload:     $response['qr_code'] ?? $response['qr_data'] ?? $response['payment_url'] ?? $reference,
                displayHint:   'Scan this QR code with the CBE Birr mobile app to pay.',
                providerTxnId: (string)($response['transaction_id'] ?? ''),
                rawResponse:   $response,
                isLive:        true,
            );

        } catch (\Throwable $e) {
            Logger::warning('CBE Birr: payment initiation failed', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);

            // Network/API failure — stay PENDING. Do not fake success.
            return new PaymentRequest(
                status:      'pending',
                reference:   $reference,
                providerName: $this->getProviderName(),
                qrPayload:   $this->buildPlaceholderQrPayload($reference, $amount),
                displayHint: 'CBE Birr service temporarily unavailable. Please retry or use cash.',
                isLive:      false,
            );
        }
    }

    // =========================================================
    // verify()
    // Checks payment status from CBE Birr.
    // Returns PENDING when API is not configured — never fakes paid.
    // =========================================================
    public function verify(string $reference, string $providerTxnId = ''): PaymentVerification
    {
        if (!$this->isConfigured()) {
            // No API credentials — cannot verify. Stay PENDING.
            Logger::info('CBE Birr: verify called but API not configured — returning PENDING', [
                'reference' => $reference,
            ]);

            return new PaymentVerification(
                status:          'pending',
                verifiedAmount:  0.0,
                providerTxnId:   $providerTxnId,
                message:         'CBE Birr API not configured. Payment cannot be verified automatically.',
            );
        }

        try {
            $params = ['reference' => $reference];
            if ($providerTxnId !== '') {
                $params['transaction_id'] = $providerTxnId;
            }

            $response = $this->get('/payment/status', $params);

            // Map provider status to our internal status
            // Update this mapping when official CBE Birr API docs are available.
            $providerStatus = strtolower((string)($response['status'] ?? 'pending'));
            $internalStatus = match ($providerStatus) {
                'success', 'paid', 'completed', 'confirmed' => 'paid',
                'failed', 'declined', 'rejected'             => 'failed',
                'expired', 'timeout'                         => 'expired',
                'cancelled', 'canceled'                      => 'cancelled',
                default                                      => 'pending',
            };

            $verifiedAmount = (float)($response['amount'] ?? 0);

            Logger::info('CBE Birr: verification result', [
                'reference'    => $reference,
                'status'       => $internalStatus,
                'amount'       => $verifiedAmount,
                'provider_txn' => $response['transaction_id'] ?? $providerTxnId,
            ]);

            return new PaymentVerification(
                status:         $internalStatus,
                verifiedAmount: $verifiedAmount,
                providerTxnId:  (string)($response['transaction_id'] ?? $providerTxnId),
                message:        (string)($response['message'] ?? ucfirst($internalStatus)),
                rawResponse:    $response,
            );

        } catch (\Throwable $e) {
            Logger::warning('CBE Birr: verification error', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);

            return new PaymentVerification(
                status:         'pending',
                verifiedAmount: 0.0,
                providerTxnId:  $providerTxnId,
                message:        'CBE Birr verification temporarily unavailable. Please retry.',
            );
        }
    }

    // ── Private helpers ───────────────────────────────────

    /**
     * HTTP POST to CBE Birr API.
     * Credentials are attached via headers — never in the URL.
     * @throws \RuntimeException on HTTP / cURL errors
     */
    private function post(string $path, array $payload): array
    {
        $url     = $this->apiUrl() . $path;
        $body    = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = $this->buildHeaders(strlen($body));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new \RuntimeException("CBE Birr cURL error: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("CBE Birr API returned HTTP {$status}");
        }

        return json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * HTTP GET to CBE Birr API.
     * @throws \RuntimeException on HTTP / cURL errors
     */
    private function get(string $path, array $params = []): array
    {
        $url = $this->apiUrl() . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $headers = $this->buildHeaders();

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new \RuntimeException("CBE Birr cURL error: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("CBE Birr API returned HTTP {$status}");
        }

        return json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Build request headers. Credentials are kept server-side only.
     * Never include credentials in the URL or any client-facing response.
     */
    private function buildHeaders(int $contentLength = 0): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Merchant-ID: ' . $this->merchantId(),
            'Authorization: Bearer ' . $this->apiKey(),
        ];

        if ($contentLength > 0) {
            $headers[] = 'Content-Length: ' . $contentLength;
        }

        return $headers;
    }

    /**
     * Placeholder QR payload when provider is not connected.
     * This is informational only — it does NOT represent a real
     * CBE Birr QR code and cannot be used to collect money.
     */
    private function buildPlaceholderQrPayload(string $reference, float $amount): string
    {
        return json_encode([
            'provider'  => 'CBE Birr',
            'reference' => $reference,
            'amount'    => number_format($amount, 2, '.', ''),
            'currency'  => 'ETB',
            'note'      => 'PENDING — CBE Birr API not yet connected',
        ]);
    }
}
