<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddKodeToStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Bagian dari TKT-03 (sub-ticket TKT-03b dari plan): tambah identifier
     * stabil `kode` di `m_status` supaya status progres baru
     * (DRAFT / SUBMITTED / VERIFIED) bisa di-lookup tanpa bergantung pada
     * id numerik. Tidak menambah row baru di sini — itu tanggung jawab
     * StatusSeeder agar idempotent.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('m_status', function (Blueprint $table) {
            $table->string('kode', 32)->nullable()->after('id');
        });

        // Backfill berdasarkan nama yang sudah ter-seed via StatusFactory.
        $map = [
            'Belum disetujui' => 'BELUM_DISETUJUI',
            'Disetujui'       => 'DISETUJUI',
            'Revisi'          => 'REVISI',
            'Ditolak'         => 'DITOLAK',
            'Pengecekan'      => 'PENGECEKAN',
            'Selesai'         => 'SELESAI',
            'In Progress'     => 'IN_PROGRESS',
            'Freeze'          => 'FREEZE',
        ];
        foreach ($map as $nama => $kode) {
            DB::table('m_status')->where('nama', $nama)->update(['kode' => $kode]);
        }

        // Safety net untuk row yang tidak ter-cover map (mis. data manual):
        // turunkan kode dari nama supaya constraint NOT NULL + UNIQUE aman.
        DB::table('m_status')
            ->whereNull('kode')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                $kode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $row->nama)) ?: 'STATUS_' . $row->id;
                DB::table('m_status')->where('id', $row->id)->update(['kode' => $kode]);
            });

        // NOT NULL via raw SQL — project belum memuat doctrine/dbal yang
        // dibutuhkan Schema::change(). PostgreSQL syntax.
        DB::statement('ALTER TABLE m_status ALTER COLUMN kode SET NOT NULL');

        Schema::table('m_status', function (Blueprint $table) {
            $table->unique('kode');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('m_status', function (Blueprint $table) {
            $table->dropUnique(['kode']);
            $table->dropColumn('kode');
        });
    }
}
