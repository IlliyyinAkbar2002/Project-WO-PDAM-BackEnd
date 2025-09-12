<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MasterKpiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $names = [
            "Produktivitas Harian",
            "Kualitas Pekerjaan",
            "Tingkat Kehadiran",
            "Efisiensi Waktu",
            "Penyelesaian Tugas",
            "Kepuasan Pelanggan",
            "Kolaborasi Tim",
            "Inovasi dan Kreativitas",
            "Target Penjualan",
            "Kecepatan Respons",
        ];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
        ];
    }
}
