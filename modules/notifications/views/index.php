<?php
/**
 * View: modules/notifications/views/index.php
 * Layout: templates/master.php
 * Purpose: All notifications list page.
 *
 * @var \AAM\Core\View $view
 * @var array          $notifications
 */
declare(strict_types=1);

$typeIcons = [
    'low_stock'                          => ['icon' => 'bi-exclamation-triangle-fill', 'color' => 'warning'],
    'out_of_stock'                       => ['icon' => 'bi-x-circle-fill',             'color' => 'danger'],
    'transfer_pending'                   => ['icon' => 'bi-arrow-left-right',          'color' => 'info'],
    'transfer_received'                  => ['icon' => 'bi-check-circle-fill',         'color' => 'success'],
    'discrepancy'                        => ['icon' => 'bi-clipboard-x-fill',          'color' => 'danger'],
    'discrepancy_gm_review'              => ['icon' => 'bi-shield-exclamation',        'color' => 'warning'],
    'discrepancy_gm_approved'            => ['icon' => 'bi-shield-check',              'color' => 'success'],
    'discrepancy_gm_rejected'            => ['icon' => 'bi-shield-x',                 'color' => 'danger'],
    'discrepancy_final_rejected'         => ['icon' => 'bi-slash-circle-fill',         'color' => 'danger'],
    'transfer_discrepancy_approved'      => ['icon' => 'bi-shield-check',              'color' => 'success'],
    'transfer_discrepancy_final_rejected'=> ['icon' => 'bi-slash-circle-fill',         'color' => 'danger'],
    'system_announcement'                => ['icon' => 'bi-megaphone-fill',            'color' => 'primary'],
    'user_message'                       => ['icon' => 'bi-chat-dots-fill',            'color' => 'success'],
    'dispatch_received'                  => ['icon' => 'bi-truck',                     'color' => 'info'],
    'adjustment_pending'                 => ['icon' => 'bi-sliders',                   'color' => 'warning'],
    'adjustment_approved'                => ['icon' => 'bi-check2-circle',             'color' => 'success'],
    'adjustment_rejected'                => ['icon' => 'bi-x-circle',                  'color' => 'danger'],
    'near_expiry'                        => ['icon' => 'bi-calendar-x-fill',           'color' => 'warning'],
    'expired_product'                    => ['icon' => 'bi-calendar-x-fill',           'color' => 'danger'],
    'backup_completed'                   => ['icon' => 'bi-cloud-check-fill',          'color' => 'success'],
    'backup_failed'                      => ['icon' => 'bi-cloud-slash-fill',          'color' => 'danger'],
    'account_locked'                     => ['icon' => 'bi-lock-fill',                 'color' => 'danger'],
];

$priorityBadge = [
    'critical' => ['label' => 'Critical', 'class' => 'danger'],
    'high'     => ['label' => 'High',     'class' => 'warning'],
    'medium'   => ['label' => 'Medium',   'class' => 'info'],
    'low'      => ['label' => 'Low',      'class' => 'secondary'],
];

$unread = array_filter($notifications, fn($n) => !(int)$n['is_read']);
?>

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-bell-fill me-2 text-primary"></i>
            All Notifications
            <?php if (count($unread) > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?= count($unread) ?> unread</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($notifications)): ?>
        <form method="POST" action="<?= url('notifications/read-all') ?>">
            <?php csrf_input(); ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-check2-all me-1"></i>Mark All Read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash display-4 d-block mb-3 opacity-25"></i>
                <h6>No notifications</h6>
                <p class="small">You have no notifications at this time.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n):
                    $meta       = $typeIcons[$n['notification_type']] ?? ['icon' => 'bi-bell', 'color' => 'secondary'];
                    $prio       = $priorityBadge[$n['priority'] ?? 'medium'] ?? $priorityBadge['medium'];
                    $unreadClass = !(int)$n['is_read'] ? 'bg-light' : '';
                    $hasLink     = !empty($n['related_url']);
                    $senderName  = !empty($n['sender_name']) ? $n['sender_name'] : null;
                ?>
                <div class="list-group-item list-group-item-action d-flex gap-3 py-3 <?= $unreadClass ?>">

                    <!-- Icon -->
                    <div class="text-<?= $meta['color'] ?> flex-shrink-0 mt-1">
                        <i class="bi <?= $meta['icon'] ?> fs-5"></i>
                    </div>

                    <!-- Content -->
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <strong class="small <?= !(int)$n['is_read'] ? '' : 'fw-normal text-muted' ?>">
                                    <?= e($n['title'] ?? 'Notification') ?>
                                </strong>
                                <!-- Priority badge (skip 'medium' to reduce noise) -->
                                <?php if (($n['priority'] ?? 'medium') !== 'medium'): ?>
                                <span class="badge bg-<?= $prio['class'] ?>" style="font-size:.6rem;">
                                    <?= $prio['label'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted small flex-shrink-0">
                                <?= \AAM\Helpers\DateHelper::timeAgo($n['created_at'] ?? '') ?>
                            </span>
                        </div>

                        <p class="mb-1 small text-muted"><?= e($n['message'] ?? '') ?></p>

                        <!-- Sender + action row -->
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <?php if ($senderName): ?>
                            <span class="text-muted" style="font-size:.72rem;">
                                <i class="bi bi-person-fill me-1"></i><?= e($senderName) ?>
                            </span>
                            <?php endif; ?>

                            <?php if ($hasLink): ?>
                            <a href="<?= e($n['related_url']) ?>"
                               class="btn btn-link btn-sm p-0 text-primary small">
                                <i class="bi bi-box-arrow-up-right me-1"></i>View details
                            </a>
                            <?php endif; ?>

                            <?php if (!(int)$n['is_read']): ?>
                            <form method="POST"
                                  action="<?= url('notifications/' . (int)$n['id'] . '/read') ?>"
                                  class="d-inline">
                                <?php csrf_input(); ?>
                                <button type="submit" class="btn btn-link btn-sm p-0 text-secondary small">
                                    Mark as read
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Unread dot -->
                    <?php if (!(int)$n['is_read']): ?>
                    <div class="flex-shrink-0 mt-2">
                        <span class="bg-primary rounded-circle d-inline-block"
                              style="width:8px;height:8px;"
                              aria-label="Unread"></span>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
