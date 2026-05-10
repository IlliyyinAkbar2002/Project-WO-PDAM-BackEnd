<?php

namespace App\Http\Controllers;

use App\Models\LaporanWorkorder;
use App\Models\MasterAction;
use App\Models\Status;
use App\Models\Workorder;
use App\Models\WorkorderAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalWorkorder extends Controller
{
    private function statusId(string $kode): ?int
    {
        return Status::where('kode', $kode)->value('id');
    }

    private function actionId(string $kode): ?int
    {
        return MasterAction::where('kode', $kode)->value('id');
    }

    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string',
        ]);

        if ((int) $request->user()->role_id !== 2) {
            return response()->json(['error' => 'Hanya manager yang bisa approve'], 403);
        }

        DB::beginTransaction();
        try {
            $workorder = Workorder::with([
                'jenisWorkorder',
                'woMeter',
                'woJaringan',
                'woInfrastruktur',
                'assignmentMembers.user.pegawai',
            ])->findOrFail($id);

            $menungguManager = $this->statusId('MENUNGGU_APPROVAL_MANAGER');
            if ((int) $workorder->status_id !== (int) $menungguManager) {
                return response()->json(['error' => 'Status WO belum siap untuk approval manager'], 422);
            }

            $workorder->update([
                'status_id' => $this->statusId('SELESAI'),
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $validated['approval_notes'] ?? null,
            ]);

            $approveActionId = $this->actionId('APPROVE');
            if ($approveActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id' => $approveActionId,
                    'actor_id' => $request->user()->id,
                    'keterangan' => 'Manager approve final workorder',
                    'waktu_mulai' => now(),
                    'estimasi_selesai' => optional($workorder->workorderAssignment)->estimasi_selesai,
                ]);
            }

            $reportNo = sprintf('LAP-WO-%s-%04d', now()->format('Y'), $workorder->id);
            $snapshot = $this->resolveKategoriSnapshot($workorder);
            $petugasSnapshot = $workorder->assignmentMembers->map(function ($member) {
                $u = $member->user;
                return [
                    'user_id' => optional($u)->id,
                    'nama' => optional(optional($u)->pegawai)->nama,
                    'nip' => optional(optional($u)->pegawai)->nip,
                ];
            })->values()->all();

            LaporanWorkorder::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'nomor_laporan' => $reportNo,
                    'tanggal_terbit' => now(),
                    'ringkasan_pekerjaan' => $workorder->deskripsi ?? $workorder->nama_workorder,
                    'hasil_akhir_snapshot' => $snapshot,
                    'petugas_snapshot' => $petugasSnapshot,
                    'catatan_manager' => $validated['approval_notes'] ?? null,
                    'issued_by_user_id' => $workorder->assigned_to,
                    'approved_by_user_id' => $request->user()->id,
                    'approved_at' => now(),
                ]
            );

            DB::commit();
            return response()->json(['message' => 'Workorder berhasil di-approve manager'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'required|string',
        ]);

        if ((int) $request->user()->role_id !== 2) {
            return response()->json(['error' => 'Hanya manager yang bisa reject'], 403);
        }

        DB::beginTransaction();
        try {
            $workorder = Workorder::findOrFail($id);
            $menungguManager = $this->statusId('MENUNGGU_APPROVAL_MANAGER');
            if ((int) $workorder->status_id !== (int) $menungguManager) {
                return response()->json(['error' => 'Status WO belum siap untuk reject manager'], 422);
            }

            $workorder->update([
                'status_id' => $this->statusId('DITOLAK_MANAGER'),
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $validated['approval_notes'],
            ]);

            $rejectActionId = $this->actionId('REJECT');
            if ($rejectActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id' => $rejectActionId,
                    'actor_id' => $request->user()->id,
                    'keterangan' => 'Manager reject final workorder',
                    'waktu_mulai' => now(),
                    'estimasi_selesai' => optional($workorder->workorderAssignment)->estimasi_selesai,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Workorder berhasil di-reject manager'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function resolveKategoriSnapshot(Workorder $workorder): array
    {
        $kategori = optional($workorder->jenisWorkorder)->kategori_form;
        if ($kategori === 'meter') {
            return optional($workorder->woMeter)->toArray() ?? [];
        }
        if ($kategori === 'jaringan') {
            return optional($workorder->woJaringan)->toArray() ?? [];
        }
        if ($kategori === 'infrastruktur') {
            return optional($workorder->woInfrastruktur)->toArray() ?? [];
        }

        return [];
    }
}
