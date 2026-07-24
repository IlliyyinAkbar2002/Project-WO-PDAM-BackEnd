<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index komposit (material_kode, status) untuk mempercepat agregat stok.
 *
 * Material::getTerpakaiAttribute() menjumlahkan wo_peminjaman_material yang
 * difilter material_kode + status; dipanggil saat cek stok pada peminjaman
 * dan saat serialisasi daftar (append terpakai/tersedia). Sebelumnya hanya
 * ada index kolom-tunggal.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            $table->index(['material_kode', 'status'], 'wo_pinjam_material_status_idx');
        });
    }

    public function down()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            $table->dropIndex('wo_pinjam_material_status_idx');
        });
    }
};
