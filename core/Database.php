<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Database.php
 * Purpose  : PDO Database Connection Manager.
 *            Singleton pattern — one connection per request.
 *            Supports transactions with nesting protection.
 *            Full error handling and query logging.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use PDO;
use PDOException;
use PDOStatement;
use AAM\Config\Application;
use AAM\Config\DatabaseConfig;

class Database
{
    /** @var Database|null Singleton instance */
    private static ?Database $instance = null;

    /** @var PDO PDO connection handle */
    private PDO $pdo;

    /** @var int Transaction nesting level */
    private int $transactionDepth = 0;

    /** @var array<array<string,mixed>> Query log (debug mode only) */
    private array $queryLog = [];

    /** @var bool Whether query logging is active */
    private bool $loggingEnabled = false;

    /**
     * Private constructor — use getInstance() or initialize().
     */
    private function __construct()
    {
        $this->loggingEnabled = Application::get('debug', false);
        $this->connect();
    }

    /**
     * Establish the PDO connection.
     *
     * @throws \RuntimeException if connection fails
     */
    private function connect(): void
    {
        $dsn      = DatabaseConfig::getDsn();
        $username = Application::get('database.username');
        $password = Application::get('database.password');
        $options  = Application::get('database.options', []);

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Log without exposing credentials
            Logger::critical('Database connection failed: ' . $e->getMessage(), [
                'host' => Application::get('database.host'),
                'db'   => Application::get('database.name'),
            ]);
            throw new \RuntimeException(
                'Unable to connect to the database. Please contact the system administrator.',
                (int) $e->getCode()
            );
        }
    }

    /**
     * Called once during bootstrap to pre-warm the connection.
     */
    public static function initialize(): void
    {
        self::getInstance();
    }

    /**
     * Get the singleton Database instance.
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the raw PDO object.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a prepared statement query.
     *
     * @param string               $sql    SQL query with :named or ? placeholders
     * @param array<mixed>         $params Bound parameters
     * @return PDOStatement
     * @throws \RuntimeException on query failure
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($this->loggingEnabled) {
                $duration = round((microtime(true) - $start) * 1000, 2);
                $this->queryLog[] = [
                    'sql'      => $sql,
                    'params'   => $params,
                    'duration' => $duration . 'ms',
                    'time'     => date(DATE_FORMAT_LOG),
                ];

                // Log slow queries
                $slowThreshold = 1000;
                if ($duration > $slowThreshold) {
                    Logger::warning('Slow query detected', [
                        'sql'      => $sql,
                        'duration' => $duration . 'ms',
                    ]);
                }
            }

            return $stmt;
        } catch (PDOException $e) {
            Logger::error('Database query failed', [
                'sql'     => $sql,
                'params'  => $params,
                'error'   => $e->getMessage(),
                'code'    => $e->getCode(),
            ]);
            throw new \RuntimeException('Database query failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Fetch a single row from a query result.
     *
     * @param string       $sql
     * @param array<mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * Fetch all rows from a query result.
     *
     * @param string       $sql
     * @param array<mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single column value from a query result.
     *
     * @param string       $sql
     * @param array<mixed> $params
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE) and return affected rows.
     *
     * @param string       $sql
     * @param array<mixed> $params
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return (int) $this->query($sql, $params)->rowCount();
    }

    /**
     * Get the last inserted auto-increment ID.
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    // ─── Transaction Management ───────────────────────────────────────────────

    /**
     * Begin a transaction.
     * Tracks nesting depth — only true BEGIN on depth 0.
     * Uses SAVEPOINTs for nested transactions.
     */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT sp_{$this->transactionDepth}");
        }
        $this->transactionDepth++;
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            throw new \RuntimeException('Cannot commit: no active transaction.');
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT sp_{$this->transactionDepth}");
        }
    }

    /**
     * Roll back the current transaction.
     */
    public function rollback(): void
    {
        if ($this->transactionDepth === 0) {
            throw new \RuntimeException('Cannot rollback: no active transaction.');
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo->rollBack();
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT sp_{$this->transactionDepth}");
        }
    }

    /**
     * Execute a callable within a transaction.
     * Automatically commits on success, rolls back on exception.
     *
     * @param callable $callback Receives the Database instance
     * @return mixed  Return value of the callback
     * @throws \Throwable Re-throws the callback exception after rollback
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Check if currently inside a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    // ─── Query Log ────────────────────────────────────────────────────────────

    /**
     * Get the full query log (debug mode only).
     *
     * @return array<array<string,mixed>>
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Get the total number of queries executed this request.
     */
    public function getQueryCount(): int
    {
        return count($this->queryLog);
    }

    /**
     * Reset the connection (useful for tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Prevent cloning of singleton.
     */
    private function __clone() {}
}
