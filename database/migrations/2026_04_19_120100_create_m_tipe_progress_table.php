<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMTipeProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel master untuk klasifikasi tipe progres pekerjaan. Saat ini
     * `progress_workorder.tipe_progress` masih string bebas
     * ('Mulai' / 'Selesai' / 'Progress 1' / ...). Ticket ini hanya membuat
     * master + seed 3 row baku — konversi kolom FK akan dikerjakan di TKT-05.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_tipe_progress', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        // Seed inline supaya tabel langsung berguna untuk TKT-05 nanti
        // (backfill `tipe_progress` lama → `tipe_progress_id` mengandalkan
        // 3 id baku ini). Id dipaksa eksplisit agar tidak ada drift bila
        // kelak ditambahkan row lewat seeder/factory.
        $now = now();
        DB::table('m_tipe_progress')->insert([
            ['id' => 1, 'kode' => 'MULAI',    'nama' => 'Mulai',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'kode' => 'PROGRESS', 'nama' => 'Progress', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'kode' => 'SELESAI',  'nama' => 'Selesai',  'created_at' => $now, 'updated_at' => $now],
        ]);

        // Sinkronkan sequence id ke nilai max supaya insert berikutnya
        // lewat Eloquent tidak collision dengan id yang sudah dipaksa.
        // (PostgreSQL only — sesuai stack project ini.)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('m_tipe_progress', 'id'), (SELECT COALESCE(MAX(id), 0) FROM m_tipe_progress))");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_tipe_progress');
    }
}
