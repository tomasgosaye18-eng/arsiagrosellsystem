<?php
/**
 * ShopModel — aam_db.shops
 * Canonical operational columns: id, name, location, is_active
 * Additional display: shop_code, shop_name, shop_type, opening_time, closing_time
 */
declare(strict_types=1);

namespace AAM\Organization\Models;

use AAM\Core\BaseModel;

class ShopModel extends BaseModel
{
    protected string $table      = 'shops';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = true;
    protected array  $fillable   = [
        'name', 'shop_name', 'shop_code', 'shop_type',
        'location', 'description',
        'manager_id', 'phone', 'email', 'opening_time', 'closing_time',
        'shop_image',
        'is_active', 'created_by', 'updated_by',
    ];

    // ── List ──────────────────────────────────────────────────────────────────

    public function getAllWithRelations(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT
                    s.id AS shop_id,
                    s.id,
                    s.name,
                    COALESCE(s.shop_name, s.name)                         AS shop_name,
                    COALESCE(s.shop_code, CONCAT('SHP', LPAD(s.id,3,'0'))) AS shop_code,
                    COALESCE(s.shop_type, 'RETAIL')                        AS shop_type,
                    s.location,
                    s.description,
                    s.is_active,
                    s.opening_time,
                    s.closing_time,
                    s.phone,
                    s.email,
                    s.shop_image,
                    s.created_at,
                    s.updated_at,
                    CONCAT(m.first_name,' ',m.last_name) AS manager_name,
                    m.username AS manager_username
                FROM shops s
                LEFT JOIN users m      ON m.id           = s.manager_id
                WHERE s.deleted_at IS NULL";

        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        $sql .= " ORDER BY s.name ASC LIMIT :lim OFFSET :off";
        $params['lim'] = $limit;
        $params['off'] = $offset;
        return $this->rawQuery($sql, $params);
    }

    public function countWithFilters(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) AS n FROM shops s WHERE s.deleted_at IS NULL";
        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        return (int)($this->rawQueryOne($sql, $params)['n'] ?? 0);
    }

    // ── Single ────────────────────────────────────────────────────────────────

    public function getByIdWithRelations(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT s.id AS shop_id, s.*,
                    COALESCE(s.shop_name, s.name)                          AS shop_name,
                    COALESCE(s.shop_code, CONCAT('SHOP-', LPAD(s.id,3,'0'))) AS shop_code,
                    COALESCE(s.shop_type, 'RETAIL')                         AS shop_type,
                    s.shop_image,
                    CONCAT(m.first_name,' ',m.last_name)  AS manager_name,
                    m.username AS manager_username,
                    m.phone    AS manager_phone,
                    m.email    AS manager_email,
                    CONCAT(cb.first_name,' ',cb.last_name) AS creator_name
             FROM shops s
             LEFT JOIN users m      ON m.id           = s.manager_id
             LEFT JOIN users cb     ON cb.id           = s.created_by
             WHERE s.id = :id AND s.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    // ── Statistics ────────────────────────────────────────────────────────────

    public function getStatistics(): array
    {
        return $this->rawQueryOne(
            "SELECT COUNT(*) AS total, SUM(is_active=1) AS active, SUM(is_active=0) AS inactive
             FROM shops WHERE deleted_at IS NULL"
        ) ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate next unique shop code (SHOP-001, SHOP-002...).
     */
    public function getNextCode(): string
    {
        $row = $this->rawQueryOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(shop_code, 6) AS UNSIGNED)), 0) AS mx
             FROM shops WHERE shop_code REGEXP '^SHOP-[0-9]+$'"
        );
        $next = ((int)($row['mx'] ?? 0)) + 1;
        $code = 'SHOP-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        while ($this->codeExists($code)) {
            $next++;
            $code = 'SHOP-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    public function getShopTypes(): array
    {
        return [
            'RETAIL'    => 'Retail Shop',
            'CAFETERIA' => 'Cafeteria',
            'KIOSK'     => 'Kiosk',
            'OUTLET'    => 'Outlet',
            'OTHER'     => 'Other',
        ];
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS n FROM shops WHERE name = :n AND deleted_at IS NULL";
        $p   = ['n' => $name];
        if ($excludeId) { $sql .= " AND id != :ex"; $p['ex'] = $excludeId; }
        return (int)($this->rawQueryOne($sql, $p)['n'] ?? 0) > 0;
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS n FROM shops WHERE shop_code = :c AND deleted_at IS NULL";
        $p   = ['c' => $code];
        if ($excludeId) { $sql .= " AND id != :ex"; $p['ex'] = $excludeId; }
        return (int)($this->rawQueryOne($sql, $p)['n'] ?? 0) > 0;
    }

    public function find(int $id): ?array
    {
        return $this->rawQueryOne("SELECT * FROM shops WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    }

    public function softDelete(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE shops SET deleted_at=NOW(), is_active=0 WHERE id=:id", ['id' => $id]);
    }

    public function restoreShop(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE shops SET deleted_at=NULL, is_active=1 WHERE id=:id", ['id' => $id]);
    }

    public function toggleStatus(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE shops SET is_active = NOT is_active WHERE id=:id", ['id' => $id]);
    }

    public function getActiveShopsForSelect(): array
    {
        return $this->rawQuery(
            "SELECT id, COALESCE(shop_name, name) AS shop_name, name, COALESCE(shop_code, CONCAT('SHP', LPAD(id,3,'0'))) AS shop_code, shop_type, location
             FROM shops WHERE is_active=1 AND deleted_at IS NULL ORDER BY name ASC"
        );
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        if (!empty($filters['search'])) {
            $sql .= " AND (s.name LIKE :sl OR s.location LIKE :sl)";
            $params['sl'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['manager_id'])) {
            $sql .= " AND s.manager_id = :mgr";
            $params['mgr'] = (int)$filters['manager_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND s.is_active = :active";
            $params['active'] = (int)$filters['is_active'];
        }
        return [$sql, $params];
    }
}
