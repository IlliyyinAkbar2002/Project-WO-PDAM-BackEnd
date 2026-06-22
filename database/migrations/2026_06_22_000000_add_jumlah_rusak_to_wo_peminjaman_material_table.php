<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom jumlah_rusak pada wo_peminjaman_material.
 *
 * Saat staff lapor pengembalian, sebagian barang bisa kembali rusak.
 * jumlah_rusak = berapa dari jumlah_kembali yang rusak (0 <= jumlah_rusak <= jumlah_kembali).
 * Saat SPV approve: bagian baik -> stok tersedia, bagian rusak -> m_material.rusak.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            $table->integer('jumlah_rusak')->nullable()->after('jumlah_kembali');
        });
    }

    public function down()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            $table->dropColumn('jumlah_rusak');
        });
    }
};
