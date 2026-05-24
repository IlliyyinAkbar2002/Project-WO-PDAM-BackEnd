<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `lembur_spl_member` — anggota tim untuk pengajuan lembur SPL.
 *
 * Mirror dari `wo_assignment_member` (anggota WO setelah approve), tapi
 * tabel ini dipakai SAAT pengajuan masih berstatus BELUM_DISETUJUI /
 * DISETUJUI. Setelah lembur SPL di-approve dan WO dibuat, anggota
 * tetap bisa direplikasi ke `wo_assignment_member` lewat AssignmentService.
 *
 * Kontrak:
 * - 1 lembur_spl punya N anggota (minimal 1, divalidasi di FormRequest).
 * - Unique pair (lembur_spl_id, user_id) — anggota tidak boleh dobel.
 * - Cascade delete kalau lembur_spl di-hapus.
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
