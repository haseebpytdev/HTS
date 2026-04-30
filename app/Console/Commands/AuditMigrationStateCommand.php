<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AuditMigrationStateCommand extends Command
{
    private const REQUIRED_APP_TABLES = [
        'inquiries',
        'seo_pages',
        'application_settings',
    ];

    protected $signature = 'db:audit-migrations {--database= : Connection name (defaults to database.default)} {--schema=database/schema/mysql-schema.sql : Relative path to schema dump file}';

    protected $description = 'Audit migration/schema drift between migration files, migrations table, schema dump, and live DB';

    public function handle(): int
    {
        $connection = (string) ($this->option('database') ?: config('database.default'));
        $schemaPath = base_path((string) $this->option('schema'));

        $this->components->info(sprintf('Running migration drift audit on connection: %s', $connection));

        $diskMigrations = $this->readDiskMigrations();
        $dbMigrations = $this->readDatabaseMigrations($connection);
        $dbTables = $this->readDatabaseTables($connection);

        $schemaDumpTables = [];
        $schemaDumpMigrations = [];
        if (File::exists($schemaPath)) {
            [$schemaDumpTables, $schemaDumpMigrations] = $this->readSchemaDumpState($schemaPath);
        } else {
            $this->components->warn(sprintf('Schema dump file not found: %s', $schemaPath));
        }

        $missingOnDisk = array_values(array_diff($dbMigrations, $diskMigrations));
        $notRecordedInDatabase = array_values(array_diff($diskMigrations, $dbMigrations));
        $missingInDatabaseFromDump = array_values(array_diff($schemaDumpTables, $dbTables));
        $extraInDatabaseVsDump = array_values(array_diff($dbTables, $schemaDumpTables));
        $missingRequiredAppTables = array_values(array_diff(self::REQUIRED_APP_TABLES, $dbTables));

        $this->newLine();
        $this->line('== Migration History Drift ==');
        $this->printList('Recorded in DB but missing on disk', $missingOnDisk);
        $this->printList('Present on disk but not recorded in DB', $notRecordedInDatabase);

        $this->newLine();
        $this->line('== Table Drift (DB vs schema dump) ==');
        if ($schemaDumpTables === []) {
            $this->components->warn('Skipped table drift check: schema dump tables unavailable.');
        } else {
            $this->printList('Missing in DB (present in schema dump)', $missingInDatabaseFromDump);
            $this->printList('Extra in DB (not present in schema dump)', $extraInDatabaseVsDump);
        }

        $this->newLine();
        $this->line('== Schema Dump Migration Drift ==');
        if ($schemaDumpMigrations === []) {
            $this->components->warn('No migration entries parsed from schema dump.');
        } else {
            $missingInDumpMigrationList = array_values(array_diff($dbMigrations, $schemaDumpMigrations));
            $extraInDumpMigrationList = array_values(array_diff($schemaDumpMigrations, $dbMigrations));
            $this->printList('Recorded in DB but missing in schema dump migration inserts', $missingInDumpMigrationList);
            $this->printList('In schema dump migration inserts but not recorded in DB', $extraInDumpMigrationList);
        }

        $this->newLine();
        $this->line('== Required Application Table Checks ==');
        $this->printList('Missing required application tables', $missingRequiredAppTables);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Disk migration files', count($diskMigrations)],
                ['DB migrations table rows', count($dbMigrations)],
                ['DB tables', count($dbTables)],
                ['Schema dump tables', count($schemaDumpTables)],
            ]
        );

        $hasDrift = $missingOnDisk !== []
            || $notRecordedInDatabase !== []
            || $missingInDatabaseFromDump !== []
            || $extraInDatabaseVsDump !== []
            || $missingRequiredAppTables !== [];

        $this->newLine();
        if ($hasDrift) {
            $this->components->warn('Potential drift detected. Review output and create forward-only repair migrations.');
            return self::FAILURE;
        }

        $this->components->info('No drift signals detected for configured inputs.');
        return self::SUCCESS;
    }

    private function readDiskMigrations(): array
    {
        $files = File::allFiles(database_path('migrations'));
        $names = array_map(
            static fn ($file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME),
            $files
        );
        sort($names);

        return array_values(array_unique($names));
    }

    private function readDatabaseMigrations(string $connection): array
    {
        if (! Schema::connection($connection)->hasTable('migrations')) {
            $this->components->warn(sprintf('Connection "%s" has no migrations table.', $connection));
            return [];
        }

        $rows = DB::connection($connection)
            ->table('migrations')
            ->orderBy('id')
            ->pluck('migration')
            ->map(static fn ($value): string => (string) $value)
            ->all();

        sort($rows);

        return array_values(array_unique($rows));
    }

    private function readDatabaseTables(string $connection): array
    {
        $tables = Schema::connection($connection)->getTableListing();
        $tables = array_map(static fn ($name): string => (string) $name, $tables);
        sort($tables);

        return array_values(array_unique($tables));
    }

    private function readSchemaDumpState(string $schemaPath): array
    {
        $sql = File::get($schemaPath);

        preg_match_all('/CREATE TABLE `([^`]+)`/i', $sql, $tableMatches);
        $tables = $tableMatches[1] ?? [];

        preg_match_all("/INSERT INTO `migrations` .* VALUES \\(\\d+,'([^']+)',\\d+\\)/i", $sql, $migrationMatches);
        $migrations = $migrationMatches[1] ?? [];

        $tables = array_values(array_unique($tables));
        $migrations = array_values(array_unique($migrations));
        sort($tables);
        sort($migrations);

        return [$tables, $migrations];
    }

    private function printList(string $title, array $items): void
    {
        $this->line(sprintf('- %s: %d', $title, count($items)));
        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            $this->line(sprintf('  - %s', $item));
        }
    }
}
