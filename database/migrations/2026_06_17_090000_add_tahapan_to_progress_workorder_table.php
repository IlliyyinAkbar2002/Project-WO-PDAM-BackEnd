<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTahapanToProgressWorkorderTable extends Migration
{


    // public const LABELS = [
    //     self::PERSIAPAN   => 'Persiapan',
    //     self::PENGERJAAN  => 'Pengerjaan',
    //     self::PENGUJIAN   => 'Pengujian',
    //     self::DOKUMENTASI => 'Dokumentasi',
    // ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->unsignedTinyInteger('tahapan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropColumn('tahapan');
        });
    }
}
