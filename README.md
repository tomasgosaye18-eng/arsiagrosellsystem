# Arsi Agri-Manage (AAM)
## Phase 7 — Authentication & Authorization — COMPLETE

**Organization:** Arsi University  
**Version:** 1.0.0  
**Phase Status:** Phase 7 ✅ Complete | Phase 8 (Module Implementation) → Next  
**Environment:** XAMPP (Windows), PHP 8.2+, MySQL 8  
**Base URL:** `http://localhost/aam/app`

---

## Quick Start

### Step 1 — Configure environment

The `.env` file is already present. Update `DB_PASSWORD` to match your MySQL user:

```ini
DB_PASSWORD=your_password_here
ENCRYPTION_KEY=replace_with_32_char_random_string
```

### Step 2 — Run SQL migrations

Open phpMyAdmin → SQL tab → run in this exact order:

```sql
-- 1. Core schema (roles, users, locations, products, settings)
SOURCE C:/xampp/htdocs/aam/database/aam_core_schema.sql;

-- 2. Inventory tables
SOURCE C:/xampp/htdocs/aam/database/aam_inventory_schema.sql;

-- 3. POS tables
SOURCE C:/xampp/htdocs/aam/database/aam_pos_schema.sql;

-- 4. Remaining tables (harvests, dispatches, audit_logs, notifications)
SOURCE C:/xampp/htdocs/aam/database/aam_final_schema.sql;

-- 5. Auth Phase 7 enhancements (account_status, remember_me_tokens)
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_28_000001_auth_enhancements.sql;

-- 6. Login history enhancements (browser, OS, device tracking)
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_28_000002_login_history_enhancements.sql;
```

### Step 3 — First login

| Field | Value |
|-------|-------|
| URL | `http://localhost/aam/app/login` |
| Username | `admin` |
| Password | `Admin@2026` |

**You will be forced to change the password on first login.**

---

## Phase 7 Deliverables — What Was Built

### Authentication (Complete)
- Login with username or phone number
- Password hashing with bcrypt (cost=12)
- Remember Me — 30-day secure cookie (selector/validator pattern)
- Session management — 30-min idle timeout, fingerprint protection, fixation prevention
- Forced password change on first login
- Forgot password — secure token generation (SHA-256 hashed in DB)
- Password reset — token validation + expiry (60 min)
- Auto-logout on session expiry
- Concurrent session detection via remember_me_tokens table

### Authorization (Complete)
- Role-Based Access Control (RBAC) — 6 roles
- Permission checking via role_permissions table
- Session-cached permissions (no DB hit per request)
- AuthMiddleware — all authenticated routes
- RoleMiddleware — route-level role enforcement
- PermissionMiddleware — granular permission enforcement
- CsrfMiddleware — all state-changing routes

### User Management (Complete)
- Admin user list with search + role filter + pagination
- Create user with temp password (must change on login)
- Edit user — name, phone, role
- Toggle active/inactive
- Unlock locked accounts
- Admin reset password (generates temp password)

### Profile Management (Complete)
- View profile — account info, session data, login history
- Edit profile — name, phone
- Upload / remove profile photo (JPG, PNG, WebP, max 5MB)
- Change password — current password verification
- Active sessions (remember-me devices) management
- Revoke single session / all sessions

### Security (Complete)
- Failed login counter with auto-lock at 5 attempts
- IP-based brute force detection (20 fails / 60 min)
- CSRF token generation, rotation, and verification
- Input sanitization via Sanitizer class
- Output escaping via e() helper
- Password strength validation (min 8, upper, lower, number)
- Common password blacklist
- SQL injection prevention (PDO prepared statements throughout)
- XSS prevention (htmlspecialchars on all output)
- Secure cookies (HttpOnly, SameSite=Strict)
- Audit log viewer for administrators

### UI / Views (Complete)
- Professional enterprise login page
- Change password (forced + optional)
- Forgot password + token display
- Reset password with strength meter
- User profile page with session countdown
- Profile edit with photo upload preview
- Access denied page (403)
- Session expired page
- Error pages: 403, 404, 500, default
- User management: list, create, edit
- Audit log: list, detail
- Role-aware sidebar (shows only relevant items per role)
- Dashboard stubs for all 6 roles

### Database Migrations (Complete)
- Migration 1: auth_enhancements — account_status, profile_photo, remember_me_tokens
- Migration 2: login_history_enhancements — browser, OS, device_type, logout tracking

---

