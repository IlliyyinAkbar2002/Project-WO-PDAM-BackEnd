<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom audit verifikasi pengembalian material oleh SPV.
 * Mendukung two-step approval flow: Staff submit kembali (PENDING_KEMBALI)
 * → SPV approve/reject (DIKEMBALIKAN atau balik ke DIPINJAM).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            if (! Schema::hasColumn('wo_peminjaman_material', 'diverifikasi_oleh')) {
                $table->foreignId('diverifikasi_oleh')
                      ->nullable()
                      ->after('kondisi_kembali')
                      ->constrained('users')
                      ->onDelete('restrict');
            }
            if (! Schema::hasColumn('wo_peminjaman_material', 'diverifikasi_at')) {
                $table->timestamp('diverifikasi_at')->nullable()->after('diverifikasi_oleh');
            }
            if (! Schema::hasColumn('wo_peminjaman_material', 'catatan_verifikator')) {
                $table->text('catatan_verifikator')->nullable()->after('diverifikasi_at');
            }
        });
    }

    public function down()
    {
        Schema::table('wo_peminjaman_material', function (Blueprint $table) {
            if (Schema::hasColumn('wo_peminjaman_material', 'diverifikasi_oleh')) {
                $table->dropForeign(['diverifikasi_oleh']);
                $table->dropColumn('diverifikasi_oleh');
            }
            if (Schema::hasColumn('wo_peminjaman_material', 'diverifikasi_at')) {
                $table->dropColumn('diverifikasi_at');
            }
            if (Schema::hasColumn('wo_peminjaman_material', 'catatan_verifikator')) {
                $table->dropColumn('catatan_verifikator');
            }
        });
    }
};
