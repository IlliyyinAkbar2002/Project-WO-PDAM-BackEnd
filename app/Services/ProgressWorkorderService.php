<?php

namespace App\Services;

use App\Models\ProgressWorkorder;
use App\Models\Status;
use App\Models\TipeProgress;
use Illuminate\Support\Facades\DB;

class ProgressWorkorderService
{
    /**
     * Cache id status progres dalam satu request supaya tidak query ulang
     * untuk setiap row yang dibuat. Lookup pakai kolom `kode` (TKT-03b)
     * supaya tidak bergantung pada id numerik master data.
     */
    private function statusId(string $kode): ?int
    {
        static $cache = [];
        if (!array_key_exists($kode, $cache)) {
            $cache[$kode] = Status::where('kode', $kode)->value('id');
        }
        return $cache[$kode];
    }

    /**
     * Cache id tipe progres (TKT-05). Sama alasannya dengan `statusId()`:
     * hindari query duplikat untuk setiap row progres yang dibuat, dan
     * pakai `kode` supaya urutan id numerik master data tidak mengikat.
     */
    private function tipeProgressId(string $kode): ?int
    {
        static $cache = [];
        if (!array_key_exists($kode, $cache)) {
            $cache[$kode] = TipeProgress::where('kode', $kode)->value('id');
        }
        return $cache[$kode];
    }

    public function createInitialProgress(int $workOrderId): void
    {
        // Dibungkus transaction supaya dua row progres "Mulai" & "Selesai"
        // tidak terlanjur ter-commit kalau salah satu create gagal di tengah.
        // Laravel akan otomatis pakai savepoint kalau sudah ada transaction
        // luar (mis. WorkorderService::createWorkorders), jadi aman.
        //
        // Revisi Mei 2026: loop DetailProgress::create() sudah DIHAPUS — tabel
        // `detail_form` + `detail_progress` di-drop, diganti Class Table
        // Inheritance (wo_meter / wo_jaringan / wo_infrastruktur). Field hasil
        // pengerjaan di-isi langsung ke tabel kategori oleh Staff saat klik
        // "Selesai" di Tahap 8.
        DB::transaction(function () use ($workOrderId) {
            $statusDraftId = $this->statusId('DRAFT');
            $tipeMulaiId   = $this->tipeProgressId('MULAI');
            $tipeSelesaiId = $this->tipeProgressId('SELESAI');

            ProgressWorkorder::create([
                'workorder_id'     => $workOrderId,
                'tipe_progress_id' => $tipeMulaiId,
                'status_id'        => $statusDraftId,
                'order'            => 0,
            ]);

            ProgressWorkorder::create([
                'workorder_id'     => $workOrderId,
                'tipe_progress_id' => $tipeSelesaiId,
                'status_id'        => $statusDraftId,
                'order'            => 1,
            ]);
        });
    }

    public function addWorkorderProgress(int $workOrderId)
    {
        // Dibungkus transaction supaya increment `order` + create row baru
        // konsisten — kalau create gagal setelah increment, seluruh perubahan
        // di-rollback.
        DB::transaction(function () use ($workOrderId) {
            $maxOrder = ProgressWorkorder::where('workorder_id', $workOrderId)
                ->max('order');

            if (is_null($maxOrder)) {
                $maxOrder = 0;
            }

            // geser finish ke belakang
            ProgressWorkorder::where('workorder_id', $workOrderId)
                ->where('order', $maxOrder)
                ->increment('order');

            // Insert progres antara (di depan SELESAI). Setelah TKT-05,
            // urutan ke-N tidak lagi dikodekan di string tipe ('Progress 1',
            // 'Progress 2', ...) — semua langkah tengah memakai kode
            // PROGRESS. Urutan tetap bisa direkonstruksi dari kolom `order`.
            ProgressWorkorder::create([
                'workorder_id'     => $workOrderId,
                'tipe_progress_id' => $this->tipeProgressId('PROGRESS'),
                'status_id'        => $this->statusId('DRAFT'),
                'order'            => $maxOrder,
            ]);
        });

        return response()->json(['message' => 'Progress ditambahkan'], 201);
    }

    public function updateStatusOnSubmit(int $progressId): void
    {
        // Eager-load `tipeProgress` supaya perbandingan kode tidak trigger
        // query tambahan. Dipakai di blok if/elseif di bawah.
        $progress = ProgressWorkorder::with(['workorder', 'tipeProgress'])->findOrFail($progressId);

        // Update status progres dulu (DRAFT -> SUBMITTED) sebelum mengubah
        // status workorder, supaya state progres konsisten meskipun kelak
        // ada perubahan logic status workorder.
        $submittedId = $this->statusId('SUBMITTED');
        if ($submittedId !== null) {
            $progress->update(['status_id' => $submittedId]);
        }

        // TKT-05: pakai kode master `m_tipe_progress` (MULAI/SELESAI) agar
        // tidak bergantung pada string literal yang rapuh terhadap typo /
        // locale. `optional()` aman untuk row lama yang (secara teoritis)
        // tidak punya tipeProgress — meski setelah migration TKT-05 kolom
        // sudah NOT NULL sehingga cabang ini tidak akan kepilih.
        $kode = optional($progress->tipeProgress)->kode;

        if ($kode === 'MULAI') {
            $progress->workorder->update(['status_id' => $this->statusId('IN_PROGRESS')]);
        } elseif ($kode === 'SELESAI') {
            $progress->workorder->update(['status_id' => $this->statusId('PENGECEKAN')]);
        }
    }
}
