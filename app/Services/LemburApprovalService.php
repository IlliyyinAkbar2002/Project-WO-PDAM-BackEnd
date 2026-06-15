<?php

namespace App\Services;

use App\Models\LemburSpl;
use App\Models\MasterAction;
use App\Models\Status;
use App\Models\User;
use App\Models\WoAssignmentMember;
use App\Models\Workorder;
use App\Models\WorkorderAssignment;
use App\Notifications\WorkOrderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class LemburApprovalService
{
    /**
     * Approve pengajuan lembur dan tugaskan staff ke WO terkait.
     *
     * @param  LemburSpl  $lembur  Sudah ter-load relasi `workorder` & `members`.
     * @return WorkorderAssignment
     *
     * @throws \LogicException  Jika lembur tidak terhubung ke WO atau state tidak valid.
     */
    public function approveAndAssign(LemburSpl $lembur): WorkorderAssignment
    {
        return DB::transaction(function () use ($lembur) {
            $workorder = $lembur->workorder;

            if (! $workorder) {
                throw new \LogicException('Pengajuan lembur tidak terhubung ke work order manapun.');
            }

            if ($workorder->workorderAssignment && $workorder->workorderAssignment->members()->exists()) {
                return $workorder->workorderAssignment;
            }

            [$tanggalMulai, $estimasiSelesai] = $this->resolveTimeline($lembur);

            $assignment = WorkorderAssignment::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'spv_user_id'      => $lembur->pemohon_id ?? $workorder->assigned_to,
                    'assigned_at'      => now(),
                    'deskripsi'        => $lembur->alasan_lembur,
                    'tanggal_mulai'    => $tanggalMulai,
                    'estimasi_selesai' => $estimasiSelesai,
                    'latitude'         => $lembur->latitude,
                    'longitude'        => $lembur->longitude,
                    'location_id'      => $lembur->location_id,
                ]
            );

            $this->attachMembers($assignment, $lembur);

            $statusStaff = Status::where('kode', 'DITUGASKAN_KE_STAFF')->value('id');
            $workorder->update(['status_id' => $statusStaff]);

            $this->recordAction($workorder, $assignment, (int) ($lembur->pemohon_id ?? $workorder->assigned_to));

            $this->notifyStaffAssigned($workorder, $lembur, $assignment);

            return $assignment;
        });
    }

    /**
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveTimeline(LemburSpl $lembur): array
    {
        if (! $lembur->tanggal_lembur) {
            return [null, null];
        }

        $tanggalMulai = Carbon::parse($lembur->tanggal_lembur);
        if ($lembur->jam_mulai) {
            $tanggalMulai->setTimeFromTimeString((string) $lembur->jam_mulai);
        }

        $estimasiSelesai = $lembur->estimasi_jam
            ? $tanggalMulai->copy()->addHours((int) $lembur->estimasi_jam)
            : null;

        return [$tanggalMulai, $estimasiSelesai];
    }

    /**
     */
    private function attachMembers(WorkorderAssignment $assignment, LemburSpl $lembur): void
    {
        $members = $lembur->members->sortBy('id')->values();

        foreach ($members as $index => $member) {
            WoAssignmentMember::updateOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'user_id'       => (int) $member->user_id,
                ],
                ['is_pic' => $index === 0],
            );
        }
    }

    /**
     */
    private function notifyStaffAssigned(Workorder $workorder, LemburSpl $lembur, WorkorderAssignment $assignment): void
    {
        try {
            $spvId = (int) ($lembur->pemohon_id ?? $workorder->assigned_to);
            $spv = $spvId ? User::with('pegawai:id,nama')->find($spvId) : null;
            $senderName = optional($spv?->pegawai)->nama ?? $spv?->name ?? 'SPV';

            $userIds = $assignment->members()
                ->pluck('user_id')
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->unique();

            $staffUsers = User::whereIn('id', $userIds)->get();

            foreach ($staffUsers as $staff) {
                $staff->notify(new WorkOrderNotification(
                    'Tugas Work Order Lembur',
                    "{$senderName} menugaskan Anda pada WO lembur #{$workorder->nama_workorder}.",
                    (int) $workorder->id,
                    'wo_assigned',
                    $senderName
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('LemburApprovalService notifyStaffAssigned failed', [
                'workorder_id'  => $workorder->id,
                'lembur_spl_id' => $lembur->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function recordAction(\App\Models\Workorder $workorder, WorkorderAssignment $assignment, int $actorId): void
    {
        $penugasanActionId = MasterAction::where('kode', 'PENUGASAN')->value('id');
        if (! $penugasanActionId) {
            return;
        }

        (new WorkorderActionService())->createAction([
            'workorder_id'     => $workorder->id,
            'action_id'        => $penugasanActionId,
            'actor_id'         => $actorId,
            'keterangan'       => 'Pengajuan lembur disetujui — staff ditugaskan',
            'waktu_mulai'      => $assignment->tanggal_mulai ?? now(),
            'estimasi_selesai' => $assignment->estimasi_selesai,
        ]);
    }
}
