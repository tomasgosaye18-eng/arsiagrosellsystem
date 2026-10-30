<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8D
 * =========================================================
 * File    : modules/settings/models/BackupModel.php
 * Purpose : CRUD for backup_history table.
 *           Records every manual, scheduled, and pre-restore backup.
 * Deps    : BaseModel, Database
 * How     : BackupService calls create(), updateStatus(), list()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Models;

use AAM\Core\BaseModel;

class BackupModel extends BaseModel
{
    protected string $table      = 'backup_history';
    protected bool   $softDelete = false;
    protected array  $fillable   = [
        'filename', 'file_path', 'file_size_bytes', 'backup_type', 'status',
        'tables_count', 'rows_count', 'checksum', 'error_message', 'notes',
        'created_by', 'completed_at', 'verified_at', 'deleted_at',
    ];

    /**
     * Get paginated backup history ordered by created_at DESC.
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function getHistory(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = '`status` = :status';
            $params['status']  = $filters['status'];
        }
        if (!empty($filters['backup_type'])) {
            $where[]           = '`backup_type` = :btype';
            $params['btype']   = $filters['backup_type'];
        }

        $whereSql = implode(' AND ', $where);
        $total    = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `backup_history` WHERE {$whereSql}",
            $params
        );
        $dataSql = "SELECT bh.*, CONCAT(u.first_name,' ',u.last_name) AS created_by_name
                    FROM `backup_history` bh
                    LEFT JOIN `users` u ON u.id = bh.created_by
                    WHERE {$whereSql}
                    ORDER BY bh.created_at DESC
                    LIMIT :lim OFFSET :off";
        $params['lim'] = $perPage;
        $params['off'] = $offset;
        $data = $this->db->fetchAll($dataSql, $params);

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Mark backup deleted (soft delete in file system, record kept).
     */
    public function markDeleted(int $id): bool
    {
        return $this->db->execute(
            "UPDATE `backup_history`
             SET `deleted_at` = NOW(), `status` = 'deleted'
             WHERE `id` = :id",
            ['id' => $id]
        ) > 0;
    }

    /**
     * Verify a backup by setting checksum and verified_at.
     */
    public function verify(int $id, string $checksum): bool
    {
        return $this->db->execute(
            "UPDATE `backup_history`
             SET `checksum` = :chk, `verified_at` = NOW(), `status` = 'verified'
             WHERE `id` = :id",
            ['chk' => $checksum, 'id' => $id]
        ) > 0;
    }
}
