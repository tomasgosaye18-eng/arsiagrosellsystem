<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/BaseModel.php
 * Purpose  : Reusable base model.
 *            All module models extend this class.
 *            Provides: CRUD, pagination, search,
 *            soft deletes, transactions, query builder.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

abstract class BaseModel
{
    /** @var Database Database manager instance */
    protected Database $db;

    /** @var string Table name — must be set in each child model */
    protected string $table = '';

    /** @var string Primary key column name */
    protected string $primaryKey = 'id';

    /** @var bool Whether this table uses soft deletes (is_active column) */
    protected bool $softDelete = true;

    /** @var string[] Columns allowed for mass assignment */
    protected array $fillable = [];

    /** @var string[] Columns excluded from all reads */
    protected array $hidden = [];

    /** @var string[] Columns that should be cast to int */
    protected array $casts = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Expose the Database instance for direct query use in subclasses
     * and services that receive a model instance.
     */
    public function getDb(): Database
    {
        return $this->db;
    }

    // ─── Single Record Finders ────────────────────────────────────────────────

    /**
     * Find a record by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Find an active record by primary key (respects soft delete).
     *
     * @return array<string, mixed>|null
     */
    public function findActive(int $id): ?array
    {
        if ($this->softDelete) {
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `{$this->primaryKey}` = :id AND `is_active` = 1 LIMIT 1";
        } else {
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `{$this->primaryKey}` = :id LIMIT 1";
        }
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Find a record by a single column value.
     *
     * @return array<string, mixed>|null
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $column = $this->sanitizeColumn($column);
        $sql    = "SELECT * FROM `{$this->table}` WHERE `{$column}` = :val LIMIT 1";
        return $this->db->fetchOne($sql, ['val' => $value]);
    }

    /**
     * Find a record by multiple conditions (AND).
     *
     * @param array<string, mixed> $conditions
     * @return array<string, mixed>|null
     */
    public function findWhere(array $conditions): ?array
    {
        [$where, $params] = $this->buildWhere($conditions);
        $sql = "SELECT * FROM `{$this->table}` WHERE {$where} LIMIT 1";
        return $this->db->fetchOne($sql, $params);
    }

    // ─── Collection Finders ───────────────────────────────────────────────────

    /**
     * Get all records (active only if softDelete enabled).
     *
     * @param string $orderBy
     * @param string $direction
     * @return array<int, array<string, mixed>>
     */
    public function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $orderBy   = $this->sanitizeColumn($orderBy);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        if ($this->softDelete) {
            $sql = "SELECT * FROM `{$this->table}` WHERE `is_active` = 1 
                    ORDER BY `{$orderBy}` {$direction}";
        } else {
            $sql = "SELECT * FROM `{$this->table}` ORDER BY `{$orderBy}` {$direction}";
        }

