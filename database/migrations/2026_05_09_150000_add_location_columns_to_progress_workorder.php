<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BACKEND ONLY] Tambah kolom lokasi GPS ke progress_workorder.
 *
 * ERD Physical: progress_workorder.latitude, longitude, accuracy.
 * Diisi oleh FE Mobile saat staff tekan Mulai/Submit progress.
 * Nullable karena data historis sebelum migration ini tidak punya lokasi.
 */
class AddLocationColumnsToProgressWorkorder extends Migration
{
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            if (! Schema::hasColumn('progress_workorder', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('waktu_submit');
            }
            if (! Schema::hasColumn('progress_workorder', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('progress_workorder', 'accuracy')) {
                $table->float('accuracy')->nullable()->after('longitude');
            }
        });
    }

    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'accuracy']);
        });
    }
}
