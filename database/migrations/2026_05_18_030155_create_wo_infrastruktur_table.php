<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWoInfrastrukturTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wo_infrastruktur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                ->constrained('workorder')
                ->onDelete('cascade');
            $table->string('nama_aset')->nullable();
            $table->string('jenis_aset')->nullable();
            $table->string('kapasitas')->nullable();
            $table->text('kondisi_awal')->nullable();
            $table->text('kondisi_akhir')->nullable();
            $table->date('jadwal_pemeliharaan')->nullable();
            $table->text('tindakan')->nullable();
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
        Schema::dropIfExists('wo_infrastruktur');
    }
}
