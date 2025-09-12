<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetailProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detail_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_workorder_id')->constrained('progress_workorder')->onDelete('cascade');
            $table->foreignId('detail_form_id')->constrained('detail_form');
            $table->string('value')->nullable();
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
        Schema::dropIfExists('detail_progress');
    }
}
