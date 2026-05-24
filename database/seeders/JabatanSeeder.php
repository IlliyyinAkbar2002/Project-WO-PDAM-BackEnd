<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JabatanSeeder extends Seeder
{
    /**
     * Seed jabatan kanonik.
     *
     * Idempotent: aman dipanggil ulang setelah `migrate:refresh`. Hirarki
     * mengikuti pola PegawaiController::index() — id makin kecil = makin
     * senior — sehingga lookup `WHERE id > callerJabatanId` mengembalikan
     * subordinat dengan benar.
     *
     * `kode` dipakai authorization logic (mis. ProgressWorkorderController
     * memvalidasi SELESAI hanya boleh dilakukan PIC dengan kode SENIOR_STAFF).
     *
     * @return void
     */
    public function run()
    {
        $rows = [
            ['id' => 1, 'kode' => 'MANAGER',      'nama' => 'Manager'],
            ['id' => 2, 'kode' => 'SPV',          'nama' => 'Supervisor'],
            ['id' => 3, 'kode' => 'SENIOR_STAFF', 'nama' => 'Senior Staff'],
            ['id' => 4, 'kode' => 'STAFF',        'nama' => 'Staff'],
        ];

        foreach ($rows as $row) {
            Jabatan::updateOrCreate(
                ['id' => $row['id']],
                ['kode' => $row['kode'], 'nama' => $row['nama']],
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('m_jabatan', 'id'), (SELECT COALESCE(MAX(id), 0) FROM m_jabatan))");
        }
    }
}
