<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['users', 'audit_logs'];

    public function up(): void
    {
        $default = (int) DB::table('tenants')->where('slug', 'scnmtc')->value('id');

        foreach ($this->tables as $name) {
            // The temporary default gives existing rows to the default tenant.
            Schema::table($name, function (Blueprint $table) use ($default) {
                $table->foreignId('tenant_id')->default($default)->constrained('tenants');
            });

            // Then remove it: from now on the application must always say which
            // tenant a row belongs to; the database never guesses.
            DB::statement("ALTER TABLE `{$name}` ALTER COLUMN `tenant_id` DROP DEFAULT");
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
