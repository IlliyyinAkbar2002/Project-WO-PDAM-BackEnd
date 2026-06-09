<?php

namespace App\Http\Controllers;

use App\Models\ProgressDetail;
use App\Models\ProgressWorkorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ProgressDetailController extends Controller
{
    /**
     * Auto-create progress_detail when staff submits progress.
     * Called internally after ProgressWorkorder is created/updated.
     *
     * @param int $progressWorkorderId
     * @return ProgressDetail
     */
    public function autoCreateOnSubmit(int $progressWorkorderId): ProgressDetail
    {
        $progress = ProgressWorkorder::findOrFail($progressWorkorderId);

        return ProgressDetail::create([
            'progress_workorder_id' => $progress->id,
            'status' => 'pending',
        ]);
    }

    /**
     * List all progress details for a specific progress_workorder.
     * Shows review history (initial submit + all resubmissions).
     *
     * GET /api/v1/progress-detail?progress_workorder_id={id}
     */
    public function index(Request $request)
    {
        try {
            $query = ProgressDetail::with(['progressWorkorder', 'reviewer']);

            if ($request->has('progress_workorder_id')) {
                $query->where('progress_workorder_id', $request->query('progress_workorder_id'))
                    ->orderBy('created_at', 'desc');
            }

            $details = $query->get();
            return response()->json($details, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data progress detail',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific progress detail.
     *
     * GET /api/v1/progress-detail/{id}
     */
    public function show($id)
    {
        try {
            $detail = ProgressDetail::with(['progressWorkorder', 'reviewer'])->findOrFail($id);
            return response()->json($detail, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Progress detail not found'], 404);
        }
    }

    /**
     * DEPRECATED — review WO sekarang satu sumber kebenaran lewat Sistem A:
     * `POST /api/v1/progress-workorder/review` (decision=accept|revisi|tolak).
     *
     * Endpoint ini dulu menggandakan logika accept dan ikut mengubah status WO,
     * sehingga ada dua sistem review paralel. Untuk menghilangkan ambiguitas,
     * penulisan status via Sistem B dimatikan. progress_detail tetap dipakai
     * sebagai riwayat review (GET index/show), diisi otomatis oleh Sistem A.
     *
     * POST /api/v1/progress-detail/{id}/approve
     */
    public function approve(Request $request, $id)
    {
        return $this->deprecatedReviewResponse();
    }

    /**
     * DEPRECATED — lihat catatan pada approve(). Endpoint `reject` ini sebenarnya
     * berperilaku "revisi" (WO balik ke IN_PROGRESS), bukan penolakan final.
     * Gunakan Sistem A: `decision=revisi` untuk minta perbaikan, atau
     * `decision=tolak` untuk penolakan FINAL (DITOLAK_SPV, terminal).
     *
     * POST /api/v1/progress-detail/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        return $this->deprecatedReviewResponse();
    }

    private function deprecatedReviewResponse()
    {
        return response()->json([
            'error' => 'Endpoint ini sudah tidak dipakai. Gunakan POST /api/v1/progress-workorder/review dengan field decision (accept|revisi|tolak).',
        ], 410);
    }
}
