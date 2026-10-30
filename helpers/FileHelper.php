<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/FileHelper.php
 * Purpose  : File upload framework.
 *            Validates uploads (size, type).
 *            Generates unique filenames.
 *            Moves files to correct storage directories.
 *            Supports product images, profile images.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Helpers;

use AAM\Config\Application;
use AAM\Exceptions\UploadException;

class FileHelper
{
    /**
     * Upload a product image.
     * Returns the generated filename on success.
     *
     * @param array<string,mixed> $file  $_FILES element
     * @throws UploadException
     */
    public static function uploadProductImage(array $file): string
    {
        return self::upload($file, UPLOAD_PRODUCT_IMAGE_DIR, ['jpg', 'jpeg', 'png', 'webp']);
    }

    /**
     * Upload a profile image.
     * Returns the generated filename on success.
     *
     * @param array<string,mixed> $file  $_FILES element
     * @throws UploadException
     */
    public static function uploadProfileImage(array $file): string
    {
        return self::upload($file, UPLOAD_PROFILE_IMAGE_DIR, ['jpg', 'jpeg', 'png', 'webp']);
    }

    /**
     * Upload a shop image.
     * Returns the generated filename on success.
     *
     * @param array<string,mixed> $file  $_FILES element
     * @throws UploadException
     */
    public static function uploadShopImage(array $file): string
    {
        return self::upload($file, UPLOAD_SHOP_IMAGE_DIR, ['jpg', 'jpeg', 'png', 'webp']);
    }

    /**
     * Core upload method.
     * Validates, renames, and moves an uploaded file.
     *
     * @param array<string,mixed> $file
     * @param string              $destination  Absolute destination directory path
     * @param string[]            $allowedTypes Allowed file extensions (lowercase)
     * @return string Generated filename (not full path)
     * @throws UploadException
     */
    public static function upload(
        array  $file,
        string $destination,
        array  $allowedTypes = []
    ): string {
        // ── PHP Upload Error Check ──────────────────────────────────────────
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException(self::uploadErrorMessage($file['error'] ?? UPLOAD_ERR_EXTENSION));
        }

        // ── Verify It's an Actual Upload (prevent path traversal) ───────────
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new UploadException('Invalid upload source.');
        }

        // ── File Size Check ──────────────────────────────────────────────────
        $maxSize = Application::get('uploads.max_size', UPLOAD_MAX_SIZE_BYTES);
        if ($file['size'] > $maxSize) {
            $maxMb = round($maxSize / 1024 / 1024, 1);
            throw new UploadException("File size exceeds the maximum allowed size of {$maxMb} MB.");
        }

        // ── Extension Check ──────────────────────────────────────────────────
        $originalName = $file['name'] ?? '';
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!empty($allowedTypes) && !in_array($extension, $allowedTypes, true)) {
            throw new UploadException(
                'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes) . '.'
            );
        }

        // ── MIME Type Verification (secondary check) ─────────────────────────
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = self::getMimeTypes($allowedTypes);
        if (!empty($allowedMimes) && !in_array($mimeType, $allowedMimes, true)) {
            throw new UploadException('File content does not match the allowed file types.');
        }

        // ── Generate Unique Filename ─────────────────────────────────────────
        $filename = self::generateFilename($extension);

        // ── Create Destination Directory if Needed ────────────────────────────
        if (!is_dir($destination)) {
            if (!mkdir($destination, 0755, true)) {
                throw new UploadException('Could not create upload directory.');
            }
        }

        // ── Move File ────────────────────────────────────────────────────────
        $destination = rtrim($destination, '/') . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new UploadException('Failed to save the uploaded file.');
        }

        return $filename;
    }

    /**
     * Delete an uploaded file by filename and directory.
     */
    public static function delete(string $filename, string $directory): bool
    {
        if ($filename === '' || str_contains($filename, '..') || str_contains($filename, '/')) {
            return false;
        }

        $path = rtrim($directory, '/') . '/' . $filename;

        if (file_exists($path) && is_file($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * Generate a UUID-based unique filename with given extension.
     */
    private static function generateFilename(string $extension): string
    {
        return sprintf(
            '%s_%s.%s',
            date('Ymd_His'),
            bin2hex(random_bytes(8)),
            $extension
        );
    }

    /**
     * Get allowed MIME types for given extensions.
     *
     * @param string[] $extensions
     * @return string[]
     */
    private static function getMimeTypes(array $extensions): array
    {
        $map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'svg'  => 'image/svg+xml',
        ];

        $mimes = [];
        foreach ($extensions as $ext) {
            if (isset($map[$ext])) {
                $mimes[] = $map[$ext];
            }
        }
        return array_unique($mimes);
    }

    /**
     * Get a human-readable message for a PHP upload error code.
     */
    private static function uploadErrorMessage(int $error): string
    {
        return match($error) {
            UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload size limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form upload size limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server configuration.',
            default               => 'Unknown upload error.',
        };
    }
}
