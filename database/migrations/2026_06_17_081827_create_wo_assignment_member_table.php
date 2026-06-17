<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWoAssignmentMemberTable extends Migration
{
    public function up()
    {
        Schema::create('wo_assignment_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')
                  ->constrained('workorder_assignment')
                  ->onDelete('cascade');
            $table->foreignId('pegawai_id')
                  ->constrained('m_pegawai')
                  ->onDelete('restrict');
            $table->boolean('is_pic')->default(false);
            $table->timestamps();

            $table->unique(['assignment_id', 'pegawai_id']);
            $table->index('pegawai_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wo_assignment_member');
    }
}
