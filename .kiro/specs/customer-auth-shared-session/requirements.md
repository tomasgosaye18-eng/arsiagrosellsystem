# Requirements Document

## Introduction

This document defines the requirements for the **Customer Authentication with Shared Session Integration** feature for the AU-AgroSale e-commerce module. The system currently operates with an internal management authentication architecture (roles: administrator, farm_manager, warehouse_manager, salesperson, general_manager) using a single shared PHP session managed by `SessionManager`. This feature extends that architecture to support customer-facing Sign In and Sign Up, protecting the checkout flow while preserving the existing internal management login without modification.

**Key architectural constraint:** There is ONE PHP session per browser. A single `SESSION_USER_ID` value identifies the current user regardless of whether they are an internal staff member or a customer. Authentication and authorization are separate concerns — the `customer` role grants access to customer-facing routes only, not to any internal management dashboard.

**Current state of the codebase (inspected):**
- `SessionManager::loginUser()`, `SessionManager::logoutUser()`, `SessionManager::isAuthenticated()`, `SessionManager::regenerate()` all exist and are reusable.
- `PasswordUtility::hash()`, `PasswordUtility::verify()`, `PasswordUtility::validate()` exist and are reusable.
- `AuthenticationService::attempt()` handles IP-level brute force, account status checks, bcrypt verification, session binding, and login history — reusable.
- `AuthMiddleware` stores `intended_url` in session before redirecting unauthenticated users — the post-auth redirect mechanism exists.
- `CsrfMiddleware` exists and is applied to all existing POST login forms.
- The `users` table holds all user accounts with `role_id` referencing `roles`.
- **No `customer` role exists in the `roles` table.** It must be added via a new migration.
- `CheckoutController::index()` currently has NO authentication check — it only validates `ca_selected_shop_id`.
- `AccountController::signout()` currently only removes `ca_*` session keys — it does NOT call `SessionManager::destroy()` and is NOT a proper logout.
- Customer routes are registered in `core/routes.php` without `AuthMiddleware`.
- The `users.email` column exists (`VARCHAR(191) NULL`) and has an index — email-based login for customers is viable.

---

## Glossary

- **Auth_System**: The shared authentication and session layer comprising `SessionManager`, `AuthenticationService`, `PasswordUtility`, `CsrfMiddleware`, and the `users` / `roles` tables.
- **Customer**: A person who browses and purchases from the AU-AgroSale storefront. Identified by the `customer` role in the `roles` table.
- **Customer_Auth_Controller**: The new PHP controller at `modules/customer/controllers/AuthController.php` that handles `GET/POST /customer/auth/login` and `GET/POST /customer/auth/register`.
- **Customer_Auth_Middleware**: A new or extended middleware that, when applied to protected customer routes, redirects unauthenticated users to `/customer/auth/login` (not to the internal `/login`).
- **Guest**: An unauthenticated visitor — no `SESSION_USER_ID` in the current session.
- **Internal_User**: An authenticated user whose role is one of: `administrator`, `farm_manager`, `warehouse_manager`, `salesperson`, `general_manager`.
- **Intended_URL**: The URL stored in the session key `intended_url` by a middleware before redirecting an unauthenticated user to the auth page. Used to return the user to their original destination after successful authentication.
- **Session**: The PHP server-side session managed by `SessionManager`. Session state is stored server-side; only the session cookie is sent to the browser.
- **CSRF_Token**: A per-session token stored in `SESSION_CSRF_TOKEN` and validated by `CsrfMiddleware` on every mutating POST request.
- **Cart**: The session-stored shopping cart held in the `ca_cart` session key. Persists across the authentication redirect.
- **ROLE_CUSTOMER**: The new role with `name = 'customer'` to be inserted into the `roles` table. Its `role_id` constant `ROLE_CUSTOMER` will be defined in `config/constants.php`.
- **Password_Policy**: The password strength rules enforced by `PasswordUtility::validate()` as configured by `MIN_PASSWORD_LENGTH`, `REQUIRE_UPPERCASE`, `REQUIRE_NUMBER`, and `REQUIRE_SPECIAL_CHAR` in `config/security.php`.
- **Bcrypt_Hash**: The output of `PasswordUtility::hash()` using PHP `password_hash()` with `PASSWORD_BCRYPT` algorithm.
- **Sliding_Panel_UI**: The frontend Sign In / Sign Up panel with a light blue/grey background, white rounded container, purple gradient decorative panel, and CSS sliding animation. Contains no real OAuth functionality.

