<?php

namespace App\Services;

use App\Models\ProgressWorkorder;
use App\Models\Status;
use App\Models\TipeProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgressWorkorderService
{
    private function statusId(string $kode): ?int
    {
        static $cache = [];
        if (!array_key_exists($kode, $cache)) {
            $cache[$kode] = Status::where('kode', $kode)->value('id');
            if ($cache[$kode] === null) {
                Log::warning("Status kode '{$kode}' tidak ditemukan.");
            }
        }
        return $cache[$kode];
    }

    private function tipeProgressId(string $kode): ?int
    {
        static $cache = [];
        if (!array_key_exists($kode, $cache)) {
            $cache[$kode] = TipeProgress::where('kode', $kode)->value('id');
            if ($cache[$kode] === null) {
                Log::warning("TipeProgress kode '{$kode}' tidak ditemukan.");
            }
        }
        return $cache[$kode];
    }

    public function addWorkorderProgress(int $workOrderId): void
    {
        $tipeProgressId = $this->tipeProgressId('PROGRESS');
        $statusDraftId  = $this->statusId('DRAFT');

        // ✅ Fix B2: null guard
        throw_if(
            $tipeProgressId === null || $statusDraftId === null,
            new \RuntimeException('Master data belum lengkap untuk addWorkorderProgress.')
        );

        DB::transaction(function () use ($workOrderId, $tipeProgressId, $statusDraftId) {
            $maxOrder = ProgressWorkorder::where('workorder_id', $workOrderId)
                ->max('order') ?? 0;

            // ✅ Fix B6: geser semua >= maxOrder
            ProgressWorkorder::where('workorder_id', $workOrderId)
                ->where('order', '>=', $maxOrder)
                ->increment('order');

            ProgressWorkorder::create([
                'workorder_id'     => $workOrderId,
                'tipe_progress_id' => $tipeProgressId,
                'status_id'        => $statusDraftId,
                'order'            => $maxOrder,
            ]);
        });
    }

    public function updateStatusOnSubmit(int $progressId): void
    {
        $progress = ProgressWorkorder::with(['workorder', 'tipeProgress'])
            ->findOrFail($progressId);

        // ✅ Fix B4: guard status terminal
        $terminalStatuses = array_filter([
            $this->statusId('VERIFIED'),
            $this->statusId('DITOLAK_SPV'),
        ]);
        if (in_array($progress->status_id, $terminalStatuses, true)) {
            Log::info('updateStatusOnSubmit: skip, status sudah terminal.', [
                'progress_id' => $progressId,
                'status_id'   => $progress->status_id,
            ]);
            return;
        }

        $submittedId = $this->statusId('SUBMITTED');
        throw_if($submittedId === null, new \RuntimeException('Status SUBMITTED tidak ditemukan.'));

        $progress->update(['status_id' => $submittedId]);

        // ✅ Fix B3: handle semua tipe dengan eksplisit
        $kode = optional($progress->tipeProgress)->kode;

        $newWoStatus = match ($kode) {
            'MULAI'   => $this->statusId('IN_PROGRESS'),
            'SELESAI' => $this->statusId('PENGECEKAN'),
            default   => null, // PROGRESS, REVISI, dll → workorder tetap
        };

        if ($newWoStatus !== null) {
            $progress->workorder->update(['status_id' => $newWoStatus]);
        }

        Log::info('updateStatusOnSubmit: OK', [
            'progress_id' => $progressId,
            'tipe_kode'   => $kode,
            'wo_status'   => $newWoStatus ?? 'UNCHANGED',
        ]);
    }
}