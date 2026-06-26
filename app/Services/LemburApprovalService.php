<?php

namespace App\Services;

use App\Models\LemburSpl;
use App\Models\User;
use App\Models\WoAssignmentMember;
use App\Models\WorkorderAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LemburApprovalService
{
    /**
     * Approve pengajuan lembur → tugaskan anggota lembur sebagai staff WO.
     *
     * Adaptasi versi golden ke skema target (ENUM + m_pegawai), acuan AssignmentService:
     * - spv_pegawai_id = workorder.assigned_to (FK m_pegawai), BUKAN pemohon_id (users.id).
     * - wo_assignment_member.pegawai_id: anggota lembur tersimpan sebagai users.id di
     *   lembur_spl_member.user_id → dipetakan ke pegawai_id lewat users.pegawai_id.
     * - TIDAK menyentuh status WO (gate Pending→Proses ditangani controller) dan
     *   TIDAK mencatat WorkorderAction (di-drop, mengikuti pola AssignmentService).
     * - Notifikasi staff TIDAK dikirim di sini (controller sudah emit wo_lembur_approved
     *   ke anggota saat approve) untuk menghindari notifikasi ganda.
     *
     * Idempoten: kalau WO sudah punya assignment beranggota, kembalikan apa adanya.
     *
     * @param  LemburSpl  $lembur  Idealnya sudah ter-load relasi `workorder` & `members`.
     *
     * @throws \LogicException  Jika lembur tak terhubung WO, atau anggota tanpa pegawai.
     */
    public function approveAndAssign(LemburSpl $lembur): WorkorderAssignment
    {
        return DB::transaction(function () use ($lembur) {
            $lembur->loadMissing('workorder.workorderAssignment', 'members');
            $workorder = $lembur->workorder;

            if (! $workorder) {
                throw new \LogicException('Pengajuan lembur tidak terhubung ke work order manapun.');
            }

            // Idempoten: jangan duplikasi assignment kalau sudah ter-assign beranggota.
            if ($workorder->workorderAssignment && $workorder->workorderAssignment->members()->exists()) {
                return $workorder->workorderAssignment;
            }

            [$tanggalMulai, $estimasiSelesai] = $this->resolveTimeline($lembur);

            if (!$workorder->assigned_to) {
                throw new \LogicException(
                    "Workorder belum memiliki SPV yang ditugaskan."
                );
            }

            $assignment = WorkorderAssignment::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'spv_pegawai_id'   => $workorder->assigned_to,
                    'assigned_at'      => now(),
                    'deskripsi'        => $lembur->alasan_lembur,
                    'tanggal_mulai'    => $tanggalMulai,
                    'tanggal_selesai'  => $estimasiSelesai,
                    'estimasi_selesai' => $estimasiSelesai,
                    'latitude'         => $lembur->latitude,
                    'longitude'        => $lembur->longitude,
                    'location_id'      => $lembur->location_id,
                ]
            );

            $this->attachMembers($assignment, $lembur);

            return $assignment;
        });
    }

    /**
     * 
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
     * Salin anggota lembur → wo_assignment_member.
     *
     * lembur_spl_member.user_id (users.id) dipetakan ke pegawai_id (m_pegawai)
     * via users.pegawai_id. Anggota pertama (urut id) jadi PIC/koordinator.
     */
    private function attachMembers(WorkorderAssignment $assignment, LemburSpl $lembur): void
    {
        $members = $lembur->members->sortBy('id')->values();

        $userIds    = $members->pluck('user_id')->filter()->unique();
        $pegawaiMap = User::whereIn('id', $userIds)->pluck('pegawai_id', 'id');

        foreach ($members as $index => $member) {
            $pegawaiId = $pegawaiMap[$member->user_id] ?? null;
            if (! $pegawaiId) {
                throw new \LogicException("Anggota lembur (user #{$member->user_id}) tidak terhubung ke data pegawai.");
            }

            WoAssignmentMember::updateOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'pegawai_id'    => (int) $pegawaiId,
                ],
                ['is_pic' => $index === 0],
            );
        }
    }
}