---

## Requirements

### Requirement 1: Customer Role

**User Story:** As a system administrator, I want a dedicated `customer` role in the database, so that customer accounts are distinguishable from internal staff accounts and authorization can be enforced separately.

#### Acceptance Criteria

1. THE Auth_System SHALL include a `customer` role in the `roles` table with `name = 'customer'`, `display_name = 'Customer'`, and `is_active = 1`.
2. THE Auth_System SHALL define a `ROLE_CUSTOMER` constant equal to `'customer'` in `config/constants.php`.
3. WHEN a new customer account is created via registration, THE Auth_System SHALL assign the `customer` role_id to the new user record.
4. IF a registration request attempts to assign any role other than `customer`, THEN THE Customer_Auth_Controller SHALL reject the request and return an error.
5. THE Auth_System SHALL ensure the `customer` role id is NOT present in any internal management role middleware arrays (`[ROLE_ADMIN]`, `[ROLE_GENERAL_MANAGER]`, `[ROLE_FARM_MANAGER]`, `[ROLE_WAREHOUSE_MANAGER]`, `[ROLE_SALESPERSON]`).

---

### Requirement 2: Customer Authentication Routes and UI

**User Story:** As a guest customer, I want a dedicated Sign In / Sign Up page with a sliding panel interface, so that I can authenticate to complete my purchase without being redirected to the internal staff login page.

#### Acceptance Criteria

1. THE Auth_System SHALL register `GET /customer/auth/login` to display the Customer Sign In / Sign Up sliding panel page using the `customer` template layout.
2. THE Auth_System SHALL register `POST /customer/auth/login` to process Sign In form submissions, protected by `CsrfMiddleware`.
3. THE Auth_System SHALL register `POST /customer/auth/register` to process Sign Up form submissions, protected by `CsrfMiddleware`.
4. THE Sliding_Panel_UI SHALL display a Sign In form with fields: Email, Password, and a "Forgot Password?" link, and a SIGN IN button.
5. THE Sliding_Panel_UI SHALL display a Sign Up form with fields: Full Name, Email, Password, and Confirm Password, and a SIGN UP button.
6. THE Sliding_Panel_UI SHALL include social media icons as visual decorations only — THE Auth_System SHALL NOT register any OAuth callback routes or process any OAuth flow.
7. THE Sliding_Panel_UI SHALL be responsive and function correctly on mobile screen widths.
8. WHEN an authenticated user (any role) visits `GET /customer/auth/login`, THE Customer_Auth_Controller SHALL redirect them to their appropriate destination (Customer Home for ROLE_CUSTOMER, or their internal dashboard for Internal_Users).

---

### Requirement 3: Customer Sign In Flow

**User Story:** As a registered customer, I want to sign in using my email and password, so that I can access protected features like Checkout and My Orders.

#### Acceptance Criteria

