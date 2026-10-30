<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/UploadHelper.php
 * Purpose  : Upload Helper class.
 *            High-level wrapper over FileHelper.
 *            Handles all upload scenarios in AAM:
 *            product images, profile photos, receipts.
 *            Returns upload result with URL for views.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Helpers;

use AAM\Exceptions\UploadException;

class UploadHelper
{
    /**
     * Handle a product image upload.
     * Returns array with filename and public URL.
     *
     * @param array<string,mixed> $file  $_FILES['image'] element
     * @return array{filename: string, url: string}
     * @throws UploadException
     */
    public static function productImage(array $file): array
    {
        $filename = FileHelper::uploadProductImage($file);
        return [
            'filename' => $filename,
            'url'      => upload_url('products/' . $filename),
        ];
    }

    /**
     * Handle a profile image upload.
     *
     * @param array<string,mixed> $file  $_FILES element
     * @return array{filename: string, url: string}
     * @throws UploadException
     */
    public static function profileImage(array $file): array
    {
        $filename = FileHelper::uploadProfileImage($file);
        return [
            'filename' => $filename,
            'url'      => upload_url('profiles/' . $filename),
        ];
    }

    /**
     * Handle a shop image upload.
     *
     * @param array<string,mixed> $file  $_FILES element
     * @return array{filename: string, url: string}
     * @throws UploadException
     */
    public static function shopImage(array $file): array
    {
        $filename = FileHelper::uploadShopImage($file);
        return [
            'filename' => $filename,
            'url'      => upload_url('shops/' . $filename),
        ];
    }

    /**
     * Delete a product image from storage.
     *
     * @param string $filename  Stored filename (not full path)
     */
    public static function deleteProductImage(string $filename): bool
    {
        return FileHelper::delete($filename, UPLOAD_PRODUCT_IMAGE_DIR);
    }

    /**
     * Delete a profile image from storage.
     */
    public static function deleteProfileImage(string $filename): bool
    {
        return FileHelper::delete($filename, UPLOAD_PROFILE_IMAGE_DIR);
    }

    /**
     * Delete a shop image from storage.
     */
    public static function deleteShopImage(string $filename): bool
    {
        return FileHelper::delete($filename, UPLOAD_SHOP_IMAGE_DIR);
    }

    /**
     * Check if a request has a file upload for a given field.
     *
     * @param string $fieldName  Form input name
     */
    public static function hasUpload(string $fieldName): bool
    {
        return isset($_FILES[$fieldName])
            && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK
            && $_FILES[$fieldName]['size'] > 0;
    }

    /**
     * Get the public URL for a stored product image.
     * Returns a placeholder URL if filename is empty.
     *
     * @param string|null $filename
     */
    public static function productImageUrl(?string $filename): string
    {
        if (!$filename) {
            return asset('images/no-product.svg');
        }
        return upload_url('products/' . $filename);
    }

    /**
     * Get the public URL for a stored profile image.
     * Returns a placeholder URL if filename is empty.
     * Appends file modification time as cache-bust query string.
     *
     * @param string|null $filename
     */
    public static function profileImageUrl(?string $filename): string
    {
        if (!$filename) {
            return asset('images/no-avatar.svg');
        }

        $url      = upload_url('profiles/' . $filename);
        $fullPath = rtrim(UPLOAD_PROFILE_IMAGE_DIR, '/') . '/' . $filename;
        $mtime    = file_exists($fullPath) ? filemtime($fullPath) : 0;

        return $mtime ? $url . '?v=' . $mtime : $url;
    }

    /**
     * Get the public URL for a stored shop image.
     * Returns null if no image — callers render a CSS placeholder instead.
     * Appends file modification time as cache-bust query string so a newly
     * uploaded image is always served fresh (no stale browser cache).
     *
     * @param string|null $filename
     */
    public static function shopImageUrl(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }

        $url      = upload_url('shops/' . $filename);
        $fullPath = rtrim(UPLOAD_SHOP_IMAGE_DIR, '/') . '/' . $filename;
        $mtime    = file_exists($fullPath) ? filemtime($fullPath) : 0;

        return $mtime ? $url . '?v=' . $mtime : $url;
    }

    /**
     * Validate a file upload without actually uploading it.
     * Returns array of error messages, or empty array if valid.
     *
     * @param array<string,mixed> $file          $_FILES element
     * @param string[]            $allowedTypes  e.g. ['jpg','jpeg','png']
     * @param int                 $maxSizeBytes
     * @return string[]
     */
    public static function validate(
        array  $file,
        array  $allowedTypes  = ['jpg', 'jpeg', 'png', 'webp'],
        int    $maxSizeBytes  = UPLOAD_MAX_SIZE_BYTES
    ): array {
        $errors = [];

        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return []; // Not uploaded — not an error unless field is required
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload error: ' . self::uploadErrorText($file['error']);
            return $errors;
        }

        // Size check
        if ($file['size'] > $maxSizeBytes) {
            $maxMb    = round($maxSizeBytes / 1024 / 1024, 1);
            $errors[] = "File size exceeds the maximum of {$maxMb} MB.";
        }

        // Extension check
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) {
            $errors[] = 'File type not allowed. Accepted: ' . implode(', ', $allowedTypes) . '.';
        }

        return $errors;
    }

    /**
     * Convert a PHP upload error code to a readable string.
     */
    private static function uploadErrorText(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE   => 'exceeds server limit',
            UPLOAD_ERR_FORM_SIZE  => 'exceeds form limit',
            UPLOAD_ERR_PARTIAL    => 'partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'no temp directory',
            UPLOAD_ERR_CANT_WRITE => 'cannot write to disk',
            default               => 'unknown error (' . $code . ')',
        };
    }
}
