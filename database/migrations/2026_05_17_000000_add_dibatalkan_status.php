<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDibatalkanStatus extends Migration
{
    public function up()
    {
        DB::table('m_status')->updateOrInsert(
            ['kode' => 'DIBATALKAN'],
            [
                'id' => 18,
                'nama' => 'Dibatalkan',
                'keterangan' => 'Progress dibatalkan oleh petugas dalam batas waktu',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down()
    {
        DB::table('m_status')->where('kode', 'DIBATALKAN')->delete();
    }
}
