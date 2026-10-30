<?php
/**
 * =========================================================
 * WAREHOUSE CONTROLLER
 * =========================================================
 * File    : modules/organization/controllers/WarehouseController.php
 * Purpose : Full lifecycle management for university
 *           warehouses: CRUD, soft delete, restore,
 *           status toggle, live search, CSV export.
 * Deps    : BaseController, WarehouseModel,
 *           WarehouseService, ActivityLogModel
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Organization\Controllers;

use AAM\Core\BaseController;
use AAM\Organization\Models\WarehouseModel;
use AAM\Organization\Models\AssignmentModel;
use AAM\Organization\Services\WarehouseService;
use AAM\Admin\Models\ActivityLogModel;

class WarehouseController extends BaseController
{
    private WarehouseModel   $warehouseModel;
    private AssignmentModel  $assignmentModel;
    private WarehouseService $warehouseService;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        parent::__construct();
        $this->warehouseModel   = new WarehouseModel();
        $this->assignmentModel  = new AssignmentModel();
        $this->warehouseService = new WarehouseService();
        $this->activityLog      = new ActivityLogModel();
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $filters = [
            'search'    => $this->query('search', ''),
            'is_active' => $this->query('is_active', ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $warehouses = $this->warehouseModel->getAllWithRelations($filters, $perPage, $offset);
        $total      = $this->warehouseModel->countWithFilters($filters);

        $this->render('organization/warehouses/index', [
            'title'          => 'Warehouse Management',
            'warehouses'     => $warehouses,
            'filters'        => $filters,
            'stats'          => $this->warehouseModel->getStatistics(),
            'warehouseTypes' => $this->warehouseModel->getWarehouseTypes(),
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
        $this->render('organization/warehouses/create', [
            'title'          => 'Add New Warehouse',
            'warehouseTypes' => $this->warehouseModel->getWarehouseTypes(),
            'nextCode'       => $this->warehouseModel->getNextCode(),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $result = $this->warehouseService->createWarehouse($data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(
                ['warehouse_id' => $result['warehouse_id'], 'redirect' => url('admin/organization/warehouses')],
                'Warehouse created successfully'
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
        $warehouse = $this->warehouseModel->getByIdWithRelations($id);
        if (!$warehouse) {
            $this->redirectWith(url('admin/organization/warehouses'), FLASH_ERROR, 'Warehouse not found');
            return;
        }

        $this->render('organization/warehouses/show', [
            'title'       => 'Warehouse Details',
            'warehouse'   => $warehouse,
            'assignments' => $this->warehouseService->getLocationAssignments('warehouse', $id),
            'history'     => $this->warehouseService->getLocationHistory('warehouse', $id),
        ]);
    }

    // ── Edit form ─────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $warehouse = $this->warehouseModel->getByIdWithRelations($id);
        if (!$warehouse) {
            $this->redirectWith(url('admin/organization/warehouses'), FLASH_ERROR, 'Warehouse not found');
            return;
        }

        $this->render('organization/warehouses/edit', [
            'title'          => 'Edit Warehouse',
            'warehouse'      => $warehouse,
            'warehouseTypes' => $this->warehouseModel->getWarehouseTypes(),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id): void
    {
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $result = $this->warehouseService->updateWarehouse($id, $data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(['redirect' => url('admin/organization/warehouses')], 'Warehouse updated successfully');
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
        $wh = $this->warehouseModel->find($id);
        if (!$wh) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if ($this->warehouseModel->toggleStatus($id)) {
            $status = $wh['is_active'] ? 'inactive' : 'active';
            $this->activityLog->log('warehouse_status_toggle', "Warehouse #{$id} set to {$status}", 'warehouses', $id, $this->userId());
            $this->jsonSuccess(['new_status' => $status], "Warehouse set to {$status}");
        } else {
            $this->jsonError('Status update failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Soft delete (AJAX) ────────────────────────────────────────────────────

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $result = $this->warehouseService->deleteWarehouse($id, $this->userId());
        if ($result['success']) {
            $this->jsonSuccess(null, 'Warehouse deleted');
        } else {
            $this->jsonError($result['error'] ?? 'Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Restore (AJAX) ────────────────────────────────────────────────────────

    public function restore(int $id): void
    {
        $this->verifyCsrf();
        if ($this->warehouseModel->restoreWarehouse($id)) {
            $this->activityLog->log('warehouse_restore', "Warehouse #{$id} restored", 'warehouses', $id, $this->userId());
            $this->jsonSuccess(null, 'Warehouse restored successfully');
        } else {
            $this->jsonError('Restore failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Live search (AJAX) ────────────────────────────────────────────────────

    public function search(): void
    {
        $filters = [
            'search'         => $this->query('q', ''),
            'warehouse_type' => $this->query('type', ''),
            'is_active'      => '1',
        ];
        $this->json(['success' => true, 'warehouses' => $this->warehouseModel->getAllWithRelations($filters, 20, 0)]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exportCsv(): void
    {
        $whs = $this->warehouseModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $this->outputWhData($whs, 'csv');
    }

    // ── Export Excel ──────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $whs = $this->warehouseModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $this->outputWhData($whs, 'xlsx');
    }

    // ── Export PDF ────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $whs = $this->warehouseModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $app = \AAM\Config\Application::get('name', 'Arsi Agri-Manage');
        header('Content-Type: text/html; charset=UTF-8');
        echo $this->pdfOpen('Warehouse Directory', $app);
        echo '<table><thead><tr>
            <th>#</th><th>Code</th><th>Name</th><th>Type</th><th>Campus</th>
            <th>Capacity</th><th>Manager</th><th>Phone</th><th>Status</th><th>Created</th>
        </tr></thead><tbody>';
        foreach ($whs as $i => $w) {
            $s = $w['is_active'] ? 'b-act' : 'b-ina';
            echo '<tr>
                <td>' . ($i+1) . '</td>
                <td><code>' . e($w['warehouse_code']) . '</code></td>
                <td><strong>' . e($w['warehouse_name']) . '</strong></td>
                <td>' . e($w['warehouse_type'] ?? '') . '</td>
                <td>' . e($w['location'] ?? '—') . '</td>
                <td>' . ($w['capacity_tons'] ? number_format((float)$w['capacity_tons'],1).' t' : '—') . '</td>
                <td>' . e($w['manager_name'] ?? '—') . '</td>
                <td>' . e($w['phone'] ?? '—') . '</td>
                <td><span class="' . $s . '">' . ($w['is_active']?'Active':'Inactive') . '</span></td>
                <td>' . e(date('d/m/Y', strtotime($w['created_at']))) . '</td>
            </tr>';
        }
        echo '</tbody></table>';
        echo '<p style="text-align:right;color:#888;font-size:10px;margin-top:8px">Total: ' . count($whs) . ' warehouses</p>';
        echo '</body></html>';
        exit;
    }

    // ── Active warehouses for dropdowns ──────────────────────────────────────

    public function getActiveWarehouses(): void
    {
        $this->json(['success' => true, 'warehouses' => $this->warehouseModel->getActiveWarehousesForSelect()]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getExportFilters(): array
    {
        return [
            'warehouse_type' => $this->query('warehouse_type', ''),
            'is_active'      => $this->query('is_active', ''),
        ];
    }

    private function outputWhData(array $rows, string $ext): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="warehouses_' . date('Ymd_His') . '.' . $ext . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code','Name','Type','Campus','Building','City','Address',
                       'Cap (sqm)','Cap (tons)','Manager','Phone','Email',
                       'Active','Created At','Updated At']);
        foreach ($rows as $w) {
            fputcsv($out, [
                $w['warehouse_code'], $w['warehouse_name'], $w['warehouse_type'],
                $w['location'] ?? '',
                $w['address'] ?? '', $w['capacity_sqm'] ?? '', $w['capacity_tons'] ?? '',
                $w['manager_name'] ?? '', $w['phone'] ?? '', $w['email'] ?? '',
                $w['is_active'] ? 'Yes' : 'No',
                $w['created_at'], $w['updated_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function pdfOpen(string $title, string $appName): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . e($title) . '</title>
        <style>
            body{font-family:Arial,sans-serif;font-size:11px;color:#222;margin:20px}
            h2{margin:0 0 3px;font-size:14px}.sub{color:#888;font-size:10px;margin-bottom:12px}
            table{width:100%;border-collapse:collapse}
            th{background:#1a1d21;color:#fff;padding:5px 7px;font-size:9px;text-transform:uppercase;letter-spacing:.04em}
            td{padding:4px 7px;border-bottom:1px solid #eee;vertical-align:top}
            tr:nth-child(even) td{background:#f8f9fa}
            .b-act{background:#d1e7dd;color:#0f5132;padding:1px 7px;border-radius:8px;font-size:9px;font-weight:700}
            .b-ina{background:#e2e3e5;color:#41464b;padding:1px 7px;border-radius:8px;font-size:9px;font-weight:700}
            .no-print{text-align:right;margin-bottom:10px}
            .no-print button{padding:5px 14px;background:#0d6efd;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:11px}
            @media print{.no-print{display:none}body{margin:6px}}
        </style></head><body>
        <div class="no-print"><button onclick="window.print()">&#128438; Print / Save PDF</button></div>
        <h2>' . e($appName) . ' — ' . e($title) . '</h2>
        <div class="sub">Generated: ' . date('d/m/Y H:i') . '</div>';
    }

    // ── Private: collect form data ────────────────────────────────────────────

    private function collectFormData(): array
    {
        return [
            'name'                      => trim($this->input('name', '')),
            'warehouse_name'            => trim($this->input('name', '')),  // keep in sync
            'warehouse_type'            => $this->input('warehouse_type', 'CENTRAL'),
            'location'                  => trim($this->input('location', '')),
            'description'               => trim($this->input('description', '')),
            'is_active'                 => (int)$this->input('is_active', 1),
            'seed_distribution_enabled' => (int)$this->input('seed_distribution_enabled', 0),
        ];
    }
}
