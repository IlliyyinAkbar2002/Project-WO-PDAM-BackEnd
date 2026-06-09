<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah FK forward `lembur_spl.workorder_id` → `workorder.id`.
 *
 * Latar belakang:
 * - Sebelumnya tautan WO ↔ pengajuan lembur HANYA satu arah lewat
 *   `workorder.lembur_spl_id` (kolom ini juga dipakai sebagai penanda
 *   "WO ini lembur"). Arah itu kebalikan dari konvensi tabel detail 1:1
 *   lain di skema ini — `wo_meter`, `wo_jaringan`, `wo_infrastruktur`,
 *   `laporan_workorder` semuanya memegang `workorder_id` unik + cascade di
 *   sisi anak (lihat 2026_04_26_201500_align_schema_with_erd_physical.php).
 * - Migration ini menyelaraskan `lembur_spl` dengan konvensi tsb: kolom
 *   `workorder_id` di sisi anak, unik (1:1), cascade-on-delete.
 *
 * Yang dilakukan:
 *   1. Tambah kolom `lembur_spl.workorder_id` (nullable, FK cascade).
 *   2. Backfill dari `workorder.lembur_spl_id` agar baris lama konsisten
 *      (relasi `LemburSpl::workorder()` kini belongsTo via kolom ini).
 *   3. Promote `workorder_id` ke UNIQUE setelah data terisi.
 *   4. Promote `workorder.lembur_spl_id` (penanda lembur) ke UNIQUE juga —
 *      sebelumnya tidak unik, jadi DB tidak benar-benar menjamin 1:1.
 *
 * Kolom `workorder.lembur_spl_id` TETAP dipertahankan sebagai penanda
 * "WO ini lembur" (`lembur_spl_id != null`). Kedua kolom di-set bersamaan
 * di LemburSplController@store, jadi selalu sinkron.
 *
 * Nullable + multi-NULL: WO non-lembur tetap NULL (Postgres UNIQUE
 * mengizinkan banyak NULL), jadi promote unik tidak merusak data lama.
 */
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