1. WHEN a Sign In form is submitted, THE Customer_Auth_Controller SHALL validate that the email field is a non-empty, valid email address format.
2. WHEN a Sign In form is submitted, THE Customer_Auth_Controller SHALL validate that the password field is non-empty.
3. IF validation fails, THEN THE Customer_Auth_Controller SHALL redirect back to `GET /customer/auth/login` with field-level validation error messages and preserve the submitted email in `old_input`.
6. WHEN a valid email and password are submitted, THE Customer_Auth_Controller SHALL check the account status first; IF the account is locked, THEN THE Customer_Auth_Controller SHALL reject the login immediately without verifying the password. IF the account is active, THE Customer_Auth_Controller SHALL look up the user by email address in the `users` table and verify the password.
5. IF no user exists with the submitted email, THEN THE Customer_Auth_Controller SHALL return a generic error message that does not reveal whether the email is registered.
6. WHEN a matching user is found, THE Customer_Auth_Controller SHALL verify the submitted password against the stored `password_hash` using `PasswordUtility::verify()`.
7. IF the password does not match, THEN THE Customer_Auth_Controller SHALL increment `failed_login_attempts` on the user record using the existing `UserModel::incrementFailedLogins()` mechanism and return a generic invalid credential message.
8. WHEN the account has reached `MAX_FAILED_LOGIN_ATTEMPTS` consecutive failures, THE Auth_System SHALL set `is_locked = 1` and `account_status = 'locked'` on the user record.
9. IF the user account has `account_status = 'locked'`, THEN THE Customer_Auth_Controller SHALL reject the login with a message indicating the account is locked.
10. WHEN authentication is successful, THE Auth_System SHALL call `SessionManager::loginUser()` which regenerates the session ID and sets `SESSION_USER_ID`, `SESSION_USER_ROLE`, `SESSION_USER_NAME`, `SESSION_USER_DATA`, `SESSION_LOGIN_TIME`, and `SESSION_LAST_ACTIVITY`.
11. WHEN authentication is successful, THE Auth_System SHALL call `UserModel::recordSuccessfulLogin()` to reset `failed_login_attempts` to 0.
12. WHEN authentication is successful and `intended_url` is set in the session, THE Customer_Auth_Controller SHALL redirect the customer to `intended_url` and delete `intended_url` from the session.
13. WHEN authentication is successful and `intended_url` is not set, THE Customer_Auth_Controller SHALL redirect to `GET /customer` (Customer Home).

---

### Requirement 4: Customer Sign Up Flow

**User Story:** As a new visitor, I want to create a customer account with my name, email, and password, so that I can complete a purchase and access my order history.

#### Acceptance Criteria

1. WHEN a Sign Up form is submitted, THE Customer_Auth_Controller SHALL validate that Full Name is non-empty and at most 160 characters.
2. WHEN a Sign Up form is submitted, THE Customer_Auth_Controller SHALL validate that the email field is a non-empty, valid email address format and at most 191 characters.
3. WHEN a Sign Up form is submitted, THE Customer_Auth_Controller SHALL validate that the password passes `PasswordUtility::validate()` against the system Password_Policy.
4. WHEN a Sign Up form is submitted, THE Customer_Auth_Controller SHALL validate that the Confirm Password field matches the Password field exactly.
5. IF any validation fails, THEN THE Customer_Auth_Controller SHALL prevent user creation and session establishment, redirect back to `GET /customer/auth/login` with field-level validation error messages, and preserve submitted non-sensitive fields (Full Name, Email) in `old_input`.
6. WHEN all fields are valid, THE Customer_Auth_Controller SHALL query the `users` table to check whether the email address is already registered.
7. IF the email is already registered, THEN THE Customer_Auth_Controller SHALL return a field-level error on the email field without revealing the account type or other details of the existing user.
8. WHEN the email is not already registered, THE Customer_Auth_Controller SHALL create a new user record using a database transaction, with: `role_id` = (id of `customer` role), `email` = submitted email, `username` = submitted email (email used as username), `first_name` and `last_name` derived from Full Name (split on first space), `password_hash` = `PasswordUtility::hash(password)`, `account_status = 'active'`, `is_active = 1`, `must_change_password = 0`. IF role assignment fails, THE Customer_Auth_Controller SHALL roll back the entire transaction and return a registration error.
9. WHEN the new user record is created, THE Auth_System SHALL call `SessionManager::loginUser()` to establish an authenticated session with session ID regeneration.
10. WHEN registration is successful and `intended_url` is set in the session, THE Customer_Auth_Controller SHALL redirect to `intended_url` and delete `intended_url` from the session.
11. WHEN registration is successful and `intended_url` is not set, THE Customer_Auth_Controller SHALL redirect to `GET /customer` (Customer Home).
12. IF a registration request contains a `role_id` parameter in the POST body, THEN THE Customer_Auth_Controller SHALL ignore it and always assign the `customer` role.

