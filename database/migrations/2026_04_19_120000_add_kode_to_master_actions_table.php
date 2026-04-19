<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddKodeToMasterActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tambah kolom `kode` (slug stabil) supaya service layer tidak lagi
     * mengidentifikasi master action via id numerik magis (`action_id == 2`),
     * yang akan rusak jika master data pernah re-seed dengan urutan berbeda
     * atau bertambah row baru di masa depan.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('m_action', function (Blueprint $table) {
            $table->string('kode', 32)->nullable()->after('id');
        });

        // Backfill berdasarkan nama yang sudah ter-seed via MasterActionFactory.
        // Nama row existing: Penugasan, Ditunda, Dilanjut, Perpanjangan.
        $map = [
            'Penugasan'    => 'PENUGASAN',
            'Ditunda'      => 'FREEZE',
            'Dilanjut'     => 'RESUME',
            'Perpanjangan' => 'EXTEND',
        ];
        foreach ($map as $nama => $kode) {
            DB::table('m_action')->where('nama', $nama)->update(['kode' => $kode]);
        }

        // Safety net: kalau ada row dengan nama lain (mis. data lama yang tidak
        // ter-cover map di atas), turunkan kode dari nama supaya constraint
        // NOT NULL + UNIQUE di bawah tidak gagal.
        DB::table('m_action')
            ->whereNull('kode')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                $kode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $row->nama)) ?: 'ACTION_' . $row->id;
                DB::table('m_action')->where('id', $row->id)->update(['kode' => $kode]);
            });

        // Set NOT NULL via raw SQL karena project ini belum memuat
        // doctrine/dbal yang dibutuhkan oleh Schema::change(). PostgreSQL
        // syntax: ALTER COLUMN ... SET NOT NULL.
        DB::statement('ALTER TABLE m_action ALTER COLUMN kode SET NOT NULL');

        Schema::table('m_action', function (Blueprint $table) {
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
        Schema::table('m_action', function (Blueprint $table) {
            $table->dropUnique(['kode']);
            $table->dropColumn('kode');
        });
    }
}
