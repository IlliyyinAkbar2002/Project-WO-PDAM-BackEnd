<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddWorkorderIdToLemburSplTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('lembur_spl')) {
            return;
        }

        // 1. Kolom FK forward di sisi anak (belum unik — diisi dulu).
        if (! Schema::hasColumn('lembur_spl', 'workorder_id')) {
            Schema::table('lembur_spl', function (Blueprint $table) {
                $table->foreignId('workorder_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('workorder')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('lembur_spl', 'latitude')) {
            Schema::table('lembur_spl', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->foreignId('location_id')->nullable()->constrained('m_location');
            });
        }

        // 2. Backfill dari arah lama (workorder.lembur_spl_id) untuk baris lama.
        if (Schema::hasTable('workorder')) {
            DB::statement(
                'UPDATE lembur_spl
                    SET workorder_id = w.id
                   FROM workorder w
                  WHERE w.lembur_spl_id = lembur_spl.id
                    AND lembur_spl.workorder_id IS NULL'
            );
        }

        // 3. Promote workorder_id → UNIQUE (1:1) setelah backfill.
        if (! $this->hasIndex('lembur_spl', 'lembur_spl_workorder_id_unique')) {
            Schema::table('lembur_spl', function (Blueprint $table) {
                $table->unique('workorder_id');
            });
        }

        // 4. Promote penanda lama workorder.lembur_spl_id → UNIQUE juga.
        if (Schema::hasTable('workorder')
            && Schema::hasColumn('workorder', 'lembur_spl_id')
            && ! $this->hasIndex('workorder', 'workorder_lembur_spl_id_unique')) {
            Schema::table('workorder', function (Blueprint $table) {
                $table->unique('lembur_spl_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('workorder')
            && $this->hasIndex('workorder', 'workorder_lembur_spl_id_unique')) {
            Schema::table('workorder', function (Blueprint $table) {
                $table->dropUnique('workorder_lembur_spl_id_unique');
            });
        }

        if (! Schema::hasTable('lembur_spl')) {
            return;
        }

        if ($this->hasIndex('lembur_spl', 'lembur_spl_workorder_id_unique')) {
            Schema::table('lembur_spl', function (Blueprint $table) {
                $table->dropUnique('lembur_spl_workorder_id_unique');
            });
        }

        if (Schema::hasColumn('lembur_spl', 'workorder_id')) {
            Schema::table('lembur_spl', function (Blueprint $table) {
                $table->dropConstrainedForeignId('workorder_id');
            });
        }

        if (Schema::hasColumn('lembur_spl', 'location_id')) {
            Schema::table('lembur_spl', function (Blueprint $table) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        if (Schema::hasColumn('lembur_spl', 'latitude')) {
            Schema::table('lembur_spl', function (Blueprint $table) {
                $table->dropColumn(['latitude', 'longitude']);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
            [$table, $indexName]
        );

        return (bool) $result;
    }
}
