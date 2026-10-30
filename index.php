<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File        : index.php
 * Location    : app/index.php
 * Purpose     : Application Bootstrap — Single Entry Point
 *               All HTTP requests are routed through this file.
 *               Initializes the entire application stack.
 * Author      : AAM Development Team
 * Organization: Arsi University
 * Phase       : 6 — Core Application Architecture
 * =========================================================
 */

declare(strict_types=1);

// ─── Measure Request Start Time ──────────────────────────────────────────────
define('AAM_START_TIME', microtime(true));
define('AAM_START_MEMORY', memory_get_usage());

// ─── Root Path Constants ──────────────────────────────────────────────────────
define('ROOT_PATH',     __DIR__);
define('CONFIG_PATH',   ROOT_PATH . '/config');
define('CORE_PATH',     ROOT_PATH . '/core');
define('MODULES_PATH',  ROOT_PATH . '/modules');
define('HELPERS_PATH',  ROOT_PATH . '/helpers');
define('TEMPLATES_PATH',ROOT_PATH . '/templates');
define('STORAGE_PATH',  ROOT_PATH . '/storage');
define('LOGS_PATH',     ROOT_PATH . '/storage/logs');
define('CACHE_PATH',    ROOT_PATH . '/storage/cache');
define('UPLOADS_PATH',  ROOT_PATH . '/storage/uploads');
define('VENDOR_PATH',   ROOT_PATH . '/vendor');
define('PUBLIC_PATH',   ROOT_PATH . '/public');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('API_PATH',      ROOT_PATH . '/api');

// ─── Require Autoloader First (needed for all subsequent requires) ────────────
require_once CORE_PATH . '/Autoloader.php';

// ─── Register AAM Autoloader ──────────────────────────────────────────────────
\AAM\Core\Autoloader::register();

// ─── Load Environment Configuration ──────────────────────────────────────────
\AAM\Config\Environment::load();

// ─── Load Application Configuration ──────────────────────────────────────────
\AAM\Config\Application::initialize();

// ─── Initialize Error / Exception Handling ───────────────────────────────────
\AAM\Core\ErrorHandler::register();

// ─── Initialize Session ───────────────────────────────────────────────────────
\AAM\Core\SessionManager::start();

// ─── Initialize Application Logger ───────────────────────────────────────────
\AAM\Core\Logger::initialize();

// ─── Initialize Database Connection ─────────────────────────────────────────
\AAM\Core\Database::initialize();

// ─── Initialize CSRF Protection ───────────────────────────────────────────────
\AAM\Security\CSRF::initialize();

// ─── Boot the Router and Dispatch Request ────────────────────────────────────
$router = new \AAM\Core\Router();

// ─── Load all application route definitions ──────────────────────────────────
require_once CORE_PATH . '/routes.php';

// ─── Dispatch incoming HTTP request ──────────────────────────────────────────
$router->dispatch();
