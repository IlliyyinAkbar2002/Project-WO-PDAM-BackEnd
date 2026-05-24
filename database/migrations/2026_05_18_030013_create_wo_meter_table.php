<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWoMeterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wo_meter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                ->constrained('workorder')
                ->onDelete('cascade');
            $table->string('nomor_meter')->nullable();
            $table->text('kondisi_meter_awal')->nullable();
            $table->text('kondisi_meter_akhir')->nullable();
            $table->text('hasil_kalibrasi')->nullable();
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
        Schema::dropIfExists('wo_meter');
    }
}
