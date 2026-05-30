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
        static $jenisworkorders = [
            [
                'nama' => 'Kalibrasi Meteran',
                'kategori' => 'meter',
            ],
            [
                'nama' => 'Penggantian Meteran',
                'kategori' => 'meter',
            ],
            [
                'nama' => 'Pemasangan Meteran',
                'kategori' => 'meter',
            ],

            [
                'nama' => 'Penanganan Kebocoran',
                'kategori' => 'jaringan',
            ],
            [
                'nama' => 'Perbaikan Pipa',
                'kategori' => 'jaringan',
            ],
            [
                'nama' => 'Pembersihan Saluran',
                'kategori' => 'jaringan',
            ],
            [
                'nama' => 'Inspeksi Jaringan',
                'kategori' => 'jaringan',
            ],

            [
                'nama' => 'Pemeliharaan Pompa',
                'kategori' => 'infrastruktur',
            ],
            [
                'nama' => 'Instalasi Baru',
                'kategori' => 'infrastruktur',
            ],
        ];
        $jenisworkorder = array_shift($jenisworkorders);

        return [
            'nama' => $jenisworkorder['nama'],
            'kategori' => $jenisworkorder['kategori'],
            'is_active' => true,
        ];
    }
}
