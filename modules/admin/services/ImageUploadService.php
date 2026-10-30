<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File        : modules/admin/services/ImageUploadService.php
 * Purpose     : Centralised image upload service.
 *               Handles all image upload operations for the
 *               admin module: profile photos, product images.
 *               Validates, stores, cleans up old files, and
 *               returns stored filename + public URL.
 * Dependencies: UploadHelper, FileHelper, Logger.
 * How it works:
 *   uploadProfilePhoto()  → validate → upload → delete old → return filename
 *   uploadProductImage()  → validate → upload → return filename
 *   deleteProfilePhoto()  → delete file from disk
 *   deleteProductImage()  → delete file from disk
 *   getProfileUrl()       → returns public URL for a stored profile photo
 *   getProductUrl()       → returns public URL for a stored product image
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Admin\Services;

use AAM\Helpers\UploadHelper;
use AAM\Core\Logger;

class ImageUploadService
{
    /** Allowed MIME types for profile photos */
    private const PROFILE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Allowed extensions for profile photos */
    private const PROFILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Max file size for profile photos: 5 MB */
    private const PROFILE_MAX_BYTES = 5 * 1024 * 1024;

    /** Allowed MIME types for product images */
    private const PRODUCT_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Allowed extensions for product images */
    private const PRODUCT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Max file size for product images: 10 MB */
    private const PRODUCT_MAX_BYTES = 10 * 1024 * 1024;

    // ─── Profile Photo ────────────────────────────────────────────────────────

    /**
     * Upload a profile photo.
     * Deletes the old photo if provided.
     *
     * @param  array<string, mixed> $file        $_FILES element
     * @param  string|null          $oldFilename Existing photo to delete
     * @return array{success: bool, message: string, filename: string|null, url: string|null}
     */
    public function uploadProfilePhoto(array $file, ?string $oldFilename = null): array
    {
        // Validate
        $errors = $this->validateImage($file, self::PROFILE_EXTENSIONS, self::PROFILE_MIME_TYPES, self::PROFILE_MAX_BYTES);
        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(' ', $errors), 'filename' => null, 'url' => null];
        }

        // Delete old file
        if ($oldFilename) {
            $this->deleteProfilePhoto($oldFilename);
        }

        // Upload
        try {
            $result = UploadHelper::profileImage($file);
            Logger::info('Profile photo uploaded', ['file' => $result['filename']]);
            return [
                'success'  => true,
                'message'  => 'Profile photo uploaded successfully.',
                'filename' => $result['filename'],
                'url'      => $result['url'],
            ];
        } catch (\Throwable $e) {
            Logger::error('Profile photo upload failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed. Please try again.', 'filename' => null, 'url' => null];
        }
    }

    /**
     * Upload a product image.
     * Deletes the old image if provided.
     *
     * @param  array<string, mixed> $file        $_FILES element
     * @param  string|null          $oldFilename Existing image to delete
     * @return array{success: bool, message: string, filename: string|null, url: string|null}
     */
    public function uploadProductImage(array $file, ?string $oldFilename = null): array
    {
        $errors = $this->validateImage($file, self::PRODUCT_EXTENSIONS, self::PRODUCT_MIME_TYPES, self::PRODUCT_MAX_BYTES);
        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(' ', $errors), 'filename' => null, 'url' => null];
        }

        if ($oldFilename) {
            $this->deleteProductImage($oldFilename);
        }

        try {
            $result = UploadHelper::productImage($file);
            Logger::info('Product image uploaded', ['file' => $result['filename']]);
            return [
                'success'  => true,
                'message'  => 'Product image uploaded successfully.',
                'filename' => $result['filename'],
                'url'      => $result['url'],
            ];
        } catch (\Throwable $e) {
            Logger::error('Product image upload failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed. Please try again.', 'filename' => null, 'url' => null];
        }
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    /**
     * Delete a stored profile photo file from disk.
     */
    public function deleteProfilePhoto(string $filename): bool
    {
        if (empty($filename)) {
            return false;
        }
        $deleted = UploadHelper::deleteProfileImage($filename);
        if ($deleted) {
            Logger::info('Profile photo deleted', ['file' => $filename]);
        }
        return $deleted;
    }

    /**
     * Delete a stored product image file from disk.
     */
    public function deleteProductImage(string $filename): bool
    {
        if (empty($filename)) {
            return false;
        }
        $deleted = UploadHelper::deleteProductImage($filename);
        if ($deleted) {
            Logger::info('Product image deleted', ['file' => $filename]);
        }
        return $deleted;
    }

    // ─── URL Helpers ──────────────────────────────────────────────────────────

    /**
     * Get the public URL for a stored profile photo.
     * Returns placeholder if filename is empty.
     *
     * @param string|null $filename
     */
    public function getProfileUrl(?string $filename): string
    {
        return UploadHelper::profileImageUrl($filename);
    }

    /**
     * Get the public URL for a stored product image.
     * Returns placeholder if filename is empty.
     *
     * @param string|null $filename
     */
    public function getProductUrl(?string $filename): string
    {
        return UploadHelper::productImageUrl($filename);
    }

    // ─── Internal Validation ──────────────────────────────────────────────────

    /**
     * Validate an uploaded file against type and size constraints.
     *
     * @param  array<string, mixed> $file
     * @param  string[]             $allowedExtensions
     * @param  string[]             $allowedMimes
     * @param  int                  $maxBytes
     * @return string[]  Error messages (empty = valid)
     */
    private function validateImage(
        array  $file,
        array  $allowedExtensions,
        array  $allowedMimes,
        int    $maxBytes
    ): array {
        $errors = [];

        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['No file was selected.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['Upload error (code: ' . $file['error'] . '). Please try again.'];
        }

        // Size check
        if ($file['size'] > $maxBytes) {
            $mb       = round($maxBytes / 1024 / 1024, 0);
            $errors[] = "File size must not exceed {$mb} MB.";
        }

        // Extension check
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $errors[] = 'Allowed file types: ' . implode(', ', array_map('strtoupper', $allowedExtensions)) . '.';
        }

        // MIME type check
        if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($mime && !in_array($mime, $allowedMimes, true)) {
                $errors[] = 'File content type is not allowed.';
            }
        }

        return $errors;
    }
}
