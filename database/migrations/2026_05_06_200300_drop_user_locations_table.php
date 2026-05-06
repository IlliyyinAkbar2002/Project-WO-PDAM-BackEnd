<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DROP tabel user_locations.
 *
 * Lokasi tracking sekarang langsung di progress_workorder.latitude/longitude/accuracy.
 * Validasi radius dilakukan saat staff tekan Mulai/Selesai via LocationGuard service.
 */
class DropUserLocationsTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('user_locations');
    }

    public function down()
    {
        // Recreate dari migration 2025_09_19_143131
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }
}
