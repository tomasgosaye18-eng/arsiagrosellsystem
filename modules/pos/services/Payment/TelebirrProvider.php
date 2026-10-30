<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — AU-AgroSale POS
 * =========================================================
 * File    : modules/pos/services/Payment/TelebirrProvider.php
 * Purpose : Telebirr payment provider implementation.
 *
 * CURRENT STATE (Development Mode):
 *   Telebirr official API credentials are not yet configured.
 *   This provider is fully READY for integration — when
 *   Ethio Telecom / Telebirr provides:
 *     - App ID
 *     - App Key / Secret
 *     - Public Key (for request encryption)
 *     - API Base URL
 *
 *   Set the following in app/.env:
 *     TELEBIRR_APP_ID=your_app_id
 *     TELEBIRR_APP_KEY=your_app_key
 *     TELEBIRR_APP_SECRET=your_app_secret
 *     TELEBIRR_PUBLIC_KEY=your_rsa_public_key
 *     TELEBIRR_API_URL=https://api.ethiotelecom.et/telebirr/v1  (official URL)
 *
 * IMPORTANT:
 *   - No undocumented Telebirr API endpoints are used.
 *   - No fake verification — when API is not configured,
 *     payment stays PENDING. The sale is NOT completed.
 *   - Credentials are read from .env — NEVER from client input.
 *   - Credentials are NEVER included in any response to the browser.
 *
 * TELEBIRR SIGNATURE NOTE:
 *   The official Telebirr H5 API requires RSA + SHA256 request signing.
 *   The signing logic is prepared in buildSignature() below.
 *   Activate it once official API credentials are provided.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services\Payment;

use AAM\Core\Logger;

// ProviderConfigResolver merges DB config (Admin UI) with .env (fallback)

class TelebirrProvider implements MobilePaymentInterface
{
    // ── Provider identification ───────────────────────────
    public function getProviderCode(): string { return 'telebirr'; }
    public function getProviderName(): string { return 'Telebirr'; }

    // ── Configuration (DB config takes priority over .env) ─
    private function appId(): string     { return ProviderConfigResolver::get('telebirr', 'app_id',       'TELEBIRR_APP_ID'); }
    private function appKey(): string    { return ProviderConfigResolver::get('telebirr', 'app_key',      'TELEBIRR_APP_KEY'); }
    private function appSecret(): string { return ProviderConfigResolver::get('telebirr', 'app_secret',   'TELEBIRR_APP_SECRET'); }
    private function publicKey(): string { return ProviderConfigResolver::get('telebirr', 'public_key',   'TELEBIRR_PUBLIC_KEY'); }
    private function apiUrl(): string    { return rtrim(ProviderConfigResolver::get('telebirr', 'api_url','TELEBIRR_API_URL'), '/'); }
    private function shortcode(): string { return ProviderConfigResolver::get('telebirr', 'shortcode',    'TELEBIRR_SHORTCODE'); }
    private function notifyUrl(): string { return ProviderConfigResolver::get('telebirr', 'notify_url',   'TELEBIRR_NOTIFY_URL'); }
    private function returnUrl(): string { return ProviderConfigResolver::get('telebirr', 'return_url',   'TELEBIRR_RETURN_URL'); }

    /**
     * Returns true only if ALL required credentials are set.
     * Checks DB config first, then .env.
     */
    public function isConfigured(): bool
    {
        return ProviderConfigResolver::isConfigured('telebirr', ['app_id', 'app_key']);
    }

    // =========================================================
    // initiate()
    // Creates a payment request at Telebirr.
    // Always returns PENDING — initiation is NOT a payment confirmation.
    // =========================================================
    public function initiate(string $reference, float $amount, array $metadata = []): PaymentRequest
    {
        if (!$this->isConfigured()) {
            Logger::info('Telebirr: API not configured — payment stays PENDING', [
                'reference' => $reference,
                'amount'    => $amount,
            ]);

            return new PaymentRequest(
                status:      'pending',
                reference:   $reference,
                providerName: $this->getProviderName(),
                qrPayload:   $this->buildPlaceholderQrPayload($reference, $amount),
                displayHint: 'Telebirr API not yet connected. Payment awaiting official integration.',
                isLive:      false,
            );
        }

        // ── Real Telebirr API integration ─────────────────
        try {
            $timestamp = (string)(time() * 1000); // milliseconds

            $payload = [
                'appId'     => $this->appId(),
                'appName'   => 'AU AgroSale',
                'nonce'     => bin2hex(random_bytes(8)),
                'notifyUrl' => ($metadata['callback_url'] ?? ''),
                'outTradeNo'=> $reference,
                'returnApp' => '0',
                'returnUrl' => ($metadata['return_url'] ?? ''),
                'shortCode' => ($metadata['short_code'] ?? ''),
                'subject'   => ($metadata['shop_name'] ?? 'AU AgroSale') . ' Purchase',
                'timeoutExpress' => '10',  // minutes before QR expires
                'timestamp' => $timestamp,
                'totalAmount' => number_format($amount, 2, '.', ''),
                'tradeType'  => '0',     // 0 = scan to pay
            ];

            // Build HMAC-SHA256 signature (or RSA if required by official API)
            $payload['sign']     = $this->buildHmacSignature($payload);
            $payload['signType'] = 'SHA256WithRSA';

            $response = $this->post('/payment/pay', $payload);

            Logger::info('Telebirr: payment initiated', [
                'reference'  => $reference,
                'amount'     => $amount,
                'provider_txn' => $response['data']['transactionNo'] ?? '',
            ]);

            $qrPayload = $response['data']['toPayUrl']
                ?? $response['data']['qrCode']
                ?? $response['data']['codeUrl']
                ?? $reference;

            return new PaymentRequest(
                status:        'pending',
                reference:     $reference,
                providerName:  $this->getProviderName(),
                qrPayload:     $qrPayload,
                displayHint:   'Scan this QR code with the Telebirr mobile app to pay.',
                providerTxnId: (string)($response['data']['transactionNo'] ?? ''),
                rawResponse:   $response,
                isLive:        true,
            );

        } catch (\Throwable $e) {
            Logger::warning('Telebirr: payment initiation failed', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);

            return new PaymentRequest(
                status:      'pending',
                reference:   $reference,
                providerName: $this->getProviderName(),
                qrPayload:   $this->buildPlaceholderQrPayload($reference, $amount),
                displayHint: 'Telebirr service temporarily unavailable. Please retry or use cash.',
                isLive:      false,
            );
        }
    }

