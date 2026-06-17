<?php

namespace App\Services;

use App\Models\ProgressWorkorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Versi enum + pegawai (target MergerManual).
 *
 * progress_workorder tidak punya kolom status_id; siklus review dilacak via
 * progress_detail + waktu_submit. tipe_progress = enum ('inpeksi','mulai','progress','selesai').
 * workorder.status = enum ('Pending','Proses','Selesai','Tutup').
 */
class ProgressWorkorderService
{
    /** Tipe progress (enum target). 'inpeksi' mengikuti ejaan kolom enum yang ada. */
    public const TIPE_INSPEKSI = 'inpeksi';
    public const TIPE_MULAI    = 'mulai';
    public const TIPE_PROGRESS = 'progress';
    public const TIPE_SELESAI  = 'selesai';

    /**
     * Tambah baris PROGRESS untuk WO aktif (dipakai manual-run/batch).
     */
    public function addWorkorderProgress(int $workOrderId): void
    {
        DB::transaction(function () use ($workOrderId) {
            $maxOrder = ProgressWorkorder::where('workorder_id', $workOrderId)
                ->max('order') ?? 0;

            // Geser semua >= maxOrder agar baris baru tersisip rapi.
            ProgressWorkorder::where('workorder_id', $workOrderId)
                ->where('order', '>=', $maxOrder)
                ->increment('order');

            ProgressWorkorder::create([
                'workorder_id'  => $workOrderId,
                'tipe_progress' => self::TIPE_PROGRESS,
                'order'         => $maxOrder,
            ]);
        });
    }

    /**
     * Tandai progress sebagai ter-submit & selaraskan status WO (enum).
     *
     * - 'mulai'  → WO 'Proses' (pekerjaan berjalan)
     * - lainnya  → WO tidak diubah (tetap 'Proses' hingga SPV approve final)
     */
    public function updateStatusOnSubmit(int $progressId): void
    {
        $progress = ProgressWorkorder::with('workorder')->findOrFail($progressId);

        $progress->update(['waktu_submit' => now()]);

        if ($progress->tipe_progress === self::TIPE_MULAI
            && $progress->workorder
            && $progress->workorder->status === 'Pending') {
            $progress->workorder->update(['status' => 'Proses']);
        }

        Log::info('updateStatusOnSubmit OK', [
            'progress_id' => $progressId,
            'tipe'        => $progress->tipe_progress,
        ]);
    }
}
