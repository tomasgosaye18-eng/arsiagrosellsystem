<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Seed Distribution Module
 * =========================================================
 * File    : modules/seed_distribution/models/SeedGradeModel.php
 * Purpose : Data access for seed_grades table.
 *           Configurable grades/classes per seed product.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\SeedDistribution\Models;

use AAM\Core\BaseModel;

class SeedGradeModel extends BaseModel
{
    protected string $table      = 'seed_grades';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'product_id', 'grade_name', 'grade_code',
        'description', 'display_order', 'is_active', 'created_by',
    ];

    /**
     * Get all active grades for a product.
     */
    public function getForProduct(int $productId): array
    {
        return $this->rawQuery(
            "SELECT sg.*, p.name AS product_name, p.sku AS product_sku
             FROM seed_grades sg
             JOIN products p ON p.id = sg.product_id
             WHERE sg.product_id = :pid AND sg.is_active = 1
             ORDER BY sg.display_order ASC, sg.grade_name ASC",
            ['pid' => $productId]
        );
    }

    /**
     * Get all seed grades grouped by product.
     */
    public function getAllWithProducts(): array
    {
        return $this->rawQuery(
            "SELECT sg.*,
                    p.name AS product_name,
                    p.sku  AS product_sku,
                    p.is_seed
             FROM seed_grades sg
             JOIN products p ON p.id = sg.product_id
             ORDER BY p.name ASC, sg.display_order ASC, sg.grade_name ASC"
        );
    }

    /**
     * Get active grades for all seed products.
     */
    public function getActiveSeedGrades(): array
    {
        return $this->rawQuery(
            "SELECT sg.*,
                    p.name AS product_name,
                    p.sku  AS product_sku
             FROM seed_grades sg
             JOIN products p ON p.id = sg.product_id AND p.is_seed = 1 AND p.is_active = 1
             WHERE sg.is_active = 1
             ORDER BY p.name ASC, sg.display_order ASC"
        );
    }

    public function find(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT sg.*, p.name AS product_name
             FROM seed_grades sg
             JOIN products p ON p.id = sg.product_id
             WHERE sg.id = :id",
            ['id' => $id]
        );
    }

    public function gradeCodeExists(int $productId, string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS n FROM seed_grades WHERE product_id = :pid AND grade_code = :code";
        $p   = ['pid' => $productId, 'code' => $code];
        if ($excludeId) { $sql .= " AND id != :ex"; $p['ex'] = $excludeId; }
        return (int)($this->rawQueryOne($sql, $p)['n'] ?? 0) > 0;
    }

    public function toggleActive(int $id): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE seed_grades SET is_active = NOT is_active WHERE id = :id",
            ['id' => $id]
        );
    }
}
