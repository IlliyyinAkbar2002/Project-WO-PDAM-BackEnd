<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkorderActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workorder_action', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')->constrained('workorder')->onDelete('cascade');
            $table->foreignId('action_id')->constrained('m_action');
            $table->string('keterangan')->nullable();
            $table->datetime('waktu_mulai');
            $table->integer('sisa_durasi_menit')->nullable();
            $table->datetime('estimasi_selesai')->nullable();
            $table->integer('status_workorder')->nullable();
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
        Schema::dropIfExists('workorder_action');
    }
}
