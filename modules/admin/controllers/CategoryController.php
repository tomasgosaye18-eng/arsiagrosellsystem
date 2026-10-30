<?php
/**
 * =========================================================
 * CATEGORY CONTROLLER
 * =========================================================
 * File    : modules/admin/controllers/CategoryController.php
 * Purpose : Product category CRUD, toggle, soft-delete,
 *           restore, and active-list AJAX endpoint.
 * Deps    : BaseController, CategoryModel, ActivityLogModel,
 *           ProductValidationService
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Admin\Models\CategoryModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Admin\Services\ProductValidationService;

class CategoryController extends BaseController
{
    private CategoryModel $categoryModel;
    private ActivityLogModel $activityLog;
    private ProductValidationService $validator;

    public function __construct()
    {
        parent::__construct();
        $this->categoryModel = new CategoryModel();
        $this->activityLog   = new ActivityLogModel();
        $this->validator     = new ProductValidationService();
    }

    // â”€â”€ List â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(): void
    {
        $filters = [
            'search' => $this->query('search', ''),
            'status' => $this->query('status', ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $categories = $this->categoryModel->getAllWithCreator($filters, $perPage, $offset);
        $total      = $this->categoryModel->countWithFilters($filters);
        $stats      = $this->categoryModel->getStatistics();

        $this->render('admin/categories/index', [
            'title'      => 'Product Categories',
            'categories' => $categories,
            'filters'    => $filters,
            'stats'      => $stats,
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
        $this->render('admin/categories/create', [
            'title'     => 'Add Category',
            'next_code' => $this->categoryModel->getNextCode(),
        ]);
    }

    // â”€â”€ Store â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(): void
    {
        $this->verifyCsrf();

        $data = [
            'name'          => trim($this->input('category_name', '')),
            'description'   => trim($this->input('description', '')),
            'sort_order'    => (int)$this->input('display_order', 0),
            'is_active'     => (int)$this->input('is_active', 1),
            // alias for validation
            'category_name' => trim($this->input('category_name', '')),
        ];

        $v = $this->validator->validateCategory($data);
        if (!$v['valid']) {
            $this->jsonError('Validation failed', HTTP_UNPROCESSABLE_ENTITY, $v['errors']);
            return;
        }

        $id = $this->categoryModel->create($data);
        if ($id) {
            $this->activityLog->log(
                ActivityLogModel::ACTION_USER_CREATED,
                "Created category: {$data['name']}",
                'product_categories', $id, $this->userId()
            );
            $this->jsonSuccess(['category_id' => $id, 'redirect' => url('admin/categories')], 'Category created');
        } else {
            $this->jsonError('Failed to create category', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Show â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(int $id): void
    {
        $category = $this->categoryModel->getByIdWithRelations($id);
        if (!$category) {
            $this->redirectWith(url('admin/categories'), FLASH_ERROR, 'Category not found');
            return;
        }
        $this->render('admin/categories/show', ['title' => 'Category Details', 'category' => $category]);
    }

    // â”€â”€ Edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(int $id): void
    {
        $category = $this->categoryModel->getByIdWithRelations($id);
        if (!$category) {
            $this->redirectWith(url('admin/categories'), FLASH_ERROR, 'Category not found');
            return;
        }
        $this->render('admin/categories/edit', ['title' => 'Edit Category', 'category' => $category]);
    }

    // â”€â”€ Update â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(int $id): void
    {
        $this->verifyCsrf();

        $existing = $this->categoryModel->find($id);
        if (!$existing) {
            $this->jsonError('Category not found', HTTP_NOT_FOUND);
            return;
        }

        $data = [
            'name'          => trim($this->input('category_name', '')),
            'description'   => trim($this->input('description', '')),
            'sort_order'    => (int)$this->input('display_order', 0),
            'is_active'     => (int)$this->input('is_active', 1),
            // alias for validation
            'category_name' => trim($this->input('category_name', '')),
        ];

        $v = $this->validator->validateCategory($data, $id);
        if (!$v['valid']) {
            $this->jsonError('Validation failed', HTTP_UNPROCESSABLE_ENTITY, $v['errors']);
            return;
        }

        if ($this->categoryModel->update($id, $data)) {
            $this->activityLog->log(
                ActivityLogModel::ACTION_USER_UPDATED,
                "Updated category: {$data['name']}",
                'product_categories',
                $id,
                $this->userId()
            );
            $this->jsonSuccess(['redirect' => url('admin/categories')], 'Category updated');
        } else {
            $this->jsonError('Failed to update category', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Toggle status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function toggleStatus(int $id): void
    {
        $this->verifyCsrf();
        $cat = $this->categoryModel->find($id);
        if (!$cat) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if ($this->categoryModel->toggleStatus($id)) {
            $newStatus = $cat['is_active'] ? 'inactive' : 'active';
            $this->jsonSuccess(['new_status' => $newStatus], "Category set to {$newStatus}");
        } else {
            $this->jsonError('Status update failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Soft delete â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $cat = $this->categoryModel->find($id);
        if (!$cat) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if (!$this->categoryModel->canDelete($id)) {
            $this->jsonError('Cannot delete a category that has products', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        if ($this->categoryModel->softDelete($id)) {
            $this->activityLog->log(
                ActivityLogModel::ACTION_USER_DELETED,
                "Deleted category: " . ($cat['name'] ?? $cat['category_name'] ?? "#{$id}"),
                'product_categories', $id, $this->userId()
            );
            $this->jsonSuccess(null, 'Category deleted');
        } else {
            $this->jsonError('Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Restore â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function restore(int $id): void
    {
        $this->verifyCsrf();
        if ($this->categoryModel->restore($id)) {
            $this->jsonSuccess(null, 'Category restored');
        } else {
            $this->jsonError('Restore failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ AJAX: active list for dropdowns â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function getActiveCategories(): void
    {
        $this->json(['success' => true, 'categories' => $this->categoryModel->getActiveCategories()]);
    }
}

