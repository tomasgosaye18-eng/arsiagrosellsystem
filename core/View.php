<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/View.php
 * Purpose  : View engine / template loader.
 *            Renders PHP view files inside a layout.
 *            Supports: master layout, partials, sections,
 *            asset helpers, error pages.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;

class View
{
    /** @var array<string, mixed> Global data available in all views */
    private static array $globalData = [];

    /** @var array<string, string> Named section content buffers */
    private array $sections = [];

    /** @var string|null Currently open section name */
    private ?string $currentSection = null;

    /**
     * Register global view data (available in every view without passing).
     *
     * @param string $key
     * @param mixed  $value
     */
    public static function share(string $key, mixed $value): void
    {
        self::$globalData[$key] = $value;
    }

    /**
     * Render a view inside the master layout.
     *
     * @param string               $viewPath  Dot-notation path relative to modules/:
     *                                         'admin.dashboard' → modules/admin/views/dashboard.php
     *                                         'auth.login'      → modules/auth/views/login.php
     * @param array<string, mixed> $data      Data to pass to the view
     * @param string               $layout    Layout name ('master', 'minimal', 'print')
     */
    public function render(string $viewPath, array $data = [], string $layout = 'master'): void
    {
        $viewFile = $this->resolveViewPath($viewPath);

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View file not found: {$viewFile}");
        }

        // Merge global data with local data (local takes priority)
        $allData = array_merge(self::$globalData, $this->getDefaultViewData(), $data);

        // Capture view content into a buffer
        $content = $this->captureView($viewFile, $allData);

