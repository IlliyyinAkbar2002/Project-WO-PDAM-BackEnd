<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel laporan akhir Work Order.
 *
 * Dibuat otomatis saat SPV approve review final (ProgressWorkorderController::review).
 * Snapshot disimpan sebagai jsonb (Postgres) — selaras cast 'array' di model LaporanWorkorder.
 */
class CreateLaporanWorkorderTable extends Migration
{
    public function up()
    {
        Schema::create('laporan_workorder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')->unique()->constrained('workorder')->cascadeOnDelete();
            $table->string('nomor_laporan', 64)->unique();
            $table->timestamp('tanggal_terbit');
            $table->text('ringkasan_pekerjaan');
            $table->jsonb('hasil_akhir_snapshot');
            $table->jsonb('petugas_snapshot');
            $table->text('catatan_spv')->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('laporan_workorder');
    }
}
