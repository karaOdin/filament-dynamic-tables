<?php

namespace KaraOdin\FilamentDynamicTables\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use KaraOdin\FilamentDynamicTables\Support\Models;

/**
 * Adds the tenant column to the package tables when they were migrated
 * before tenancy was set up (or with another tenant column name).
 */
class AddTenancyCommand extends Command
{
    protected $signature = 'dynamic-tables:add-tenancy {--force : Run the migration without asking}';

    protected $description = 'Add the tenant column to the Filament Dynamic Tables tables and migrate';

    public function handle(): int
    {
        $column = Models::tenantColumn();
        $tables = array_values(array_filter((array) config('filament-dynamic-tables.tables', [])));

        $missing = array_values(array_filter(
            $tables,
            fn (string $table): bool => Schema::hasTable($table) && (! Schema::hasColumn($table, $column)),
        ));

        if (blank($missing)) {
            $this->components->info("Every table already has a [{$column}] column. Tenancy is ready.");

            return self::SUCCESS;
        }

        $name = date('Y_m_d_His') . '_add_' . $column . '_to_filament_dynamic_tables_tables.php';
        $path = database_path("migrations/{$name}");
        $tableList = implode("', '", $missing);

        file_put_contents($path, <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                foreach (['{$tableList}'] as \$table) {
                    Schema::table(\$table, function (Blueprint \$table): void {
                        \$table->string('{$column}')->nullable()->index();
                    });
                }
            }

            public function down(): void
            {
                foreach (['{$tableList}'] as \$table) {
                    Schema::table(\$table, function (Blueprint \$table): void {
                        \$table->dropColumn('{$column}');
                    });
                }
            }
        };

        PHP);

        $this->components->info("Created [database/migrations/{$name}].");

        if ($this->option('force') || $this->confirm('Run the migration now?', true)) {
            $this->call('migrate', ['--path' => "database/migrations/{$name}", '--force' => true]);
        }

        return self::SUCCESS;
    }
}
