<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Authentication Controller
 * =========================================================
 * File    : modules/customer/controllers/AuthController.php
 *
 * Routes (all registered in core/routes.php):
 *   GET  /customer/auth/login   → showLogin()   — display Sign In / Sign Up page
 *   POST /customer/auth/login   → login()        — process Sign In
 *   POST /customer/auth/register → register()   — process Sign Up
 *
 * ARCHITECTURE:
 *   - Shares the SAME PHP session as internal management.
 *   - Reuses SessionManager::loginUser() — identical to internal login.
 *   - Reuses PasswordUtility::hash() / verify() / validate().
 *   - Reuses UserModel for all DB operations.
 *   - Customers receive ROLE_CUSTOMER ('customer') only — never any
 *     privileged internal role.
 *
 * SECURITY:
 *   - CSRF validated by CsrfMiddleware on POST routes.
 *   - Session ID regenerated via SessionManager::loginUser().
 *   - Password never stored in session, HTML, or JavaScript.
 *   - role_id in POST body is ignored — server always forces ROLE_CUSTOMER.
 *   - Brute-force protection via UserModel::incrementFailedLogins().
 *   - Cart (ca_cart) is NEVER cleared during login or registration.
 *   - After successful auth, customer is returned to intended_url
 *     (checkout, etc.) or Customer Home.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Core\SessionManager;
use AAM\Auth\Models\UserModel;
use AAM\Security\PasswordUtility;

