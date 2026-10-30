<?php
declare(strict_types=1);

namespace AAM\Organization\Controllers;

use AAM\Core\BaseController;
use AAM\Organization\Models\FarmModel;
use AAM\Organization\Models\AssignmentModel;
use AAM\Organization\Services\FarmService;
use AAM\Admin\Models\ActivityLogModel;

class FarmController extends BaseController
{
    private FarmModel        $farmModel;
    private AssignmentModel  $assignmentModel;
    private FarmService      $farmService;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        parent::__construct();
        $this->farmModel       = new FarmModel();
        $this->assignmentModel = new AssignmentModel();
        $this->farmService     = new FarmService();
        $this->activityLog     = new ActivityLogModel();
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $filters = [
            'search'    => $this->query('search', ''),
            'farm_type' => $this->query('farm_type', ''),
            'is_active' => $this->query('is_active', ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $farms = $this->farmModel->getAllWithRelations($filters, $perPage, $offset);
        $total = $this->farmModel->countWithFilters($filters);

        $this->render('organization/farms/index', [
            'title'     => 'Farm Management',
            'farms'     => $farms,
            'filters'   => $filters,
            'stats'     => $this->farmModel->getStatistics(),
            'farmTypes' => $this->farmModel->getFarmTypes(),
            'pagination' => [
                'current_page'  => $page,
                'total_pages'   => (int)ceil($total / $perPage),
                'total_records' => $total,
                'per_page'      => $perPage,
                'from'          => $total > 0 ? $offset + 1 : 0,
                'to'            => min($offset + $perPage, $total),
            ],
        ]);
    }

    // ── Create form ───────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->render('organization/farms/create', [
            'title'     => 'Add New Farm',
            'farmTypes' => $this->farmModel->getFarmTypes(),
            'nextCode'  => $this->farmModel->getNextCode(),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $result = $this->farmService->createFarm($data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(
                ['farm_id' => $result['farm_id'], 'redirect' => url('admin/organization/farms')],
                'Farm created successfully'
            );
        } else {
            $this->jsonError(
                $result['errors']['general'] ?? 'Validation failed',
                HTTP_UNPROCESSABLE_ENTITY,
                $result['errors']
            );
        }
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $farm = $this->farmModel->getByIdWithRelations($id);
        if (!$farm) {
            $this->redirectWith(url('admin/organization/farms'), FLASH_ERROR, 'Farm not found');
            return;
        }

        $this->render('organization/farms/show', [
            'title'       => 'Farm Details',
            'farm'        => $farm,
            'assignments' => $this->farmService->getLocationAssignments('farm', $id),
            'history'     => $this->farmService->getLocationHistory('farm', $id),
        ]);
    }

    // ── Edit form ─────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $farm = $this->farmModel->getByIdWithRelations($id);
        if (!$farm) {
            $this->redirectWith(url('admin/organization/farms'), FLASH_ERROR, 'Farm not found');
            return;
        }

