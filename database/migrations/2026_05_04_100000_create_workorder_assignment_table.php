<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkorderAssignmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Revisi Mei 2026 — Tabel 1:1 ke `workorder` untuk mencatat event
     * "SPV melakukan assign" secara eksplisit. Menjawab pertanyaan
     * "kapan + siapa + catatan apa" tanpa perlu JOIN ke `workorder_action`.
     *
     * Diisi di Tahap 5 (SPV klik Tugaskan) bersamaan dengan insert ke
     * `wo_{kategori}` + `workorder_petugas` dalam satu DB transaction.
     *
     * Design:
     *  - `workorder_id` UNIQUE + cascadeOnDelete: 1 WO = 1 row assignment.
     *    Kalau SPV ganti petugas, tetap 1 row (update `catatan_spv` /
     *    biarkan `assigned_at` sebagai waktu assign pertama). Histori
     *    perubahan petugas di-log di `workorder_action` dengan kode PENUGASAN.
     *  - `spv_user_id` FK ke users, tanpa cascade — hapus user harus gagal
     *    (bukan diam-diam ikut hapus).
     *  - `assigned_at` NOT NULL — aplikasi selalu mengisi now() saat insert.
     *  - `catatan_spv` nullable text — instruksi opsional untuk tim.
     *
     * Tabel ini sengaja denormalisasi ringan dari workorder_action(PENUGASAN
     * oleh SPV): trade-off supaya dashboard "list WO SPV" tidak perlu JOIN
     * audit trail untuk dapat info "kapan assign".
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workorder_assignment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                ->unique()
                ->constrained('workorder')
                ->onDelete('cascade');
            $table->foreignId('spv_user_id')
                ->constrained('users');
            $table->timestamp('assigned_at');
            $table->text('catatan_spv')->nullable();
            $table->timestamps();

            $table->index('spv_user_id');
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workorder_assignment');
    }
}
