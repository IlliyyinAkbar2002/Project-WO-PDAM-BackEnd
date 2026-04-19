<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Catatan: untuk seed canonical 11 row (termasuk DRAFT/SUBMITTED/VERIFIED
     * untuk progress_workorder), gunakan StatusSeeder yang idempotent.
     * Factory ini tetap dipertahankan untuk testing & menghasilkan urutan
     * 8 row historis pertama.
     *
     * @return array
     */
    public function definition()
    {
        static $rows = [
            ['kode' => 'BELUM_DISETUJUI', 'nama' => 'Belum disetujui', 'keterangan' => 'Menunggu persetujuan'],
            ['kode' => 'DISETUJUI',       'nama' => 'Disetujui',       'keterangan' => 'Disetujui oleh atasan'],
            ['kode' => 'REVISI',          'nama' => 'Revisi',          'keterangan' => 'Revisi hasil pekerjaan'],
            ['kode' => 'DITOLAK',         'nama' => 'Ditolak',         'keterangan' => 'Ditolak oleh atasan'],
            ['kode' => 'PENGECEKAN',      'nama' => 'Pengecekan',      'keterangan' => 'Pengecekan workorder oleh atasan'],
            ['kode' => 'SELESAI',         'nama' => 'Selesai',         'keterangan' => 'Workorder Selesai'],
            ['kode' => 'IN_PROGRESS',     'nama' => 'In Progress',     'keterangan' => 'Status aktif'],
            ['kode' => 'FREEZE',          'nama' => 'Freeze',          'keterangan' => 'Status nonaktif'],
        ];

        $row = array_shift($rows);

        return [
            'kode'       => $row['kode'],
            'nama'       => $row['nama'],
            'keterangan' => $row['keterangan'],
            'aktif'      => true,
        ];
    }
}