        $this->render('organization/farms/edit', [
            'title'     => 'Edit Farm',
            'farm'      => $farm,
            'farmTypes' => $this->farmModel->getFarmTypes(),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id): void
    {
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $result = $this->farmService->updateFarm($id, $data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(['redirect' => url('admin/organization/farms')], 'Farm updated successfully');
        } else {
            $this->jsonError(
                $result['errors']['general'] ?? 'Validation failed',
                HTTP_UNPROCESSABLE_ENTITY,
                $result['errors']
            );
        }
    }

    // ── Toggle status (AJAX) ──────────────────────────────────────────────────

    public function toggleStatus(int $id): void
    {
        $this->verifyCsrf();
        $farm = $this->farmModel->find($id);
        if (!$farm) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if ($this->farmModel->toggleStatus($id)) {
            $status = $farm['is_active'] ? 'inactive' : 'active';
            $this->activityLog->log('farm_status_toggle', "Farm #{$id} set to {$status}", 'farms', $id, $this->userId());
            $this->jsonSuccess(['new_status' => $status], "Farm set to {$status}");
        } else {
            $this->jsonError('Status update failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Soft delete (AJAX) ────────────────────────────────────────────────────

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $result = $this->farmService->deleteFarm($id, $this->userId());
        if ($result['success']) {
            $this->jsonSuccess(null, 'Farm deleted');
        } else {
            $this->jsonError($result['error'] ?? 'Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Restore (AJAX) ────────────────────────────────────────────────────────

    public function restore(int $id): void
    {
        $this->verifyCsrf();
        if ($this->farmModel->restoreFarm($id)) {
            $this->activityLog->log('farm_restore', "Farm #{$id} restored", 'farms', $id, $this->userId());
            $this->jsonSuccess(null, 'Farm restored successfully');
        } else {
            $this->jsonError('Restore failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Live search (AJAX) ────────────────────────────────────────────────────

    public function search(): void
    {
        $filters = [
            'search'    => $this->query('q', ''),
            'farm_type' => $this->query('type', ''),
            'is_active' => '1',
        ];
        $this->json(['success' => true, 'farms' => $this->farmModel->getAllWithRelations($filters, 20, 0)]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exportCsv(): void
    {
        $farms = $this->farmModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $this->outputFarmData($farms, 'csv');
    }

    // ── Export Excel ──────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $farms = $this->farmModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $this->outputFarmData($farms, 'xlsx');
    }

    // ── Export PDF ────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $farms = $this->farmModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $this->renderFarmPdf($farms);
    }

    // ── Active farms for dropdowns (AJAX) ────────────────────────────────────

    public function getActiveFarms(): void
    {
        $this->json(['success' => true, 'farms' => $this->farmModel->getActiveFarmsForSelect()]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getExportFilters(): array
    {
        return [
            'farm_type' => $this->query('farm_type', ''),
            'is_active' => $this->query('is_active', ''),
        ];
    }

    private function outputFarmData(array $farms, string $ext): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="farms_' . date('Ymd_His') . '.' . $ext . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code','Name','Type','Location','Manager','Active','Created At']);
        foreach ($farms as $f) {
            fputcsv($out, [
                $f['farm_code']    ?? '',
                $f['farm_name']    ?? $f['name'] ?? '',
                $f['farm_type']    ?? '',
                $f['location']     ?? '',
                $f['manager_name'] ?? '',
                $f['is_active'] ? 'Yes' : 'No',
                $f['created_at']   ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function renderFarmPdf(array $farms): void
    {
        $app = \AAM\Config\Application::get('name', 'Arsi Agri-Manage');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Farm Directory</title>
        <style>body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
        table{width:100%;border-collapse:collapse}th{background:#198754;color:#fff;padding:5px 7px;font-size:9px}
        td{padding:4px 7px;border-bottom:1px solid #eee}tr:nth-child(even) td{background:#f8f9fa}
        .act{background:#d1e7dd;color:#0f5132;padding:1px 6px;border-radius:8px;font-size:9px}
        .ina{background:#e2e3e5;color:#41464b;padding:1px 6px;border-radius:8px;font-size:9px}
        </style></head><body>';
        echo '<h2>' . e($app) . ' — Farm Directory</h2><p style="color:#888;font-size:10px">Generated: ' . date('d/m/Y H:i') . '</p>';
        echo '<table><thead><tr><th>#</th><th>Code</th><th>Name</th><th>Type</th><th>Location</th><th>Status</th></tr></thead><tbody>';
        foreach ($farms as $i => $f) {
            $cls = $f['is_active'] ? 'act' : 'ina';
            $lbl = $f['is_active'] ? 'Active' : 'Inactive';
            echo '<tr><td>' . ($i+1) . '</td><td><code>' . e($f['farm_code'] ?? '') . '</code></td>'
                . '<td>' . e($f['farm_name'] ?? $f['name'] ?? '') . '</td>'
                . '<td>' . e($f['farm_type'] ?? '') . '</td>'
                . '<td>' . e($f['location'] ?? '') . '</td>'
                . '<td><span class="' . $cls . '">' . $lbl . '</span></td></tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    // ── Private: collect form data (NO farm_code — never from user input) ─────

    private function collectFormData(): array
    {
        return [
            'name'        => trim($this->input('name', '')),
            'farm_name'   => trim($this->input('name', '')),
            'farm_type'   => $this->input('farm_type', 'MIXED'),
            'location'    => trim($this->input('location', '')),
            'description' => trim($this->input('description', '')),
            'is_active'   => (int)$this->input('is_active', 1),
        ];
    }
}