class AuthController extends BaseController
{
    private UserModel $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/auth/login
    // Display the sliding Sign In / Sign Up page.
    // If already authenticated, redirect away immediately.
    // ──────────────────────────────────────────────────────
    public function showLogin(): void
    {
        // Already authenticated — send to the right place
        if (SessionManager::isAuthenticated()) {
            $role = SessionManager::userRole();
            if ($role === ROLE_CUSTOMER) {
                // Check for intended_url first (e.g. they were mid-checkout)
                $intended = SessionManager::get('intended_url');
                if ($intended) {
                    SessionManager::delete('intended_url');
                    $this->redirectAbsolute($intended);
                    return;
                }
                $this->redirect('customer');
            } else {
                // Internal user — send to internal dashboard
                $this->redirect('dashboard');
            }
            return;
        }

        // Restore any validation errors / old input from previous attempt
        $errors   = SessionManager::get('validation_errors', []);
        $oldInput = SessionManager::get('old_input', []);
        SessionManager::delete('validation_errors');
        SessionManager::delete('old_input');

        // Which panel to show: 'login' (default) or 'register'
        $activePanel = SessionManager::get('auth_active_panel', 'login');
        SessionManager::delete('auth_active_panel');

        $this->render('customer.auth.login', [
            'pageTitle'   => 'Sign In | AU-AgroSale',
            'errors'      => $errors,
            'oldInput'    => $oldInput,
            'activePanel' => $activePanel,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/auth/login
    // Process Sign In form (CSRF protected by CsrfMiddleware).
    //
    // Flow:
    //  1. Validate fields
    //  2. Look up user by EMAIL (customers use email, not username)
    //  3. Check account status
    //  4. Verify password
    //  5. Enforce ROLE_CUSTOMER — internal users cannot use this endpoint
    //  6. Regenerate session + establish authenticated session
    //  7. Redirect to intended_url or Customer Home
    // ──────────────────────────────────────────────────────
    public function login(): void
    {
        $email    = trim((string)$this->input('email', ''));
        $password = (string)$this->input('password', '');

        // ── Validation ────────────────────────────────────
        $errors = [];
        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        }

        if (!empty($errors)) {
            SessionManager::set('validation_errors', $errors);
            SessionManager::set('old_input', ['email' => $email]);
            SessionManager::set('auth_active_panel', 'login');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Find user by email ────────────────────────────
        $user = $this->findUserByEmail($email);

        if ($user === null) {
            // Generic error — don't reveal whether email is registered
            SessionManager::set('validation_errors', [
                '_form' => 'Invalid email or password.',
            ]);
            SessionManager::set('old_input', ['email' => $email]);
            SessionManager::set('auth_active_panel', 'login');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Account status check ──────────────────────────
        $blockReason = $this->userModel->getLoginBlockReason($user);
        if ($blockReason !== null) {
            Logger::security('Customer login blocked', [
                'user_id' => $user['id'],
                'status'  => $user['account_status'],
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            SessionManager::set('validation_errors', ['_form' => $blockReason]);
            SessionManager::set('old_input', ['email' => $email]);
            SessionManager::set('auth_active_panel', 'login');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Role guard — only ROLE_CUSTOMER may use this endpoint ────────────
        // Internal staff must use the /login page, not the customer auth page.
        // This prevents account harvesting across roles.
        $roleName = $user['role_name'] ?? '';
        if ($roleName !== ROLE_CUSTOMER) {
            // Return a generic error — don't confirm the email exists
            SessionManager::set('validation_errors', ['_form' => 'Invalid email or password.']);
            SessionManager::set('old_input', ['email' => $email]);
            SessionManager::set('auth_active_panel', 'login');
            Logger::security('Customer auth endpoint used by non-customer role', [
                'user_id'  => $user['id'],
                'role'     => $roleName,
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Password verification ─────────────────────────
        if (!PasswordUtility::verify($password, $user['password_hash'] ?? '')) {
            $maxAttempts = defined('MAX_FAILED_LOGIN_ATTEMPTS') ? MAX_FAILED_LOGIN_ATTEMPTS : 5;
            $attempts    = $this->userModel->incrementFailedLogins((int)$user['id'], $maxAttempts);
            Logger::security('Customer login failed — wrong password', [
                'user_id'  => $user['id'],
                'attempts' => $attempts,
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            // Generic message — same as "user not found" to avoid enumeration
            SessionManager::set('validation_errors', ['_form' => 'Invalid email or password.']);
            SessionManager::set('old_input', ['email' => $email]);
            SessionManager::set('auth_active_panel', 'login');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Successful login ──────────────────────────────
        $this->userModel->recordSuccessfulLogin((int)$user['id']);

        // If there is already an authenticated session (e.g. a different user or
        // role was active in this browser), destroy it first so that only ONE
        // active authenticated identity ever exists per browser session.
        if (SessionManager::isAuthenticated()) {
            SessionManager::logoutUser();
        }

        // Pre-fill customer session keys from DB user data (name, email)
        // These are informational — session auth identity comes from SESSION_USER_ID
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if ($fullName) {
            $_SESSION['ca_customer_name']  = $fullName;
        }
        if (!empty($user['email'])) {
            $_SESSION['ca_customer_email'] = $user['email'];
        }
        if (!empty($user['phone'])) {
            $_SESSION['ca_customer_phone'] = $user['phone'];
        }

        // Establish authenticated session (regenerates session ID internally)
        SessionManager::loginUser($user);

        Logger::info('Customer logged in', [
            'user_id' => $user['id'],
            'email'   => $email,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $this->redirectAfterAuth();
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/auth/register
    // Process Sign Up form (CSRF protected by CsrfMiddleware).
    //
    // Flow:
    //  1. Validate all fields
    //  2. Check duplicate email
    //  3. Create user with ROLE_CUSTOMER (server-enforced, POST ignored)
    //  4. Establish authenticated session
    //  5. Redirect to intended_url or Customer Home
    // ──────────────────────────────────────────────────────
    public function register(): void
    {
        $fullName        = trim((string)$this->input('full_name', ''));
        $email           = trim((string)$this->input('email', ''));
        $password        = (string)$this->input('password', '');
        $confirmPassword = (string)$this->input('confirm_password', '');

        // ── Validation ────────────────────────────────────
        $errors = [];

        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required.';
        } elseif (strlen($fullName) > 160) {
            $errors['full_name'] = 'Full name must be 160 characters or fewer.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (strlen($email) > 191) {
            $errors['email'] = 'Email address is too long.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } else {
            $pwCheck = PasswordUtility::validate($password);
            if (!$pwCheck['valid']) {
                $errors['password'] = implode(' ', $pwCheck['errors']);
            }
        }

        if (empty($confirmPassword)) {
            $errors['confirm_password'] = 'Please confirm your password.';
        } elseif ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            SessionManager::set('validation_errors', $errors);
            SessionManager::set('old_input', [
                'full_name' => $fullName,
                'email'     => $email,
            ]);
            SessionManager::set('auth_active_panel', 'register');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Duplicate email check ─────────────────────────
        $existing = $this->findUserByEmail($email);
        if ($existing !== null) {
            SessionManager::set('validation_errors', [
                'email' => 'This email address is already registered.',
            ]);
            SessionManager::set('old_input', [
                'full_name' => $fullName,
                'email'     => $email,
            ]);
            SessionManager::set('auth_active_panel', 'register');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Resolve ROLE_CUSTOMER id from DB ──────────────
        // We look it up to be safe; we never trust the POST body for role.
        $customerRoleId = $this->resolveCustomerRoleId();
        if ($customerRoleId === null) {
            Logger::error('Customer registration failed — customer role not found in DB');
            SessionManager::set('validation_errors', [
                '_form' => 'Registration is temporarily unavailable. Please try again later.',
            ]);
            SessionManager::set('old_input', ['full_name' => $fullName, 'email' => $email]);
            SessionManager::set('auth_active_panel', 'register');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Split full name ───────────────────────────────
        // "John Doe Smith" → first_name="John", last_name="Doe Smith"
        // "Alice"          → first_name="Alice", last_name=""
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '';

        // ── Create user in a transaction ──────────────────
        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $passwordHash = PasswordUtility::hash($password);

            $db->execute(
                "INSERT INTO users
                    (role_id, username, email, password_hash,
                     first_name, last_name, account_status, is_active,
                     must_change_password, created_at, updated_at)
                 VALUES
                    (:role_id, :username, :email, :password_hash,
                     :first_name, :last_name, 'active', 1,
                     0, NOW(), NOW())",
                [
                    'role_id'       => $customerRoleId,
                    'username'      => $email,      // email used as username
                    'email'         => $email,
                    'password_hash' => $passwordHash,
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                ]
            );

            $newUserId = (int)$db->lastInsertId();
            if (!$newUserId) {
                throw new \RuntimeException('User insert returned no ID.');
            }

            $db->commit();

        } catch (\Throwable $e) {
            $db->rollback();
            Logger::error('Customer registration DB error', ['error' => $e->getMessage(), 'email' => $email]);
            SessionManager::set('validation_errors', [
                '_form' => 'Registration failed. Please try again.',
            ]);
            SessionManager::set('old_input', ['full_name' => $fullName, 'email' => $email]);
            SessionManager::set('auth_active_panel', 'register');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Fetch the newly created user (with role) ──────
        $newUser = $this->userModel->findWithRole($newUserId);
        if ($newUser === null) {
            Logger::error('Customer registration: user not found after insert', ['user_id' => $newUserId]);
            SessionManager::set('validation_errors', [
                '_form' => 'Registration failed. Please try again.',
            ]);
            SessionManager::set('old_input', ['full_name' => $fullName, 'email' => $email]);
            SessionManager::set('auth_active_panel', 'register');
            $this->redirect('customer/auth/login');
            return;
        }

        // ── Pre-fill customer session keys ────────────────
        $_SESSION['ca_customer_name']  = trim($firstName . ' ' . $lastName);
        $_SESSION['ca_customer_email'] = $email;

        // ── Establish authenticated session ───────────────
        // If there is already an authenticated session (e.g. a different user was
        // active in this browser), clear it first — one active identity per browser.
        if (SessionManager::isAuthenticated()) {
            SessionManager::logoutUser();
        }

        // SessionManager::loginUser() regenerates session ID (prevents fixation)
        // and sets SESSION_USER_ID, SESSION_USER_ROLE, etc.
        // The ca_cart is preserved — loginUser() never touches ca_* keys.
        SessionManager::loginUser($newUser);

        Logger::info('Customer registered and logged in', [
            'user_id' => $newUserId,
            'email'   => $email,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $this->redirectAfterAuth();
    }

    // ──────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────

    /**
     * Find a user by email address (customers log in by email).
     * Returns full user row including role_name, or null.
     *
     * @return array<string,mixed>|null
     */
    private function findUserByEmail(string $email): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT u.*, r.name AS role_name, r.display_name AS role_display_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE LOWER(u.email) = LOWER(:email)
             LIMIT 1",
            ['email' => $email]
        );
    }

    /**
     * Resolve the customer role ID from the database.
     * Returns null if the customer role has not been migrated yet.
     */
    private function resolveCustomerRoleId(): ?int
    {
        // First try the constant (fast path)
        if (defined('ROLE_ID_CUSTOMER')) {
            // Verify it actually exists in DB
            $row = Database::getInstance()->fetchOne(
                "SELECT id FROM roles WHERE id = :id AND name = 'customer' LIMIT 1",
                ['id' => ROLE_ID_CUSTOMER]
            );
            if ($row) {
                return (int)$row['id'];
            }
        }

        // Fallback: look up by name
        $row = Database::getInstance()->fetchOne(
            "SELECT id FROM roles WHERE name = 'customer' LIMIT 1"
        );
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Redirect the user after successful authentication.
     * If an intended_url was saved (e.g., /customer/checkout), go there.
     * Otherwise go to Customer Home.
     */
    private function redirectAfterAuth(): void
    {
        $intended = SessionManager::get('intended_url');

        if ($intended) {
            SessionManager::delete('intended_url');
            $this->redirectAbsolute($intended);
            return;
        }

        $this->redirect('customer');
    }

    /**
     * Redirect to an absolute URL.
     * Validates the URL is local to prevent open redirect attacks.
     */
    private function redirectAbsolute(string $url): void
    {
        $appUrl = rtrim((string)\AAM\Config\Application::get('url', ''), '/');

        // Safety: only redirect to our own domain
        if (!str_starts_with($url, $appUrl) && !str_starts_with($url, '/')) {
            $this->redirect('customer');
            return;
        }

        // If it's a relative path, prepend appUrl
        if (str_starts_with($url, '/')) {
            $url = $appUrl . $url;
        }

        header('Location: ' . $url);
        exit;
    }
}
