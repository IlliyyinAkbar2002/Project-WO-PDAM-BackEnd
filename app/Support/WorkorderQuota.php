<?php

namespace App\Support;

use App\Models\ProgressWorkorder;
use App\Models\TipeProgress;
use Illuminate\Support\Carbon;

/**
 * Perhitungan kuota pelaporan progres work order.
 *
 * Sebelumnya logika date-math ini diduplikasi di 5 tempat (Model + 4 endpoint
 * Controller) dan semuanya keliru menambah `+ 1` hari, sehingga kuota total
 * selalu kelebihan 1 hari (mis. WO 1 hari mendapat kuota 16, bukan 8).
 * Disentralisasi di sini supaya konsisten dan tidak terulang.
 */
class WorkorderQuota
{
    public const SUBMISSIONS_PER_DAY = 8;

    /**
     * Jumlah hari kalender yang dicakup work order, minimal 1.
     * WO yang mulai & selesai di tanggal yang sama dihitung 1 hari supaya
     * pekerjaan per-jam tetap mendapat kuota harian penuh.
     *
     * ASUMSI TIMEZONE (WIB): perhitungan batas hari di sini benar SELAMA
     * config('app.timezone') = 'Asia/Jakarta' DAN kolom tanggal disimpan
     * sebagai `timestamp without time zone` berisi wall-clock WIB. Dengan
     * konfigurasi itu Carbon::parse()->startOfDay() sudah beroperasi di WIB,
     * sehingga tidak ada skew UTC<->WIB yang bisa melewati batas tengah malam.
     * Jika salah satu berubah — app.timezone jadi UTC, kolom dimigrasi ke
     * `timestamptz`, atau klien mengirim string ISO-8601 dengan offset/'Z' —
     * batas hari mulai bergantung pada UTC dan totalDays bisa off-by-one.
     */
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

    /**
     * Query builder untuk submission yang dihitung terhadap kuota & progress %.
     * Hanya menghitung laporan PROGRESS yang difile user. Mengecualikan:
     *   - MULAI    (transisi status: "Mulai Kerja")
     *   - SELESAI  (transisi status: "Selesai Pekerjaan", PIC only, one-time)
     *   - REVISI   (auto-generated saat SPV minta revisi)
     *   - DITOLAK  (auto-generated saat SPV menolak)
     *
     * Filter positif tunggal (kode = PROGRESS) lebih aman daripada empat filter
     * negatif: tipe baru apa pun yang ditambahkan kelak otomatis tidak terhitung.
     * Sebelumnya tiap call-site memakai `!= MULAI` sehingga SELESAI/REVISI/DITOLAK
     * ikut terhitung dan membuat progress melonjak tanpa user benar-benar lapor.
     *
     * Pakai ini untuk pertanyaan "berapa submission yang dibuat user ini".
     * Untuk hitung baris mentah jenis apa pun, query ProgressWorkorder langsung.
     */
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
