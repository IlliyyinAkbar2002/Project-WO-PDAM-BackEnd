<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisWorkorderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $names = [
            "Perbaikan Pipa",
            "Pemasangan Meteran",
            "Inspeksi Jaringan",
            "Penggantian Meteran",
            "Pembersihan Saluran",
            "Pemeliharaan Pompa",
            "Pengaduan Pelanggan",
            "Penanganan Kebocoran",
            "Instalasi Baru",
            "Kalibrasi Meteran",
        ];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
            'kpi_id' => $this->faker->numberBetween(1, 10),
        ];
    }
}
