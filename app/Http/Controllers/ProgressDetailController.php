<?php

namespace App\Http\Controllers;

use App\Models\ProgressDetail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Riwayat review progress (read-only). Penulisan review = satu sumber kebenaran
 * lewat ProgressWorkorderController::review (decision=accept|revisi).
 */
class ProgressDetailController extends Controller
{
    /**
     * List riwayat review untuk sebuah progress_workorder.
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

            return response()->json($query->get(), 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Terjadi kesalahan saat mengambil data progress detail',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
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

    /** DEPRECATED — gunakan POST /api/v1/progress-workorder/review. */
    public function approve(Request $request, $id)
    {
        return $this->deprecatedReviewResponse();
    }

    /** DEPRECATED — gunakan POST /api/v1/progress-workorder/review. */
    public function reject(Request $request, $id)
    {
        return $this->deprecatedReviewResponse();
    }

    private function deprecatedReviewResponse()
    {
        return response()->json([
            'error' => 'Endpoint ini sudah tidak dipakai. Gunakan POST /api/v1/progress-workorder/review dengan field decision (accept|revisi).',
        ], 410);
    }
}
