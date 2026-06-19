<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLemburSplsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lembur_spl', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workorder_id')->nullable()->index();
            $table->foreignId('pemohon_id')->nullable()->constrained('users');
            $table->foreignId('verifikator_id')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->datetime('waktu_pengajuan');
            $table->datetime('waktu_verifikasi')->nullable();
            $table->string('alasan_ditolak')->nullable();

            // Detail form "Pengajuan Lembur" (di-input dari mobile Flutter).
            // Sumber: referensi 2026_05_22_000000_add_form_columns_to_lembur_spl_table.php (golden).
            $table->string('jenis_pekerjaan', 255)->nullable();
            $table->string('judul_pekerjaan', 255)->nullable();
            $table->date('tanggal_lembur')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->unsignedSmallInteger('estimasi_jam')->nullable();
            $table->text('alasan_lembur')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lembur_spl');
    }
}
