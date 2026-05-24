<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKodeToJabatanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tambah kolom `kode` di `m_jabatan` mengikuti pola yang sudah dipakai
     * di `m_status.kode` dan `m_tipe_progress.kode`. Authorization yang
     * sensitif terhadap jabatan (mis. hanya Senior Staff yang boleh submit
     * SELESAI) kini bisa lookup via kode alih-alih nama bebas.
     *
     * Nullable agar migrasi aman di database yang sudah berisi baris
     * `m_jabatan` lama (factory-generated). JabatanSeeder mengisi kode
     * kanonik via updateOrCreate keyed pada `kode`.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('m_jabatan', function (Blueprint $table) {
            $table->string('kode')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('m_jabatan', function (Blueprint $table) {
            $table->dropUnique(['kode']);
            $table->dropColumn('kode');
        });
    }
}
