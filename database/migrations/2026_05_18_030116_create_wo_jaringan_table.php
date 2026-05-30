<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWoJaringanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wo_jaringan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                ->constrained('workorder')
                ->onDelete('cascade');
            $table->string('jenis_pipa')->nullable();
            $table->string('diameter_pipa')->nullable();
            $table->float('panjang_pipa')->nullable();
            $table->string('tingkat_kerusakan')->nullable();
            $table->text('tindakan_perbaikan')->nullable();
            $table->text('hasil_inspeksi')->nullable();
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
        Schema::dropIfExists('wo_jaringan');
    }
}
