<?php
/**
 * =========================================================
 * PRODUCT IMAGE UPLOAD SERVICE
 * =========================================================
 * File: app/modules/admin/services/ProductImageUploadService.php
 * Purpose: Handle product image uploads with optional GD processing.
 *
 * If the PHP GD extension is available, images are resized and a
 * thumbnail is generated.  If GD is NOT available (e.g. fresh XAMPP
 * install where php_gd2 is not enabled), the uploaded file is stored
 * as-is using move_uploaded_file().  This allows the product catalog
 * to work in all environments without requiring GD.
 * =========================================================
 */

namespace AAM\Admin\Services;

class ProductImageUploadService
{
    private string $uploadDir    = 'public/uploads/products/';
    private string $thumbnailDir = 'public/uploads/products/thumbnails/';

    private int $maxWidth        = 1200;
    private int $maxHeight       = 1200;
    private int $thumbnailWidth  = 300;
    private int $thumbnailHeight = 300;

    public function __construct()
    {
        $this->ensureDirectoryExists($this->uploadDir);
        $this->ensureDirectoryExists($this->thumbnailDir);
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Upload and process a product image.
     *
     * Works with or without the PHP GD extension:
     *  - GD available  → resize main image + generate thumbnail
     *  - GD not available → copy file as-is (no resize/thumbnail)
     *
     * @param array $file  $_FILES array element
     * @return array ['success'=>bool, 'path'=>string, 'thumbnail_path'=>string]
     */
    public function uploadProductImage(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload error (code ' . ($file['error'] ?? '?') . ')'];
        }

        $extension      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed        = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowed, true)) {
            return ['success' => false, 'error' => 'Unsupported image type. Use JPEG, PNG or WEBP.'];
        }

        $filename           = uniqid('product_', true) . '.' . $extension;
        $thumbnailFilename  = uniqid('thumb_',   true) . '.' . $extension;
        $targetPath         = $this->uploadDir    . $filename;
        $thumbnailPath      = $this->thumbnailDir . $thumbnailFilename;

        // Validate it is actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'Invalid image file.'];
        }

        // ── Path 1: GD available — resize + thumbnail ────────────────────
        if (extension_loaded('gd')) {
            try {
                [$width, $height, $type] = $imageInfo;

                $sourceImage = $this->createImageFromFile($file['tmp_name'], $type);
                if ($sourceImage === false) {
                    // GD loaded but can't read this specific file — fall back to copy
                    return $this->copyFallback($file['tmp_name'], $targetPath, $thumbnailPath);
                }

                if ($width > $this->maxWidth || $height > $this->maxHeight) {
                    $main = $this->resizeImage($sourceImage, $width, $height, $this->maxWidth, $this->maxHeight);
                    $this->saveImage($main, $targetPath, $type);
                    imagedestroy($main);
                } else {
                    $this->saveImage($sourceImage, $targetPath, $type);
                }

                $thumb = $this->createThumbnail($sourceImage, $width, $height, $this->thumbnailWidth, $this->thumbnailHeight);
                $this->saveImage($thumb, $thumbnailPath, $type);
                imagedestroy($thumb);
                imagedestroy($sourceImage);

                $finalInfo = @getimagesize($targetPath);

                return [
                    'success'        => true,
                    'path'           => $targetPath,
                    'thumbnail_path' => $thumbnailPath,
                    'width'          => $finalInfo[0] ?? $width,
                    'height'         => $finalInfo[1] ?? $height,
                ];

            } catch (\Throwable $e) {
                // Clean up partial files
                foreach ([$targetPath, $thumbnailPath] as $p) {
                    if (file_exists($p)) @unlink($p);
                }
                // Fall through to copy fallback
            }
        }

        // ── Path 2: GD not available — plain file copy ───────────────────
        return $this->copyFallback($file['tmp_name'], $targetPath, $thumbnailPath);
    }

    /**
     * Fallback: just move the uploaded file without any GD processing.
     * thumbnail_path points to the same file (no separate thumbnail).
     */
    private function copyFallback(string $tmpPath, string $targetPath, string $thumbnailPath): array
    {
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            // move_uploaded_file only works for actual uploads; for safety also try copy
            if (!@copy($tmpPath, $targetPath)) {
                return ['success' => false, 'error' => 'Could not save uploaded file.'];
            }
        }

        // No separate thumbnail — reuse the main image path
        return [
            'success'        => true,
            'path'           => $targetPath,
            'thumbnail_path' => $targetPath,  // same file, no GD thumbnail
            'width'          => 0,
            'height'         => 0,
        ];
    }

    // ── GD helpers (only called when GD is loaded) ────────────────────────

    private function createImageFromFile(string $filepath, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($filepath),
            IMAGETYPE_PNG  => imagecreatefrompng($filepath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                                  ? imagecreatefromwebp($filepath)
                                  : false,
            default        => false,
        };
    }

    private function resizeImage($src, int $srcW, int $srcH, int $maxW, int $maxH)
    {
        $ratio  = min($maxW / $srcW, $maxH / $srcH);
        $newW   = (int) round($srcW * $ratio);
        $newH   = (int) round($srcH * $ratio);
        $dst    = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        return $dst;
    }

    private function createThumbnail($src, int $srcW, int $srcH, int $tW, int $tH)
    {
        $srcAspect = $srcW / max($srcH, 1);
        $tAspect   = $tW   / max($tH,  1);

        if ($srcAspect > $tAspect) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $tAspect);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $tAspect);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $thumb = imagecreatetruecolor($tW, $tH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $src, 0, 0, $cropX, $cropY, $tW, $tH, $cropW, $cropH);
        return $thumb;
    }

    private function saveImage($image, string $filepath, int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $filepath, 90),
            IMAGETYPE_PNG  => imagepng($image,  $filepath, 9),
            IMAGETYPE_WEBP => function_exists('imagewebp')
                                  ? imagewebp($image, $filepath, 90)
                                  : false,
            default        => false,
        };
    }

    // ── Public utilities ──────────────────────────────────────────────────

    public function deleteImageFile(string $imagePath): bool
    {
        return !file_exists($imagePath) || unlink($imagePath);
    }

    public function getImageDimensions(string $imagePath): ?array
    {
        if (!file_exists($imagePath)) return null;
        $info = @getimagesize($imagePath);
        if ($info === false) return null;
        return ['width' => $info[0], 'height' => $info[1], 'mime' => $info['mime']];
    }

    public function getUploadDir(): string    { return $this->uploadDir; }
    public function getThumbnailDir(): string { return $this->thumbnailDir; }
}
