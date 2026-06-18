<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Pegawai;
use Illuminate\Database\Seeder;

/**
 * MaterialSeeder — master material berstok untuk diuji di FE Mobile
 * (fitur peminjaman material). Idempotent: aman dijalankan berulang.
 *
 * Catatan: m_material.pegawai_id NOT NULL (FK m_pegawai) → dipakai
 * pegawai pertama yang tersedia sebagai pemilik/penanggung jawab stok.
 */
class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $pegawai = Pegawai::first();
        if (! $pegawai) {
            $this->command->error('MaterialSeeder butuh minimal 1 pegawai (m_pegawai kosong).');
            return;
        }

        $materials = [
            ['kode_material' => 9001, 'nama' => 'Pipa PVC 4 inch',        'jumlah_stok' => 100],
            ['kode_material' => 9002, 'nama' => 'Meter Air 1/2 inch',     'jumlah_stok' => 50],
            ['kode_material' => 9003, 'nama' => 'Coupling Water Meter',   'jumlah_stok' => 200],
            ['kode_material' => 9004, 'nama' => 'Lem PVC 1 kg',           'jumlah_stok' => 30],
            ['kode_material' => 9005, 'nama' => 'Klem Pipa Stainless',    'jumlah_stok' => 150],
        ];

        foreach ($materials as $m) {
            Material::updateOrCreate(
                ['kode_material' => $m['kode_material']],
                [
                    'nama'        => $m['nama'],
                    'jumlah_stok' => $m['jumlah_stok'],
                    'pegawai_id'  => $pegawai->id,
                ]
            );
        }

        $this->command->info('MaterialSeeder selesai: ' . count($materials) . ' material berstok (kode 9001-9005) siap dipinjam dari FE Mobile.');
    }
}
