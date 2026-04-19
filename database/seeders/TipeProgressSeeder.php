<?php

namespace Database\Seeders;

use App\Models\TipeProgress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipeProgressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Idempotent: aman dipanggil berkali-kali (mis. setelah `migrate:refresh
     * --seed`). Migration sudah seed inline juga — seeder ini berfungsi
     * sebagai jaring pengaman bila row sempat terhapus manual.
     *
     * @return void
     */
    public function run()
    {
        $rows = [
            ['id' => 1, 'kode' => 'MULAI',    'nama' => 'Mulai'],
            ['id' => 2, 'kode' => 'PROGRESS', 'nama' => 'Progress'],
            ['id' => 3, 'kode' => 'SELESAI',  'nama' => 'Selesai'],
        ];

        foreach ($rows as $row) {
            TipeProgress::updateOrCreate(
                ['id' => $row['id']],
                ['kode' => $row['kode'], 'nama' => $row['nama']],
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('m_tipe_progress', 'id'), (SELECT COALESCE(MAX(id), 0) FROM m_tipe_progress))");
        }
    }
}
