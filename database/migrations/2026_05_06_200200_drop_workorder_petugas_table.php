<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill data dari workorder_petugas ke wo_assignment_member,
 * lalu DROP tabel workorder_petugas.
 *
 * Prasyarat: migration create_wo_assignment_member_table sudah jalan.
 *
 * Catatan: backfill hanya bisa jalan kalau workorder_assignment sudah
 * ada row-nya. Kalau belum (WO lama), data pivot lama akan hilang.
 * Pastikan seed/backfill workorder_assignment sebelum migration ini.
 */
class DropWorkorderPetugasTable extends Migration
{
    public function up()
    {
        // Backfill: pindahkan data dari workorder_petugas ke wo_assignment_member
        // hanya untuk WO yang sudah punya workorder_assignment.
        if (Schema::hasTable('workorder_petugas') && Schema::hasTable('wo_assignment_member')) {
            DB::statement("
                INSERT INTO wo_assignment_member (assignment_id, user_id, is_pic, created_at, updated_at)
                SELECT
                    wa.id,
                    wp.user_id,
                    CASE WHEN wp.peran = 'koordinator' THEN true ELSE false END,
                    COALESCE(wp.created_at, NOW()),
                    COALESCE(wp.updated_at, NOW())
                FROM workorder_petugas wp
                INNER JOIN workorder_assignment wa ON wa.workorder_id = wp.workorder_id
                ON CONFLICT (assignment_id, user_id) DO NOTHING
            ");
        }

        Schema::dropIfExists('workorder_petugas');
    }

    public function down()
    {
        // Recreate workorder_petugas (schema dari migration asli)
        Schema::create('workorder_petugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                  ->constrained('workorder')
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('restrict');
            $table->string('peran')->nullable();
            $table->timestamps();

            $table->unique(['workorder_id', 'user_id']);
            $table->index('user_id');
        });
    }
}
