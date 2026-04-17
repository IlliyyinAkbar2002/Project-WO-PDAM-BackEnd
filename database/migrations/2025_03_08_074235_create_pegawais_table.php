<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePegawaisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // nip, alamat, departemen_id, jabatan_id dibiarkan nullable karena
            // diisi oleh Super Admin setelah karyawan melakukan self-register
            // via aplikasi mobile (endpoint POST /v1/auth/register).
            $table->string('nip')->nullable();
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan']);
            $table->string('alamat')->nullable();
            $table->string('telepon');
            $table->foreignId('departemen_id')->nullable()->constrained('m_departemen');
            $table->foreignId('jabatan_id')->nullable()->constrained('m_jabatan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_pegawai');
    }
}
