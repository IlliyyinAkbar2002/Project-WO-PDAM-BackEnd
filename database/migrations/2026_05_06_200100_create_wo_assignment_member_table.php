<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buat tabel wo_assignment_member — anggota tim per WO.
 * Menggantikan tabel pivot workorder_petugas.
 *
 * Relasi: workorder_assignment 1:N wo_assignment_member.
 * PIC ditandai dengan is_pic=true.
 */
class CreateWoAssignmentMemberTable extends Migration
{
    public function up()
    {
        Schema::create('wo_assignment_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')
                  ->constrained('workorder_assignment')
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('restrict');
            $table->boolean('is_pic')->default(false);
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wo_assignment_member');
    }
}
