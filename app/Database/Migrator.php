<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Database;
use App\Core\Logger;
use RuntimeException;

/**
 * Migration runner for plain .sql files in database/migrations.
 * Applied migrations are recorded in the `migrations` table so runs are
 * idempotent and safe to repeat during deployment.
 */
final class Migrator
{
    public function __construct(
        private Database $db,
        private string $migrationPath,
        private Logger $logger
    ) {
    }

    public function ensureRepository(): void
    {
        $this->db->run(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT UNSIGNED NOT NULL,
                `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `migrations_migration_unique` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<int,string> */
    public function pending(): array
    {
        $this->ensureRepository();
        $applied = array_column($this->db->select('SELECT migration FROM migrations'), 'migration');
        $all = $this->files();
        return array_values(array_diff($all, $applied));
    }

    /** @return array<int,string> */
    public function applied(): array
    {
        $this->ensureRepository();
        return array_column(
            $this->db->select('SELECT migration FROM migrations ORDER BY batch, migration'),
            'migration'
        );
    }

    /** Every migration file shipped with the application. @return array<int,string> */
    public function files(): array
    {
        $files = glob(rtrim($this->migrationPath, '/') . '/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_STRING);
        return $names;
    }

    /** @return array<int,string> runnable migrations executed in this call */
    public function run(): array
    {
        $this->ensureRepository();
        $pending = $this->pending();

        if ($pending === []) {
            return [];
        }

        $batch = $this->db->int('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations');
        $executed = [];

        foreach ($pending as $migration) {
            $path = rtrim($this->migrationPath, '/') . '/' . $migration;
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException("Unable to read migration {$migration}.");
            }

            $this->executeSql($sql);

            $this->db->insert('migrations', [
                'migration' => $migration,
                'batch'     => $batch,
            ]);

            $executed[] = $migration;
            $this->logger->info('Migration applied', ['migration' => $migration, 'batch' => $batch]);
        }

        return $executed;
    }

    /**
     * MySQL supports multiple statements through PDO::exec; when a server
     * rejects that, fall back to statement-by-statement execution.
     */
    private function executeSql(string $sql): void
    {
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }

        try {
            $this->db->pdo()->exec($sql);
            return;
        } catch (\PDOException $e) {
            $this->logger->warning('Bulk migration exec failed, retrying statement by statement', [
                'reason' => $e->getMessage(),
            ]);
        }

        foreach ($this->splitStatements($sql) as $statement) {
            $this->db->pdo()->exec($statement);
        }
    }

    /** @return array<int,string> */
    private function splitStatements(string $sql): array
    {
        // Strip line comments, then split on semicolons that terminate a line.
        $lines = preg_split('/\R/', $sql) ?: [];
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with(rtrim($line), ';')) {
                $statements[] = trim($buffer);
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return array_filter($statements, static fn (string $s): bool => trim($s, ";\n\t ") !== '');
    }
}
