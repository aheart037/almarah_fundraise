<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * PDO wrapper. Every query is a prepared statement; no string interpolation of
 * values is permitted anywhere in the application.
 */
final class Database
{
    private ?PDO $pdo = null;
    private static ?Database $instance = null;

    public function __construct(private array $config)
    {
        self::$instance = $this;
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            throw new RuntimeException('Database has not been configured.');
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = $this->config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ($this->config['database'] ?? ':memory:');
            $this->pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'] ?? '127.0.0.1',
            (int) ($this->config['port'] ?? 3306),
            $this->config['database'] ?? '',
            $this->config['charset'] ?? 'utf8mb4'
        );

        try {
            $this->pdo = new PDO($dsn, (string) ($this->config['username'] ?? ''), (string) ($this->config['password'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00', sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
            ]);
        } catch (PDOException $e) {
            // Never leak credentials in the surfaced message.
            throw new RuntimeException('Database connection failed. Check DB_* settings.', 0, $e);
        }

        return $this->pdo;
    }

    /** @param array<string,mixed>|array<int,mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($this->normalise($params));
        return $statement;
    }

    /** @return array<int,array<string,mixed>> */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return is_array($row) ? $row : null;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function int(string $sql, array $params = []): int
    {
        return (int) ($this->scalar($sql, $params) ?? 0);
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $this->run($sql, $data);
        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = sprintf('`%s` = :%s', $column, $column);
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return $this->run($sql, array_merge($data, $whereParams))->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->run(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $params)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo()->inTransaction()) {
            return $callback($this);
        }

        $this->pdo()->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo()->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Portable "insert if not exists" helper used for idempotent writes.
     * Returns false when the row already existed.
     */
    public function insertIgnore(string $table, array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $verb = ($this->config['driver'] ?? 'mysql') === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

        $sql = sprintf(
            '%s INTO `%s` (`%s`) VALUES (%s)',
            $verb,
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        return $this->run($sql, $data)->rowCount() > 0;
    }

    /** Named-parameter binding helper (integers bound as ints, everything else as strings). */
    private function normalise(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $out[$key] = $value ? 1 : 0;
            } elseif ($value === null) {
                $out[$key] = null;
            } elseif (is_int($value)) {
                $out[$key] = $value;
            } elseif (is_float($value)) {
                $out[$key] = (string) $value;
            } elseif (is_array($value)) {
                $out[$key] = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
            } elseif (is_object($value) && $value instanceof \DateTimeInterface) {
                $out[$key] = $value->format('Y-m-d H:i:s');
            } else {
                $out[$key] = (string) $value;
            }
        }
        return $out;
    }

    public function driver(): string
    {
        return (string) ($this->config['driver'] ?? 'mysql');
    }
}
