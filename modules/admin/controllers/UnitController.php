<?php
/**
 * =========================================================
 * UNIT CONTROLLER
 * =========================================================
 * File    : modules/admin/controllers/UnitController.php
 * Purpose : Unit of Measurement CRUD + AJAX helpers
 * Deps    : BaseController, UnitModel, ActivityLogModel,
 *           ProductValidationService
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Admin\Models\UnitModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Admin\Services\ProductValidationService;

class UnitController extends BaseController
{
    private UnitModel $unitModel;
    private ActivityLogModel $activityLog;
    private ProductValidationService $validator;

    public function __construct()
    {
        parent::__construct();
        $this->unitModel   = new UnitModel();
        $this->activityLog = new ActivityLogModel();
        $this->validator   = new ProductValidationService();
    }

    // â”€â”€ List â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(): void
    {
        $filters = [
            'search'    => $this->query('search', ''),
            'status'    => $this->query('status', ''),
            'unit_type' => $this->query('unit_type', ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $units  = $this->unitModel->getAllWithCreator($filters, $perPage, $offset);
        $total  = $this->unitModel->countWithFilters($filters);
        $stats  = $this->unitModel->getStatistics();

        $this->render('admin/units/index', [
            'title'      => 'Units of Measurement',
            'units'      => $units,
            'filters'    => $filters,
            'stats'      => $stats,
            'unit_types' => $this->unitModel->getUnitTypes(),
            'pagination' => [
                'current_page'  => $page,
                'total_pages'   => (int)ceil($total / $perPage),
                'total_records' => $total,
                'per_page'      => $perPage,
            ],
        ]);
    }

    // â”€â”€ Create form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create(): void
    {
        $this->render('admin/units/create', [
            'title'      => 'Add Unit',
            'unit_types' => $this->unitModel->getUnitTypes(),
        ]);
    }

    // â”€â”€ Store â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(): void
    {
        $this->verifyCsrf();

        $data = [
            'name'         => trim($this->input('unit_name', '')),
            'abbreviation' => trim($this->input('abbreviation', '')),
            'description'  => trim($this->input('description', '')),
            'unit_type'    => $this->input('unit_type') ?: null,
            'is_active'    => (int)$this->input('is_active', 1),
            // aliases for validation only
            'unit_name'    => trim($this->input('unit_name', '')),
            'unit_code'    => trim($this->input('unit_name', '')),
        ];

        $v = $this->validator->validateUnit($data);
        if (!$v['valid']) {
            $this->jsonError('Validation failed', HTTP_UNPROCESSABLE_ENTITY, $v['errors']);
            return;
        }

        $id = $this->unitModel->create($data);
        if ($id) {
            $this->activityLog->log(
                ActivityLogModel::ACTION_USER_CREATED,
                "Created unit: {$data['name']}",
                'units_of_measure', $id, $this->userId()
            );
            $this->jsonSuccess(['unit_id' => $id, 'redirect' => url('admin/units')], 'Unit created');
        } else {
            $this->jsonError('Failed to create unit', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(int $id): void
    {
        $unit = $this->unitModel->getByIdWithRelations($id);
        if (!$unit) {
            $this->redirectWith(url('admin/units'), FLASH_ERROR, 'Unit not found');
            return;
        }
        $this->render('admin/units/edit', [
            'title'      => 'Edit Unit',
            'unit'       => $unit,
            'unit_types' => $this->unitModel->getUnitTypes(),
        ]);
    }

    // â”€â”€ Update â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(int $id): void
    {
        $this->verifyCsrf();

        $existing = $this->unitModel->find($id);
        if (!$existing) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        $data = [
            'name'         => trim($this->input('unit_name', '')),
            'abbreviation' => trim($this->input('abbreviation', '')),
            'description'  => trim($this->input('description', '')),
            'unit_type'    => $this->input('unit_type') ?: null,
            'is_active'    => (int)$this->input('is_active', 1),
            // aliases for validation only
            'unit_name'    => trim($this->input('unit_name', '')),
            'unit_code'    => trim($this->input('unit_name', '')),
        ];

        $v = $this->validator->validateUnit($data, $id);
        if (!$v['valid']) {
            $this->jsonError('Validation failed', HTTP_UNPROCESSABLE_ENTITY, $v['errors']);
            return;
        }

        if ($this->unitModel->update($id, $data)) {
            $this->activityLog->log(
                ActivityLogModel::ACTION_USER_UPDATED,
                "Updated unit: {$data['name']}",
                'units_of_measure', $id, $this->userId()
            );
            $this->jsonSuccess(['redirect' => url('admin/units')], 'Unit updated');
        } else {
            $this->jsonError('Failed to update unit', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Toggle status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function toggleStatus(int $id): void
    {
        $this->verifyCsrf();
        $unit = $this->unitModel->find($id);
        if (!$unit) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if ($this->unitModel->toggleStatus($id)) {
            $s = $unit['is_active'] ? 'inactive' : 'active';
            $this->jsonSuccess(['new_status' => $s], "Unit set to {$s}");
        } else {
            $this->jsonError('Status update failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Soft delete â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $unit = $this->unitModel->find($id);
        if (!$unit) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if (!$this->unitModel->canDelete($id)) {
            $this->jsonError('Cannot delete a unit that is used by products', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        if ($this->unitModel->softDelete($id)) {
            $this->activityLog->log(
                ActivityLogModel::ACTION_USER_DELETED,
                "Deleted unit: " . ($unit['name'] ?? $unit['unit_name'] ?? "#{$id}"),
                'units_of_measure', $id, $this->userId()
            );
            $this->jsonSuccess(null, 'Unit deleted');
        } else {
            $this->jsonError('Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Restore â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function restore(int $id): void
    {
        $this->verifyCsrf();
        if ($this->unitModel->restore($id)) {
            $this->jsonSuccess(null, 'Unit restored');
        } else {
            $this->jsonError('Restore failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ AJAX: active list for dropdowns â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function getActiveUnits(): void
    {
        $type  = $this->query('type') ?: null;
        $this->json(['success' => true, 'units' => $this->unitModel->getActiveUnits($type)]);
    }

    // â”€â”€ AJAX: grouped by type â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function getGroupedUnits(): void
    {
        $this->json(['success' => true, 'units' => $this->unitModel->getGroupedByType()]);
    }
}

