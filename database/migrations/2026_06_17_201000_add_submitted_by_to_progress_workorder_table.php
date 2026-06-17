<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BACKEND ONLY] Tambah kolom submitted_by_pegawai_id ke progress_workorder.
 *
 * Mencatat pegawai (anggota tim) yang men-submit tiap progress.
 * Dibutuhkan oleh progress-workorder/by-member & member-summary untuk
 * mengelompokkan progress per anggota. Nullable untuk data historis.
 */
class AddSubmittedByToProgressWorkorderTable extends Migration
{
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            if (! Schema::hasColumn('progress_workorder', 'submitted_by_pegawai_id')) {
                $table->foreignId('submitted_by_pegawai_id')
                    ->nullable()
                    ->constrained('m_pegawai')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_pegawai_id');
        });
    }
}
