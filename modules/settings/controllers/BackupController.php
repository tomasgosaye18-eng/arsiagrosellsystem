<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8D
 * =========================================================
 * File    : modules/settings/controllers/BackupController.php
 * Purpose : Manages database backup and restore operations.
 *           List, create, download, verify, delete, restore.
 * Deps    : BackupService, BaseController
 * Routes  : /settings/backups/*  (admin only)
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Controllers;

use AAM\Core\BaseController;
use AAM\Settings\Services\BackupService;

class BackupController extends BaseController
{
    private BackupService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new BackupService();
    }

    // ── GET /settings/backups ─────────────────────────────────────────────
    public function index(): void
    {
        $this->requireRole(ROLE_ADMIN);

        $page    = max(1, (int) $this->query('page', 1));
        $filters = [
            'status'      => $this->query('status', ''),
            'backup_type' => $this->query('backup_type', ''),
        ];

        $backups  = $this->service->list($page, 15, $filters);
        $diskInfo = $this->getDiskInfo();

        $this->render('settings.backups.index', [
            'pageTitle'  => 'Backup & Restore',
            'backups'    => $backups,
            'filters'    => $filters,
            'diskInfo'   => $diskInfo,
        ]);
    }

    // ── POST /settings/backups/create ────────────────────────────────────
    public function create(): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $notes  = trim($this->input('notes', ''));
        $result = $this->service->createBackup('manual', $this->userId());

        if ($result['success'] && $notes) {
            // Attach notes via model (direct update after creation)
            $model = new \AAM\Settings\Models\BackupModel();
            $model->update($result['id'], ['notes' => $notes]);
        }

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(['filename' => $result['filename'], 'id' => $result['id']], 'Backup created successfully.')
                : $this->jsonError($result['error'] ?? 'Backup failed.');
            return;
        }

        if ($result['success']) {
            $this->flash(FLASH_SUCCESS, "Backup created: {$result['filename']}");
        } else {
            $this->flash(FLASH_ERROR, 'Backup failed: ' . ($result['error'] ?? 'Unknown error'));
        }
        $this->redirect('/settings/backups');
    }

    // ── GET /settings/backups/{id}/download ───────────────────────────────
    public function download(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->service->downloadBackup((int) $id);
    }

    // ── POST /settings/backups/{id}/verify ───────────────────────────────
    public function verify(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->service->verifyBackup((int) $id);
        $result['success']
            ? $this->jsonSuccess(null, 'Backup verified successfully.')
            : $this->jsonError($result['error'] ?? 'Verification failed.');
    }

    // ── POST /settings/backups/{id}/delete ───────────────────────────────
    public function destroy(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->service->deleteBackup((int) $id);

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(null, 'Backup deleted.')
                : $this->jsonError($result['error'] ?? 'Delete failed.');
            return;
        }

        $result['success']
            ? $this->flash(FLASH_SUCCESS, 'Backup deleted.')
            : $this->flash(FLASH_ERROR, $result['error'] ?? 'Delete failed.');
        $this->redirect('/settings/backups');
    }

    // ── POST /settings/backups/{id}/restore ──────────────────────────────
    public function restore(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->service->restoreBackup((int) $id, (int) $this->userId());

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(['pre_restore_id' => $result['pre_restore_id'] ?? null], 'Database restored successfully.')
                : $this->jsonError($result['error'] ?? 'Restore failed.');
            return;
        }

        if ($result['success']) {
            $this->flash(FLASH_SUCCESS, 'Database restored. Pre-restore backup ID: ' . ($result['pre_restore_id'] ?? 'N/A'));
        } else {
            $this->flash(FLASH_ERROR, 'Restore failed: ' . ($result['error'] ?? 'Unknown'));
        }
        $this->redirect('/settings/backups');
    }

    // ── Utility: disk info ────────────────────────────────────────────────
    private function getDiskInfo(): array
    {
        $path  = defined('ROOT_PATH') ? ROOT_PATH : '/';
        $free  = disk_free_space($path);
        $total = disk_total_space($path);
        $used  = $total - $free;
        return [
            'free_gb'      => round($free  / 1024 ** 3, 2),
            'total_gb'     => round($total / 1024 ** 3, 2),
            'used_gb'      => round($used  / 1024 ** 3, 2),
            'percent_used' => $total > 0 ? round($used / $total * 100, 1) : 0,
        ];
    }
}
