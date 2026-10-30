<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Router.php
 * Purpose  : Enterprise HTTP Router.
 *            Supports GET, POST, PUT, DELETE.
 *            Named route parameters: /farms/{id}
 *            Route groups with prefix.
 *            Middleware stack per route.
 *            404 and 405 handling.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;

class Router
{
    /**
     * Registered routes.
     * [ method => [ pattern => [ 'handler' => callable|string, 'middleware' => [] ] ] ]
     *
     * @var array<string, array<string, array<string,mixed>>>
     */
    private array $routes = [];

    /** @var string[] Active route group prefix stack */
    private array $groupPrefixes = [];

    /** @var string[] Active group middleware stack */
    private array $groupMiddleware = [];

    /** @var callable|null Custom 404 handler */
    private mixed $notFoundHandler = null;

    /** @var callable|null Custom 405 handler */
    private mixed $methodNotAllowedHandler = null;

    // ─── Route Registration ───────────────────────────────────────────────────

    /**
     * Register a GET route.
     *
     * @param string          $pattern    URL pattern: /farms/{id}
     * @param callable|string $handler    Closure or 'Controller@method'
     * @param string[]        $middleware Middleware class names to run
     */
    public function get(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $pattern, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * Register a route that responds to any HTTP method.
     */
    public function any(string $pattern, callable|string $handler, array $middleware = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $this->addRoute($method, $pattern, $handler, $middleware);
        }
        return $this;
    }

    /**
     * Create a route group with a shared prefix and/or middleware.
     *
     * @param string   $prefix
     * @param callable $callback   Receives $this router
     * @param string[] $middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $this->groupPrefixes[]   = $prefix;
        $this->groupMiddleware[] = $middleware;

        $callback($this);

        array_pop($this->groupPrefixes);
        array_pop($this->groupMiddleware);

        return $this;
    }

    /**
     * Internal: add a route to the registry.
     */
    private function addRoute(
        string         $method,
        string         $pattern,
        callable|string $handler,
        array          $middleware
    ): self {
        // Build full prefix from group stack
        $prefix = implode('', $this->groupPrefixes);
        $fullPattern = $prefix . '/' . ltrim($pattern, '/');
        $fullPattern = rtrim($fullPattern, '/') ?: '/';

        // Merge group middleware
        $groupMerged   = empty($this->groupMiddleware) ? [] : array_merge(...$this->groupMiddleware);
        $allMiddleware = array_merge($groupMerged, $middleware);

        // De-duplicate middleware by class name, preserving objects (e.g. RoleMiddleware instances)
        $seen = [];
        $deduplicated = [];
        foreach ($allMiddleware as $mw) {
            $key = is_object($mw) ? serialize($mw) : $mw;
            if (!in_array($key, $seen, true)) {
                $seen[] = $key;
                $deduplicated[] = $mw;
            }
        }

        $this->routes[$method][$fullPattern] = [
            'handler'    => $handler,
            'middleware' => $deduplicated,
        ];

        return $this;
    }

    // ─── Dispatch ─────────────────────────────────────────────────────────────

    /**
     * Dispatch the current HTTP request to the matching route.
     */
    public function dispatch(): void
    {
        $method = $this->getMethod();
        $uri    = $this->getUri();

        // Find matching route
        $match = $this->match($method, $uri);

        if ($match === null) {
            // Check if the URI matches any route with a different method (405)
            if ($this->uriExistsWithAnotherMethod($uri, $method)) {
                $this->handleMethodNotAllowed($method, $uri);
            } else {
                $this->handleNotFound($uri);
            }
            return;
        }

        // Run middleware stack then call handler
        $this->runMiddleware(
            $match['middleware'],
            $match['params'],
            fn() => $this->callHandler($match['handler'], $match['params'])
        );
    }

