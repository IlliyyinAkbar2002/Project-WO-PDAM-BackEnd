<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_material', function (Blueprint $table) {
            $table->string('kode_material')->primary();
            $table->string('nama');
            $table->integer('jumlah_stok');
            $table->integer('rusak')->default(0);
            $table->foreignId('pegawai_id')->constrained('m_pegawai');
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
        Schema::dropIfExists('materials');
    }
}
