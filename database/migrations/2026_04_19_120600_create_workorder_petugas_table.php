<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkorderPetugasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * TKT-07 (Q1) — Tabel pivot many-to-many antara `workorder` dan `users`
     * untuk menggantikan FK tunggal `workorder.petugas_id`.
     *
     * Motivasi:
     *  - 1 WO bisa dikerjakan oleh banyak petugas.
     *  - Workaround lama (membuat N row workorder untuk N petugas) merusak
     *    laporan (jumlah WO meledak jadi N kali lipat) dan bikin assignment
     *    tidak bisa di-update sebagai satu kesatuan.
     *
     * Catatan desain:
     *  - `onDelete('cascade')` di `workorder_id` supaya jika WO dihapus,
     *    baris pivot ikut terhapus — tidak meninggalkan orphan.
     *  - Tidak ada cascade di `user_id`: menghapus user sebaiknya gagal
     *    di-FK (perlu proses khusus), bukan diam-diam ikut terhapus.
     *  - `peran` nullable → placeholder untuk fitur peran ganda (koordinator,
     *    anggota, dst.) di masa depan tanpa bikin migration lagi.
     *  - Unique `(workorder_id, user_id)` — satu user tidak bisa di-assign
     *    dua kali ke WO yang sama.
     *
     * Migration ini **hanya** membuat pivot + (nanti di migration B) mengisi
     * datanya. Kolom `workorder.petugas_id` **belum** di-drop di sini supaya
     * jika ada bug di migration B, kita bisa rollback pivot tanpa kehilangan
     * data lama.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workorder_petugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')
                ->constrained('workorder')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users');
            $table->string('peran')->nullable();
            $table->timestamps();

            $table->unique(['workorder_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workorder_petugas');
    }
}
