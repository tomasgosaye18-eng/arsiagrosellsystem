<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File        : modules/auth/controllers/ProfileController.php
 * Purpose     : Profile management controller.
 *               Phase 8A: Added middle_name, gender, email
 *               fields + activity logging on all changes.
 * Dependencies: BaseController, UserModel, SessionService,
 *               PasswordService, UploadHelper, ActivityLogModel.
 * Routes      :
 *   GET  /profile                   → show()
 *   GET  /profile/edit              → edit()
 *   POST /profile/edit              → update()
 *   POST /profile/photo             → uploadPhoto()
 *   POST /profile/photo/delete      → deletePhoto()
 *   GET  /profile/change-password   → showChangePassword()
 *   POST /profile/change-password   → changePassword()
 *   POST /profile/revoke-session    → revokeSession()
 *   POST /profile/revoke-all-sessions → revokeAllSessions()
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Auth\Models\UserModel;
use AAM\Auth\Models\RememberMeModel;
use AAM\Auth\Services\PasswordService;
use AAM\Auth\Services\SessionService;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Helpers\UploadHelper;
use AAM\Core\Logger;

class ProfileController extends BaseController
{
    private UserModel        $userModel;
    private PasswordService  $passwordService;
    private SessionService   $sessionService;
    private ActivityLogModel $activityModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel       = new UserModel();
        $this->passwordService = new PasswordService();
        $this->sessionService  = new SessionService();
        $this->activityModel   = new ActivityLogModel();
    }

    // ─── View Profile ─────────────────────────────────────────────────────────

    /**
     * GET /profile
     */
    public function show(): void
    {
        $this->requireAuth();

        $user           = $this->userModel->findWithRole($this->userId());
        $sessionInfo    = $this->sessionService->getInfo();
        $activeSessions = (new RememberMeModel())->getForUser($this->userId());

        // Login history is restricted to administrator only.
        // Other roles see an empty array — no DB query is made.
        $isAdmin      = \AAM\Core\SessionManager::userRole() === ROLE_ADMIN;
        $loginHistory = $isAdmin
            ? (new \AAM\Auth\Models\LoginHistoryModel())->getForUser($this->userId(), 10)
            : [];

        $this->render('auth.profile', [
            'pageTitle'      => 'My Profile',
            'user'           => $user,
            'sessionInfo'    => $sessionInfo,
            'activeSessions' => $activeSessions,
            'loginHistory'   => $loginHistory,
            'showLoginHistory' => $isAdmin,
        ]);
    }

    // ─── Edit Profile ─────────────────────────────────────────────────────────

    /**
     * GET /profile/edit
     */
    public function edit(): void
    {
        $this->requireAuth();
        $user = $this->userModel->findWithRole($this->userId());

        $this->render('auth.profile_edit', [
            'pageTitle' => 'Edit Profile',
            'user'      => $user,
        ]);
    }

    /**
     * POST /profile/edit
     * Update name, middle name, gender, email, phone.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $data = [
            'first_name'  => trim((string) $this->input('first_name',  '')),
            'middle_name' => trim((string) $this->input('middle_name', '')),
            'last_name'   => trim((string) $this->input('last_name',   '')),
            'gender'      => trim((string) $this->input('gender',      '')),
            'email'       => trim((string) $this->input('email',       '')),
            'phone'       => trim((string) $this->input('phone',       '')),
        ];

        $result = $this->validate($data, [
            'first_name'  => 'required|min:2|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name'   => 'required|min:2|max:80',
            'gender'      => 'nullable|in:male,female,other,prefer_not_to_say',
            'email'       => 'nullable|email|max:191',
            'phone'       => 'nullable|phone',
        ], true);

        if (is_array($result)) {
            SessionManager::set('validation_errors', $result);
            SessionManager::set('old_input', $data);
            $this->flash(FLASH_ERROR, 'Please correct the errors below.');
            $this->redirect('/profile/edit');
            return;
        }

        // Convert empty strings to NULL so the UNIQUE constraint on email/phone works correctly
        // (MySQL allows multiple NULL values in a UNIQUE column but not multiple empty strings)
        $data['email']       = $data['email']       !== '' ? $data['email']       : null;
        $data['phone']       = $data['phone']       !== '' ? $data['phone']       : null;
        $data['middle_name'] = $data['middle_name'] !== '' ? $data['middle_name'] : null;
        $data['gender']      = $data['gender']      !== '' ? $data['gender']      : null;

        // Email uniqueness — exclude self
        if ($data['email'] !== null) {
            $count = (int) Database::getInstance()->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:e) AND id != :id",
                ['e' => $data['email'], 'id' => $this->userId()]
            );
            if ($count > 0) {
                SessionManager::set('validation_errors', ['email' => ['This email address is already registered to another account.']]);
                SessionManager::set('old_input', $data);
                $this->flash(FLASH_ERROR, 'Email address is already in use.');
                $this->redirect('/profile/edit');
                return;
            }
        }

        try {
            $this->userModel->updateProfile($this->userId(), $data);
        } catch (\Throwable $e) {
            Logger::error('Profile update failed: ' . $e->getMessage(), ['user_id' => $this->userId()]);
            $this->flash(FLASH_ERROR, 'Failed to save profile. Please try again.');
            $this->redirect('/profile/edit');
            return;
        }
        $this->sessionService->syncUserData($this->userId());

        try {
            $this->activityModel->log(
                ActivityLogModel::ACTION_PROFILE_UPDATED,
                SessionManager::get(SESSION_USER_NAME, 'User') . ' updated their profile',
                'users',
                $this->userId()
            );
        } catch (\Throwable $ignored) {
            // Activity log is non-critical
        }

        Logger::info('Profile updated', ['user_id' => $this->userId()]);
        $this->flash(FLASH_SUCCESS, 'Profile updated successfully.');
        $this->redirect('/profile');
    }

    // ─── Profile Photo ────────────────────────────────────────────────────────

    /**
     * POST /profile/photo
     */
    public function uploadPhoto(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        if (!$this->request->hasFile('profile_photo')) {
            $this->flash(FLASH_ERROR, 'Please select an image to upload.');
            $this->redirect('/profile/edit');
            return;
        }

        $file   = $this->request->file('profile_photo');
        $errors = UploadHelper::validate($file, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);

        if (!empty($errors)) {
            $this->flash(FLASH_ERROR, implode(' ', $errors));
            $this->redirect('/profile/edit');
            return;
        }

        try {
            $user     = $this->currentUser();
            $oldPhoto = $user['profile_photo'] ?? null;

            // Upload new file first — only delete old one after DB is updated
            $result   = UploadHelper::profileImage($file);
            $filename = $result['filename'];

            $this->userModel->updateProfile($this->userId(), ['profile_photo' => $filename]);
            $this->sessionService->syncUserData($this->userId());

            // Safe to delete old photo now that DB is updated
            if ($oldPhoto && $oldPhoto !== $filename) {
                UploadHelper::deleteProfileImage($oldPhoto);
            }

            try {
                $this->activityModel->log(
                    ActivityLogModel::ACTION_PHOTO_UPLOADED,
                    SessionManager::get(SESSION_USER_NAME, 'User') . ' uploaded a new profile photo',
                    'users',
                    $this->userId()
                );
            } catch (\Throwable $ignored) {
                // Activity log is non-critical — do not block the upload on log failure
            }

            Logger::info('Profile photo updated', ['user_id' => $this->userId()]);
            $this->flash(FLASH_SUCCESS, 'Profile photo updated successfully.');
            $this->redirect('/profile');
            return;

        } catch (\Throwable $e) {
            Logger::error('Profile photo upload failed: ' . $e->getMessage(), ['user_id' => $this->userId()]);
            $this->flash(FLASH_ERROR, 'Photo upload failed: ' . $e->getMessage());
        }

        $this->redirect('/profile/edit');
    }

    /**
     * POST /profile/photo/delete
     */
    public function deletePhoto(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $user  = $this->currentUser();
        $photo = $user['profile_photo'] ?? null;

        if ($photo) {
            UploadHelper::deleteProfileImage($photo);
            $this->userModel->updateProfile($this->userId(), ['profile_photo' => null]);
            $this->sessionService->syncUserData($this->userId());
            $this->flash(FLASH_SUCCESS, 'Profile photo removed.');
        }

        $this->redirect('/profile/edit');
    }

    // ─── Change Password ──────────────────────────────────────────────────────

    /**
     * GET /profile/change-password
     */
    public function showChangePassword(): void
    {
        $this->requireAuth();
        $this->render('auth.change_password', [
            'pageTitle' => 'Change Password',
            'forced'    => false,
        ], 'minimal');
    }

    /**
     * POST /profile/change-password
     */
    public function changePassword(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $result = $this->passwordService->changePassword(
            $this->userId(),
            (string) $this->request->rawPost('current_password', ''),
            (string) $this->request->rawPost('new_password',     ''),
            (string) $this->request->rawPost('confirm_password', '')
        );

        if (!$result['success']) {
            if (!empty($result['errors'])) {
                SessionManager::set('validation_errors', $result['errors']);
            }
            $this->flash(FLASH_ERROR, $result['message']);
            $this->redirect('/profile/change-password');
            return;
        }

        try {
            $this->activityModel->log(
                ActivityLogModel::ACTION_PASSWORD_CHANGED,
                SessionManager::get(SESSION_USER_NAME, 'User') . ' changed their password',
                'users',
                $this->userId()
            );
        } catch (\Throwable $ignored) {
            // Activity log is non-critical
        }

        $this->flash(FLASH_SUCCESS, 'Password changed successfully.');
        $this->redirect('/profile');
    }

    // ─── Session / Device Management ─────────────────────────────────────────

    /**
     * POST /profile/revoke-session
     */
    public function revokeSession(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $selector = (string) $this->input('selector', '');
        if ($selector !== '') {
            $token = (new RememberMeModel())->findBySelector($selector);
            if ($token && (int) $token['user_id'] === $this->userId()) {
                (new RememberMeModel())->deleteBySelector($selector);
                $this->flash(FLASH_SUCCESS, 'Session revoked successfully.');
            }
        }

        $this->redirect('/profile');
    }

    /**
     * POST /profile/revoke-all-sessions
     */
    public function revokeAllSessions(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        (new RememberMeModel())->deleteForUser($this->userId());
        $this->flash(FLASH_SUCCESS, 'All other sessions have been revoked.');
        $this->redirect('/profile');
    }
}