    /**
     * Try to match URI against registered routes for given method.
     *
     * @return array<string,mixed>|null
     */
    private function match(string $method, string $uri): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $pattern => $route) {
            $params = $this->matchPattern($pattern, $uri);
            if ($params !== null) {
                return [
                    'handler'    => $route['handler'],
                    'middleware' => $route['middleware'],
                    'params'     => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Match a URL pattern against a URI and extract named parameters.
     *
     * @return array<string, string>|null  Parameters on match, null on no match
     */
    private function matchPattern(string $pattern, string $uri): ?array
    {
        // Escape pattern and replace {param} with named capture groups
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Filter out numeric keys from matches
            return array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Check if a URI is registered under a different HTTP method.
     */
    private function uriExistsWithAnotherMethod(string $uri, string $currentMethod): bool
    {
        foreach ($this->routes as $method => $routes) {
            if ($method === $currentMethod) {
                continue;
            }
            foreach ($routes as $pattern => $_) {
                if ($this->matchPattern($pattern, $uri) !== null) {
                    return true;
                }
            }
        }
        return false;
    }

    // ─── Handler Invocation ───────────────────────────────────────────────────

    /**
     * Call the route handler: closure or 'ControllerClass@method'.
     *
     * @param callable|string      $handler
     * @param array<string,string> $params
     */
    private function callHandler(callable|string $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        // Parse 'ControllerClass@method'
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);

            if (!class_exists($class)) {
                throw new \RuntimeException("Controller class '{$class}' not found.");
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method '{$method}' not found on controller '{$class}'.");
            }

            call_user_func_array([$controller, $method], $params);
            return;
        }

        throw new \RuntimeException("Invalid route handler: " . print_r($handler, true));
    }

    // ─── Middleware Pipeline ──────────────────────────────────────────────────

    /**
     * Run a middleware stack.
     * Each middleware receives ($params, $next) and must call $next() to continue.
     *
     * @param string[]             $middleware
     * @param array<string,string> $params
     * @param callable             $final  Final handler (route action)
     */
    private function runMiddleware(array $middleware, array $params, callable $final): void
    {
        if (empty($middleware)) {
            $final();
            return;
        }

        $middlewareEntry = array_shift($middleware);

        // Support pre-instantiated middleware objects (e.g. new RoleMiddleware([ROLE_ADMIN]))
        if (is_object($middlewareEntry)) {
            $middlewareEntry->handle($params, fn() => $this->runMiddleware($middleware, $params, $final));
            return;
        }

        if (!class_exists($middlewareEntry)) {
            throw new \RuntimeException("Middleware class '{$middlewareEntry}' not found.");
        }

        $instance = new $middlewareEntry();
        $instance->handle($params, fn() => $this->runMiddleware($middleware, $params, $final));
    }

    // ─── Custom Handlers ─────────────────────────────────────────────────────

    /**
     * Register a custom 404 handler.
     */
    public function setNotFoundHandler(callable $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Register a custom 405 handler.
     */
    public function setMethodNotAllowedHandler(callable $handler): self
    {
        $this->methodNotAllowedHandler = $handler;
        return $this;
    }

    /**
     * Handle a 404 Not Found response.
     */
    private function handleNotFound(string $uri): void
    {
        http_response_code(HTTP_NOT_FOUND);
        Logger::notice('404 Not Found', ['uri' => $uri]);

        if ($this->notFoundHandler !== null) {
            call_user_func($this->notFoundHandler, $uri);
            return;
        }

        // Default 404 view
        $view = new View();
        $view->renderError(404, 'Page Not Found', "The page you requested ({$uri}) does not exist.");
    }

    /**
     * Handle a 405 Method Not Allowed response.
     */
    private function handleMethodNotAllowed(string $method, string $uri): void
    {
        http_response_code(HTTP_METHOD_NOT_ALLOWED);
        Logger::notice('405 Method Not Allowed', ['method' => $method, 'uri' => $uri]);

        if ($this->methodNotAllowedHandler !== null) {
            call_user_func($this->methodNotAllowedHandler, $method, $uri);
            return;
        }

        $view = new View();
        $view->renderError(405, 'Method Not Allowed', "The HTTP method '{$method}' is not allowed for this URL.");
    }

    // ─── Request Helpers ─────────────────────────────────────────────────────

    /**
     * Get the HTTP method, honouring _method override for PUT/DELETE from forms.
     */
    private function getMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Support method override via POST field _method (for HTML forms)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE', 'PATCH'], true)) {
                $method = $override;
            }
        }

        return $method;
    }

    /**
     * Get the current request URI, stripped of query string and base path.
     */
    private function getUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode((string) $uri);

        // Strip the application base path prefix (e.g. /aam/app)
        $basePath = parse_url(
            (string) Application::get('url', 'http://localhost/aam/app'),
            PHP_URL_PATH
        );

        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . ltrim($uri, '/');
        return rtrim($uri, '/') ?: '/';
    }

    /**
     * Get all registered routes (for debugging/testing).
     *
     * @return array<string, array<string, array<string,mixed>>>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
