<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillAndDropPetugasIdOnWorkorderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * TKT-07 langkah 2: backfill pivot `workorder_petugas` dari kolom lama
     * `workorder.petugas_id`, lalu drop kolom tersebut. Dipisah dari
     * migration pivot supaya jika backfill gagal di tengah, pivot yang
     * sudah dibuat tidak ikut ter-drop saat rollback.
     *
     * Backfill pakai `INSERT ... SELECT ... ON CONFLICT DO NOTHING`
     * (PostgreSQL-specific, sesuai stack project ini) supaya idempotent —
     * aman dijalankan ulang bila sebagian data sudah ter-copy lewat seeder
     * atau jalur lain.
     *
     * @return void
     */
    public function up()
    {
        // Backfill 1-to-1: setiap WO lama → satu baris pivot.
        // `COALESCE(..., now())` untuk timestamps defensif, meskipun kolom
        // tersebut semestinya selalu terisi pada row yang valid.
        DB::statement(<<<SQL
            INSERT INTO workorder_petugas (workorder_id, user_id, created_at, updated_at)
            SELECT id, petugas_id, COALESCE(created_at, now()), COALESCE(updated_at, now())
            FROM workorder
            WHERE petugas_id IS NOT NULL
            ON CONFLICT (workorder_id, user_id) DO NOTHING
        SQL);

        // Setelah data aman tersalin, drop FK + kolom lama.
        // `dropConstrainedForeignId` melakukan dua hal sekaligus (drop FK
        // constraint + drop column) — matching dengan cara kolom ini dibuat
        // di migration asli via `foreignId()->constrained()`.
        Schema::table('workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('petugas_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Rollback mengembalikan kolom `petugas_id` dan mengisi ulang dari
     * pivot. Kalau satu WO punya banyak petugas di pivot, kita ambil
     * petugas dengan `id` pivot terkecil — asumsi: petugas pertama yang
     * di-attach adalah "primary" untuk representasi legacy kolom tunggal.
     *
     * Ini best-effort — environment yang sudah pakai multi-petugas secara
     * aktif akan kehilangan data tambahan saat di-rollback. Itu trade-off
     * yang wajar untuk migration "aditif + drop" seperti ini.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workorder', function (Blueprint $table) {
            $table->foreignId('petugas_id')
                ->nullable()
                ->after('latitude')
                ->constrained('users');
        });

        DB::statement(<<<SQL
            UPDATE workorder w
            SET petugas_id = wp.user_id
            FROM (
                SELECT DISTINCT ON (workorder_id) workorder_id, user_id
                FROM workorder_petugas
                ORDER BY workorder_id, id ASC
            ) wp
            WHERE w.id = wp.workorder_id
        SQL);
    }
}
