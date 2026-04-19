<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MasterActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $rows = [
            ['kode' => 'PENUGASAN', 'nama' => 'Penugasan',    'keterangan' => 'Penugasan kepada pegawai'],
            ['kode' => 'FREEZE',    'nama' => 'Ditunda',      'keterangan' => 'Pekerjaan ditunda sementara'],
            ['kode' => 'RESUME',    'nama' => 'Dilanjut',     'keterangan' => 'Pekerjaan dilanjutkan'],
            ['kode' => 'EXTEND',    'nama' => 'Perpanjangan', 'keterangan' => 'Perpanjangan waktu tugas'],
        ];

        $row = array_shift($rows);

        return [
            'kode'       => $row['kode'],
            'nama'       => $row['nama'],
            'keterangan' => $row['keterangan'],
        ];
    }
}
