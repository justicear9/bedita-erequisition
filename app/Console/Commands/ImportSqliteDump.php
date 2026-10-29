<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportSqliteDump extends Command
{
    protected $signature = 'db:import-sqlite
                            {path=database/imports/live-database.sqlite : Path to the SQLite dump}
                            {--fresh : Truncate target tables before import}
                            {--force : Run without confirmation}';

    protected $description = 'Import users, departments, products, and requisitions from a SQLite dump into the current database';

    /**
     * @var list<string>
     */
    private array $tables = [
        'departments',
        'users',
        'products',
        'requisitions',
        'requisition_items',
        'requisition_events',
        'notifications',
        'approval_delegations',
        'push_subscriptions',
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("SQLite file not found: {$path}");

            return self::FAILURE;
        }

        config([
            'database.connections.sqlite_import' => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        $source = DB::connection('sqlite_import');
        $target = DB::connection();

        $this->info('Source: '.$path);
        $this->info('Target: '.$target->getDriverName().' / '.$target->getDatabaseName());

        if (! $this->option('force') && ! $this->confirm('This will overwrite matching rows in the target database. Continue?', true)) {
            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();

        try {
            if ($this->option('fresh')) {
                foreach (array_reverse($this->tables) as $table) {
                    if (Schema::hasTable($table)) {
                        $target->table($table)->truncate();
                        $this->line("Truncated {$table}");
                    }
                }
            }

            // Clear manager FK before departments/users so re-import is clean.
            if (Schema::hasTable('departments')) {
                $target->table('departments')->update(['manager_id' => null]);
            }

            foreach ($this->tables as $table) {
                if (! $this->sqliteHasTable($source, $table) || ! Schema::hasTable($table)) {
                    $this->warn("Skipping {$table} (missing on source or target)");

                    continue;
                }

                $rows = $source->table($table)->orderBy('id')->get();
                $count = 0;

                foreach ($rows as $row) {
                    $data = (array) $row;

                    // departments.manager_id may reference users not yet inserted — null first pass
                    if ($table === 'departments') {
                        $data['manager_id'] = null;
                    }

                    $target->table($table)->updateOrInsert(
                        ['id' => $data['id']],
                        $data,
                    );
                    $count++;
                }

                $this->info("Imported {$count} row(s) into {$table}");
            }

            // Second pass: restore department manager_id from SQLite
            if ($this->sqliteHasTable($source, 'departments') && Schema::hasTable('departments')) {
                foreach ($source->table('departments')->get() as $dept) {
                    $target->table('departments')
                        ->where('id', $dept->id)
                        ->update(['manager_id' => $dept->manager_id]);
                }
                $this->info('Restored department manager assignments');
            }

            // Reset auto-increments for MySQL
            if ($target->getDriverName() === 'mysql') {
                foreach ($this->tables as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }
                    $max = (int) $target->table($table)->max('id');
                    if ($max > 0) {
                        $target->statement("ALTER TABLE {$table} AUTO_INCREMENT = ".($max + 1));
                    }
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->newLine();
        $this->info('Import complete.');
        $this->warn('Also copy storage/app/public/signatures from the old server if signature images are missing.');

        return self::SUCCESS;
    }

    private function sqliteHasTable($connection, string $table): bool
    {
        $row = $connection->selectOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
            [$table],
        );

        return (bool) $row;
    }
}
