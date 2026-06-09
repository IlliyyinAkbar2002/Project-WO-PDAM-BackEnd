<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * ProgressLemburController
 *
 * Alur progres untuk WO LEMBUR (overtime). Sebuah "WO lembur" adalah baris
 * `workorder` biasa yang `lembur_spl_id`-nya terisi, jadi seluruh siklus
 * progres petugas — start → submit (progress/selesai) → dokumentasi foto →
 * kuota — IDENTIK dengan WO reguler. Karena itu controller ini meng-extend
 * ProgressWorkorderController dan mewarisi semua method (start, submit,
 * resubmit, cancel, index, show, update, quota, progressByMember,
 * memberSummary) tanpa perubahan.
 *
 * Satu-satunya perbedaan: REVIEW oleh SPV. Di halaman mobile lembur hanya ada
 * tombol Accept dan Reject:
 *   - accept → SELESAI (sama seperti reguler)
 *   - reject → minta revisi (WO balik IN_PROGRESS, petugas bisa resubmit) —
 *     BUKAN penolakan final. Dipetakan ke cabang `revisi` milik parent.
 *
 * Endpoint reguler `progress-workorder/*` tetap memakai Revise/Accept.
 */
class ProgressLemburController extends ProgressWorkorderController
{
    /**
     * Review progres WO lembur oleh SPV — hanya Accept / Reject.
     *
     * `reject` diterjemahkan ke `revisi` lalu didelegasikan ke
     * parent::review() agar seluruh logika (progress_detail, baris penanda,
     * transisi status, LaporanWorkorder saat accept) dipakai ulang apa adanya.
     * Alasan penolakan dikirim FE lewat `alasan_penolakan` dan ikut tersimpan
     * di cabang revisi parent. Mengirim `decision` selain accept/reject (mis.
     * `tolak`/`revisi` langsung) ditolak 422 — mengunci kontrak review lembur.
     */
    public function review(Request $request)
    {
        // Hidrasi dulu agar `decision` terbaca untuk klien multipart/json
        // sebelum diterjemahkan (parent::review akan hydrate lagi; idempoten).
        $this->hydrateInputFromBody($request);

        $request->validate([
            'decision' => 'required|in:accept,reject',
        ]);

        if ($request->input('decision') === 'reject') {
            $request->merge(['decision' => 'revisi']);
        }

        return parent::review($request);
    }
}
