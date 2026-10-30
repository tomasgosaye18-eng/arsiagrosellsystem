<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File        : modules/auth/services/ProfileService.php
 * Purpose     : Profile management service.
 *               Orchestrates all own-profile operations:
 *               update info, upload/delete photo, change
 *               email, change phone, activity logging.
 *               Separates profile business logic from the
 *               ProfileController HTTP layer.
 * Dependencies: UserModel, SessionService, ActivityLogModel,
 *               UploadHelper, Logger.
 * How it works:
 *   updateProfile() → validate → uniqueness check → save → sync session → log
 *   uploadPhoto()   → validate image → resize → save → update DB → log
 *   deletePhoto()   → remove file → clear DB column → log
 *   changeEmail()   → validate → uniqueness → save → log
 *   changePhone()   → validate → save → log
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Services;

use AAM\Auth\Models\UserModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Core\SessionManager;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Helpers\UploadHelper;
use AAM\Helpers\Validator;

class ProfileService
{
    private UserModel        $userModel;
    private SessionService   $sessionService;
    private ActivityLogModel $activityModel;
    private Database         $db;

    public function __construct()
    {
        $this->userModel      = new UserModel();
        $this->sessionService = new SessionService();
        $this->activityModel  = new ActivityLogModel();
        $this->db             = Database::getInstance();
    }

    // ─── Update Profile ───────────────────────────────────────────────────────

    /**
     * Update the authenticated user's profile fields.
     *
     * @param int                  $userId
     * @param array<string, mixed> $data  Validated input from controller
     * @return array{success: bool, message: string, errors: array}
     */
    public function updateProfile(int $userId, array $data): array
    {
        // Validate
        $validator = new Validator($data, [
            'first_name'  => 'required|min:2|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name'   => 'required|min:2|max:80',
            'gender'      => 'nullable|in:male,female,other,prefer_not_to_say',
            'email'       => 'nullable|email|max:191',
            'phone'       => 'nullable|phone',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Please correct the validation errors.',
                'errors'  => $validator->errors(),
            ];
        }

        // Email uniqueness (exclude self)
        if (!empty($data['email'])) {
            $count = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:e) AND id != :id",
                ['e' => $data['email'], 'id' => $userId]
            );
            if ($count > 0) {
                return [
                    'success' => false,
                    'message' => 'Email address is already registered to another account.',
                    'errors'  => ['email' => ['This email address is already in use.']],
                ];
            }
        }

        $this->userModel->updateProfile($userId, $data);
        $this->sessionService->syncUserData($userId);

        $this->activityModel->log(
            ActivityLogModel::ACTION_PROFILE_UPDATED,
            SessionManager::get(SESSION_USER_NAME, 'User') . ' updated their profile',
            'users',
            $userId
        );

        Logger::info('Profile updated', ['user_id' => $userId]);

        return ['success' => true, 'message' => 'Profile updated successfully.', 'errors' => []];
    }

    // ─── Upload Photo ─────────────────────────────────────────────────────────

    /**
     * Upload and save a new profile photo.
     *
     * @param int                  $userId
     * @param array<string, mixed> $file   $_FILES element
     * @return array{success: bool, message: string}
     */
    public function uploadPhoto(int $userId, array $file): array
    {
        // Validate file
        $errors = UploadHelper::validate($file, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        // Delete old photo
        $user     = $this->userModel->find($userId);
        $oldPhoto = $user['profile_photo'] ?? null;
        if ($oldPhoto) {
            UploadHelper::deleteProfileImage($oldPhoto);
        }

        // Upload new
        try {
            $result   = UploadHelper::profileImage($file);
            $filename = $result['filename'];

            $this->userModel->updateProfile($userId, ['profile_photo' => $filename]);
            $this->sessionService->syncUserData($userId);

            $this->activityModel->log(
                ActivityLogModel::ACTION_PHOTO_UPLOADED,
                SessionManager::get(SESSION_USER_NAME, 'User') . ' uploaded a new profile photo',
                'users',
                $userId
            );

            Logger::info('Profile photo uploaded', ['user_id' => $userId, 'file' => $filename]);

            return ['success' => true, 'message' => 'Profile photo updated successfully.'];

        } catch (\Throwable $e) {
            Logger::error('Profile photo upload failed: ' . $e->getMessage(), ['user_id' => $userId]);
            return ['success' => false, 'message' => 'Failed to upload photo. Please try again.'];
        }
    }

    // ─── Delete Photo ─────────────────────────────────────────────────────────

    /**
     * Remove the current profile photo.
     *
     * @return array{success: bool, message: string}
     */
    public function deletePhoto(int $userId): array
    {
        $user  = $this->userModel->find($userId);
        $photo = $user['profile_photo'] ?? null;

        if ($photo) {
            UploadHelper::deleteProfileImage($photo);
            $this->userModel->updateProfile($userId, ['profile_photo' => null]);
            $this->sessionService->syncUserData($userId);
            Logger::info('Profile photo removed', ['user_id' => $userId]);
        }

        return ['success' => true, 'message' => 'Profile photo removed.'];
    }

    // ─── Change Email ─────────────────────────────────────────────────────────

    /**
     * Update only the email field with uniqueness check.
     *
     * @return array{success: bool, message: string, errors: array}
     */
    public function changeEmail(int $userId, string $email): array
    {
        $email = trim($email);

        if (!empty($email)) {
            $validator = new Validator(['email' => $email], ['email' => 'required|email|max:191']);
            if ($validator->fails()) {
                return ['success' => false, 'message' => 'Invalid email address.', 'errors' => $validator->errors()];
            }

            $count = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:e) AND id != :id",
                ['e' => $email, 'id' => $userId]
            );
            if ($count > 0) {
                return [
                    'success' => false,
                    'message' => 'Email already in use.',
                    'errors'  => ['email' => ['This email is registered to another account.']],
                ];
            }
        }

        $this->userModel->updateProfile($userId, ['email' => $email ?: null]);
        $this->sessionService->syncUserData($userId);

        return ['success' => true, 'message' => 'Email updated successfully.', 'errors' => []];
    }

    // ─── Change Phone ─────────────────────────────────────────────────────────

    /**
     * Update only the phone field.
     *
     * @return array{success: bool, message: string, errors: array}
     */
    public function changePhone(int $userId, string $phone): array
    {
        $phone = trim($phone);

        if (!empty($phone)) {
            $validator = new Validator(['phone' => $phone], ['phone' => 'required|phone']);
            if ($validator->fails()) {
                return ['success' => false, 'message' => 'Invalid phone number.', 'errors' => $validator->errors()];
            }
        }

        $this->userModel->updateProfile($userId, ['phone' => $phone ?: null]);
        $this->sessionService->syncUserData($userId);

        $this->activityModel->log(
            ActivityLogModel::ACTION_PROFILE_UPDATED,
            SessionManager::get(SESSION_USER_NAME, 'User') . ' updated their phone number',
            'users',
            $userId
        );

        return ['success' => true, 'message' => 'Phone number updated successfully.', 'errors' => []];
    }
}
