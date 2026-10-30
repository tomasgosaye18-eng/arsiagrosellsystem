<?php
/**
 * =========================================================
 * ASSIGNMENT SERVICE
 * =========================================================
 * File    : modules/organization/services/AssignmentService.php
 * Purpose : Business logic for staff assignments:
 *           create (with auto-supersede), remove, and
 *           providing role metadata.
 * Deps    : AssignmentModel, OrgValidationService,
 *           ActivityLogModel
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Organization\Services;

use AAM\Organization\Models\AssignmentModel;
use AAM\Admin\Models\ActivityLogModel;

class AssignmentService
{
    private AssignmentModel      $assignmentModel;
    private OrgValidationService $validator;
    private ActivityLogModel     $activityLog;

    public function __construct()
    {
        $this->assignmentModel = new AssignmentModel();
        $this->validator       = new OrgValidationService();
        $this->activityLog     = new ActivityLogModel();
    }

    public function createAssignment(array $data, ?int $assignedBy): array
    {
        $validation = $this->validator->validateAssignment($data);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        $userId       = (int)$data['user_id'];
        $locationType = $data['location_type'];
        $locationId   = (int)$data['location_id'];
        $role         = $data['assignment_role'];

        $existing = $this->assignmentModel->getActiveForUser($userId);
        $action   = $existing ? 'REASSIGNED' : 'ASSIGNED';

        try {
            // Write to organization_assignments (full lifecycle tracking)
            $id = $this->assignmentModel->createAssignment(
                $userId, $locationType, $locationId, $role,
                $assignedBy, $action, $data['notes'] ?: null
            );

            // CRITICAL: also write to user_location_assignments so operational
            // controllers and the login session flow can find it.
            $this->syncUserLocationAssignment($userId, $locationType, $locationId, $assignedBy);

            $this->activityLog->log(
                'assignment_create',
                "User #{$userId} {$action} to {$locationType} #{$locationId}",
                'organization_assignments', $id, $assignedBy
            );
            return ['success' => true, 'assignment_id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'errors' => ['general' => 'Assignment failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Upsert into user_location_assignments.
     * This is the table that ALL operational modules read at runtime.
     */
    private function syncUserLocationAssignment(
        int    $userId,
        string $locationType,
        int    $locationId,
        ?int   $assignedBy
    ): void {
        try {
            $db = $this->assignmentModel->getDb();
            // Delete any existing row for this user, then insert fresh
            $db->execute("DELETE FROM user_location_assignments WHERE user_id = :uid", ['uid' => $userId]);
            $db->execute(
                "INSERT INTO user_location_assignments (user_id, location_type, location_id, assigned_by, assigned_at)
                 VALUES (:uid, :lt, :lid, :ab, NOW())",
                ['uid' => $userId, 'lt' => $locationType, 'lid' => $locationId, 'ab' => $assignedBy]
            );
        } catch (\Throwable $e) {
            // Log but don't fail the whole assignment
            \AAM\Core\Logger::error('syncUserLocationAssignment failed: ' . $e->getMessage());
        }
    }

    public function removeAssignment(int $assignmentId, ?int $performedBy, ?string $notes): array
    {
        // Get assignment details before removing
        $assignment = $this->assignmentModel->getDb()->fetchOne(
            "SELECT * FROM organization_assignments WHERE assignment_id = :id",
            ['id' => $assignmentId]
        );

        $ok = $this->assignmentModel->removeAssignment($assignmentId, $performedBy, $notes);
        if ($ok) {
            // Also remove from user_location_assignments
            if ($assignment) {
                try {
                    $this->assignmentModel->getDb()->execute(
                        "DELETE FROM user_location_assignments WHERE user_id = :uid AND location_type = :lt AND location_id = :lid",
                        ['uid' => $assignment['user_id'], 'lt' => $assignment['location_type'], 'lid' => $assignment['location_id']]
                    );
                } catch (\Throwable) {}
            }
            $this->activityLog->log('assignment_remove', "Assignment #{$assignmentId} removed", 'organization_assignments', $assignmentId, $performedBy);
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Failed to remove assignment'];
    }

    /**
     * Returns the list of assignable role keys → display names.
     * Only field-operation roles that have location assignments.
     * System Administrator and General Manager are not assignable
     * to locations — they have system-wide or business-wide access.
     */
    public function getAssignableRoles(): array
    {
        return [
            'farm_manager'      => 'Farm Manager',
            'warehouse_manager' => 'Warehouse Manager',
            'salesperson'       => 'Salesperson',
        ];
    }
}
