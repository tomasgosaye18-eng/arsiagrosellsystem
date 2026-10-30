<?php
/**
 * =========================================================
 * PRODUCT IMAGE MODEL
 * =========================================================
 * File: app/modules/admin/models/ProductImageModel.php
 * Purpose: Product image gallery data access layer
 *
 * Actual aam_db schema:
 *   id          INT PK
 *   product_id  INT FK → products.id
 *   filename    VARCHAR(255)   — stored file name / path
 *   alt_text    VARCHAR(200)
 *   is_primary  TINYINT(1)
 *   sort_order  SMALLINT
 *   created_at  TIMESTAMP
 * =========================================================
 */

namespace AAM\Admin\Models;

use AAM\Core\BaseModel;

class ProductImageModel extends BaseModel
{
    protected string $table      = 'product_images';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'product_id', 'filename', 'alt_text', 'is_primary', 'sort_order',
    ];

    // ── Aliases used by controllers / views ───────────────────────────────────
    // Controllers reference image_id, image_path, image_type, display_order,
    // uploaded_by — map these to actual columns in every query.

    private function selectCols(string $alias = 'pi'): string
    {
        return "
            {$alias}.id          AS image_id,
            {$alias}.product_id,
            {$alias}.filename    AS image_path,
            {$alias}.filename    AS thumbnail_path,
            {$alias}.alt_text,
            {$alias}.is_primary,
            CASE WHEN {$alias}.is_primary = 1 THEN 'PRIMARY' ELSE 'GALLERY' END AS image_type,
            {$alias}.sort_order  AS display_order,
            {$alias}.created_at,
            NULL                 AS file_size,
            NULL                 AS mime_type,
            NULL                 AS width,
            NULL                 AS height,
            NULL                 AS uploaded_by
        ";
    }

    // ── Get all images for a product ──────────────────────────────────────────
    public function getProductImages(int $productId): array
    {
        $sql = "SELECT " . $this->selectCols() . "
                FROM {$this->table} pi
                WHERE pi.product_id = :product_id
                ORDER BY pi.is_primary DESC, pi.sort_order ASC";

        return $this->rawQuery($sql, ['product_id' => $productId]);
    }

    // ── Get primary image ─────────────────────────────────────────────────────
    public function getPrimaryImage(int $productId): ?array
    {
        $sql = "SELECT " . $this->selectCols() . "
                FROM {$this->table} pi
                WHERE pi.product_id = :product_id AND pi.is_primary = 1
                LIMIT 1";

        $result = $this->rawQuery($sql, ['product_id' => $productId]);
        return $result[0] ?? null;
    }

    // ── Set image as primary ──────────────────────────────────────────────────
    public function setAsPrimary(int $imageId, int $productId): bool
    {
        // Demote all images for this product
        $this->rawExecute(
            "UPDATE {$this->table} SET is_primary = 0 WHERE product_id = :product_id",
            ['product_id' => $productId]
        );
        // Promote the target image
        return (bool)$this->rawExecute(
            "UPDATE {$this->table} SET is_primary = 1 WHERE id = :id",
            ['id' => $imageId]
        );
    }

    // ── Max sort order ────────────────────────────────────────────────────────
    public function getMaxDisplayOrder(int $productId): int
    {
        $result = $this->rawQuery(
            "SELECT MAX(sort_order) AS max_order FROM {$this->table} WHERE product_id = :product_id",
            ['product_id' => $productId]
        );
        return (int)($result[0]['max_order'] ?? 0);
    }

    // ── Count images for a product ────────────────────────────────────────────
    public function countProductImages(int $productId): int
    {
        $result = $this->rawQuery(
            "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE product_id = :product_id",
            ['product_id' => $productId]
        );
        return (int)($result[0]['cnt'] ?? 0);
    }

    // ── Get image by ID (with optional product safety check) ──────────────────
    public function getImageById(int $imageId, ?int $productId = null): ?array
    {
        $sql    = "SELECT " . $this->selectCols() . " FROM {$this->table} pi WHERE pi.id = :id";
        $params = ['id' => $imageId];

        if ($productId !== null) {
            $sql    .= " AND pi.product_id = :product_id";
            $params['product_id'] = $productId;
        }

        $result = $this->rawQuery($sql, $params);
        return $result[0] ?? null;
    }

    // ── Delete a single image ─────────────────────────────────────────────────
    // Returns the deleted row (for file cleanup) or null if not found.
    public function deleteImage(int $imageId, int $productId): ?array
    {
        $image = $this->getImageById($imageId, $productId);
        if (!$image) {
            return null;
        }

        $this->rawExecute(
            "DELETE FROM {$this->table} WHERE id = :id",
            ['id' => $imageId]
        );

        return $image;
    }

    // ── Delete all images for a product ──────────────────────────────────────
    public function getAndDeleteAllForProduct(int $productId): array
    {
        $images = $this->rawQuery(
            "SELECT filename AS image_path, NULL AS thumbnail_path FROM {$this->table} WHERE product_id = :product_id",
            ['product_id' => $productId]
        );

        $this->rawExecute(
            "DELETE FROM {$this->table} WHERE product_id = :product_id",
            ['product_id' => $productId]
        );

        return $images;
    }

    // ── Update display order ──────────────────────────────────────────────────
    public function updateDisplayOrder(array $orderMap): bool
    {
        foreach ($orderMap as $imageId => $order) {
            $this->rawExecute(
                "UPDATE {$this->table} SET sort_order = :order WHERE id = :id",
                ['order' => (int)$order, 'id' => (int)$imageId]
            );
        }
        return true;
    }

    // ── Soft deactivate (kept for back-compat; does hard delete instead) ──────
    public function deactivateImage(int $imageId): bool
    {
        return (bool)$this->rawExecute(
            "DELETE FROM {$this->table} WHERE id = :id",
            ['id' => $imageId]
        );
    }
}
