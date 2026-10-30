<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/settings/controllers/SettingsController.php
 * Purpose : System settings controller.
 *           Handles backup config.
 *           Security settings are managed via config/security.php
 *           and are no longer exposed through the UI.
 * Deps    : SettingsService, BaseController
 * Routes  : /settings/*  (admin only)
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Controllers;

use AAM\Core\BaseController;
use AAM\Settings\Services\SettingsService;

class SettingsController extends BaseController
{
    private SettingsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new SettingsService();
    }

    // ── GET /settings → redirect to backups ───────────────────────────────
    public function index(): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->redirect('/settings/backups');
    }

    // ── GET /settings/backup ──────────────────────────────────────────────
    public function backup(): void
    {
        $this->requireRole(ROLE_ADMIN);
        $settings = $this->service->getGroup('backup');

        $this->render('settings.backup_config', [
            'pageTitle' => 'Backup Configuration',
            'settings'  => $settings,
        ]);
    }

    // ── POST /settings/backup ─────────────────────────────────────────────
    public function saveBackup(): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $data = [
            'backup.auto_enabled'   => $this->input('auto_enabled', '0'),
            'backup.frequency'      => $this->input('frequency', 'daily'),
            'backup.retention_days' => $this->input('retention_days', '30'),
            'backup.time'           => $this->input('backup_time', '02:00'),
        ];

        $result = $this->service->saveGroup($data, 'backup', (int) $this->userId());
        $this->respondAfterSave($result, '/settings/backup', 'Backup configuration saved.');
    }

    // ── Shared response helper ────────────────────────────────────────────
    private function respondAfterSave(array $result, string $redirect, string $successMsg): void
    {
        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(null, $successMsg)
                : $this->jsonError('Validation failed.', HTTP_BAD_REQUEST, $result['errors']);
            return;
        }
        if ($result['success']) {
            $this->flash(FLASH_SUCCESS, $successMsg);
        } else {
            $this->flash(FLASH_ERROR, implode(' ', $result['errors']));
        }
        $this->redirect($redirect);
    }
}