## Roles & Default Access

| Role | Login Redirects To | Can Access |
|------|--------------------|------------|
| Administrator | /dashboard | Everything |
| General Manager | /dashboard | Dashboard, Reports, Analytics (read-only) |
| Farm Manager | /dashboard | Farm, Harvests, Dispatches |
| Warehouse Manager | /dashboard | Warehouse, Receiving, Transfers |
| Storekeeper | /dashboard | Shop, Transfers, Notifications |
| Salesperson | /pos | POS Terminal only |

---

## Project Structure

```
app/
├── index.php                    ← Single entry point (bootstrap)
├── .htaccess                    ← URL rewriting + security headers
├── .env                         ← Local credentials (NOT in Git)
├── .env.example                 ← Template
│
├── config/
│   ├── Application.php          ← App config (dot-notation)
│   ├── Environment.php          ← .env loader
│   ├── constants.php            ← All constants (roles, HTTP codes, etc.)
│   └── database.php             ← DB DSN builder
│
├── core/
│   ├── Autoloader.php           ← PSR-4 autoloader
│   ├── Router.php               ← HTTP router
│   ├── routes.php               ← All route definitions
│   ├── BaseController.php       ← Parent controller
│   ├── BaseModel.php            ← Parent model (CRUD, paginate)
│   ├── Database.php             ← PDO singleton + transactions
│   ├── View.php                 ← Template engine + sections
│   ├── Request.php              ← Sanitized request wrapper
│   ├── SessionManager.php       ← Secure session lifecycle
│   ├── Logger.php               ← Multi-channel logger
│   ├── ErrorHandler.php         ← Global error/exception handler
│   ├── Pagination.php           ← Bootstrap 5 pagination
│   ├── Cache.php                ← File-based cache
│   ├── Cookie.php               ← Encrypted cookie utility
│   ├── Security/
│   │   ├── CSRF.php             ← Token generation + verification
│   │   ├── Sanitizer.php        ← Input sanitization
│   │   ├── PasswordUtility.php  ← bcrypt hash/verify/generate
│   │   └── Encryption.php       ← AES-256-CBC encrypt/decrypt
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── RoleMiddleware.php
│   │   ├── PermissionMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   └── MaintenanceMiddleware.php
│   └── Exceptions/
│       └── Exceptions.php       ← Custom exception classes
│
├── helpers/
│   ├── Validator.php            ← Server-side validation (25+ rules)
│   ├── Response.php             ← JSON response helpers
│   ├── DateHelper.php           ← Date/time formatting
│   ├── NumberHelper.php         ← Currency, quantity, percent
│   ├── StringHelper.php         ← Slugs, refs, masks, truncate
│   ├── FileHelper.php           ← Secure file upload
│   ├── UploadHelper.php         ← Upload facade
│   ├── functions.php            ← Global helpers (url, e, csrf_input…)
│   ├── format.php               ← View formatting functions
│   ├── security_helpers.php     ← can(), sanitize(), csrf_input()
│   └── response_helpers.php     ← json_success(), json_error()
│
├── modules/
│   ├── auth/                    ← ✅ Phase 7 — COMPLETE
│   │   ├── controllers/         ← AuthController, PasswordController, ProfileController
│   │   ├── models/              ← UserModel, RoleModel, LoginHistoryModel, RememberMeModel
│   │   ├── services/            ← AuthenticationService, AuthorizationService,
│   │   │                           PasswordService, SessionService
│   │   └── views/               ← login, change_password, forgot_password,
│   │                               reset_password, profile, profile_edit,
│   │                               access_denied, session_expired
│   ├── admin/                   ← ✅ Phase 7 — User management + Audit logs
│   │   ├── controllers/         ← UserController, AuditController, ProductController (stub),
│   │   │                           CategoryController (stub), UnitController (stub)
│   │   └── views/               ← users/ (index, create, edit), audit/ (index, show)
│   ├── dashboard/               ← ✅ Phase 7 — Role stubs (KPIs in Phase 8)
│   ├── notifications/           ← ✅ Phase 7 — List + AJAX polling
│   ├── farm/                    ← 🔲 Phase 8 stub
│   ├── warehouse/               ← 🔲 Phase 8 stub
│   ├── transfer/                ← 🔲 Phase 8 stub
│   ├── inventory/               ← 🔲 Phase 8 stub
│   ├── shop/                    ← 🔲 Phase 8 stub
│   ├── pos/                     ← 🔲 Phase 8 stub
│   ├── reports/                 ← 🔲 Phase 8 stub
│   └── settings/                ← 🔲 Phase 8 stub
│
├── templates/
│   ├── master.php               ← Main layout (sidebar + topbar)
│   ├── minimal.php              ← Login/reset layout (no nav)
│   ├── print.php                ← Receipt/report print layout
│   ├── topbar.php               ← Fixed top navigation
│   ├── sidebar.php              ← Role-aware sidebar
│   ├── breadcrumb.php           ← Auto-breadcrumb
│   ├── footer.php               ← Page footer
│   ├── alerts.php               ← Flash messages component
│   └── errors/                  ← 403, 404, 500, default
│
├── public/
│   └── assets/
│       ├── css/app.css          ← Custom styles + Bootstrap overrides
│       ├── css/print.css        ← Thermal receipt + A4 print styles
│       ├── js/app.js            ← Global JS (sidebar, AJAX, notifications)
│       └── js/validation.js     ← Client-side form validation
│
├── storage/
│   ├── logs/                    ← app.log, error.log, security.log, db.log
│   ├── cache/                   ← File cache
│   └── uploads/
│       ├── profiles/            ← Profile photos
│       ├── products/            ← Product images (Phase 8)
│       └── receipts/            ← Receipt files (Phase 8)
│
└── database/
    └── migrations/
        ├── 2026_07_28_000001_auth_enhancements.sql
        ├── 2026_07_28_000002_login_history_enhancements.sql
        └── README.md
```

