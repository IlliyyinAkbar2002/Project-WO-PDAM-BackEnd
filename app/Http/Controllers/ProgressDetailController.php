<?php

namespace App\Http\Controllers;

use App\Models\ProgressDetail;
use App\Models\ProgressWorkorder;
use App\Models\Status;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressDetailController extends Controller
{
    private function statusId(string $kode): ?int
    {
        return Status::where('kode', $kode)->value('id');
    }

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
     * Staff resubmits after rejection.
     * Creates new progress_detail with status='pending'.
     *
     * POST /api/v1/progress-detail/resubmit
     * Body: { progress_workorder_id: int }
     */
    public function resubmit(Request $request)
    {
        $validated = $request->validate([
            'progress_workorder_id' => 'required|exists:progress_workorder,id',
        ]);

        $userId = optional($request->user())->id;
        $progress = ProgressWorkorder::with('workorder.assignmentMembers')->findOrFail($validated['progress_workorder_id']);

        // Verify user is assigned to this workorder
        if (!$progress->workorder->assignmentMembers->pluck('user_id')->contains($userId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        // Check latest detail is rejected
        $latestDetail = $progress->progressDetails()->latest()->first();
        if (!$latestDetail || $latestDetail->status !== 'rejected') {
            return response()->json(['error' => 'Progress tidak dalam status rejected'], 422);
        }

        DB::beginTransaction();
        try {
            $newDetail = ProgressDetail::create([
                'progress_workorder_id' => $progress->id,
                'status' => 'pending',
            ]);

            // Update progress status back to SUBMITTED
            $progress->update(['status_id' => $this->statusId('SUBMITTED')]);

            DB::commit();

            return response()->json([
                'message' => 'Progress berhasil diresubmit',
                'detail' => $newDetail->load('progressWorkorder'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * SPV approves progress detail.
     *
     * POST /api/v1/progress-detail/{id}/approve
     */
    public function approve(Request $request, $id)
    {
        $detail = ProgressDetail::with('progressWorkorder.workorder')->findOrFail($id);
        $userId = optional($request->user())->id;
        $workorder = $detail->progressWorkorder->workorder;

        // Verify user is SPV who created this WO
        if ((int) $workorder->assigned_to !== (int) $userId) {
            return response()->json(['error' => 'Hanya SPV yang membuat WO ini yang bisa approve'], 403);
        }

        if ($detail->status !== 'pending') {
            return response()->json(['error' => 'Detail sudah direview'], 422);
        }

        DB::beginTransaction();
        try {
            $detail->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
            ]);

            // reviewed_by_user_id sudah dipindah sepenuhnya ke progress_detail.
            // progress_workorder hanya update status_id.
            $detail->progressWorkorder->update([
                'status_id' => $this->statusId('VERIFIED'),
            ]);

            // If this is SELESAI type, mark workorder as SELESAI
            $tipeKode = optional($detail->progressWorkorder->tipeProgress)->kode;
            if ($tipeKode === 'SELESAI') {
                $workorder->update([
                    'status_id' => $this->statusId('SELESAI'),
                    'approved_by_user_id' => $userId,
                    'approved_at' => now(),
                    'tanggal_selesai' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Progress berhasil diapprove',
                'detail' => $detail->fresh(['progressWorkorder', 'reviewer']),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * SPV rejects progress detail.
     *
     * POST /api/v1/progress-detail/{id}/reject
     * Body: { alasan_penolakan: string, field_to_revise: string }
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'alasan_penolakan' => 'required|string',
            'field_to_revise' => 'nullable|string',
        ]);

        $detail = ProgressDetail::with('progressWorkorder.workorder')->findOrFail($id);
        $userId = optional($request->user())->id;
        $workorder = $detail->progressWorkorder->workorder;

        // Verify user is SPV who created this WO
        if ((int) $workorder->assigned_to !== (int) $userId) {
            return response()->json(['error' => 'Hanya SPV yang membuat WO ini yang bisa reject'], 403);
        }

        if ($detail->status !== 'pending') {
            return response()->json(['error' => 'Detail sudah direview'], 422);
        }

        DB::beginTransaction();
        try {
            $detail->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'alasan_penolakan' => $validated['alasan_penolakan'],
                'field_to_revise' => $validated['field_to_revise'] ?? null,
            ]);

            // reviewed_by_user_id sudah dipindah sepenuhnya ke progress_detail.
            // progress_workorder hanya update status_id.
            $detail->progressWorkorder->update([
                'status_id' => $this->statusId('REVISI_REQUESTED'),
            ]);

            // Update workorder status
            $workorder->update(['status_id' => $this->statusId('IN_PROGRESS')]);

            DB::commit();

            return response()->json([
                'message' => 'Progress berhasil ditolak',
                'detail' => $detail->fresh(['progressWorkorder', 'reviewer']),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
