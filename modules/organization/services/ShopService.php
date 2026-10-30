<?php
declare(strict_types=1);

namespace AAM\Organization\Services;

use AAM\Organization\Models\ShopModel;
use AAM\Organization\Models\AssignmentModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Helpers\UploadHelper;
use AAM\Core\Logger;

class ShopService
{
    private ShopModel        $shopModel;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        $this->shopModel   = new ShopModel();
        $this->activityLog = new ActivityLogModel();
    }

    public function createShop(array $data, ?int $userId): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => 'Shop name is required']];
        }
        if (mb_strlen($name) < 2) {
            return ['success' => false, 'errors' => ['name' => 'Shop name must be at least 2 characters']];
        }
        if ($this->shopModel->nameExists($name)) {
            return ['success' => false, 'errors' => ['name' => 'A shop with this name already exists']];
        }

        $code = $this->shopModel->getNextCode();

        $insert = [
            'name'        => $name,
            'shop_name'   => $name,
            'shop_code'   => $code,
            'shop_type'   => $data['shop_type'] ?? 'RETAIL',
            'location'    => trim($data['location'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'is_active'   => (int)($data['is_active'] ?? 1),
            'created_by'  => $userId,
        ];

        // ── Handle image upload ───────────────────────────────────────────────
        $imageFile = $data['image_file'] ?? null;
        if ($imageFile && isset($imageFile['error']) && $imageFile['error'] === UPLOAD_ERR_OK) {
            $imageResult = $this->uploadShopImage($imageFile);
            if ($imageResult['success']) {
                $insert['shop_image'] = $imageResult['filename'];
            } else {
                return ['success' => false, 'errors' => ['shop_image' => $imageResult['message']]];
            }
        }

        $id = $this->shopModel->create($insert);
        if ($id) {
            $this->activityLog->log('shop_create', "Shop '{$name}' [{$code}] created", 'shops', $id, $userId);
            return ['success' => true, 'shop_id' => $id, 'shop_code' => $code];
        }

        return ['success' => false, 'errors' => ['general' => 'Failed to save shop to database']];
    }

    public function updateShop(int $id, array $data, ?int $userId): array
    {
        $shop = $this->shopModel->find($id);
        if (!$shop) {
            return ['success' => false, 'errors' => ['general' => 'Shop not found']];
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => 'Shop name is required']];
        }
        if ($this->shopModel->nameExists($name, $id)) {
            return ['success' => false, 'errors' => ['name' => 'A shop with this name already exists']];
        }

        $update = [
            'name'        => $name,
            'shop_name'   => $name,
            'shop_type'   => $data['shop_type'] ?? $shop['shop_type'] ?? 'RETAIL',
            'location'    => trim($data['location'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'is_active'   => (int)($data['is_active'] ?? 1),
            'updated_by'  => $userId,
        ];

        // ── Handle image upload ───────────────────────────────────────────────
        $imageFile = $data['image_file'] ?? null;

        if ($imageFile && isset($imageFile['error']) && $imageFile['error'] === UPLOAD_ERR_OK) {
            $imageResult = $this->uploadShopImage($imageFile, $shop['shop_image'] ?? null);
            if ($imageResult['success']) {
                $update['shop_image'] = $imageResult['filename'];
            } else {
                return ['success' => false, 'errors' => ['shop_image' => $imageResult['message']]];
            }
        }

        // ── Handle explicit image removal ─────────────────────────────────────
        if (!empty($data['remove_image'])) {
            $this->deleteShopImageFile($shop['shop_image'] ?? null);
            $update['shop_image'] = null;
        }

        $this->shopModel->update($id, $update);
        $this->activityLog->log('shop_update', "Shop #{$id} updated", 'shops', $id, $userId);
        return ['success' => true];
    }

    /**
     * Remove only the image from a shop (does NOT delete the shop record).
     */
    public function removeShopImage(int $id, ?int $userId): array
    {
        $shop = $this->shopModel->find($id);
        if (!$shop) {
            return ['success' => false, 'error' => 'Shop not found'];
        }
        $this->deleteShopImageFile($shop['shop_image'] ?? null);
        $this->shopModel->update($id, ['shop_image' => null, 'updated_by' => $userId]);
        $this->activityLog->log('shop_image_remove', "Shop #{$id} image removed", 'shops', $id, $userId);
        return ['success' => true];
    }

    public function deleteShop(int $id, ?int $userId): array
    {
        if (!$this->shopModel->find($id)) {
            return ['success' => false, 'error' => 'Shop not found'];
        }
        $this->shopModel->softDelete($id);
        $this->activityLog->log('shop_delete', "Shop #{$id} deleted", 'shops', $id, $userId);
        return ['success' => true];
    }

    public function getLocationAssignments(string $type, int $id): array
    {
        try {
            return (new AssignmentModel())->getActiveForLocation($type, $id);
        } catch (\Throwable) { return []; }
    }

    public function getLocationHistory(string $type, int $id): array
    {
        try {
            return (new AssignmentModel())->getHistoryForLocation($type, $id, 20);
        } catch (\Throwable) { return []; }
    }

    // ── Image helpers ─────────────────────────────────────────────────────────

    /**
     * Upload a shop image, deleting the old one if provided.
     * Returns {success, message, filename}.
     */
    private function uploadShopImage(array $file, ?string $oldFilename = null): array
    {
        // Validate
        $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $maxBytes    = 5 * 1024 * 1024; // 5 MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload error. Please try again.', 'filename' => null];
        }
        if ($file['size'] > $maxBytes) {
            return ['success' => false, 'message' => 'Shop image must not exceed 5 MB.', 'filename' => null];
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return ['success' => false, 'message' => 'Allowed image types: JPG, JPEG, PNG, WEBP.', 'filename' => null];
        }
        // MIME check
        if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime && !in_array($mime, $allowedMime, true)) {
                return ['success' => false, 'message' => 'File content type is not allowed.', 'filename' => null];
            }
            // Confirm it's actually an image
            if (@getimagesize($file['tmp_name']) === false) {
                return ['success' => false, 'message' => 'Invalid image file.', 'filename' => null];
            }
        }

        // Delete old image
        if ($oldFilename) {
            $this->deleteShopImageFile($oldFilename);
        }

        try {
            $result = UploadHelper::shopImage($file);
            Logger::info('Shop image uploaded', ['file' => $result['filename']]);
            return ['success' => true, 'message' => 'Image uploaded.', 'filename' => $result['filename']];
        } catch (\Throwable $e) {
            Logger::error('Shop image upload failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed. Please try again.', 'filename' => null];
        }
    }

    private function deleteShopImageFile(?string $filename): void
    {
        if ($filename) {
            UploadHelper::deleteShopImage($filename);
        }
    }
}
