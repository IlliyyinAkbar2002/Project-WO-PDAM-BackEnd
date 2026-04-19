<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubmittedByToProgressWorkordersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tambah `submitted_by_user_id` di `progress_workorder` sebagai audit
     * trail eksplisit: siapa user yang submit progres ini.
     *
     * Konsekuensi langsung dari Q1 (1 WO bisa dikerjakan banyak petugas —
     * lihat TKT-07): tanpa kolom ini, kalau Senior Staff dan Staff sama-sama
     * submit progres untuk WO yang sama, kita tidak bisa membedakan siapa
     * submit yang mana. Sekarang dipersiapkan duluan supaya saat TKT-07
     * (pivot multi-petugas) merge, audit trail per progres sudah ready.
     *
     * Nullable: row hasil `createInitialProgress()` belum punya submitter
     * (masih DRAFT). Field baru terisi saat petugas submit lewat
     * `ProgressWorkorderController::update()`.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->after('status_id')
                ->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
        });
    }
}