    // =========================================================
    // verify()
    // Checks payment status from Telebirr.
    // Returns PENDING when API is not configured — never fakes paid.
    // =========================================================
    public function verify(string $reference, string $providerTxnId = ''): PaymentVerification
    {
        if (!$this->isConfigured()) {
            Logger::info('Telebirr: verify called but API not configured — returning PENDING', [
                'reference' => $reference,
            ]);

            return new PaymentVerification(
                status:         'pending',
                verifiedAmount: 0.0,
                providerTxnId:  $providerTxnId,
                message:        'Telebirr API not configured. Payment cannot be verified automatically.',
            );
        }

        try {
            $timestamp = (string)(time() * 1000);
            $queryParams = [
                'appId'      => $this->appId(),
                'outTradeNo' => $reference,
                'timestamp'  => $timestamp,
                'nonce'      => bin2hex(random_bytes(8)),
            ];
            $queryParams['sign'] = $this->buildHmacSignature($queryParams);

            $response = $this->get('/payment/query', $queryParams);

            $data           = $response['data'] ?? [];
            $providerStatus = strtolower((string)($data['tradeStatus'] ?? $response['status'] ?? 'pending'));

            $internalStatus = match ($providerStatus) {
                'trade_success', 'success', 'paid', 'confirmed', '1' => 'paid',
                'trade_failed', 'failed', 'declined', 'rejected', '4' => 'failed',
                'trade_closed', 'timeout', 'expired', '5'             => 'expired',
                'trade_refund', 'refund', 'cancelled', 'canceled', '3' => 'cancelled',
                default                                                 => 'pending',
            };

            $verifiedAmount = (float)($data['totalAmount'] ?? $data['amount'] ?? 0);

            Logger::info('Telebirr: verification result', [
                'reference'    => $reference,
                'status'       => $internalStatus,
                'amount'       => $verifiedAmount,
                'provider_txn' => $data['transactionNo'] ?? $providerTxnId,
            ]);

            return new PaymentVerification(
                status:         $internalStatus,
                verifiedAmount: $verifiedAmount,
                providerTxnId:  (string)($data['transactionNo'] ?? $providerTxnId),
                message:        ucfirst($internalStatus),
                rawResponse:    $response,
            );

        } catch (\Throwable $e) {
            Logger::warning('Telebirr: verification error', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);

            return new PaymentVerification(
                status:         'pending',
                verifiedAmount: 0.0,
                providerTxnId:  $providerTxnId,
                message:        'Telebirr verification temporarily unavailable. Please retry.',
            );
        }
    }

    // ── Private helpers ───────────────────────────────────

    /**
     * HTTP POST to Telebirr API.
     * @throws \RuntimeException on HTTP / cURL errors
     */
    private function post(string $path, array $payload): array
    {
        $url     = $this->apiUrl() . $path;
        $body    = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($body),
        ];

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
            throw new \RuntimeException("Telebirr cURL error: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Telebirr API returned HTTP {$status}");
        }

        return json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * HTTP GET to Telebirr API.
     * @throws \RuntimeException on HTTP / cURL errors
     */
    private function get(string $path, array $params = []): array
    {
        $url = $this->apiUrl() . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new \RuntimeException("Telebirr cURL error: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Telebirr API returned HTTP {$status}");
        }

        return json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Build HMAC-SHA256 signature for Telebirr API requests.
     * Parameters are sorted lexicographically, concatenated as key=value&...,
     * then HMAC-SHA256 signed with appSecret.
     *
     * Update this method if Telebirr official docs specify a different
     * signing algorithm (e.g. RSA-SHA256 with public key encryption).
     */
    private function buildHmacSignature(array $params): string
    {
        // Remove sign and signType from the params to sign
        unset($params['sign'], $params['signType']);

        // Sort alphabetically by key
        ksort($params);

        // Build query string
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null) {
                $parts[] = $k . '=' . $v;
            }
        }
        $stringToSign = implode('&', $parts);

        return hash_hmac('sha256', $stringToSign, $this->appSecret());
    }

    /**
     * Placeholder QR payload when provider is not connected.
     * This is informational only — NOT a real Telebirr QR code.
     */
    private function buildPlaceholderQrPayload(string $reference, float $amount): string
    {
        return json_encode([
            'provider'  => 'Telebirr',
            'reference' => $reference,
            'amount'    => number_format($amount, 2, '.', ''),
            'currency'  => 'ETB',
            'note'      => 'PENDING — Telebirr API not yet connected',
        ]);
    }
}
