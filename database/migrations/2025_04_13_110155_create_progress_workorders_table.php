<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProgressWorkordersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('progress_workorder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')->constrained('workorder')->onDelete('cascade');
            $table->enum('tipe_progress', ['pending', 'in_progress', 'completed']);
            $table->string('hasil_pengerjaan')->nullable();
            $table->integer('order');
            $table->dateTime('waktu_submit')->nullable();
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
        Schema::dropIfExists('progress_workorder');
    }
}
