<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [BACKEND ONLY] [BREAKING CHANGE]
 *
 * Drop 3 master tables yang sudah tidak relevan lagi:
 *
 *   1. m_tipe_workorder   → distinction "normal / lembur" di level WO
 *                           sudah ditiadakan. Lembur masih tetap ada
 *                           sebagai flow terpisah via tabel `lembur_spl`
 *                           (kolom `workorder.lembur_spl_id` tetap dipertahankan).
 *   2. m_jenis_pengaduan  → kategorisasi pengaduan tidak dibutuhkan.
 *                           Pengaduan cukup punya deskripsi + status.
 *                           FK `pengaduan.jenis_pengaduan_id` ikut di-drop.
 *   3. m_jenis_lokasi     → pembedaan lokasi "statis / dinamis" dihapus.
 *                           Kolom FK di `workorder` sudah di-drop oleh
 *                           migration 2026_05_10_100000 — tinggal drop
 *                           master-nya di sini.
 *
 * Juga drop kolom `workorder_assignment.tipe_workorder` yang sebelumnya
 * menampung label "Normal / Lembur" sebagai text — sekarang tidak dipakai.
 *
 * Urutan drop:
 *   a. Drop kolom `tipe_workorder` di workorder_assignment.
 *   b. Drop FK + kolom `jenis_pengaduan_id` di pengaduan.
 *   c. Drop tabel master: m_jenis_pengaduan → m_jenis_lokasi → m_tipe_workorder.
 */
class DropLegacyMasterTables extends Migration
{
    public function up()
    {
        // ─── a. workorder_assignment.tipe_workorder ────────────────────
        if (Schema::hasColumn('workorder_assignment', 'tipe_workorder')) {
            Schema::table('workorder_assignment', function (Blueprint $table) {
                $table->dropColumn('tipe_workorder');
            });
        }

        // ─── b. pengaduan.jenis_pengaduan_id ───────────────────────────
        if (Schema::hasTable('pengaduan') && Schema::hasColumn('pengaduan', 'jenis_pengaduan_id')) {
            Schema::table('pengaduan', function (Blueprint $table) {
                // dropForeign idempotent: tanggkap exception kalau FK sudah tidak ada
                try {
                    $table->dropForeign(['jenis_pengaduan_id']);
                } catch (\Throwable $e) {
                    // FK sudah tidak ada, abaikan
                }
                $table->dropColumn('jenis_pengaduan_id');
            });
        }

        // ─── c. Drop tabel master ──────────────────────────────────────
        Schema::dropIfExists('m_jenis_pengaduan');
        Schema::dropIfExists('m_jenis_lokasi');
        Schema::dropIfExists('m_tipe_workorder');
    }

    public function down()
    {
        // ─── Recreate master tables (schema sama persis dengan migration asal) ─
        if (! Schema::hasTable('m_tipe_workorder')) {
            Schema::create('m_tipe_workorder', function (Blueprint $table) {
                $table->id();
                $table->string('nama');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('m_jenis_lokasi')) {
            Schema::create('m_jenis_lokasi', function (Blueprint $table) {
                $table->id();
                $table->string('nama');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('m_jenis_pengaduan')) {
            Schema::create('m_jenis_pengaduan', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 32)->unique();
                $table->string('nama');
                $table->timestamps();
            });
        }

        // ─── Restore kolom di pengaduan ───────────────────────────────
        if (Schema::hasTable('pengaduan') && ! Schema::hasColumn('pengaduan', 'jenis_pengaduan_id')) {
            Schema::table('pengaduan', function (Blueprint $table) {
                $table->foreignId('jenis_pengaduan_id')->nullable()->constrained('m_jenis_pengaduan');
            });
        }

        // ─── Restore kolom di workorder_assignment ────────────────────
        if (Schema::hasTable('workorder_assignment') && ! Schema::hasColumn('workorder_assignment', 'tipe_workorder')) {
            Schema::table('workorder_assignment', function (Blueprint $table) {
                $table->text('tipe_workorder')->nullable()->after('deskripsi');
            });
        }
    }
}
