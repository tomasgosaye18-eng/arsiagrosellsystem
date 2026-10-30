<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/alerts.php
 * Purpose  : Reusable alert / flash message component.
 *            Can be included in any view to show alerts.
 *            Reads from session flash and validation errors.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);
?>

<?php
// ─── Flash Messages ───────────────────────────────────────────────────────────
$flash = \AAM\Core\SessionManager::get(SESSION_FLASH);
if ($flash):
    \AAM\Core\SessionManager::delete(SESSION_FLASH);
    $type    = e($flash['type'] ?? 'info');
    $message = e($flash['message'] ?? '');
    $bsClass = match($flash['type'] ?? '') {
        FLASH_SUCCESS => 'success',
        FLASH_ERROR   => 'danger',
        FLASH_WARNING => 'warning',
        default       => 'info',
    };
    $icon = match($flash['type'] ?? '') {
        FLASH_SUCCESS => 'bi-check-circle-fill',
        FLASH_ERROR   => 'bi-x-circle-fill',
        FLASH_WARNING => 'bi-exclamation-triangle-fill',
        default       => 'bi-info-circle-fill',
    };
?>
<div class="alert alert-<?= $bsClass ?> alert-dismissible fade show d-flex align-items-start gap-2 mb-3"
     role="alert" aria-live="polite">
    <i class="bi <?= $icon ?> flex-shrink-0 mt-1" aria-hidden="true"></i>
    <div class="flex-grow-1"><?= $message ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php
// ─── Validation Errors Summary ────────────────────────────────────────────────
$valErrors = \AAM\Core\SessionManager::get('validation_errors', []);
if (!empty($valErrors)):
    \AAM\Core\SessionManager::delete('validation_errors');
    \AAM\Core\SessionManager::delete('old_input');
    $allMessages = array_merge(...array_values($valErrors));
?>
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert" aria-live="polite">
    <div class="d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0 mt-1" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($allMessages as $msg): ?>
                    <li><?= e($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
