<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk JenisWorkorder.
 *
 * Revisi Mei 2026: afterCreating() yang dulu spawn baris DetailForm (EAV)
 * sudah di-hapus — tabel `detail_form` dan `form_workorder` di-drop. Sekarang
 * factory cukup isi `nama` + `kategori_form` (diputar round-robin supaya
 * seeder selalu menghasilkan kombinasi 3 meter / 4 jaringan / 3 infrastruktur
 * sesuai mapping di .cursor/ERD_Physical.dbml #6).
 */
class JenisWorkorderFactory extends Factory
{
    /**
     * Mapping nama jenis → kategori_form, sesuai .cursor/ERD_Physical.dbml
     * catatan implementasi #6. Di-konsumsi round-robin oleh definition().
     *
     * @var array<int, array{nama: string, kategori_form: string}>
     */
    private static array $starter = [
        ['nama' => 'Kalibrasi Meteran',  'kategori_form' => 'meter'],
        ['nama' => 'Pergantian Meteran',       'kategori_form' => 'meter'],
        ['nama' => 'Pemasangan Meteran',      'kategori_form' => 'meter'],
        ['nama' => 'Penanganan Kebocoran',  'kategori_form' => 'jaringan'],
        ['nama' => 'Perbaikan Pipa',         'kategori_form' => 'jaringan'],
        ['nama' => 'Pembersihan Saluran',    'kategori_form' => 'jaringan'],
        ['nama' => 'Inspeksi Jaringan',      'kategori_form' => 'jaringan'],
        ['nama' => 'Pemeliharaan Pompa',     'kategori_form' => 'infrastruktur'],
        ['nama' => 'Instalasi Baru',         'kategori_form' => 'infrastruktur'],
    ];

    public function definition()
    {
        $row = array_shift(self::$starter) ?? [
            'nama'          => $this->faker->unique()->words(3, true),
            'kategori_form' => $this->faker->randomElement(['meter', 'jaringan', 'infrastruktur']),
        ];

        return $row;
    }
}