---

### Requirement 5: Shared Session Architecture

**User Story:** As an AU-AgroSale system, I want a single shared PHP session to represent the current user regardless of whether they are a customer or internal staff member, so that there is never a dual-login state and session security is maintained uniformly.

#### Acceptance Criteria

1. THE Auth_System SHALL use a single PHP session per browser, identified by the `aam_session` cookie, to hold authentication state for both customer and internal management access.
2. WHILE a session has `SESSION_USER_ID` set with role `customer`, THE Auth_System SHALL recognize the user as an authenticated Customer on the customer-facing storefront.
3. WHILE a session has `SESSION_USER_ID` set with an Internal_User role, THE Auth_System SHALL recognize the user as authenticated on the customer storefront (bypassing the customer auth gate).
4. THE Auth_System SHALL NOT create a second parallel session or a second set of session variables to represent a customer alongside an internal staff login.
5. THE Auth_System SHALL NOT store authentication tokens in `localStorage`, `sessionStorage`, or any client-side storage mechanism for the purpose of authentication.
6. WHEN `SessionManager::loginUser()` is called for a customer, THE Auth_System SHALL regenerate the session ID via `session_regenerate_id(true)` to prevent session fixation attacks.

---

### Requirement 6: Customer Authentication Middleware

**User Story:** As a system architect, I want a Customer_Auth_Middleware that protects customer-only routes by redirecting unauthenticated users to the customer auth page (not the internal login), so that customers are not confused by the internal staff login UI.

#### Acceptance Criteria

1. THE Customer_Auth_Middleware SHALL check `SessionManager::isAuthenticated()` before allowing access to protected customer routes.
2. WHEN an unauthenticated request is made to a protected customer route, THE Customer_Auth_Middleware SHALL store the requested URI in the session as `intended_url`.
3. WHEN an unauthenticated request is made to a protected customer route, THE Customer_Auth_Middleware SHALL redirect to `GET /customer/auth/login`.
4. WHEN an AJAX request is made to a protected customer route without authentication, THE Customer_Auth_Middleware SHALL return a JSON response with `success: false`, `message: 'Authentication required.'`, and HTTP status 401, with no access to the protected resource.
5. WHEN an authenticated user with any valid role accesses a protected customer route, THE Customer_Auth_Middleware SHALL allow the request to proceed.
6. THE Customer_Auth_Middleware SHALL NOT redirect authenticated Internal_Users away from customer routes — an Internal_User browsing the storefront is permitted.

---

### Requirement 7: Checkout Protection

**User Story:** As a security-conscious architect, I want the Checkout page to be inaccessible to unauthenticated (guest) users, so that order placement always has an associated authenticated user identity.

#### Acceptance Criteria

1. THE Auth_System SHALL apply `Customer_Auth_Middleware` to `GET /customer/checkout` so that unauthenticated access triggers a redirect to `GET /customer/auth/login`.
2. WHEN a guest clicks "Proceed to Checkout", THE Customer_Auth_Middleware SHALL store `intended_url = /customer/checkout` in the session before redirecting.
3. WHEN a customer successfully authenticates and `intended_url = /customer/checkout`, THE Customer_Auth_Controller SHALL redirect to `GET /customer/checkout` (not to Customer Home).
4. THE Auth_System SHALL apply `Customer_Auth_Middleware` to `POST /customer/checkout/place` to prevent unauthenticated order placement.
5. THE Auth_System SHALL apply `Customer_Auth_Middleware` to `GET /customer/checkout/payment/{id}` and `POST /customer/checkout/verify`.
6. THE Auth_System SHALL NOT apply `Customer_Auth_Middleware` to: `GET /customer`, `GET /customer/products`, `GET /customer/products/{id}`, `GET /customer/products/search`, `GET /customer/shops`, `GET /customer/cart`, `POST /customer/cart/add`, `POST /customer/cart/update`, `POST /customer/cart/remove`.

---

### Requirement 8: Cart Preservation

**User Story:** As a customer, I want my cart items to remain intact when I am redirected to the Sign In / Sign Up page, so that I don't have to re-add products after authenticating.

