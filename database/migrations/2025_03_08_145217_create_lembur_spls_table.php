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
            $table->foreignId('verifikator_id')->nullable()->constrained('users');
            $table->foreignId('status_id')->constrained('m_status');
            $table->datetime('waktu_pengajuan');
            $table->datetime('waktu_verifikasi')->nullable();
            $table->string('alasan_ditolak')->nullable();
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
