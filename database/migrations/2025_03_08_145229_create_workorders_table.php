<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkordersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workorder', function (Blueprint $table) {
            $table->id();
            $table->string('judul_pekerjaan');
            $table->datetime('waktu_penugasan');
            $table->integer('estimasi_durasi');
            $table->string('unit_waktu');
            $table->datetime('estimasi_selesai');
            $table->decimal('longitude', 9, 6)->nullable();
            $table->decimal('latitude', 8, 6)->nullable();
            $table->foreignId('petugas_id')->constrained('users');
            $table->foreignId('pic_id')->constrained('users');
            $table->foreignId('lembur_spl_id')->nullable()->constrained('lembur_spl');
            $table->foreignId('status_id')->constrained('m_status');
            $table->foreignId('jenis_workorder_id')->constrained('m_jenis_workorder');
            $table->foreignId('jenis_lokasi_id')->constrained('m_jenis_lokasi');
            $table->foreignId('tipe_workorder_id')->constrained('m_tipe_workorder');
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
        Schema::dropIfExists('workorder');
    }
}
