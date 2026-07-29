<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom foto_kerusakan — bukti foto kerusakan material saat pengembalian.
 *
 * Menyimpan array path relatif storage (mis. ["material_rusak/abc.jpg"]) agar
 * halaman approval SPV di mobile bisa merendernya lewat baseStorageUrl + path.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            $table->json('foto_kerusakan')->nullable()->after('kondisi_kembali');
        });
    }

    public function down()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            $table->dropColumn('foto_kerusakan');
        });
    }
};
