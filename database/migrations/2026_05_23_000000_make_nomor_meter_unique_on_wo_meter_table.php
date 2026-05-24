<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promote the existing plain index on `wo_meter.nomor_meter` to a UNIQUE
 * constraint. One physical meter must not appear on two work orders.
 *
 * The original wo_meter table was created in
 * 2026_04_26_201500_align_schema_with_erd_physical.php with:
 *   $table->string('nomor_meter', 64);
 *   $table->index('nomor_meter');
 *
 * This migration drops `wo_meter_nomor_meter_index` (if present) and adds
 * `wo_meter_nomor_meter_unique`. Both steps are guarded so the migration
 * is safe to re-run and can be rolled back.
 *
 * PRE-FLIGHT: ensure no duplicates exist before applying:
 *   SELECT nomor_meter, COUNT(*) FROM wo_meter
 *   GROUP BY nomor_meter HAVING COUNT(*) > 1;
 */
class MakeNomorMeterUniqueOnWoMeterTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('wo_meter')) {
            return;
        }

        if ($this->hasIndex('wo_meter', 'wo_meter_nomor_meter_index')) {
            Schema::table('wo_meter', function (Blueprint $table) {
                $table->dropIndex('wo_meter_nomor_meter_index');
            });
        }

        if (! $this->hasIndex('wo_meter', 'wo_meter_nomor_meter_unique')) {
            Schema::table('wo_meter', function (Blueprint $table) {
                $table->unique('nomor_meter');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('wo_meter')) {
            return;
        }

        if ($this->hasIndex('wo_meter', 'wo_meter_nomor_meter_unique')) {
            Schema::table('wo_meter', function (Blueprint $table) {
                $table->dropUnique('wo_meter_nomor_meter_unique');
            });
        }

        if (! $this->hasIndex('wo_meter', 'wo_meter_nomor_meter_index')) {
            Schema::table('wo_meter', function (Blueprint $table) {
                $table->index('nomor_meter');
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
