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
        static $names = ['Penugasan', 'Ditunda', 'Dilanjut', 'Perpanjangan'];
        static $desc = ['Penugasan kepada pegawai', 'Pekerjaan ditunda sementara', 'Pekerjaan dilanjutkan', 'Perpanjangan waktu tugas'];
        $nama = array_shift($names);
        $keterangan = array_shift($desc);

        return [
            'nama' => $nama,
            'keterangan' => $keterangan,
        ];
    }
}
