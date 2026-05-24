<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePengaduansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pengaduan', function (Blueprint $table) {
            $table->string('kode_pengaduan')->primary();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->enum('status', [
                'pending',
                'diproses',
                'selesai',
                'ditolak'
            ])->default('pending');
            $table->timestamp('tanggal_pengaduan')->nullable();
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
        Schema::dropIfExists('pengaduan');
    }
}
