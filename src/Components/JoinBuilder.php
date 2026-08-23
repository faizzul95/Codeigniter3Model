<?php

namespace OnlyPHP\Codeigniter3Model\Components;

class JoinBuilder
{
    private $db;
    private $table;
    private $type;
    private $conditions = [];
    private $whereConditions = [];
    private $orWhereConditions = [];
    private $orOnConditions = [];

    // Valid operators for validation
    private $validOperators = [
        '=', '!=', '<>', '<', '<=', '>', '>=', 
        'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE',
        'IN', 'NOT IN', 'IS', 'IS NOT', 'BETWEEN', 'NOT BETWEEN',
        'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP'
    ];

    public function __construct($database, $table, $type)
    {
        $this->db = $database;
        $this->table = $this->sanitizeTableName($table);
        $this->type = strtolower($type);

        if (empty($this->table)) {
            throw new \InvalidArgumentException('Table name cannot be empty');
        }
    }

    /**
     * Sanitize table name to prevent SQL injection
     */
    private function sanitizeTableName($table)
    {
        // Remove any dangerous characters and validate
        $table = trim($table);
        if (!preg_match('/^[a-zA-Z0-9_]+(\s+as\s+[a-zA-Z0-9_]+)?$/i', $table)) {
            throw new \InvalidArgumentException("Invalid table name format: {$table}");
        }
        return $table;
    }

    /**
     * Sanitize column name
     */
    private function sanitizeColumnName($column)
    {
        $column = trim($column);
        if (!preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name format: {$column}");
        }
        return $column;
    }

