<?php

namespace App\Support;

use App\Models\ProgressWorkorder;
use App\Models\TipeProgress;
use Illuminate\Support\Carbon;

class WorkorderQuota
{
    public const SUBMISSIONS_PER_DAY = 8;

    public static function totalDays(?string $tanggalMulai, ?string $estimasiSelesai): int
    {
        if (!$tanggalMulai || !$estimasiSelesai) {
            return 1;
        }
        $start = Carbon::parse($tanggalMulai)->startOfDay();
        $end   = Carbon::parse($estimasiSelesai)->startOfDay();
        return max(1, (int) $start->diffInDays($end, false));
    }

    /**
     * Kuota pelaporan total = jumlah hari * batas harian.
     */
    public static function quotaTotal(?string $tanggalMulai, ?string $estimasiSelesai): int
    {
        return self::totalDays($tanggalMulai, $estimasiSelesai) * self::SUBMISSIONS_PER_DAY;
    }

    public static function countableSubmissions(int $workorderId, int $userId)
    {
        $progressTipeId = TipeProgress::where('kode', 'PROGRESS')->value('id');

        return ProgressWorkorder::query()
            ->where('workorder_id', $workorderId)
            ->where('submitted_by_user_id', $userId)
            ->where('tipe_progress_id', $progressTipeId)
            ->whereNotNull('waktu_submit');
    }
}
