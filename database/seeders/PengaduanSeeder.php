<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PengaduanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('pengaduan')->insert([
            [
                'kode_pengaduan' => 'PGD-001',
                'judul' => 'Air keruh di rumah warga',
                'deskripsi' => 'Air berwarna coklat dan berbau',
                'tanggal_pengaduan' => Carbon::now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_pengaduan' => 'PGD-002',
                'judul' => 'Kebocoran pipa utama',
                'deskripsi' => 'Air keluar dari pipa distribusi',
                'tanggal_pengaduan' => Carbon::now()->subDays(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_pengaduan' => 'PGD-003',
                'judul' => 'Meter tidak berjalan',
                'deskripsi' => 'Meter tidak mencatat pemakaian',
                'tanggal_pengaduan' => Carbon::now()->subDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_pengaduan' => 'PGD-004',
                'judul' => 'Meter tidak berjalan',
                'deskripsi' => 'Meter tidak mencatat pemakaian',
                'tanggal_pengaduan' => Carbon::now()->subDays(3),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_pengaduan' => 'PGD-005',
                'judul' => 'Meter tidak berjalan',
                'deskripsi' => 'Meter tidak mencatat pemakaian',
                'tanggal_pengaduan' => Carbon::now()->subDays(4),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        
        $this->call(PengaduanSeeder::class);
    }

}
