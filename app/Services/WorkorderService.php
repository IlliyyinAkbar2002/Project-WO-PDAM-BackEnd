<?php

namespace App\Services;

use App\Models\LemburSpl;
use App\Models\Workorder;
use Illuminate\Support\Facades\DB;
use App\Services\ProgressWorkorderService; 
use App\Services\WorkorderActionService; 

class WorkorderService
{
    // === DEFENISI KONSTANTA (SESUAIKAN DENGAN ID DATABASE ANDA!) ===
    const TIPE_WO_LEMBUR_ID     = 2;
    const STATUS_BELUM_DISETUJUI_ID = 1; // Asumsi: Status WO Normal
    const STATUS_SEDANG_BERJALAN_ID = 7; // Asumsi: Status WO Lembur yang langsung 'Sedang berjalan'
    
    // 👇 KONSTANTA YANG HILANG DAN DIBUTUHKAN UNTUK REJECT
    const STATUS_DITOLAK_ID     = 4; // ASUMSI: ID Status Workorder untuk 'Ditolak'
    const ACTION_REJECT_ID      = 3; // ASUMSI: ID Action untuk 'Penolakan'
    // ==============================================================

    public function createWorkorders(array $data)
    {
        return DB::transaction(function () use ($data) {
            $createdWorkorders = [];
            
            // 1. Tentukan Status Awal (Default: WO Normal = Belum Disetujui)
            $initialStatusId = self::STATUS_BELUM_DISETUJUI_ID;

            // 2. Logika Penentuan Status Awal berdasarkan Tipe WO
            // Jika tipe WO adalah Lembur (ID 2), ganti status ke Sedang Berjalan (ID 7)
            if ((int) $data['tipe_workorder_id'] === self::TIPE_WO_LEMBUR_ID) {
                $initialStatusId = self::STATUS_SEDANG_BERJALAN_ID;
            }
            // Jika bukan WO Lembur, $initialStatusId akan tetap 1 (Belum Disetujui)

            
            foreach ($data['petugas_id'] as $petugasId) {
                $lemburSplId = null;
                // Tetapkan status WO ini (akan bernilai 1 atau 7)
                $statusId = $initialStatusId; 

                // 3. Logika Khusus untuk WO Lembur (Membuat Dokumen SPL)
                if ((int) $data['tipe_workorder_id'] === self::TIPE_WO_LEMBUR_ID) {
                    $lemburSpl = LemburSpl::create([
                        // Status Lembur SPL (Sub-dokumen) tetap harus disetujui atasan
                        // sehingga statusnya diatur ke Belum Disetujui (ID 1)
                        'status_id' => self::STATUS_BELUM_DISETUJUI_ID, 
                        'waktu_pengajuan' => now(),
                    ]);
                    $lemburSplId = $lemburSpl->id;
                    
                    // Status WO utama ($statusId) adalah ID 7 (Sudah Sedang Berjalan)
                }
                
                // 4. Proses Pembuatan Workorder Utama
                $workorder = Workorder::create([
                    'judul_pekerjaan'    => $data['judul_pekerjaan'],
                    'waktu_penugasan'    => $data['waktu_penugasan'],
                    'estimasi_durasi'    => $data['estimasi_durasi'],
                    'unit_waktu'         => $data['unit_waktu'],
                    'estimasi_selesai'   => $data['estimasi_selesai'],
                    'longitude'          => $data['longitude'],
                    'latitude'           => $data['latitude'],
                    'petugas_id'         => $petugasId,
                    'pic_id'             => $data['pic_id'],
                    'lembur_spl_id'      => $lemburSplId,
                    'status_id'          => $statusId, // 7 (Lembur) atau 1 (Normal)
                    'jenis_workorder_id' => $data['jenis_workorder_id'],
                    'jenis_lokasi_id'    => $data['jenis_lokasi_id'],
                    'tipe_workorder_id'  => $data['tipe_workorder_id'],
                ]);

                // 5. Logika Progress dan Action (Hanya untuk WO yang Langsung Dikerjakan/Sedang Berjalan)
                if ($statusId === self::STATUS_SEDANG_BERJALAN_ID) { // Status ID 7 (WO Lembur)
                    // Membuat Progress awal
                    (new ProgressWorkorderService())->createInitialProgress($workorder->id);
                    // Mencatat Aksi Penugasan awal
                    (new WorkorderActionService())->createAction([
                        'workorder_id'      => $workorder->id,
                        'action_id'         => 1, // Asumsi Action ID 1 adalah 'Assignment'
                        'keterangan'        => 'Penugasan awal (WO Lembur langsung dimulai)',
                        'waktu_mulai'       => $data['waktu_penugasan'],
                        'estimasi_selesai'  => $data['estimasi_selesai'],
                    ]);
                }
                
                $createdWorkorders[] = $workorder;
            }

            return $createdWorkorders;
        });
    }

    /**
     * LOGIKA BARU UNTUK MENOLAK WORKORDER
     * Melakukan update status dan mencatat aksi penolakan dalam satu transaksi database.
     * @param int $workorderId ID Workorder yang akan ditolak
     * @param string $keterangan Alasan penolakan dari user
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException jika WO tidak ditemukan
     * @return Workorder
     */
    public function rejectWorkorder(int $workorderId, string $keterangan): Workorder
    {
        return DB::transaction(function () use ($workorderId, $keterangan) {
            // 1. Cari Workorder
            $workorder = Workorder::findOrFail($workorderId);

            // 2. Update Status Workorder menjadi DITOLAK
            $workorder->update([
                'status_id' => self::STATUS_DITOLAK_ID,
                'keterangan_penolakan' => $keterangan, // ASUMSI: ada kolom ini di tabel Workorder
            ]);

            // 3. Catat Aksi Penolakan
            (new WorkorderActionService())->createAction([
                'workorder_id'      => $workorderId,
                'action_id'         => self::ACTION_REJECT_ID, 
                'keterangan'        => 'Ditolak dengan alasan: ' . $keterangan,
                'waktu_mulai'       => now(), 
                // Tambahkan user_id atau pic_id jika perlu dicatat siapa yang menolak
                // 'user_id' => auth()->id(), 
            ]);

            return $workorder;
        });
    }
}