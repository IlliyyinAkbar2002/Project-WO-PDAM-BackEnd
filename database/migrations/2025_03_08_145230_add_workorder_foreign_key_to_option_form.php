<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkorderForeignKeyToOptionForm extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('option_form', function (Blueprint $table) {
            $table->foreign('workorder_id')
                  ->references('id')
                  ->on('workorder')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('option_form', function (Blueprint $table) {
            $table->dropForeign(['workorder_id']);
        });
    }
}

