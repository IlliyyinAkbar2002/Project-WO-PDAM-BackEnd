<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buat tabel wo_peminjaman_material — peminjaman material/aset untuk WO.
 *
 * Adaptasi skema target (MergerManual): identitas berbasis m_pegawai
 * (bukan users). diajukan_oleh / diverifikasi_oleh menyimpan pegawai_id,
 * konsisten dengan wo_assignment_member.pegawai_id & workorder_assignment.spv_pegawai_id.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('wo_peminjaman_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                  ->constrained('workorder')
                  ->onDelete('cascade');

            $table->integer('material_kode');
            $table->foreign('material_kode')
                  ->references('kode_material')
                  ->on('m_material')
                  ->onDelete('restrict');

            // Pengajuan
            $table->integer('jumlah_pinjam');
            $table->text('catatan')->nullable();

            $table->foreignId('diajukan_oleh')
                  ->constrained('m_pegawai')
                  ->onDelete('restrict');
            $table->timestamp('diajukan_at');

            // Status: DIPINJAM | PENDING_KEMBALI | DIKEMBALIKAN
            $table->string('status', 32)->default('DIPINJAM');

            // Pengembalian
            $table->integer('jumlah_kembali')->nullable();
            $table->integer('jumlah_rusak')->nullable();
            $table->string('kondisi_kembali', 64)->nullable();
            $table->timestamp('dikembalikan_at')->nullable();

            // Verifikasi pengembalian oleh SPV (two-step approval)
            $table->foreignId('diverifikasi_oleh')->nullable()
                  ->constrained('m_pegawai')
                  ->onDelete('restrict');
            $table->timestamp('diverifikasi_at')->nullable();
            $table->text('catatan_verifikator')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('workorder_id');
            $table->index('material_kode');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wo_peminjaman_material');
    }
};