#### Acceptance Criteria

1. WHEN an unauthenticated customer with items in `ca_cart` is redirected to `GET /customer/auth/login`, THE Auth_System SHALL NOT modify or clear the `ca_cart` session key during the redirect.
2. WHEN a customer successfully authenticates and is redirected back to `GET /customer/checkout`, THE Auth_System SHALL preserve all `ca_cart` items from before the authentication redirect.
3. THE Customer_Auth_Controller SHALL NOT call `CartService::clearCart()` or `unset($_SESSION['ca_cart'])` as part of the login or registration process.
4. WHEN a customer completes authentication, the cart item count SHALL equal the cart item count that existed immediately before the authentication redirect.

---

### Requirement 9: Order Ownership Security

**User Story:** As a security architect, I want every order to be permanently linked to the authenticated customer's server-side session identity, so that Customer A can never place or view an order belonging to Customer B.

#### Acceptance Criteria

1. WHEN `POST /customer/checkout/place` is processed, THE CheckoutController SHALL derive `customer_id` exclusively from `SessionManager::userId()`.
2. THE CheckoutController SHALL NOT accept `customer_id`, `user_id`, or any equivalent identity claim from POST body parameters, query strings, or hidden HTML inputs.
3. WHEN an order record is created in the database, THE Auth_System SHALL store `customer_id = SessionManager::userId()` in the order record.
4. WHEN `GET /customer/orders` is requested, THE OrderController SHALL query only orders where `customer_id = SessionManager::userId()`.
5. WHEN `GET /customer/orders/{id}` is requested, THE OrderController SHALL verify that the order's `customer_id = SessionManager::userId()` and return HTTP 404 if it does not match.
6. WHEN `GET /customer/orders/{id}/receipt` is requested, THE OrderController SHALL verify that the order's `customer_id = SessionManager::userId()` and return HTTP 404 if it does not match.

---

### Requirement 10: Protected Customer Routes

**User Story:** As a system architect, I want all customer routes requiring identity to be protected server-side, so that security is enforced at the routing layer and cannot be bypassed by manipulating client-side state.

#### Acceptance Criteria

1. THE Auth_System SHALL protect the following routes with `Customer_Auth_Middleware`: `GET /customer/checkout`, `POST /customer/checkout/place`, `GET /customer/checkout/payment/{id}`, `POST /customer/checkout/verify`, `GET /customer/checkout/success/{ref}`, `POST /customer/checkout/cancel`, `GET /customer/orders`, `GET /customer/orders/{id}`, `GET /customer/orders/{id}/receipt`, `GET /customer/account`, `GET /customer/account/receipts`, `GET /customer/account/pickup`, `GET /customer/account/security`, `GET /customer/account/help`.
2. THE Auth_System SHALL leave the following routes without `Customer_Auth_Middleware` (publicly accessible): `GET /customer`, `GET /customer/products`, `GET /customer/products/search`, `GET /customer/products/{id}`, `GET /customer/shops`, `GET /customer/shops/{id}/availability`, `GET /customer/cart`, `POST /customer/cart/add`, `POST /customer/cart/update`, `POST /customer/cart/remove`, `POST /customer/cart/clear`, `GET /customer/auth/login`, `POST /customer/auth/login`, `POST /customer/auth/register`.
3. THE Auth_System SHALL validate authentication server-side on every request to a protected route — THE Auth_System SHALL NOT rely solely on client-side JavaScript or hidden fields to determine authentication status.

---

### Requirement 11: Internal Management Compatibility

**User Story:** As an internal staff user (Admin, GM, Farm Manager, Warehouse Manager, Salesperson), I want the existing internal login, session, and role-based access controls to remain completely unchanged, so that the addition of customer authentication does not break my workflow.

#### Acceptance Criteria

