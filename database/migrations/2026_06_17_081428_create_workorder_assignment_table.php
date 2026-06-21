<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkorderAssignmentTable extends Migration
{
    public function up()
    {
        Schema::create('workorder_assignment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                ->unique()
                ->constrained('workorder')
                ->onDelete('cascade');
            $table->foreignId('spv_pegawai_id')->constrained('m_pegawai');
            $table->timestamp('assigned_at');
            $table->text('deskripsi')->nullable();
            $table->timestamp('tanggal_mulai')->nullable();
            $table->timestamp('tanggal_selesai')->nullable();
            $table->timestamp('estimasi_selesai')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('m_location');
            $table->timestamps();

            $table->index('spv_pegawai_id');
            $table->index('assigned_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('workorder_assignment');
    }
}
