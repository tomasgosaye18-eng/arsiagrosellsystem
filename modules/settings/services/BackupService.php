<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8D
 * =========================================================
 * File    : modules/settings/services/BackupService.php
 * Purpose : Handles database backup and restore operations.
 *           Uses mysqldump for export, source for restore.
 *           Generates checksums, verifies integrity.
 * Deps    : BackupModel, Database, Logger
 * How     : BackupController calls createBackup(), restore()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Services;

use AAM\Settings\Models\BackupModel;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Config\Application;

class BackupService
{
    private BackupModel $model;
    private string $backupDir;

    public function __construct()
    {
        $this->model     = new BackupModel();
        $this->backupDir = realpath(__DIR__ . '/../../../storage/backups') ?: '';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    // ── Create backup ──────────────────────────────────────────────────────
    /**
     * @param string $type       manual | scheduled | pre_restore
     * @param int|null $userId   NULL for scheduled
     * @return array{success:bool, filename?:string, id?:int, error?:string}
     */
    public function createBackup(string $type = 'manual', ?int $userId = null): array
    {
        $timestamp = date('Y-m-d_His');
        $filename  = "aam_backup_{$timestamp}.sql";
        $filePath  = $this->backupDir . '/' . $filename;

        // Record start
        $id = $this->model->create([
            'filename'    => $filename,
            'file_path'   => $filePath,
            'backup_type' => $type,
            'status'      => 'running',
            'created_by'  => $userId,
        ]);

        try {
            $dbConfig      = Application::get('database');
            $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

            // Build command — write output directly to file using -r flag (Windows safe)
            $cmd = sprintf(
                '"%s" --user=%s --password=%s --host=%s --port=%d --single-transaction --routines --triggers %s -r %s 2>&1',
                $mysqldumpPath,
                escapeshellarg((string) $dbConfig['username']),
                escapeshellarg((string) $dbConfig['password']),
                escapeshellarg((string) $dbConfig['host']),
                (int) $dbConfig['port'],
                escapeshellarg((string) $dbConfig['name']),
                escapeshellarg($filePath)
            );

            exec($cmd, $output, $returnVar);

            if ($returnVar !== 0 || !file_exists($filePath) || filesize($filePath) < 100) {
                throw new \Exception('Backup command failed (exit ' . $returnVar . '): ' . implode("\n", $output));
            }

            $fileSize = filesize($filePath);
            $checksum = hash_file('sha256', $filePath);

            $this->model->update($id, [
                'file_size_bytes' => $fileSize,
                'checksum'        => $checksum,
                'status'          => 'completed',
                'completed_at'    => date('Y-m-d H:i:s'),
            ]);

            Logger::info('Backup created successfully', [
                'filename' => $filename,
                'size'     => $fileSize,
                'user_id'  => $userId,
            ]);

            return ['success' => true, 'filename' => $filename, 'id' => $id];

        } catch (\Throwable $e) {
            $this->model->update($id, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => date('Y-m-d H:i:s'),
            ]);
            Logger::error('Backup failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Download ───────────────────────────────────────────────────────────
    /**
     * Send backup file to browser as download.
     */
    public function downloadBackup(int $id): array
    {
        $backup = $this->model->find($id);
        if (!$backup || !file_exists($backup['file_path'])) {
            return ['success' => false, 'error' => 'Backup file not found.'];
        }
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($backup['filename']) . '"');
        header('Content-Length: ' . filesize($backup['file_path']));
        readfile($backup['file_path']);
        exit;
    }

    // ── Restore ───────────────────────────────────────────────────────────
    /**
     * WARNING: Destructive operation. Creates pre-restore backup first.
     */
    public function restoreBackup(int $id, int $userId): array
    {
        $backup = $this->model->find($id);
        if (!$backup || !file_exists($backup['file_path'])) {
            return ['success' => false, 'error' => 'Backup file not found.'];
        }

        // Pre-restore backup
        $preRestore = $this->createBackup('pre_restore', $userId);
        if (!$preRestore['success']) {
            return ['success' => false, 'error' => 'Failed to create pre-restore backup.'];
        }

        try {
            $dbConfig  = Application::get('database');
            $mysqlPath = 'C:\\xampp\\mysql\\bin\\mysql.exe';
            $cmd = sprintf(
                '"%s" --user=%s --password=%s --host=%s --port=%d %s < %s 2>&1',
                $mysqlPath,
                escapeshellarg((string) $dbConfig['username']),
                escapeshellarg((string) $dbConfig['password']),
                escapeshellarg((string) $dbConfig['host']),
                (int) $dbConfig['port'],
                escapeshellarg((string) $dbConfig['name']),
                escapeshellarg($backup['file_path'])
            );

            exec($cmd, $output, $returnVar);
            if ($returnVar !== 0) {
                throw new \Exception('Restore command failed: ' . implode("\n", $output));
            }

            Logger::warning('Database restored', [
                'backup_id' => $id,
                'user_id'   => $userId,
                'pre_restore_id' => $preRestore['id'] ?? null,
            ]);

            return ['success' => true, 'pre_restore_id' => $preRestore['id'] ?? null];

        } catch (\Throwable $e) {
            Logger::error('Restore failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Verify ────────────────────────────────────────────────────────────
    public function verifyBackup(int $id): array
    {
        $backup = $this->model->find($id);
        if (!$backup || !file_exists($backup['file_path'])) {
            return ['success' => false, 'error' => 'File not found.'];
        }
        $currentChecksum = hash_file('sha256', $backup['file_path']);
        if ($backup['checksum'] && $currentChecksum !== $backup['checksum']) {
            return ['success' => false, 'error' => 'Checksum mismatch — file corrupted.'];
        }
        $this->model->verify($id, $currentChecksum);
        return ['success' => true];
    }

    // ── Delete ────────────────────────────────────────────────────────────
    public function deleteBackup(int $id): array
    {
        $backup = $this->model->find($id);
        if (!$backup) {
            return ['success' => false, 'error' => 'Backup record not found.'];
        }
        if (file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }
        $this->model->markDeleted($id);
        return ['success' => true];
    }

    // ── List ──────────────────────────────────────────────────────────────
    public function list(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        return $this->model->getHistory($page, $perPage, $filters);
    }
}
