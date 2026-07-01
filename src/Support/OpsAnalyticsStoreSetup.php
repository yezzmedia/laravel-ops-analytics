<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OpsAnalyticsStoreSetup
{
    /**
     * @var array<string, bool>
     */
    private array $tableExistsMemo = [];

    private ?bool $migrationsTableExistsMemo = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $appliedMigrationNamesMemo = null;

    public function migrationPath(): string
    {
        return dirname(__DIR__, 2).'/database/migrations';
    }

    /**
     * @return array<int, string>
     */
    public function missingTables(): array
    {
        return array_values(array_filter(
            $this->requiredTables(),
            fn (string $table): bool => ! $this->tableExists($table),
        ));
    }

    public function hasPartialTables(): bool
    {
        $missingTables = $this->missingTables();

        return $missingTables !== [] && count($missingTables) !== count($this->requiredTables());
    }

    public function storeReady(): bool
    {
        return $this->missingTables() === [];
    }

    public function runMigrations(): void
    {
        $paths = $this->pendingMigrationPaths();

        foreach ($paths as $path) {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => $path,
            ]);
        }

        $this->tableExistsMemo = [];
        $this->migrationsTableExistsMemo = null;
        $this->appliedMigrationNamesMemo = null;
    }

    /**
     * @return array<int, string>
     */
    private function publishableMigrationNames(): array
    {
        return [
            'create_ops_analytics_tables',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function pendingMigrationPaths(): array
    {
        $paths = [];
        $publishedPath = database_path('migrations');

        $applied = $this->migrationsTableExists()
            ? $this->appliedMigrationNames(migrationsTableExists: true)
            : [];

        foreach ($this->publishableMigrationNames() as $name) {
            $matches = glob($publishedPath.'/*_'.$name.'.php');

            if (empty($matches)) {
                continue;
            }

            $migrationKey = basename($matches[0], '.php');

            if (! in_array($migrationKey, $applied, true)) {
                $paths[] = str_replace(base_path().'/', '', $matches[0]);
            }
        }

        return $paths;
    }

    private function migrationsTableExists(): bool
    {
        return $this->migrationsTableExistsMemo ??= Schema::hasTable('migrations');
    }

    /**
     * @return array<int, string>
     */
    private function appliedMigrationNames(bool $migrationsTableExists = false): array
    {
        if ($this->appliedMigrationNamesMemo !== null) {
            return $this->appliedMigrationNamesMemo;
        }

        if (! $migrationsTableExists && ! $this->migrationsTableExists()) {
            return $this->appliedMigrationNamesMemo = [];
        }

        return $this->appliedMigrationNamesMemo = DB::table('migrations')
            ->pluck('migration')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    private function requiredTables(): array
    {
        return [
            'ops_analytics_trackers',
            'ops_analytics_dispatches',
            'ops_analytics_dispatch_attempts',
        ];
    }

    private function tableExists(string $table): bool
    {
        return $this->tableExistsMemo[$table] ??= Schema::hasTable($table);
    }
}
