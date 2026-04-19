<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusIdToProgressWorkordersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tambah `status_id` di `progress_workorder` sehingga setiap progres bisa
     * dibedakan eksplisit: DRAFT (di-spawn saat assign, belum disubmit) →
     * SUBMITTED (Staff sudah submit) → VERIFIED (SPV sudah approve, opsional).
     *
     * Sebelumnya status implisit lewat `waktu_submit IS NULL` — kurang
     * ekspresif & bertabrakan dengan relasi `Status::progressWorkorder()`
     * yang sudah declare `hasMany(.., 'status_id')` padahal kolomnya belum
     * ada (bom waktu silent error).
     *
     * Nullable supaya backfill data lama tidak gagal & kompatibel dengan row
     * yang sudah ada sebelum migration ini jalan.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->foreignId('status_id')
                ->nullable()
                ->after('hasil_pengerjaan')
                ->constrained('m_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_id');
        });
    }
}
