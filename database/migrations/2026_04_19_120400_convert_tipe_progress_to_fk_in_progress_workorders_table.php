<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConvertTipeProgressToFkInProgressWorkordersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * TKT-05: konversi `progress_workorder.tipe_progress` (string bebas —
     * 'Mulai' / 'Selesai' / 'Progress 1' / 'Progress 2' / ...) menjadi FK
     * `tipe_progress_id` → `m_tipe_progress` yang dibuat di TKT-02.
     *
     * Tujuan:
     *  - Hilangkan branching by string yang rapuh terhadap typo / locale
     *    (mis. `=== 'Mulai'` di `ProgressWorkorderService::updateStatusOnSubmit`).
     *  - Validasi ketat di level DB — tidak mungkin lagi menyimpan nilai liar.
     *  - Urutan progres ke-N tetap ditentukan kolom `order` yang sudah ada,
     *    jadi string 'Progress 1', 'Progress 2', ... tidak perlu dipertahankan
     *    (semuanya dipetakan ke kode `PROGRESS`).
     *
     * Strategi 3 langkah (aman, reversible):
     *  1. Tambah kolom `tipe_progress_id` nullable + FK.
     *  2. Backfill dari string lama berdasarkan mapping tetap.
     *  3. Set NOT NULL (via raw SQL karena project belum memuat
     *     doctrine/dbal), lalu drop kolom string lama.
     *
     * @return void
     */
    public function up()
    {
        // Langkah 1 — tambah kolom FK (nullable dulu supaya backfill bisa
        // jalan tanpa melanggar constraint).
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->foreignId('tipe_progress_id')
                ->nullable()
                ->after('workorder_id')
                ->constrained('m_tipe_progress');
        });

        // Langkah 2 — backfill berdasarkan string lama. Id 1/2/3 di-hardcode
        // sesuai seeder `m_tipe_progress` (MULAI=1, PROGRESS=2, SELESAI=3)
        // yang dipaksa eksplisit di TKT-02 → aman untuk direferensikan di sini.
        DB::table('progress_workorder')
            ->where('tipe_progress', 'Mulai')
            ->update(['tipe_progress_id' => 1]);

        DB::table('progress_workorder')
            ->where('tipe_progress', 'Selesai')
            ->update(['tipe_progress_id' => 3]);

        // Semua varian 'Progress N' / string lain yang bukan Mulai/Selesai
        // dipetakan ke PROGRESS. Termasuk juga NULL legacy (kalau ada) — kita
        // anggap PROGRESS sebagai fallback aman, lebih baik daripada gagal
        // constraint NOT NULL di langkah 3.
        DB::table('progress_workorder')
            ->whereNull('tipe_progress_id')
            ->update(['tipe_progress_id' => 2]);

        // Langkah 3a — kunci kolom jadi NOT NULL. Dipakai raw SQL PostgreSQL
        // karena `->change()` butuh doctrine/dbal (lihat pola sama di
        // migration `add_kode_to_master_actions_table`).
        DB::statement('ALTER TABLE progress_workorder ALTER COLUMN tipe_progress_id SET NOT NULL');

        // Langkah 3b — buang kolom string lama. Tidak ada lagi kode yang
        // membaca kolom ini setelah refactor service & controller di TKT-05.
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropColumn('tipe_progress');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Rollback mengembalikan kolom string lama dan mengisi ulang dari FK
     * supaya environment lama tetap bisa jalan seandainya migration ini
     * ter-rollback. Varian 'Progress N' tidak dipulihkan per-row — semua
     * baris PROGRESS di-set jadi string generik 'Progress' karena urutan
     * asli sudah cukup direpresentasikan kolom `order`.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->string('tipe_progress')->nullable()->after('workorder_id');
        });

        DB::table('progress_workorder')->where('tipe_progress_id', 1)->update(['tipe_progress' => 'Mulai']);
        DB::table('progress_workorder')->where('tipe_progress_id', 2)->update(['tipe_progress' => 'Progress']);
        DB::table('progress_workorder')->where('tipe_progress_id', 3)->update(['tipe_progress' => 'Selesai']);

        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipe_progress_id');
        });
    }
}
