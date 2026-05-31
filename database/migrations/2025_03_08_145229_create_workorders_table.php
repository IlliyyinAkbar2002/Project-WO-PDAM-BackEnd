<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkordersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workorder', function (Blueprint $table) {
            $table->id();
            $table->string('nama_workorder');
            $table->text('deskripsi')->nullable();
            $table->string('lokasi');
            $table->enum('prioritas', [
                'Rendah',
                'Sedang',
                'Tinggi',
                'Urgent'
            ]);
            $table->enum('status', [
                'Pending',
                'Proses',
                'Selesai',
                'Ditolak'
            ])->default('Pending');
            $table->string('kode_pengaduan');

            $table->foreignId('departemen_id')
                ->constrained('m_departemen')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('jenis_workorder_id')
                ->constrained('m_jenis_workorder')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Ditujukan kepada SPV/PIC
            $table->foreignId('assigned_to')
                ->constrained('m_pegawai')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // User pembuat workorder
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            // $table->foreignId('petugas_id')->constrained('users');
            // $table->foreignId('pic_id')->constrained('users');
            // $table->foreignId('jenis_workorder_id')->constrained('m_jenis_workorder');
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
        Schema::dropIfExists('workorder');
    }
}
