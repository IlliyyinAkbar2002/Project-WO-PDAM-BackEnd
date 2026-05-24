<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom form pengajuan lembur ke tabel `lembur_spl`.
 *
 * Latar belakang:
 * - Tabel `lembur_spl` awalnya hanya menyimpan metadata verifikasi
 *   (verifikator_id, status_id, waktu_pengajuan, waktu_verifikasi,
 *   alasan_ditolak). Form "Pengajuan Lembur" di mobile (Flutter) butuh
 *   menyimpan detail pengajuan: judul pekerjaan, jenis pekerjaan (free text,
 *   contoh "Emergency Maintainance"), tanggal lembur, jam mulai, estimasi
 *   durasi (jam), alasan, dan pemohon.
 * - Anggota tim disimpan di tabel terpisah `lembur_spl_member` (1:N).
 *
 * Semua kolom NULLABLE agar:
 * - Data lembur_spl yang sudah ada (kalau ada) tidak rusak.
 * - Endpoint update (approval) di web FE tetap kompatibel — tidak ada
 *   field baru yang wajib diisi saat approve.
 *
 * Lihat juga: migration 2026_05_22_000100_create_lembur_spl_member_table.php
 */
class AddFormColumnsToLemburSplTable extends Migration
{
    public function up()
    {
        Schema::table('lembur_spl', function (Blueprint $table) {
            if (! Schema::hasColumn('lembur_spl', 'pemohon_id')) {
                $table->foreignId('pemohon_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users');
            }

            if (! Schema::hasColumn('lembur_spl', 'jenis_pekerjaan')) {
                $table->string('jenis_pekerjaan', 255)
                    ->nullable()
                    ->after('pemohon_id');
            }

            if (! Schema::hasColumn('lembur_spl', 'judul_pekerjaan')) {
                $table->string('judul_pekerjaan', 255)
                    ->nullable()
                    ->after('jenis_pekerjaan');
            }

            if (! Schema::hasColumn('lembur_spl', 'tanggal_lembur')) {
                $table->date('tanggal_lembur')
                    ->nullable()
                    ->after('judul_pekerjaan');
            }

            if (! Schema::hasColumn('lembur_spl', 'jam_mulai')) {
                $table->time('jam_mulai')
                    ->nullable()
                    ->after('tanggal_lembur');
            }

            if (! Schema::hasColumn('lembur_spl', 'estimasi_jam')) {
                $table->unsignedSmallInteger('estimasi_jam')
                    ->nullable()
                    ->after('jam_mulai');
            }

            if (! Schema::hasColumn('lembur_spl', 'alasan_lembur')) {
                $table->text('alasan_lembur')
                    ->nullable()
                    ->after('estimasi_jam');
            }
        });
    }

    public function down()
    {
        Schema::table('lembur_spl', function (Blueprint $table) {
            if (Schema::hasColumn('lembur_spl', 'pemohon_id')) {
                $table->dropConstrainedForeignId('pemohon_id');
            }

            $cols = ['alasan_lembur', 'estimasi_jam', 'jam_mulai', 'tanggal_lembur', 'judul_pekerjaan', 'jenis_pekerjaan'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('lembur_spl', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
