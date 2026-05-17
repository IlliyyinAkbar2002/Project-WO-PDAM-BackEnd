<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seed canonical 11 status master:
     *  - 8 row historis untuk workorder/lembur (BELUM_DISETUJUI ... FREEZE)
     *  - 3 row baru untuk progres (DRAFT, SUBMITTED, VERIFIED)
     *
     * Idempotent: aman dipanggil berkali-kali. Id dipaksa eksplisit supaya
     * referensi dari kode lain (yang sebagian masih hardcoded id, mis.
     * `status_id => 5` di ProgressWorkorderService) tidak bergeser.
     *
     * @return void
     */
    public function run()
    {
        $rows = [
            ['id' => 1,  'kode' => 'BELUM_DISETUJUI', 'nama' => 'Belum disetujui', 'keterangan' => 'Menunggu persetujuan'],
            ['id' => 2,  'kode' => 'DISETUJUI',       'nama' => 'Disetujui',       'keterangan' => 'Disetujui oleh atasan'],
            ['id' => 3,  'kode' => 'REVISI',          'nama' => 'Revisi',          'keterangan' => 'Revisi hasil pekerjaan'],
            ['id' => 4,  'kode' => 'DITOLAK',         'nama' => 'Ditolak',         'keterangan' => 'Ditolak oleh atasan'],
            ['id' => 5,  'kode' => 'PENGECEKAN',      'nama' => 'Pengecekan',      'keterangan' => 'Pengecekan workorder oleh atasan'],
            ['id' => 6,  'kode' => 'SELESAI',         'nama' => 'Selesai',         'keterangan' => 'Workorder Selesai'],
            ['id' => 7,  'kode' => 'IN_PROGRESS',     'nama' => 'In Progress',     'keterangan' => 'Status aktif'],
            ['id' => 8,  'kode' => 'FREEZE',          'nama' => 'Freeze',          'keterangan' => 'Status nonaktif'],

            // Status baru khusus progress_workorder (TKT-03).
            ['id' => 9,  'kode' => 'DRAFT',           'nama' => 'Draft',           'keterangan' => 'Progres dibuat saat assign, belum disubmit oleh petugas'],
            ['id' => 10, 'kode' => 'SUBMITTED',       'nama' => 'Submitted',       'keterangan' => 'Progres sudah disubmit oleh petugas, menunggu verifikasi'],
            ['id' => 11, 'kode' => 'VERIFIED',        'nama' => 'Verified',        'keterangan' => 'Progres sudah diverifikasi/approve oleh SPV'],
            ['id' => 12, 'kode' => 'DITUGASKAN_KE_SPV',        'nama' => 'Ditugaskan ke SPV',        'keterangan' => 'WO dibuat Super Admin dan menunggu penugasan tim oleh SPV'],
            ['id' => 13, 'kode' => 'DITUGASKAN_KE_STAFF',      'nama' => 'Ditugaskan ke Staff',      'keterangan' => 'WO sudah diassign SPV ke tim pelaksana'],
            ['id' => 14, 'kode' => 'REVISI_REQUESTED',         'nama' => 'Revisi Requested',         'keterangan' => 'SPV meminta perbaikan progres'],
            ['id' => 15, 'kode' => 'DITOLAK_SPV',              'nama' => 'Ditolak SPV',              'keterangan' => 'Progress atau WO ditolak oleh SPV'],
            ['id' => 16, 'kode' => 'MENUNGGU_APPROVAL_MANAGER','nama' => 'Menunggu Approval Manager','keterangan' => 'WO menunggu persetujuan akhir Manager'],
            ['id' => 17, 'kode' => 'DITOLAK_MANAGER',          'nama' => 'Ditolak Manager',          'keterangan' => 'WO ditolak oleh Manager'],
            ['id' => 18, 'kode' => 'DIBATALKAN',               'nama' => 'Dibatalkan',               'keterangan' => 'Progress dibatalkan oleh petugas dalam batas waktu'],
        ];

        foreach ($rows as $row) {
            Status::updateOrCreate(
                ['id' => $row['id']],
                [
                    'kode'       => $row['kode'],
                    'nama'       => $row['nama'],
                    'keterangan' => $row['keterangan'],
                    'aktif'      => true,
                ],
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('m_status', 'id'), (SELECT COALESCE(MAX(id), 0) FROM m_status))");
        }
    }
}
