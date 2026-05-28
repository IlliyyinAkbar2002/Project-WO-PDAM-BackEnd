<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed master material PDAM Surabaya.
 *
 * Sumber: jenis_barang_material_yang_dipakai_oleh_pdam.md.
 * Idempotent: pakai updateOrCreate berbasis kode_material yang stabil.
 *
 * Range kode_material:
 *   1000-1099 — Perpipaan (HDPE / PVC / Galvanis)
 *   1100-1199 — Fitting & Aksesoris
 *   1200-1299 — Sambungan Rumah (SR) & Meter Air
 *   1300-1399 — Bahan Kimia Pengolahan Air
 */
class MaterialSeeder extends Seeder
{
    public function run()
    {
        $rows = [
            // -------- Perpipaan (1000-1099) --------
            ['kode_material' => 1001, 'nama' => 'Pipa HDPE DN-100 PN-10',  'jumlah_stok' => 500, 'satuan' => 'meter', 'kategori' => 'Perpipaan'],
            ['kode_material' => 1002, 'nama' => 'Pipa HDPE DN-200 PN-10',  'jumlah_stok' => 300, 'satuan' => 'meter', 'kategori' => 'Perpipaan'],
            ['kode_material' => 1003, 'nama' => 'Pipa HDPE DN-600 PN-16',  'jumlah_stok' => 100, 'satuan' => 'meter', 'kategori' => 'Perpipaan'],
            ['kode_material' => 1010, 'nama' => 'Pipa PVC-O 4"',           'jumlah_stok' => 400, 'satuan' => 'meter', 'kategori' => 'Perpipaan'],
            ['kode_material' => 1011, 'nama' => 'Pipa PVC SNI 6"',         'jumlah_stok' => 300, 'satuan' => 'meter', 'kategori' => 'Perpipaan'],
            ['kode_material' => 1020, 'nama' => 'Pipa Galvanis 3/4"',      'jumlah_stok' => 600, 'satuan' => 'meter', 'kategori' => 'Perpipaan'],

            // -------- Fitting & Aksesoris (1100-1199) --------
            ['kode_material' => 1101, 'nama' => 'Sadle Clamp',             'jumlah_stok' => 200, 'satuan' => 'buah',  'kategori' => 'Fitting'],
            ['kode_material' => 1102, 'nama' => 'Elbow 90 derajat',        'jumlah_stok' => 250, 'satuan' => 'buah',  'kategori' => 'Fitting'],
            ['kode_material' => 1103, 'nama' => 'Socket Penyambung Lurus', 'jumlah_stok' => 250, 'satuan' => 'buah',  'kategori' => 'Fitting'],
            ['kode_material' => 1104, 'nama' => 'Tee',                     'jumlah_stok' => 200, 'satuan' => 'buah',  'kategori' => 'Fitting'],
            ['kode_material' => 1105, 'nama' => 'Reducer',                 'jumlah_stok' => 150, 'satuan' => 'buah',  'kategori' => 'Fitting'],
            ['kode_material' => 1106, 'nama' => 'Air Valve Reinforced Nylon', 'jumlah_stok' => 80, 'satuan' => 'buah','kategori' => 'Fitting'],

            // -------- Sambungan Rumah (SR) & Meter Air (1200-1299) --------
            ['kode_material' => 1201, 'nama' => 'Water Meter 1/2"',        'jumlah_stok' => 500, 'satuan' => 'buah',  'kategori' => 'SR'],
            ['kode_material' => 1202, 'nama' => 'Water Meter 3/4"',        'jumlah_stok' => 300, 'satuan' => 'buah',  'kategori' => 'SR'],
            ['kode_material' => 1210, 'nama' => 'Ball Valve 1/2"',         'jumlah_stok' => 400, 'satuan' => 'buah',  'kategori' => 'SR'],
            ['kode_material' => 1211, 'nama' => 'Box Meter & Plug',        'jumlah_stok' => 350, 'satuan' => 'buah',  'kategori' => 'SR'],

            // -------- Bahan Kimia (1300-1399) --------
            // ['kode_material' => 1301, 'nama' => 'Tawas / PAC',             'jumlah_stok' => 2000, 'satuan' => 'kg',    'kategori' => 'Bahan Kimia'],
            // ['kode_material' => 1302, 'nama' => 'Klorin Cair Cl2',         'jumlah_stok' => 1000, 'satuan' => 'liter', 'kategori' => 'Bahan Kimia'],
            // ['kode_material' => 1303, 'nama' => 'Kaporit',                 'jumlah_stok' => 500,  'satuan' => 'kg',    'kategori' => 'Bahan Kimia'],
            // ['kode_material' => 1304, 'nama' => 'Karbon Aktif',            'jumlah_stok' => 300,  'satuan' => 'kg',    'kategori' => 'Bahan Kimia'],
        ];

        foreach ($rows as $row) {
            Material::updateOrCreate(
                ['kode_material' => $row['kode_material']],
                [
                    'nama'        => $row['nama'],
                    'jumlah_stok' => $row['jumlah_stok'],
                    'terpakai'    => 0,
                    'satuan'      => $row['satuan'],
                    'kategori'    => $row['kategori'],
                    'pegawai_id'  => 1,
                ],
            );
        }
    }
}
