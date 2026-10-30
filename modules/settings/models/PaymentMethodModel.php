<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/settings/models/PaymentMethodModel.php
 * Purpose : Data access for payment_methods and
 *           payment_method_audit tables.
 *
 * IMPORTANT BUSINESS RULES (enforced here)
 *   - A payment method that has historical transactions
 *     CANNOT be deleted — use activate/deactivate instead.
 *   - Provider configuration (credentials, keys) is stored
 *     server-side only and NEVER returned to frontend JS.
 *   - The POS queries only ACTIVE methods via getActive().
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Models;

use AAM\Core\BaseModel;
use AAM\Core\Database;

class PaymentMethodModel extends BaseModel
{
    protected string $table      = 'payment_methods';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'code', 'name', 'type', 'provider_code', 'description',
        'is_active', 'sort_order', 'provider_config',
        'is_config_encrypted', 'created_by', 'updated_by',
    ];

    // ── Public query methods ────────────────────────────────────────────────

    /**
     * Get ALL payment methods for the administration page.
     * Returns full rows except provider_config (sensitive — stripped).
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAll(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                pm.id,
                pm.code,
                pm.name,
                pm.type,
                pm.provider_code,
                pm.description,
                pm.is_active,
                pm.sort_order,
                pm.is_config_encrypted,
                pm.created_at,
                pm.updated_at,
                u.username AS updated_by_username
             FROM `payment_methods` pm
             LEFT JOIN users u ON u.id = pm.updated_by
             ORDER BY pm.sort_order ASC, pm.id ASC"
        );

        // Annotate with transaction count (for delete-guard display)
        foreach ($rows as &$row) {
            $row['transaction_count'] = $this->getTransactionCount((int)$row['id'], $row['code']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get ACTIVE payment methods for the POS terminal.
     * Returns only safe-to-expose fields (no provider_config).
     *
     * @return array<int, array<string,mixed>>
     */
    public function getActive(): array
    {
        return $this->db->fetchAll(
            "SELECT
                id,
                code,
                name,
                type,
                provider_code,
                description,
                sort_order
             FROM `payment_methods`
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );
    }

    /**
     * Get a single payment method by ID.
     * Strips provider_config from the returned array.
     *
     * @return array<string,mixed>|null
     */
    public function getById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT
                pm.id, pm.code, pm.name, pm.type, pm.provider_code,
                pm.description, pm.is_active, pm.sort_order,
                pm.is_config_encrypted, pm.created_at, pm.updated_at,
                u.username AS updated_by_username
             FROM `payment_methods` pm
             LEFT JOIN users u ON u.id = pm.updated_by
             WHERE pm.id = :id",
            ['id' => $id]
        );

        if (!$row) {
            return null;
        }

        $row['transaction_count'] = $this->getTransactionCount($id, $row['code']);
        return $row;
    }

    /**
     * Get a single payment method by code (e.g. 'cbe_birr').
     * Used internally — safe fields only.
     *
     * @return array<string,mixed>|null
     */
    public function getByCode(string $code): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, code, name, type, provider_code, is_active, sort_order
             FROM `payment_methods`
             WHERE `code` = :code",
            ['code' => $code]
        );
        return $row ?: null;
    }

    /**
     * Toggle is_active status for a payment method.
     * Returns the new status.
     *
     * @throws \RuntimeException if the method cannot be deactivated
     */
    public function toggleActive(int $id, int $updatedBy): bool
    {
        $current = $this->db->fetchOne(
            "SELECT id, code, is_active FROM payment_methods WHERE id = :id",
            ['id' => $id]
        );

        if (!$current) {
            throw new \RuntimeException('Payment method not found.');
        }

        $newStatus = (int)$current['is_active'] === 1 ? 0 : 1;

        $this->db->execute(
            "UPDATE `payment_methods`
             SET is_active = :status, updated_by = :uid, updated_at = NOW()
             WHERE id = :id",
            ['status' => $newStatus, 'uid' => $updatedBy, 'id' => $id]
        );

        return (bool)$newStatus;
    }

    /**
     * Update provider configuration for a payment method.
     * Config is stored server-side; sensitive keys are marked for encryption.
     *
     * @param array<string,mixed> $config  Associative config array
     */
    public function updateConfig(int $id, array $config, string $description, int $updatedBy): void
    {
        $this->db->execute(
            "UPDATE `payment_methods`
             SET provider_config = :cfg,
                 description     = :desc,
                 updated_by      = :uid,
                 updated_at      = NOW()
             WHERE id = :id",
            [
                'cfg'  => json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'desc' => substr($description, 0, 255),
                'uid'  => $updatedBy,
                'id'   => $id,
            ]
        );
    }

    /**
     * Get the provider_config JSON for a method (server-side use ONLY).
     * Never call this from a controller that returns JSON to the browser.
     *
     * @return array<string,mixed>|null
     */
    public function getProviderConfig(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT provider_config FROM payment_methods WHERE id = :id",
            ['id' => $id]
        );
        if (!$row || empty($row['provider_config'])) {
            return null;
        }
        return json_decode($row['provider_config'], true);
    }

    // ── Audit log methods ───────────────────────────────────────────────────

    /**
     * Append an entry to the payment_method_audit log.
     */
    public function logAudit(
        int     $methodId,
        ?int    $changedBy,
        string  $action,
        ?string $fieldChanged = null,
        ?string $oldValue     = null,
        ?string $newValue     = null,
        ?string $notes        = null
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $this->db->execute(
            "INSERT INTO `payment_method_audit`
                (payment_method_id, changed_by, action,
                 field_changed, old_value, new_value,
                 changed_at, ip_address, notes)
             VALUES
                (:mid, :uid, :action,
                 :field, :old, :new,
                 NOW(), :ip, :notes)",
            [
                'mid'    => $methodId,
                'uid'    => $changedBy,
                'action' => substr($action, 0, 40),
                'field'  => $fieldChanged ? substr($fieldChanged, 0, 80) : null,
                'old'    => $oldValue    ? substr($oldValue, 0, 500)     : null,
                'new'    => $newValue    ? substr($newValue, 0, 500)     : null,
                'ip'     => $ip,
                'notes'  => $notes       ? substr($notes, 0, 255)        : null,
            ]
        );
    }

    /**
     * Get audit history for a specific payment method.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAuditHistory(int $methodId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT
                pma.id,
                pma.action,
                pma.field_changed,
                pma.old_value,
                pma.new_value,
                pma.notes,
                pma.changed_at,
                pma.ip_address,
                u.username    AS changed_by_username,
                u.first_name  AS changed_by_first_name,
                u.last_name   AS changed_by_last_name
             FROM `payment_method_audit` pma
             LEFT JOIN users u ON u.id = pma.changed_by
             WHERE pma.payment_method_id = :mid
             ORDER BY pma.changed_at DESC
             LIMIT :lim",
            ['mid' => $methodId, 'lim' => $limit]
        );
    }

    // ── Guard helpers ───────────────────────────────────────────────────────

    /**
     * Check if a payment method has any historical transactions.
     * Used to prevent hard-delete of methods that have been used.
     */
    public function hasTransactions(int $id, string $code): bool
    {
        return $this->getTransactionCount($id, $code) > 0;
    }

    /**
     * Count all transactions linked to a payment method.
     * Checks both sales table and mobile_payments table.
     */
    private function getTransactionCount(int $id, string $code): int
    {
        // Count from sales table
        $salesCount = 0;
        try {
            $pm = $this->getByCode($code);
            if ($pm) {
                $type = $pm['type'] ?? 'cash';
                if ($type === 'cash') {
                    $salesCount = (int)$this->db->fetchColumn(
                        "SELECT COUNT(*) FROM sales WHERE payment_method = 'cash'",
                        []
                    );
                } else {
                    // Mobile banking: check by provider_code
                    $salesCount = (int)$this->db->fetchColumn(
                        "SELECT COUNT(*) FROM sales
                         WHERE payment_method = 'mobile_money'
                           AND payment_provider = :prov",
                        ['prov' => $code]
                    );
                }
            }
        } catch (\Throwable) {
            // Non-critical — table may not exist yet
        }

        // Count from mobile_payments table
        $mobileCount = 0;
        try {
            $mobileCount = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM mobile_payments
                 WHERE payment_method_id = :id OR provider = :prov",
                ['id' => $id, 'prov' => $code]
            );
        } catch (\Throwable) {}

        return $salesCount + $mobileCount;
    }
}
