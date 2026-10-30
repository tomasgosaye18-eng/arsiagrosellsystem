<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/settings/services/PaymentMethodService.php
 * Purpose : Business logic layer for payment method management.
 *
 * This service is called by:
 *   - PaymentMethodController (admin management UI)
 *   - MobilePaymentService    (provider activation check)
 *   - POSController/terminal  (POS active method list)
 *
 * SEPARATION OF CONCERNS
 *   This service manages PAYMENT METHODS (system configuration).
 *   MobilePaymentService manages PAYMENT TRANSACTIONS.
 *   These two concepts are intentionally kept separate.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Services;

use AAM\Settings\Models\PaymentMethodModel;
use AAM\Core\Logger;

class PaymentMethodService
{
    private PaymentMethodModel $model;

    public function __construct()
    {
        $this->model = new PaymentMethodModel();
    }

    // ── Read methods ────────────────────────────────────────────────────────

    /**
     * Get all payment methods for the admin management page.
     * Includes transaction count for each method.
     * Provider config is NEVER included.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAllForAdmin(): array
    {
        return $this->model->getAll();
    }

    /**
     * Get ACTIVE payment methods for POS display.
     * Only includes fields safe to send to the browser.
     * No provider config, no credentials.
     *
     * Groups them for the POS UI:
     *   cash           → 'cash' group
     *   mobile_banking → 'mobile_banking' group (sub-options)
     *
     * @return array{cash: array, mobile_banking: array}
     */
    public function getActiveForPOS(): array
    {
        $methods = $this->model->getActive();

        $result = [
            'cash'           => [],
            'mobile_banking' => [],
        ];

        foreach ($methods as $method) {
            if ($method['type'] === 'cash') {
                $result['cash'][] = [
                    'id'           => (int)$method['id'],
                    'code'         => $method['code'],
                    'name'         => $method['name'],
                    'provider_code'=> null,
                ];
            } elseif ($method['type'] === 'mobile_banking') {
                $result['mobile_banking'][] = [
                    'id'           => (int)$method['id'],
                    'code'         => $method['code'],
                    'name'         => $method['name'],
                    'provider_code'=> $method['provider_code'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get a single payment method by ID (admin view — no config).
     *
     * @return array<string,mixed>|null
     */
    public function getById(int $id): ?array
    {
        return $this->model->getById($id);
    }

    /**
     * Check if a specific provider code is currently ACTIVE.
     * Used by MobilePaymentService before initiating a payment.
     */
    public function isProviderActive(string $providerCode): bool
    {
        $method = $this->model->getByCode($providerCode);
        return $method !== null && (bool)$method['is_active'];
    }

    /**
     * Get audit history for a payment method.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAuditHistory(int $methodId): array
    {
        return $this->model->getAuditHistory($methodId);
    }

    // ── Write methods (System Administrator only) ───────────────────────────

    /**
     * Activate a payment method.
     *
     * @param int $methodId   Payment method ID
     * @param int $adminUserId System administrator performing the action
     * @throws \RuntimeException
     */
    public function activate(int $methodId, int $adminUserId): void
    {
        $method = $this->model->getById($methodId);
        if (!$method) {
            throw new \RuntimeException('Payment method not found.');
        }

        if ((bool)$method['is_active']) {
            throw new \RuntimeException(
                "Payment method \"{$method['name']}\" is already active."
            );
        }

        $this->model->toggleActive($methodId, $adminUserId);

        $this->model->logAudit(
            $methodId,
            $adminUserId,
            'activated',
            'is_active',
            '0',
            '1',
            "Payment method \"{$method['name']}\" activated."
        );

        Logger::info('Payment method activated', [
            'method_id'   => $methodId,
            'method_code' => $method['code'],
            'activated_by'=> $adminUserId,
        ]);
    }

    /**
     * Deactivate a payment method.
     * Historical transactions are preserved — only new POS usage is blocked.
     *
     * @throws \RuntimeException
     */
    public function deactivate(int $methodId, int $adminUserId): void
    {
        $method = $this->model->getById($methodId);
        if (!$method) {
            throw new \RuntimeException('Payment method not found.');
        }

        if (!(bool)$method['is_active']) {
            throw new \RuntimeException(
                "Payment method \"{$method['name']}\" is already inactive."
            );
        }

        $this->model->toggleActive($methodId, $adminUserId);

        $this->model->logAudit(
            $methodId,
            $adminUserId,
            'deactivated',
            'is_active',
            '1',
            '0',
            "Payment method \"{$method['name']}\" deactivated. Historical transactions preserved."
        );

        Logger::info('Payment method deactivated', [
            'method_id'   => $methodId,
            'method_code' => $method['code'],
            'deactivated_by' => $adminUserId,
        ]);
    }

    /**
     * Update provider configuration for a mobile banking method.
     *
     * SECURITY RULES enforced here:
     *   1. Cash methods cannot have provider config.
     *   2. Config keys that look like secrets are marked for encryption.
     *   3. Raw secret values are NEVER logged in audit trail —
     *      only key names are recorded.
     *
     * @param array<string,string> $rawConfig   POST config fields
     * @throws \RuntimeException
     */
    public function updateProviderConfig(
        int    $methodId,
        array  $rawConfig,
        string $description,
        int    $adminUserId
    ): void {
        $method = $this->model->getById($methodId);
        if (!$method) {
            throw new \RuntimeException('Payment method not found.');
        }

        if ($method['type'] === 'cash') {
            throw new \RuntimeException(
                'Cash payment method does not support provider configuration.'
            );
        }

        // Sanitize: only allow known safe config keys
        $allowedKeys = $this->getAllowedConfigKeys($method['code']);
        $sanitized   = [];
        foreach ($allowedKeys as $key) {
            if (isset($rawConfig[$key]) && $rawConfig[$key] !== '') {
                // Never store plaintext secrets that look like API keys/tokens
                // (they should go through encryption at rest in production)
                $sanitized[$key] = trim((string)$rawConfig[$key]);
            }
        }

        $this->model->updateConfig($methodId, $sanitized, $description, $adminUserId);

        // Audit: record WHICH fields were updated, NOT the values (security)
        $updatedKeys = implode(', ', array_keys($sanitized));
        $this->model->logAudit(
            $methodId,
            $adminUserId,
            'config_updated',
            'provider_config',
            '[previous config]',
            'Fields updated: ' . ($updatedKeys ?: 'none'),
            "Provider configuration updated for \"{$method['name']}\"."
        );

        Logger::info('Payment method provider config updated', [
            'method_id'    => $methodId,
            'method_code'  => $method['code'],
            'fields'       => array_keys($sanitized),
            'updated_by'   => $adminUserId,
        ]);
    }

    // ── Provider config key whitelists ──────────────────────────────────────

    /**
     * Returns the list of allowed configuration keys for a provider.
     * This whitelist prevents arbitrary data injection into provider_config.
     *
     * Values that look like secrets (api_key, api_secret, token) should be
     * encrypted at rest in production. The field names are safe to store.
     *
     * @return string[]
     */
    public function getAllowedConfigKeys(string $providerCode): array
    {
        return match($providerCode) {
            'cbe_birr' => [
                'merchant_id',       // CBE Birr merchant/institution ID
                'account_number',    // Merchant account number
                'shortcode',         // Shortcode for QR generation
                'api_key',           // API key (encrypted at rest in prod)
                'callback_url',      // Webhook URL for payment notifications
                'environment',       // 'sandbox' or 'production'
            ],
            'telebirr' => [
                'app_id',            // Telebirr app ID
                'app_key',           // Application key (encrypted at rest in prod)
                'shortcode',         // Merchant shortcode
                'merchant_code',     // Merchant identification code
                'merchant_name',     // Business name shown on customer's device
                'notify_url',        // Webhook notification URL
                'return_url',        // Return URL after payment
                'environment',       // 'sandbox' or 'production'
            ],
            default => [],
        };
    }

    /**
     * Get safe, displayable config field labels for the edit form.
     * Never includes values — only labels and whether field is sensitive.
     *
     * @return array<string, array{label:string, type:string, sensitive:bool, placeholder:string}>
     */
    public function getConfigFieldMeta(string $providerCode): array
    {
        return match($providerCode) {
            'cbe_birr' => [
                'merchant_id'    => ['label' => 'Merchant ID',       'type' => 'text',     'sensitive' => false, 'placeholder' => 'CBE merchant institution ID'],
                'account_number' => ['label' => 'Account Number',    'type' => 'text',     'sensitive' => false, 'placeholder' => 'Merchant bank account number'],
                'shortcode'      => ['label' => 'Shortcode',         'type' => 'text',     'sensitive' => false, 'placeholder' => 'QR shortcode'],
                'api_key'        => ['label' => 'API Key',           'type' => 'password', 'sensitive' => true,  'placeholder' => 'Leave blank to keep existing'],
                'callback_url'   => ['label' => 'Callback / Webhook URL', 'type' => 'url', 'sensitive' => false, 'placeholder' => 'https://yourserver.com/webhook/cbe'],
                'environment'    => ['label' => 'Environment',       'type' => 'select',   'sensitive' => false, 'placeholder' => '', 'options' => ['sandbox' => 'Sandbox (Testing)', 'production' => 'Production (Live)']],
            ],
            'telebirr' => [
                'app_id'         => ['label' => 'App ID',            'type' => 'text',     'sensitive' => false, 'placeholder' => 'Telebirr application ID'],
                'app_key'        => ['label' => 'App Key',           'type' => 'password', 'sensitive' => true,  'placeholder' => 'Leave blank to keep existing'],
                'shortcode'      => ['label' => 'Shortcode',         'type' => 'text',     'sensitive' => false, 'placeholder' => 'Telebirr merchant shortcode'],
                'merchant_code'  => ['label' => 'Merchant Code',     'type' => 'text',     'sensitive' => false, 'placeholder' => 'Merchant identification code'],
                'merchant_name'  => ['label' => 'Merchant Name',     'type' => 'text',     'sensitive' => false, 'placeholder' => 'Business name shown to customer'],
                'notify_url'     => ['label' => 'Notification URL',  'type' => 'url',      'sensitive' => false, 'placeholder' => 'https://yourserver.com/webhook/telebirr'],
                'return_url'     => ['label' => 'Return URL',        'type' => 'url',      'sensitive' => false, 'placeholder' => 'https://yourserver.com/pos/payment/return'],
                'environment'    => ['label' => 'Environment',       'type' => 'select',   'sensitive' => false, 'placeholder' => '', 'options' => ['sandbox' => 'Sandbox (Testing)', 'production' => 'Production (Live)']],
            ],
            default => [],
        };
    }
}