1. THE Auth_System SHALL NOT modify `AuthController` (at `modules/auth/controllers/AuthController.php`) or its login/logout flow.
2. THE Auth_System SHALL NOT modify `AuthMiddleware` (at `core/Middleware/AuthMiddleware.php`).
3. THE Auth_System SHALL NOT modify `SessionManager` (at `core/SessionManager.php`).
4. THE Auth_System SHALL NOT modify `AuthenticationService` (at `modules/auth/services/AuthenticationService.php`).
5. THE Auth_System SHALL NOT modify any existing `RoleMiddleware` instantiation in `core/routes.php` for internal management routes.
6. WHEN an Internal_User is authenticated and visits `GET /customer/checkout`, THE Customer_Auth_Middleware SHALL allow the request to proceed (they are authenticated via the shared session).
7. WHEN a Customer is authenticated and visits `GET /dashboard`, THE Auth_System (via existing `AuthMiddleware` and `RoleMiddleware`) SHALL enforce the existing role-based redirect — the customer's session will pass `AuthMiddleware::isAuthenticated()` but `getDashboardUrl()` will route them correctly if `ROLE_CUSTOMER` is handled.
8. THE Auth_System SHALL ensure that a user with `role = 'customer'` cannot access any route protected by `RoleMiddleware([ROLE_ADMIN])`, `RoleMiddleware([ROLE_GENERAL_MANAGER])`, `RoleMiddleware([ROLE_FARM_MANAGER])`, `RoleMiddleware([ROLE_WAREHOUSE_MANAGER])`, or `RoleMiddleware([ROLE_SALESPERSON])`.

---

### Requirement 12: Customer Logout

**User Story:** As an authenticated customer, I want to sign out of my account, so that my session is fully terminated and another person using the same device cannot access my account.

#### Acceptance Criteria

1. THE Auth_System SHALL register `GET /customer/account/signout` to process customer logout.
2. WHEN a customer requests `GET /customer/account/signout`, THE Auth_System SHALL call `SessionManager::logoutUser()` to remove all auth session keys (`SESSION_USER_ID`, `SESSION_USER_ROLE`, `SESSION_USER_NAME`, `SESSION_USER_DATA`, `SESSION_LOGIN_TIME`, and any associated keys).
3. WHEN a customer requests `GET /customer/account/signout`, THE Auth_System SHALL call `SessionManager::destroy()` to invalidate the session file, regenerate the session ID, and expire the session cookie.
4. WHEN a customer requests `GET /customer/account/signout`, THE Auth_System SHALL also clear customer-specific session keys: `ca_customer_name`, `ca_customer_phone`, `ca_customer_email`, `ca_selected_shop_id`, `ca_selected_shop_name`, `ca_cart`, `ca_pending_payment`.
5. AFTER logout, THE Auth_System SHALL redirect the former customer to `GET /customer` (Customer Home storefront).
6. THE Auth_System SHALL NOT perform logout by simply clearing `ca_*` keys without also destroying the PHP session and auth session keys.

---

### Requirement 13: Navbar Authentication State

**User Story:** As a customer, I want the customer navbar to show a "Sign In" link when I am a guest and my profile information when I am authenticated, so that I always know my current authentication status.

#### Acceptance Criteria

1. WHEN a Guest visits any customer page, THE customer template (`templates/customer.php`) SHALL display a "Sign In" link or button in the navbar that navigates to `GET /customer/auth/login`.
2. WHEN a Customer is authenticated, THE customer template SHALL display a profile icon with the customer's initial (derived from `SESSION_USER_NAME`) in the navbar.
3. WHEN a Customer is authenticated, THE customer template SHALL display the account dropdown with links to: My Account, My Orders, Receipts, and Sign Out.
4. WHEN a Guest views the navbar, THE customer template SHALL NOT display "My Account", "My Orders", or "Receipts" as links (these are protected sections).
5. WHEN a Guest views the navbar, THE customer template SHALL NOT display a "Sign Out" link.
6. THE customer template SHALL determine authentication state from `SessionManager::isAuthenticated()` called server-side — not from localStorage or JavaScript variables.

---

### Requirement 14: Session Security

**User Story:** As a security-conscious architect, I want all customer authentication operations to follow the existing secure session practices, so that session fixation, hijacking, and CSRF attacks are prevented.

