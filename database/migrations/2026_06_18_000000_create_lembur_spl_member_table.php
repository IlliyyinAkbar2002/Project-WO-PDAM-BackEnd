<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `lembur_spl_member` — anggota tim per pengajuan lembur SPL (1:N).
 *
 * Diisi di LemburSplController::store (min 1 anggota). Saat lembur di-approve
 * Superadmin, anggota direplikasi ke `wo_assignment_member` oleh
 * LemburApprovalService (user_id → pegawai_id via users.pegawai_id).
 *
 * Catatan skema target (MergerManual): anggota disimpan sebagai `user_id`
 * (users.id), konsisten dengan store() & kode notifikasi di update().
 * Jembatan ke identitas m_pegawai dilakukan saat approval.
 *
 * Kontrak:
 * - Unique pair (lembur_spl_id, user_id) — anggota tidak boleh dobel.
 * - Cascade delete kalau lembur_spl dihapus.
 */
class CreateLemburSplMemberTable extends Migration
{
    public function up()
    {
        Schema::create('lembur_spl_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembur_spl_id')
                ->constrained('lembur_spl')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->timestamps();

            $table->unique(['lembur_spl_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lembur_spl_member');
    }
}