    /**
     * Validate operator
     */
    private function validateOperator($operator)
    {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, $this->validOperators)) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}");
        }
        return $operator;
    }

    /**
     * Escape value based on type
     */
    private function escapeValue($value)
    {
        if ($value === null) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_numeric($value)) {
            return $value;
        } elseif (is_string($value)) {
            return "'" . $this->db->escape_str($value) . "'";
        } elseif (is_array($value)) {
            return array_map([$this, 'escapeValue'], $value);
        } else {
            throw new \InvalidArgumentException('Invalid value type for database query');
        }
    }

    /**
     * Add ON condition for join
     */
    public function on($column1, $operator = '=', $column2 = null)
    {
        try {
            if ($column2 === null) {
                $column2 = $operator;
                $operator = '=';
            }

            $column1 = $this->sanitizeColumnName($column1);
            $column2 = $this->sanitizeColumnName($column2);
            $operator = $this->validateOperator($operator);

            $this->conditions[] = "{$column1} {$operator} {$column2}";
        } catch (\Exception $e) {
            log_message('error', 'Join ON condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add an OR ON condition. Kept in its own bucket so apply() can OR it with the
     * ON group.
     */
    public function orOn($column1, $operator = '=', $column2 = null)
    {
        try {
            if ($column2 === null) {
                $column2 = $operator;
                $operator = '=';
            }

            $column1 = $this->sanitizeColumnName($column1);
            $column2 = $this->sanitizeColumnName($column2);
            $operator = $this->validateOperator($operator);

            $this->orOnConditions[] = "{$column1} {$operator} {$column2}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR ON condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE condition to join
     */
    public function where($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);

            // Handle special cases
            if (strtoupper($operator) === 'IS' && $value === null) {
                $this->whereConditions[] = "{$column} IS NULL";
            } elseif (strtoupper($operator) === 'IS NOT' && $value === null) {
                $this->whereConditions[] = "{$column} IS NOT NULL";
            } else {
                $escapedValue = $this->escapeValue($value);
                $this->whereConditions[] = "{$column} {$operator} {$escapedValue}";
            }
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE condition
     */
    public function orWhere($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->orWhereConditions[] = "{$column} {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE IN condition
     */
    public function whereIn($column, $values)
    {
        try {
            if (!is_array($values) || empty($values)) {
                throw new \InvalidArgumentException('Values for whereIn must be a non-empty array');
            }

            $column = $this->sanitizeColumnName($column);
            $escapedValues = $this->escapeValue($values);
            $valuesList = implode(', ', $escapedValues);

            $this->whereConditions[] = "{$column} IN ({$valuesList})";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE IN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE NOT IN condition
     */
    public function whereNotIn($column, $values)
    {
        try {
            if (!is_array($values) || empty($values)) {
                throw new \InvalidArgumentException('Values for whereNotIn must be a non-empty array');
            }

            $column = $this->sanitizeColumnName($column);
            $escapedValues = $this->escapeValue($values);
            $valuesList = implode(', ', $escapedValues);

            $this->whereConditions[] = "{$column} NOT IN ({$valuesList})";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE NOT IN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE IN condition
     */
    public function orWhereIn($column, $values)
    {
        try {
            if (!is_array($values) || empty($values)) {
                throw new \InvalidArgumentException('Values for orWhereIn must be a non-empty array');
            }

            $column = $this->sanitizeColumnName($column);
            $escapedValues = $this->escapeValue($values);
            $valuesList = implode(', ', $escapedValues);

            $this->orWhereConditions[] = "{$column} IN ({$valuesList})";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE IN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE NOT IN condition. Fills a gap - the other three existed.
     */
    public function orWhereNotIn($column, $values)
    {
        try {
            if (!is_array($values) || empty($values)) {
                throw new \InvalidArgumentException('Values for orWhereNotIn must be a non-empty array');
            }

            $column = $this->sanitizeColumnName($column);
            $escapedValues = $this->escapeValue($values);
            $valuesList = implode(', ', $escapedValues);

            $this->orWhereConditions[] = "{$column} NOT IN ({$valuesList})";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE NOT IN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE NULL condition
     */
    public function whereNull($column)
    {
        try {
            $column = $this->sanitizeColumnName($column);
            $this->whereConditions[] = "{$column} IS NULL";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE NULL condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE NOT NULL condition
     */
    public function whereNotNull($column)
    {
        try {
            $column = $this->sanitizeColumnName($column);
            $this->whereConditions[] = "{$column} IS NOT NULL";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE NOT NULL condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE NULL condition
     */
    public function orWhereNull($column)
    {
        try {
            $column = $this->sanitizeColumnName($column);
            $this->orWhereConditions[] = "{$column} IS NULL";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE NULL condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE NOT NULL condition
     */
    public function orWhereNotNull($column)
    {
        try {
            $column = $this->sanitizeColumnName($column);
            $this->orWhereConditions[] = "{$column} IS NOT NULL";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE NOT NULL condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE BETWEEN condition
     */
    public function whereBetween($column, $values)
    {
        try {
            if (!is_array($values) || count($values) < 2) {
                throw new \InvalidArgumentException('Values for whereBetween must be an array with at least 2 elements');
            }

            $column = $this->sanitizeColumnName($column);
            $val1 = $this->escapeValue($values[0]);
            $val2 = $this->escapeValue($values[1]);

            $this->whereConditions[] = "{$column} BETWEEN {$val1} AND {$val2}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE BETWEEN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE BETWEEN condition
     */
    public function orWhereBetween($column, $values)
    {
        try {
            if (!is_array($values) || count($values) < 2) {
                throw new \InvalidArgumentException('Values for orWhereBetween must be an array with at least 2 elements');
            }

            $column = $this->sanitizeColumnName($column);
            $val1 = $this->escapeValue($values[0]);
            $val2 = $this->escapeValue($values[1]);

            $this->orWhereConditions[] = "{$column} BETWEEN {$val1} AND {$val2}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE BETWEEN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE NOT BETWEEN condition
     */
    public function whereNotBetween($column, $values)
    {
        try {
            if (!is_array($values) || count($values) < 2) {
                throw new \InvalidArgumentException('Values for whereNotBetween must be an array with at least 2 elements');
            }

            $column = $this->sanitizeColumnName($column);
            $val1 = $this->escapeValue($values[0]);
            $val2 = $this->escapeValue($values[1]);

            $this->whereConditions[] = "{$column} NOT BETWEEN {$val1} AND {$val2}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE NOT BETWEEN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE NOT BETWEEN condition
     */
    public function orWhereNotBetween($column, $values)
    {
        try {
            if (!is_array($values) || count($values) < 2) {
                throw new \InvalidArgumentException('Values for orWhereNotBetween must be an array with at least 2 elements');
            }

            $column = $this->sanitizeColumnName($column);
            $val1 = $this->escapeValue($values[0]);
            $val2 = $this->escapeValue($values[1]);

            $this->orWhereConditions[] = "{$column} NOT BETWEEN {$val1} AND {$val2}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE NOT BETWEEN condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE LIKE condition
     */
    public function whereLike($column, $value, $wildcard = 'both')
    {
        try {
            $column = $this->sanitizeColumnName($column);

            switch (strtolower($wildcard)) {
                case 'before':
                    $value = '%' . $value;
                    break;
                case 'after':
                    $value = $value . '%';
                    break;
                case 'both':
                default:
                    $value = '%' . $value . '%';
                    break;
                case 'none':
                    // Use value as is
                    break;
            }

            $escapedValue = $this->escapeValue($value);
            $this->whereConditions[] = "{$column} LIKE {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE LIKE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE LIKE condition
     */
    public function orWhereLike($column, $value, $wildcard = 'both')
    {
        try {
            $column = $this->sanitizeColumnName($column);

            switch (strtolower($wildcard)) {
                case 'before':
                    $value = '%' . $value;
                    break;
                case 'after':
                    $value = $value . '%';
                    break;
                case 'both':
                default:
                    $value = '%' . $value . '%';
                    break;
                case 'none':
                    break;
            }

            $escapedValue = $this->escapeValue($value);
            $this->orWhereConditions[] = "{$column} LIKE {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE LIKE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE NOT LIKE condition
     */
    public function whereNotLike($column, $value, $wildcard = 'both')
    {
        try {
            $column = $this->sanitizeColumnName($column);

            switch (strtolower($wildcard)) {
                case 'before':
                    $value = '%' . $value;
                    break;
                case 'after':
                    $value = $value . '%';
                    break;
                case 'both':
                default:
                    $value = '%' . $value . '%';
                    break;
                case 'none':
                    break;
            }

            $escapedValue = $this->escapeValue($value);
            $this->whereConditions[] = "{$column} NOT LIKE {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE NOT LIKE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE NOT LIKE condition
     */
    public function orWhereNotLike($column, $value, $wildcard = 'both')
    {
        try {
            $column = $this->sanitizeColumnName($column);

            switch (strtolower($wildcard)) {
                case 'before':
                    $value = '%' . $value;
                    break;
                case 'after':
                    $value = $value . '%';
                    break;
                case 'both':
                default:
                    $value = '%' . $value . '%';
                    break;
                case 'none':
                    break;
            }

            $escapedValue = $this->escapeValue($value);
            $this->orWhereConditions[] = "{$column} NOT LIKE {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE NOT LIKE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE DATE condition
     */
    public function whereDate($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->whereConditions[] = "DATE({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE DATE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE DATE condition
     */
    public function orWhereDate($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->orWhereConditions[] = "DATE({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE DATE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE YEAR condition
     */
    public function whereYear($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->whereConditions[] = "YEAR({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE YEAR condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE YEAR condition
     */
    public function orWhereYear($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->orWhereConditions[] = "YEAR({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE YEAR condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE MONTH condition
     */
    public function whereMonth($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->whereConditions[] = "MONTH({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE MONTH condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE MONTH condition
     */
    public function orWhereMonth($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->orWhereConditions[] = "MONTH({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE MONTH condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE DAY condition
     */
    public function whereDay($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->whereConditions[] = "DAY({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE DAY condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE DAY condition
     */
    public function orWhereDay($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->orWhereConditions[] = "DAY({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE DAY condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add WHERE TIME condition
     */
    public function whereTime($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->whereConditions[] = "TIME({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE TIME condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add OR WHERE TIME condition
     */
    public function orWhereTime($column, $operator = '=', $value = null)
    {
        try {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $column = $this->sanitizeColumnName($column);
            $operator = $this->validateOperator($operator);
            $escapedValue = $this->escapeValue($value);

            $this->orWhereConditions[] = "TIME({$column}) {$operator} {$escapedValue}";
        } catch (\Exception $e) {
            log_message('error', 'Join OR WHERE TIME condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Add raw WHERE condition (use with caution)
     */
    public function whereRaw($condition, $bindings = [])
    {
        try {
            if (empty($condition) || !is_string($condition)) {
                throw new \InvalidArgumentException('Raw condition must be a non-empty string');
            }

            // No keyword denylist here. One used to reject any condition containing
            // CREATE/UPDATE as a substring, which caught `created_at` and
            // `updated_at`, and it was bypassable anyway. Check statement structure
            // instead: a real condition never terminates or opens a comment.
            if (preg_match('/(;|--|\/\*|\*\/|#)/', $condition)) {
                throw new \InvalidArgumentException(
                    'Raw condition may not contain statement terminators (;) or SQL comments (--, /*, #).'
                );
            }

            // Replace bindings if provided
            if (!empty($bindings) && is_array($bindings)) {
                foreach ($bindings as $key => $value) {
                    $escapedValue = $this->escapeValue($value);
                    $condition = str_replace(":{$key}", $escapedValue, $condition);
                }
            }

            $this->whereConditions[] = $condition;
        } catch (\Exception $e) {
            log_message('error', 'Join WHERE RAW condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Start a grouped WHERE condition
     */
    public function whereGroup(callable $callback)
    {
        try {
            $tempConditions = [];
            $originalWhereConditions = $this->whereConditions;
            $this->whereConditions = [];

            $callback($this);

            if (!empty($this->whereConditions)) {
                $groupedCondition = '(' . implode(' AND ', $this->whereConditions) . ')';
                $tempConditions[] = $groupedCondition;
            }

            $this->whereConditions = array_merge($originalWhereConditions, $tempConditions);
        } catch (\Exception $e) {
            log_message('error', 'Join grouped WHERE condition error: ' . $e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Apply the built join condition to the database
     */
    /**
     * Combine one bucket into a fragment: AND-set AND-ed, OR-set OR-ed with it.
     * Returns '' when both are empty.
     */
    private function buildGroup(array $andSet, array $orSet)
    {
        $and = $andSet ? implode(' AND ', $andSet) : '';
        $or = $orSet ? implode(' OR ', $orSet) : '';

        if ($and === '') {
            return $or === '' ? '' : (count($orSet) > 1 ? '(' . $or . ')' : $or);
        }

        if ($or === '') {
            return $and;
        }

        // Parenthesise both sides so precedence is explicit, never inherited.
        $left = count($andSet) > 1 ? '(' . $and . ')' : $and;
        $right = count($orSet) > 1 ? '(' . $or . ')' : $or;

        return '(' . $left . ' OR ' . $right . ')';
    }

    /**
     * Apply the built join condition as (ON-group) AND (WHERE-group).
     *
     * The groups must stay separate: a flat `(ON AND where) OR orWhere` lets the OR
     * branch match rows satisfying no join key, silently making the join a partial
     * cross product.
     */
    public function apply()
    {
        try {
            $onGroup = $this->buildGroup($this->conditions ?: [], $this->orOnConditions ?: []);
            $whereGroup = $this->buildGroup($this->whereConditions ?: [], $this->orWhereConditions ?: []);

            $groups = array_values(array_filter([$onGroup, $whereGroup], function ($g) {
                return $g !== '';
            }));

            if (empty($groups)) {
                throw new \RuntimeException('No join conditions specified');
            }

            $finalCondition = implode(' AND ', $groups);

            $this->db->join($this->table, $finalCondition, $this->type);
        } catch (\Exception $e) {
            log_message('error', 'Join apply error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to apply join conditions: ' . $e->getMessage());
        }
    }

    /**
     * Get debug information about the join
     */
    public function debug()
    {
        return [
            'table' => $this->table,
            'type' => $this->type,
            'on_conditions' => $this->conditions,
            'or_on_conditions' => $this->orOnConditions,
            'where_conditions' => $this->whereConditions,
            'or_where_conditions' => $this->orWhereConditions,
        ];
    }
}