#### Acceptance Criteria

1. THE Auth_System SHALL regenerate the PHP session ID (via `SessionManager::regenerate()`) immediately after a successful Sign In or Sign Up.
2. THE Auth_System SHALL apply `CsrfMiddleware` to `POST /customer/auth/login` and `POST /customer/auth/register`.
3. THE Auth_System SHALL NOT store the user's password in any session variable, HTML comment, hidden input, or JavaScript variable.
4. THE Auth_System SHALL NOT store authentication credentials or tokens in `localStorage` or `sessionStorage`.
5. THE Auth_System SHALL use the existing `SessionManager::enforceIntegrity()` IP/User-Agent fingerprinting for customer sessions (this is automatic since `SessionManager::start()` is called on every request).
6. WHEN the session idle timeout (`session.lifetime` from `config/Application.php`) is reached, THE Auth_System SHALL destroy the session and require the customer to re-authenticate.
7. THE Auth_System SHALL include the CSRF token in the customer auth forms via the existing `$csrfToken` template variable.

---

### Requirement 15: Payment Security

**User Story:** As a security architect, I want the order total and customer identity to always be derived server-side, so that a customer cannot manipulate the payment amount or fraudulently create orders for other customers.

#### Acceptance Criteria

1. WHEN a checkout is submitted, THE CheckoutService SHALL calculate the order total by summing `price × quantity` for each cart item using prices fetched from the database — not from the POST body.
2. THE CheckoutController SHALL NOT use `total_amount`, `price`, or any monetary value from POST parameters for the purpose of creating the order record.
3. THE CheckoutController SHALL NOT use `customer_id`, `user_id`, or `shop_id` from POST parameters for the purpose of creating the order record — these MUST come from the server-side session and database.
4. WHEN a payment method selection is submitted, THE CheckoutService SHALL validate that the selected `payment_method_id` corresponds to an active payment method in the database.
5. WHEN `POST /customer/checkout/place` succeeds, THE Auth_System SHALL have verified: the shop exists and is active, the cart items are available at the selected shop, the order total matches the server-side calculation, and the `customer_id` equals `SessionManager::userId()`.

---

### Requirement 16: Database Migration for Customer Role

**User Story:** As a database administrator, I want a safe, idempotent migration that adds the customer role to the roles table, so that the feature can be deployed without breaking existing data.

#### Acceptance Criteria

1. THE Auth_System SHALL include a new SQL migration file that inserts the `customer` role into the `roles` table using `INSERT IGNORE` to prevent duplicate insertion.
2. THE migration SHALL assign a unique `id` value that does not conflict with existing role IDs (1=administrator, 2=farm_manager, 3=warehouse_manager, 5=salesperson, 6=general_manager) — id 7 is the next available value.
3. THE migration SHALL be safe to run multiple times without duplicating data or causing errors.
4. THE migration SHALL include a `SELECT` verification statement confirming the role was inserted or already exists.

---

### Requirement 17: Frontend JavaScript Responsibilities (Boundary)

**User Story:** As a frontend developer, I want clearly defined responsibilities for client-side JavaScript in the customer auth feature, so that security logic is never accidentally delegated to the browser.

#### Acceptance Criteria

1. THE Sliding_Panel_UI JavaScript SHALL be responsible for: toggling the sliding animation between Sign In and Sign Up panels, displaying client-side field validation feedback (empty field indicators, password match preview), managing loading states on form submission buttons, and focusing the correct input field when the panel slides.
2. THE Sliding_Panel_UI JavaScript SHALL NOT be responsible for: authenticating the user, verifying passwords, determining if a session is valid, storing auth tokens, or redirecting the browser based on auth state.
3. THE Auth_System SHALL perform all security-sensitive validation (password strength, email uniqueness, credential verification, CSRF validation) exclusively server-side.
4. IF JavaScript is disabled in the browser, THE Auth_System SHALL still authenticate customers correctly via standard HTML form POST submissions and server-side redirects, including full credential verification server-side — this is a hard dependency and the system SHALL NOT gracefully degrade to unauthenticated access.
