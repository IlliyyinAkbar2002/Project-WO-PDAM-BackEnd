<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddMissingMasterActionCodes extends Migration
{
    public function up()
    {
        $actions = [
            ['kode' => 'MULAI_KERJA',     'nama' => 'Mulai Kerja',     'keterangan' => 'Petugas memulai pekerjaan'],
            ['kode' => 'SUBMIT_PROGRESS', 'nama' => 'Submit Progress', 'keterangan' => 'Petugas melaporkan progres'],
            ['kode' => 'SELESAI_KERJA',   'nama' => 'Selesai Kerja',   'keterangan' => 'Petugas menandai pekerjaan selesai'],
            ['kode' => 'APPROVE',         'nama' => 'Persetujuan',     'keterangan' => 'SPV menyetujui hasil pekerjaan'],
            ['kode' => 'REJECT',          'nama' => 'Penolakan',       'keterangan' => 'SPV menolak hasil pekerjaan'],
        ];

        foreach ($actions as $action) {
            DB::table('m_action')->updateOrInsert(
                ['kode' => $action['kode']],
                array_merge($action, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down()
    {
        DB::table('m_action')->whereIn('kode', [
            'MULAI_KERJA', 'SUBMIT_PROGRESS', 'SELESAI_KERJA', 'APPROVE', 'REJECT',
        ])->delete();
    }
}