        return $this->db->fetchAll($sql);
    }

    /**
     * Get records matching conditions.
     *
     * @param array<string, mixed> $conditions
     * @param string               $orderBy
     * @param string               $direction
     * @return array<int, array<string, mixed>>
     */
    public function where(
        array  $conditions,
        string $orderBy   = 'id',
        string $direction = 'ASC'
    ): array {
        $orderBy   = $this->sanitizeColumn($orderBy);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        [$where, $params] = $this->buildWhere($conditions);
        $sql = "SELECT * FROM `{$this->table}` WHERE {$where} 
                ORDER BY `{$orderBy}` {$direction}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count records matching conditions.
     *
     * @param array<string, mixed> $conditions
     */
    public function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            $whereClause = $this->softDelete ? 'WHERE `is_active` = 1' : '';
            $sql = "SELECT COUNT(*) FROM `{$this->table}` {$whereClause}";
            return (int) $this->db->fetchColumn($sql);
        }

        [$where, $params] = $this->buildWhere($conditions);
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}";
        return (int) $this->db->fetchColumn($sql, $params);
    }

    /**
     * Check if a record exists.
     *
     * @param array<string, mixed> $conditions
     */
    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    // ─── Paginated Results ────────────────────────────────────────────────────

    /**
     * Get paginated records with optional search.
     *
     * @param int                  $page       Current page (1-based)
     * @param int                  $perPage    Records per page
     * @param string               $search     Search term (searches all varchar columns)
     * @param string[]             $searchCols Columns to search in
     * @param array<string, mixed> $conditions Additional WHERE conditions
     * @param string               $orderBy
     * @param string               $direction
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function paginate(
        int    $page       = 1,
        int    $perPage    = 20,
        string $search     = '',
        array  $searchCols = [],
        array  $conditions = [],
        string $orderBy    = 'id',
        string $direction  = 'DESC'
    ): array {
        $page      = max(1, $page);
        $perPage   = min(max(1, $perPage), PAGINATION_MAX);
        $orderBy   = $this->sanitizeColumn($orderBy);
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $offset    = ($page - 1) * $perPage;

        $params = [];
        $wheres = [];

        // Soft delete filter
        if ($this->softDelete) {
            $wheres[] = '`is_active` = 1';
        }

        // Static conditions
        foreach ($conditions as $col => $val) {
            $col      = $this->sanitizeColumn($col);
            $paramKey = 'cond_' . $col;
            $wheres[] = "`{$col}` = :{$paramKey}";
            $params[$paramKey] = $val;
        }

        // Search conditions
        if ($search !== '' && !empty($searchCols)) {
            $searchParts = [];
            foreach ($searchCols as $i => $col) {
                $col          = $this->sanitizeColumn($col);
                $paramKey     = 'search_' . $i;
                $searchParts[] = "`{$col}` LIKE :{$paramKey}";
                $params[$paramKey] = '%' . $search . '%';
            }
            $wheres[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $whereClause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

        // Count total
        $countSql = "SELECT COUNT(*) FROM `{$this->table}` {$whereClause}";
        $total    = (int) $this->db->fetchColumn($countSql, $params);

        // Fetch page
        $dataSql = "SELECT * FROM `{$this->table}` {$whereClause} 
                    ORDER BY `{$orderBy}` {$direction}
                    LIMIT :limit OFFSET :offset";

        $dataParams            = $params;
        $dataParams['limit']  = $perPage;
        $dataParams['offset'] = $offset;

        $data = $this->db->fetchAll($dataSql, $dataParams);

        return [
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => (int) ceil($total / $perPage),
            'from'        => $total > 0 ? $offset + 1 : 0,
            'to'          => min($offset + $perPage, $total),
        ];
    }

    // ─── Write Operations ─────────────────────────────────────────────────────

    /**
     * Insert a new record.
     *
     * @param array<string, mixed> $data
     * @return int New record ID
     */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);

        if (empty($data)) {
            throw new \InvalidArgumentException('No fillable data provided for insert.');
        }

        $columns      = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);
        $columnList   = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $valueList    = implode(', ', $placeholders);

        $sql = "INSERT INTO `{$this->table}` ({$columnList}) VALUES ({$valueList})";
        $this->db->execute($sql, $data);

        return $this->db->lastInsertId();
    }

    /**
     * Update a record by primary key.
     *
     * @param int                  $id
     * @param array<string, mixed> $data
     * @return int Affected rows
     */
    public function update(int $id, array $data): int
    {
        $data = $this->filterFillable($data);

        if (empty($data)) {
            throw new \InvalidArgumentException('No fillable data provided for update.');
        }

        $setParts = [];
        foreach (array_keys($data) as $col) {
            $col        = $this->sanitizeColumn($col);
            $setParts[] = "`{$col}` = :{$col}";
        }

        $data[$this->primaryKey] = $id;
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setParts) .
               " WHERE `{$this->primaryKey}` = :{$this->primaryKey}";

        return $this->db->execute($sql, $data);
    }

    /**
     * Update records matching conditions.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $conditions
     * @return int Affected rows
     */
    public function updateWhere(array $data, array $conditions): int
    {
        $data = $this->filterFillable($data);

        $setParts = [];
        $params   = [];

        foreach ($data as $col => $val) {
            $col         = $this->sanitizeColumn($col);
            $setParts[]  = "`{$col}` = :set_{$col}";
            $params["set_{$col}"] = $val;
        }

        [$where, $whereParams] = $this->buildWhere($conditions);
        $params = array_merge($params, $whereParams);

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setParts) . " WHERE {$where}";
        return $this->db->execute($sql, $params);
    }

    /**
     * Soft-delete a record (set is_active = 0).
     * If softDelete is false, performs a physical delete.
     *
     * @return int Affected rows
     */
    public function delete(int $id): int
    {
        if ($this->softDelete) {
            return $this->db->execute(
                "UPDATE `{$this->table}` SET `is_active` = 0 WHERE `{$this->primaryKey}` = :id",
                ['id' => $id]
            );
        }

        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id",
            ['id' => $id]
        );
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(int $id): int
    {
        if (!$this->softDelete) {
            throw new \LogicException("Table '{$this->table}' does not use soft deletes.");
        }

        return $this->db->execute(
            "UPDATE `{$this->table}` SET `is_active` = 1 WHERE `{$this->primaryKey}` = :id",
            ['id' => $id]
        );
    }

    // ─── Raw Query Helpers ────────────────────────────────────────────────────

    /**
     * Run a raw SELECT query and return all results.
     *
     * @param string       $sql
     * @param array<mixed> $params
     * @return array<int, array<string,mixed>>
     */
    protected function rawQuery(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Run a raw SELECT and return first row.
     *
     * @param string       $sql
     * @param array<mixed> $params
     * @return array<string,mixed>|null
     */
    protected function rawQueryOne(string $sql, array $params = []): ?array
    {
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Execute a raw INSERT/UPDATE/DELETE and return affected rows.
     */
    protected function rawExecute(string $sql, array $params = []): int
    {
        return $this->db->execute($sql, $params);
    }

    // ─── Transaction Helpers ─────────────────────────────────────────────────

    /**
     * Wrap an operation in a database transaction.
     *
     * @param callable $callback
     * @return mixed
     */
    protected function transaction(callable $callback): mixed
    {
        return $this->db->transaction($callback);
    }

    // ─── Internal Helpers ─────────────────────────────────────────────────────

    /**
     * Build a WHERE clause from a conditions array.
     *
     * @param array<string, mixed> $conditions
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $conditions): array
    {
        $parts  = [];
        $params = [];

        foreach ($conditions as $col => $val) {
            $safe = $this->sanitizeColumn($col);
            if ($val === null) {
                $parts[]  = "`{$safe}` IS NULL";
            } else {
                $paramKey  = 'w_' . $safe;
                $parts[]   = "`{$safe}` = :{$paramKey}";
                $params[$paramKey] = $val;
            }
        }

        $where = $parts ? implode(' AND ', $parts) : '1=1';
        return [$where, $params];
    }

    /**
     * Filter data array to only include fillable columns.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data; // No fillable list = allow all (use with care)
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Sanitize a column name to prevent injection.
     * Allows only alphanumeric characters and underscores.
     */
    private function sanitizeColumn(string $column): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: '{$column}'");
        }
        return $column;
    }
}