        // Render inside layout
        $layoutFile = TEMPLATES_PATH . '/' . $layout . '.php';

        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout file not found: {$layoutFile}");
        }

        // Expose data + captured content to layout
        $allData['content'] = $content;
        $allData['view']    = $this;

        $this->captureAndOutput($layoutFile, $allData);
    }

    /**
     * Render a partial view without any layout wrapper.
     * Useful for AJAX partial responses.
     *
     * @param string               $viewPath
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $viewPath, array $data = []): void
    {
        $viewFile = $this->resolveViewPath($viewPath);

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Partial view not found: {$viewFile}");
        }

        $allData = array_merge(self::$globalData, $this->getDefaultViewData(), $data);
        $this->captureAndOutput($viewFile, $allData);
    }

    /**
     * Render a template file in the templates/ directory.
     *
     * @param string               $templateName  File name without .php
     * @param array<string, mixed> $data
     */
    public function renderTemplate(string $templateName, array $data = []): void
    {
        $file = TEMPLATES_PATH . '/' . $templateName . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Template not found: {$file}");
        }

        $allData = array_merge(self::$globalData, $this->getDefaultViewData(), $data);
        $this->captureAndOutput($file, $allData);
    }

    /**
     * Include a shared template fragment (header, sidebar, footer, etc.)
     * Called from within view files as: $view->include('sidebar', $data)
     *
     * @param string               $name
     * @param array<string, mixed> $data
     */
    public function include(string $name, array $data = []): void
    {
        $file = TEMPLATES_PATH . '/' . $name . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Template fragment not found: {$file}");
        }

        $allData = array_merge(self::$globalData, $this->getDefaultViewData(), $data);
        extract($allData, EXTR_SKIP);
        require $file;
    }

    /**
     * Render a standard error page.
     *
     * @param int    $code     HTTP status code (403, 404, 500, etc.)
     * @param string $title    Error title
     * @param string $message  Error message
     */
    public function renderError(int $code, string $title, string $message): void
    {
        http_response_code($code);

        $errorView = TEMPLATES_PATH . '/errors/' . $code . '.php';
        $defaultErrorView = TEMPLATES_PATH . '/errors/default.php';

        $data = [
            'code'    => $code,
            'title'   => $title,
            'message' => $message,
            'view'    => $this,
        ];

        if (file_exists($errorView)) {
            extract($data, EXTR_SKIP);
            require $errorView;
        } elseif (file_exists($defaultErrorView)) {
            extract($data, EXTR_SKIP);
            require $defaultErrorView;
        } else {
            // Absolute fallback — inline minimal HTML
            echo "<!DOCTYPE html><html><head><title>{$code} {$title}</title></head>";
            echo "<body><h1>{$code} — {$title}</h1><p>" . htmlspecialchars($message) . "</p></body></html>";
        }
    }

    // ─── Asset Helpers (called from view files) ───────────────────────────────

    /**
     * Generate URL to a public asset.
     * Usage in views: $view->asset('css/app.css')
     */
    public function asset(string $path): string
    {
        $base = rtrim((string) Application::get('url'), '/');
        // Append file modification time as cache-buster so browsers
        // always load the latest JS/CSS after a code change.
        $fullPath = PUBLIC_PATH . '/assets/' . ltrim($path, '/');
        $ver      = file_exists($fullPath) ? filemtime($fullPath) : AAM_VERSION;
        return $base . '/public/assets/' . ltrim($path, '/') . '?v=' . $ver;
    }

    /**
     * Generate URL to an uploaded file.
     */
    public function upload(string $path): string
    {
        $base = rtrim((string) Application::get('url'), '/');
        return $base . '/public/uploads/' . ltrim($path, '/');
    }

    /**
     * Generate an application URL.
     */
    public function url(string $path = ''): string
    {
        $base = rtrim((string) Application::get('url'), '/');
        return $base . '/' . ltrim($path, '/');
    }

    // ─── Section Support (for layout regions) ────────────────────────────────

    /**
     * Start capturing output for a named section.
     */
    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End a section and store its content.
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new \RuntimeException('endSection() called without a matching section().');
        }
        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    /**
     * Output (yield) a section's captured content.
     * Used in layout templates.
     */
    public function yield(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    // ─── Flash Message Helper ─────────────────────────────────────────────────

    /**
     * Render flash message HTML if one exists.
     * Clears the flash message after reading.
     */
    public function flash(): void
    {
        $flash = SessionManager::get(SESSION_FLASH);
        if (!$flash) {
            return;
        }

        SessionManager::delete(SESSION_FLASH);

        $type    = htmlspecialchars((string) ($flash['type'] ?? 'info'));
        $message = htmlspecialchars((string) ($flash['message'] ?? ''));

        // Map AAM flash types to Bootstrap alert classes
        $bsClass = match($type) {
            FLASH_SUCCESS => 'success',
            FLASH_ERROR   => 'danger',
            FLASH_WARNING => 'warning',
            default       => 'info',
        };

        $icon = match($type) {
            FLASH_SUCCESS => 'bi-check-circle-fill',
            FLASH_ERROR   => 'bi-x-circle-fill',
            FLASH_WARNING => 'bi-exclamation-triangle-fill',
            default       => 'bi-info-circle-fill',
        };

        echo <<<HTML
        <div class="alert alert-{$bsClass} alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi {$icon} me-2 flex-shrink-0"></i>
            <div>{$message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        HTML;
    }

    /**
     * Render validation errors for a specific field.
     */
    public function fieldError(string $field): void
    {
        $errors = SessionManager::get('validation_errors', []);
        $fieldErrors = $errors[$field] ?? [];

        foreach ($fieldErrors as $error) {
            echo '<div class="invalid-feedback d-block">' . htmlspecialchars($error) . '</div>';
        }
    }

    /**
     * Return CSS class for invalid Bootstrap form field.
     */
    public function isInvalid(string $field): string
    {
        $errors = SessionManager::get('validation_errors', []);
        return isset($errors[$field]) ? 'is-invalid' : '';
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * Capture a PHP view file into a string.
     *
     * @param string               $file
     * @param array<string, mixed> $data
     * @return string
     */
    private function captureView(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /**
     * Render a PHP file with data and send to output directly.
     *
     * @param string               $file
     * @param array<string, mixed> $data
     */
    private function captureAndOutput(string $file, array $data): void
    {
        extract($data, EXTR_SKIP);
        require $file;
    }

    /**
     * Resolve a dot-notation view path to an absolute file path.
     *
     * Module views: 'admin.dashboard'    → modules/admin/views/dashboard.php
     *               'farm.harvests.create' → modules/farm/views/harvests/create.php
     *
     * @param string $viewPath
     * @return string Absolute file path
     */
    private function resolveViewPath(string $viewPath): string
    {
        // Support both slash-notation ('organization/farms/index')
        // and dot-notation ('organization.farms.index')
        if (str_contains($viewPath, '/')) {
            // Slash notation: first segment is module, rest is the path
            $parts  = explode('/', $viewPath);
            $module = array_shift($parts);
            $file   = implode('/', $parts);
        } else {
            // Dot notation: first segment is module, rest becomes slash-separated
            $parts  = explode('.', $viewPath);
            $module = array_shift($parts);
            $file   = implode('/', $parts);
        }

        return MODULES_PATH . "/{$module}/views/{$file}.php";
    }

    /**
     * Build default data available in every view.
     *
     * @return array<string, mixed>
     */
    private function getDefaultViewData(): array
    {
        return [
            'view'         => $this,
            'appName'      => Application::get('name', AAM_APP_NAME),
            'appVersion'   => Application::get('version', AAM_VERSION),
            'appUrl'       => Application::get('url', ''),
            'currentUser'  => SessionManager::get(SESSION_USER_DATA),
            'userRole'     => SessionManager::get(SESSION_USER_ROLE),
            'userId'       => SessionManager::get(SESSION_USER_ID),
            'csrfToken'    => \AAM\Security\CSRF::getToken(),
            'currentYear'  => date('Y'),
            'oldInput'     => SessionManager::get('old_input', []),
            'valErrors'    => SessionManager::get('validation_errors', []),
        ];
    }
}
