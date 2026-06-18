<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJenisToDokumentasiProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Controller ProgressWorkorderController menyimpan tipe foto dokumentasi
     * (INSPEKSI vs HASIL_KERJA) lewat atribut `jenis`, tapi kolomnya belum ada
     * di branch ini -> INSERT gagal "Unknown column 'jenis'" -> submit 500.
     * String nullable (bukan enum) supaya aman terhadap nilai baru.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('dokumentasi_progress')) {
            return;
        }

        Schema::table('dokumentasi_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('dokumentasi_progress', 'jenis')) {
                $table->string('jenis', 32)->nullable()->after('url');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('dokumentasi_progress')) {
            return;
        }

        Schema::table('dokumentasi_progress', function (Blueprint $table) {
            if (Schema::hasColumn('dokumentasi_progress', 'jenis')) {
                $table->dropColumn('jenis');
            }
        });
    }
}
