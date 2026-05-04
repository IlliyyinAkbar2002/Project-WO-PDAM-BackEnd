<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropLegacyFormTables extends Migration
{
    /**
     * Run the migrations.
     *
     * Revisi Mei 2026 — Drop tabel legacy pola EAV form:
     *   - `detail_progress` (child, punya FK ke detail_form + progress_workorder)
     *   - `detail_form`     (parent, punya FK ke m_jenis_workorder)
     *   - `form_workorder`  (sibling detail_form dengan FK ke m_kpi)
     *
     * Alasan drop:
     *  - Pola EAV (schema dinamis via row) ditolak Dospem.
     *  - Diganti Class Table Inheritance: `wo_meter`, `wo_jaringan`,
     *    `wo_infrastruktur` (form statis per kategori, di-INSERT oleh SPV
     *    di Tahap 5).
     *  - Kode yang masih refer (model/controller/service) dibersihkan di
     *    ticket yang sama (Mei 2026).
     *
     * Urutan drop penting karena FK:
     *   detail_progress → detail_form (child → parent)
     *   detail_progress → progress_workorder (child — progress_workorder
     *                     tidak ikut di-drop)
     *   detail_form     → m_jenis_workorder
     *   form_workorder  → m_jenis_workorder + m_kpi
     *
     * Dropping `detail_progress` dulu supaya FK ke `detail_form` lepas.
     * Baru `detail_form`. Terakhir `form_workorder` (tidak ada yang depend
     * ke dia, tapi urutan ini konsisten).
     *
     * File migration asli TIDAK dihapus (standard Laravel):
     *   - 2025_03_08_074135_create_form_workorders_table.php
     *   - 2025_03_08_074202_create_detail_forms_table.php
     *   - 2025_04_14_110521_create_detail_progress_table.php
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('detail_progress');
        Schema::dropIfExists('detail_form');
        Schema::dropIfExists('form_workorder');
    }

    /**
     * Reverse the migrations.
     *
     * Recreate schema persis seperti migration asli. Data tidak dipulihkan —
     * rollback hanya memulihkan struktur tabel (kosong).
     *
     * Urutan create reverse: parent dulu (form_workorder, detail_form),
     * baru child (detail_progress) supaya FK bisa di-bind.
     *
     * Catatan FK `form_workorder.kpi_id`: migration asli refer ke `master_kpi`;
     * per Apr 2026 tabel itu sudah di-rename jadi `m_kpi`
     * (migration 2026_04_26_201500_align_schema_with_erd_physical). Restore
     * pakai nama baru `m_kpi` supaya tetap konsisten dengan state DB saat ini.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('form_workorder')) {
            Schema::create('form_workorder', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jenis_workorder_id')
                    ->constrained('m_jenis_workorder')
                    ->onDelete('cascade');
                $table->foreignId('kpi_id')
                    ->constrained('m_kpi')
                    ->onDelete('cascade');
                $table->string('nama_field');
                $table->string('tipe_field');
                $table->string('tipe_data')->nullable();
                $table->string('sifat');
                $table->integer('min')->nullable();
                $table->integer('max')->nullable();
                $table->integer('parent');
                $table->string('keterangan')->nullable();
                $table->string('hint_text');
                $table->integer('order');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('detail_form')) {
            Schema::create('detail_form', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jenis_workorder_id')
                    ->constrained('m_jenis_workorder')
                    ->onDelete('cascade');
                $table->string('nama_field');
                $table->string('tipe_field');
                $table->string('tipe_data')->nullable();
                $table->string('unit_satuan')->nullable();
                $table->string('sifat');
                $table->integer('min')->nullable();
                $table->integer('max')->nullable();
                $table->integer('parent');
                $table->string('keterangan')->nullable();
                $table->string('hint_text');
                $table->integer('order');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('detail_progress')) {
            Schema::create('detail_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('progress_workorder_id')
                    ->constrained('progress_workorder')
                    ->onDelete('cascade');
                $table->foreignId('detail_form_id')
                    ->constrained('detail_form');
                $table->string('value')->nullable();
                $table->timestamps();
            });
        }
    }
}