---

## Security Architecture

| Control | Implementation |
|---------|---------------|
| Password storage | bcrypt, cost=12, `password_hash()` |
| Session security | Regeneration on login, fingerprint check, idle timeout |
| CSRF protection | Per-session token, `hash_equals()` comparison |
| Input sanitization | `Sanitizer::clean()` on all GET/POST before use |
| Output escaping | `e()` = `htmlspecialchars(ENT_QUOTES|ENT_HTML5)` on all output |
| SQL injection | PDO prepared statements — no string interpolation |
| Remember me | Selector/validator pattern, SHA-256 hash in DB |
| Brute force | Account lockout at 5 fails + IP rate limiting |
| Encryption | AES-256-CBC + HMAC-SHA256 for cookies |
| File uploads | Extension + MIME check, UUID rename, no PHP execution in uploads/ |
| Cookie security | HttpOnly=true, SameSite=Strict, Secure=true (production) |
| Authorization | Role middleware + permission middleware on every sensitive route |

---

## Coding Standards

- `declare(strict_types=1)` in every PHP file
- PSR-4 namespace structure: `AAM\Module\SubNamespace\ClassName`
- All controllers extend `AAM\Core\BaseController`
- All models extend `AAM\Core\BaseModel`
- All user input sanitized via `$this->request->post()` or `$this->input()`
- All output escaped with `e()` function
- All DB queries use PDO prepared statements (never string concatenation)
- All state-changing POST routes protected by `CsrfMiddleware`
- All authenticated routes protected by `AuthMiddleware`
- PHPDoc block on every file explaining purpose, dependencies, how it works

---

## Phase 8 — Next Steps

| Module | Status |
|--------|--------|
| Farm Management | Harvest registration, dispatch workflow |
| Warehouse Management | Receiving queue, discrepancy tracking |
| Transfer Management | Warehouse→shop transfer + confirmation |
| Inventory Management | Live stock levels, adjustments, alerts |
| Shop Management | Shop inventory, storekeeper views |
| Point of Sale | Cart, payment, receipt printing |
| Reports | Date-range reports, PDF export |
| Analytics | KPI charts, trend analysis |
| Settings | System configuration UI |

---

## Testing

See `AAM_Phase7_Testing_Checklist.md` in the project root for 153 test cases covering:
- Functional tests (login, logout, profile, user management)
- Security tests (brute force, CSRF, injection, authorization)
- Session tests (timeout, fingerprint, history)
- Role tests (all 6 roles, access control)
- UI/UX tests (responsive, accessibility, error pages)
- Database verification queries

---

---

## Team Members

This project was built by a team of 5 students from Arsi University.

| # | Name  |
|---|-------|
| 1 | Tomas |
| 2 | Hiwot |
| 3 | Eden  |
| 4 | Fenet |
| 5 | Marta |

---

*Arsi Agri-Manage — Arsi University, Ethiopia*  
*Phase 7 completed: July 28, 2026*
