<?php
/**
 * =========================================================
 * SHOP CONTROLLER (Admin Organization Module)
 * =========================================================
 * File    : modules/organization/controllers/ShopController.php
 * Purpose : Full lifecycle management for retail shops:
 *           CRUD, soft delete, restore, status toggle,
 *           live search, CSV export, AJAX endpoints.
 * Deps    : BaseController, ShopModel,
 *           ShopService, ActivityLogModel
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Organization\Controllers;

use AAM\Core\BaseController;
use AAM\Organization\Models\ShopModel;
use AAM\Organization\Models\AssignmentModel;
use AAM\Organization\Services\ShopService;
use AAM\Admin\Models\ActivityLogModel;

class ShopController extends BaseController
{
    private ShopModel        $shopModel;
    private AssignmentModel  $assignmentModel;
    private ShopService      $shopService;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        parent::__construct();
        $this->shopModel       = new ShopModel();
        $this->assignmentModel = new AssignmentModel();
        $this->shopService     = new ShopService();
        $this->activityLog     = new ActivityLogModel();
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

        $shops = $this->shopModel->getAllWithRelations($filters, $perPage, $offset);
        $total = $this->shopModel->countWithFilters($filters);

        $this->render('organization/shops/index', [
            'title'     => 'Shop Management',
            'shops'     => $shops,
            'filters'   => $filters,
            'stats'     => $this->shopModel->getStatistics(),
            'shopTypes' => $this->shopModel->getShopTypes(),
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
        $this->render('organization/shops/create', [
            'title'     => 'Add New Shop',
            'shopTypes' => $this->shopModel->getShopTypes(),
            'nextCode'  => $this->shopModel->getNextCode(),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->verifyCsrf();

        $data               = $this->collectFormData();
        $data['image_file'] = $this->resolveUploadedFile('shop_image');

        $result = $this->shopService->createShop($data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(
                ['shop_id' => $result['shop_id'], 'redirect' => url('admin/organization/shops')],
                'Shop created successfully'
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
        $shop = $this->shopModel->getByIdWithRelations($id);
        if (!$shop) {
            $this->redirectWith(url('admin/organization/shops'), FLASH_ERROR, 'Shop not found');
            return;
        }

        $this->render('organization/shops/show', [
            'title'       => 'Shop Details',
            'shop'        => $shop,
            'assignments' => $this->shopService->getLocationAssignments('shop', $id),
            'history'     => $this->shopService->getLocationHistory('shop', $id),
        ]);
    }

    // ── Edit form ─────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $shop = $this->shopModel->getByIdWithRelations($id);
        if (!$shop) {
            $this->redirectWith(url('admin/organization/shops'), FLASH_ERROR, 'Shop not found');
            return;
        }

        $this->render('organization/shops/edit', [
            'title'     => 'Edit Shop',
            'shop'      => $shop,
            'shopTypes' => $this->shopModel->getShopTypes(),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id): void
    {
        $this->verifyCsrf();

        $data               = $this->collectFormData();
        $data['image_file'] = $this->resolveUploadedFile('shop_image');
        $result = $this->shopService->updateShop($id, $data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(['redirect' => url('admin/organization/shops')], 'Shop updated successfully');
        } else {
            $this->jsonError(
                $result['errors']['general'] ?? 'Validation failed',
                HTTP_UNPROCESSABLE_ENTITY,
                $result['errors']
            );
        }
    }

    // ── Remove shop image (AJAX) ──────────────────────────────────────────────

    public function removeImage(int $id): void
    {
        $this->verifyCsrf();
        $result = $this->shopService->removeShopImage($id, $this->userId());
        if ($result['success']) {
            $this->jsonSuccess(null, 'Shop image removed');
        } else {
            $this->jsonError($result['error'] ?? 'Remove failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Toggle status (AJAX) ──────────────────────────────────────────────────

    public function toggleStatus(int $id): void
    {
        $this->verifyCsrf();
        $shop = $this->shopModel->find($id);
        if (!$shop) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if ($this->shopModel->toggleStatus($id)) {
            $status = $shop['is_active'] ? 'inactive' : 'active';
            $this->activityLog->log('shop_status_toggle', "Shop #{$id} set to {$status}", 'shops', $id, $this->userId());
            $this->jsonSuccess(['new_status' => $status], "Shop set to {$status}");
        } else {
            $this->jsonError('Status update failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Soft delete (AJAX) ────────────────────────────────────────────────────

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $result = $this->shopService->deleteShop($id, $this->userId());
        if ($result['success']) {
            $this->jsonSuccess(null, 'Shop deleted');
        } else {
            $this->jsonError($result['error'] ?? 'Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Restore (AJAX) ────────────────────────────────────────────────────────

    public function restore(int $id): void
    {
        $this->verifyCsrf();
        if ($this->shopModel->restoreShop($id)) {
            $this->activityLog->log('shop_restore', "Shop #{$id} restored", 'shops', $id, $this->userId());
            $this->jsonSuccess(null, 'Shop restored successfully');
        } else {
            $this->jsonError('Restore failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Live search (AJAX) ────────────────────────────────────────────────────

    public function search(): void
    {
        $filters = [
            'search'    => $this->query('q', ''),
            'shop_type' => $this->query('type', ''),
            'is_active' => '1',
        ];
        $this->json(['success' => true, 'shops' => $this->shopModel->getAllWithRelations($filters, 20, 0)]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exportCsv(): void
    {
        $shops = $this->shopModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $this->outputShopData($shops, 'csv');
    }

    // ── Export Excel ──────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $shops = $this->shopModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $this->outputShopData($shops, 'xlsx');
    }

    // ── Export PDF ────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $shops = $this->shopModel->getAllWithRelations($this->getExportFilters(), 5000, 0);
        $app   = \AAM\Config\Application::get('name', 'Arsi Agri-Manage');
        header('Content-Type: text/html; charset=UTF-8');
        echo $this->pdfOpen('Shop Directory', $app);
        echo '<table><thead><tr>
            <th>#</th><th>Code</th><th>Name</th><th>Type</th><th>Campus</th>
            <th>Hours</th><th>Manager</th><th>Phone</th><th>Status</th><th>Created</th>
        </tr></thead><tbody>';
        foreach ($shops as $i => $s) {
            $cls   = $s['is_active'] ? 'b-act' : 'b-ina';
            $hours = ($s['opening_time'] && $s['closing_time'])
                ? e(substr($s['opening_time'],0,5)).'–'.e(substr($s['closing_time'],0,5))
                : '—';
            echo '<tr>
                <td>' . ($i+1) . '</td>
                <td><code>' . e($s['shop_code']) . '</code></td>
                <td><strong>' . e($s['shop_name']) . '</strong></td>
                <td>' . e($s['shop_type'] ?? '') . '</td>
                <td>' . e($s['location'] ?? '—') . '</td>
                <td>' . $hours . '</td>
                <td>' . e($s['manager_name'] ?? '—') . '</td>
                <td>' . e($s['phone'] ?? '—') . '</td>
                <td><span class="' . $cls . '">' . ($s['is_active']?'Active':'Inactive') . '</span></td>
                <td>' . e(date('d/m/Y', strtotime($s['created_at']))) . '</td>
            </tr>';
        }
        echo '</tbody></table>';
        echo '<p style="text-align:right;color:#888;font-size:10px;margin-top:8px">Total: ' . count($shops) . ' shops</p>';
        echo '</body></html>';
        exit;
    }

    // ── Active shops for dropdowns ────────────────────────────────────────────

    public function getActiveShops(): void
    {
        $this->json(['success' => true, 'shops' => $this->shopModel->getActiveShopsForSelect()]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getExportFilters(): array
    {
        return [
            'shop_type' => $this->query('shop_type', ''),
            'is_active' => $this->query('is_active', ''),
        ];
    }

    private function outputShopData(array $rows, string $ext): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="shops_' . date('Ymd_His') . '.' . $ext . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code','Name','Type','Campus','Building','City','Address',
                       'Manager','Phone','Email','Opens','Closes',
                       'Active','Created At','Updated At']);
        foreach ($rows as $s) {
            fputcsv($out, [
                $s['shop_code'], $s['shop_name'], $s['shop_type'],
                $s['location'] ?? '',
                $s['address'] ?? '', $s['manager_name'] ?? '',
                $s['phone'] ?? '', $s['email'] ?? '',
                $s['opening_time'] ?? '', $s['closing_time'] ?? '',
                $s['is_active'] ? 'Yes' : 'No',
                $s['created_at'], $s['updated_at'] ?? '',
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
            'name'         => trim($this->input('name', '')),
            'shop_name'    => trim($this->input('name', '')),  // keep in sync
            'shop_type'    => $this->input('shop_type', 'RETAIL'),
            'location'     => trim($this->input('location', '')),
            'description'  => trim($this->input('description', '')),
            'is_active'    => (int)$this->input('is_active', 1),
            'remove_image' => $this->input('remove_image', ''),
        ];
    }

    /**
     * Resolve a single usable uploaded file from $_FILES.
     *
     * The edit form can contain two <input name="shop_image"> elements
     * (one for new upload, one for change). When both are present in the DOM,
     * FormData may submit both and PHP stores them as indexed arrays inside
     * $_FILES['shop_image']['name'][0..1] etc.
     *
     * This method normalises both cases:
     *   - Single file:  $_FILES['shop_image'] is a standard file array
     *   - Multiple:     $_FILES['shop_image']['name'] is an array — pick the
     *                   first entry whose error === UPLOAD_ERR_OK
     *
     * Returns null if no valid upload is present.
     */
    private function resolveUploadedFile(string $fieldName): ?array
    {
        if (!isset($_FILES[$fieldName])) {
            return null;
        }

        $f = $_FILES[$fieldName];

        // Standard single-file upload
        if (!is_array($f['name'])) {
            return ($f['error'] === UPLOAD_ERR_NO_FILE) ? null : $f;
        }

        // Multiple inputs with the same name — find the first valid upload
        foreach ($f['name'] as $i => $name) {
            if (isset($f['error'][$i]) && $f['error'][$i] === UPLOAD_ERR_OK) {
                return [
                    'name'     => $f['name'][$i],
                    'type'     => $f['type'][$i],
                    'tmp_name' => $f['tmp_name'][$i],
                    'error'    => $f['error'][$i],
                    'size'     => $f['size'][$i],
                ];
            }
        }

        return null;
    }
}
