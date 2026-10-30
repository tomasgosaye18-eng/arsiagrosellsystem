<?php
/**
 * =========================================================
 * CATEGORY SERVICE
 * =========================================================
 * File    : modules/admin/services/CategoryService.php
 * Purpose : Category business logic with activity logging
 * Deps    : CategoryModel, ActivityLogModel,
 *           ProductValidationService
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Services;

use AAM\Admin\Models\CategoryModel;
use AAM\Admin\Models\ActivityLogModel;

class CategoryService
{
    private CategoryModel $categoryModel;
    private ActivityLogModel $activityLog;
    private ProductValidationService $validationService;

    public function __construct()
    {
        $this->categoryModel   = new CategoryModel();
        $this->activityLog     = new ActivityLogModel();
        $this->validationService = new ProductValidationService();
    }

    public function createCategory(array $data, int $userId): array
    {
        $v = $this->validationService->validateCategory($data);
        if (!$v['valid']) {
            return ['success' => false, 'errors' => $v['errors']];
        }

        $data['created_by'] = $userId;
        $id = $this->categoryModel->create($data);

        if ($id) {
            $this->activityLog->log(
                'category.created',
                "Created category: {$data['category_name']}",
                'product_categories', $id, $userId
            );
            return ['success' => true, 'category_id' => $id];
        }

        return ['success' => false, 'errors' => ['general' => 'Failed to create category']];
    }

    public function updateCategory(int $id, array $data, int $userId): array
    {
        $existing = $this->categoryModel->find($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['general' => 'Category not found']];
        }

        $v = $this->validationService->validateCategory($data, $id);
        if (!$v['valid']) {
            return ['success' => false, 'errors' => $v['errors']];
        }

        $affected = $this->categoryModel->update($id, $data);

        if ($affected !== false) {
            $this->activityLog->log(
                'category.updated',
                "Updated category: {$data['category_name']}",
                'product_categories', $id, $userId
            );
            return ['success' => true];
        }

        return ['success' => false, 'errors' => ['general' => 'Failed to update category']];
    }

    public function deleteCategory(int $id, int $userId): array
    {
        $category = $this->categoryModel->find($id);
        if (!$category) {
            return ['success' => false, 'error' => 'Category not found'];
        }

        if (!$this->categoryModel->canDelete($id)) {
            return ['success' => false, 'error' => 'Cannot delete a category that has products'];
        }

        // Use BaseModel::delete() which does soft-delete (is_active=0)
        $affected = $this->categoryModel->delete($id);

        if ($affected > 0) {
            $this->activityLog->log(
                'category.deleted',
                "Deleted category: {$category['category_name']}",
                'product_categories', $id, $userId
            );
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Delete failed'];
    }

    public function generateCode(): string
    {
        return $this->categoryModel->getNextCode();
    }
}
