<?php

namespace Tests\Feature;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\JenisWorkorder;
use App\Models\Pegawai;
use App\Models\ProgressWorkorder;
use App\Models\Role;
use App\Models\Status;
use App\Models\TipeProgress;
use App\Models\User;
use App\Models\WoAssignmentMember;
use App\Models\WoInfrastruktur;
use App\Models\Workorder;
use App\Models\WorkorderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration test untuk flow penolakan FINAL Work Order.
 *
 * Memastikan keputusan bisnis (reject = final, tidak recoverable):
 * - decision=tolak → WO DITOLAK_SPV dan baris penanda SPV TIDAK berstatus SUBMITTED.
 * - WO DITOLAK_SPV terkunci terminal: start / resubmit / review ditolak (422).
 * - decision=revisi tetap jalan (WO balik IN_PROGRESS) dan baris penanda revisi
 *   TIDAK berstatus SUBMITTED (anti baris "hantu").
 */
class ProgressRejectFinalTest extends TestCase
{
    use RefreshDatabase;

    private User $pic;
    private User $spv;
    private Workorder $workorder;
    private WorkorderAssignment $assignment;
    private Departemen $dept;
    private Jabatan $jabatanSenior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        $this->createUsersAndWorkorder();
    }

    private function seedMasterData(): void
    {
        $statuses = [
            ['id' => 5, 'kode' => 'PENGECEKAN', 'nama' => 'Pengecekan', 'keterangan' => '', 'aktif' => true],
            ['id' => 6, 'kode' => 'SELESAI', 'nama' => 'Selesai', 'keterangan' => '', 'aktif' => true],
            ['id' => 7, 'kode' => 'IN_PROGRESS', 'nama' => 'In Progress', 'keterangan' => '', 'aktif' => true],
            ['id' => 10, 'kode' => 'SUBMITTED', 'nama' => 'Submitted', 'keterangan' => '', 'aktif' => true],
            ['id' => 11, 'kode' => 'VERIFIED', 'nama' => 'Verified', 'keterangan' => '', 'aktif' => true],
            ['id' => 13, 'kode' => 'DITUGASKAN_KE_STAFF', 'nama' => 'Ditugaskan ke Staff', 'keterangan' => '', 'aktif' => true],
            ['id' => 14, 'kode' => 'REVISI_REQUESTED', 'nama' => 'Revisi Requested', 'keterangan' => '', 'aktif' => true],
            ['id' => 15, 'kode' => 'DITOLAK_SPV', 'nama' => 'Ditolak SPV', 'keterangan' => '', 'aktif' => true],
        ];
        foreach ($statuses as $s) {
            Status::updateOrCreate(['id' => $s['id']], $s);
        }

        $tipes = [
            ['id' => 1, 'kode' => 'MULAI', 'nama' => 'Mulai'],
            ['id' => 2, 'kode' => 'PROGRESS', 'nama' => 'Progress'],
            ['id' => 3, 'kode' => 'SELESAI', 'nama' => 'Selesai'],
            ['id' => 4, 'kode' => 'REVISI', 'nama' => 'Revisi'],
            ['id' => 5, 'kode' => 'DITOLAK', 'nama' => 'Ditolak'],
        ];
        foreach ($tipes as $t) {
            TipeProgress::updateOrCreate(['id' => $t['id']], $t);
        }
    }

    private function createUsersAndWorkorder(): void
    {
        Role::forceCreate(['id' => 3, 'nama' => 'Employee']);
        $this->dept = Departemen::forceCreate(['nama' => 'Dept Test']);
        $this->jabatanSenior = Jabatan::forceCreate(['id' => 3, 'kode' => 'SENIOR_STAFF', 'nama' => 'Senior Staff']);

        $jenisInfra = JenisWorkorder::create([
            'nama' => 'Pemeliharaan Reservoir Test',
            'kategori_form' => 'infrastruktur',
        ]);

        $picPegawai = Pegawai::factory()->create([
            'departemen_id' => $this->dept->id,
            'jabatan_id' => $this->jabatanSenior->id,
        ]);
        $this->pic = User::factory()->create(['role_id' => 3, 'pegawai_id' => $picPegawai->id]);

        $spvPegawai = Pegawai::factory()->create([
            'departemen_id' => $this->dept->id,
            'jabatan_id' => $this->jabatanSenior->id,
        ]);
        $this->spv = User::factory()->create(['role_id' => 3, 'pegawai_id' => $spvPegawai->id]);

        $this->workorder = Workorder::create([
            'nama_workorder' => 'WO Reject Final Test',
            'status_id' => Status::where('kode', 'PENGECEKAN')->value('id'),
            'assigned_to' => $this->spv->id,
            'tanggal_mulai' => now()->toDateString(),
            'jenis_workorder_id' => $jenisInfra->id,
        ]);

        $this->assignment = WorkorderAssignment::create([
            'workorder_id' => $this->workorder->id,
            'spv_user_id' => $this->spv->id,
            'assigned_at' => now(),
            'tanggal_mulai' => now()->toDateString(),
            'estimasi_selesai' => now()->addDay()->toDateString(),
        ]);

        WoAssignmentMember::create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->pic->id,
            'is_pic' => true,
        ]);

        WoInfrastruktur::create([
            'workorder_id' => $this->workorder->id,
            'nama_aset' => 'Reservoir A',
            'jenis_aset' => 'reservoir',
        ]);
    }

    private function createProgress(string $tipeKode, string $statusKode, int $order = 1): ProgressWorkorder
    {
        return ProgressWorkorder::create([
            'workorder_id' => $this->workorder->id,
            'tipe_progress_id' => TipeProgress::where('kode', $tipeKode)->value('id'),
            'status_id' => Status::where('kode', $statusKode)->value('id'),
            'submitted_by_user_id' => $this->pic->id,
            'hasil_pengerjaan' => 'Hasil',
            'waktu_submit' => now(),
            'order' => $order,
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    public function test_tolak_sets_wo_final_and_marker_row_not_submitted(): void
    {
        $progress = $this->createProgress('SELESAI', 'SUBMITTED');

        $response = $this->actingAs($this->spv, 'sanctum')
            ->postJson('/api/v1/progress-workorder/review', [
                'progress_id' => $progress->id,
                'decision' => 'tolak',
                'alasan_penolakan' => 'Hasil tidak sesuai standar',
            ]);

        $response->assertStatus(200);

        $this->workorder->refresh();
        $this->assertSame(Status::where('kode', 'DITOLAK_SPV')->value('id'), $this->workorder->status_id);

        // Tidak boleh ada baris "hantu" SUBMITTED dari aksi SPV.
        $submittedId = Status::where('kode', 'SUBMITTED')->value('id');
        $ditolakTipeId = TipeProgress::where('kode', 'DITOLAK')->value('id');
        $markerSubmitted = ProgressWorkorder::where('workorder_id', $this->workorder->id)
            ->where('tipe_progress_id', $ditolakTipeId)
            ->where('status_id', $submittedId)
            ->exists();
        $this->assertFalse($markerSubmitted, 'Baris penanda DITOLAK tidak boleh berstatus SUBMITTED');
    }

    public function test_ditolak_spv_wo_blocks_start_resubmit_review(): void
    {
        $this->workorder->update(['status_id' => Status::where('kode', 'DITOLAK_SPV')->value('id')]);
        $progress = $this->createProgress('SELESAI', 'REVISI_REQUESTED');

        // start ditolak
        $this->actingAs($this->pic, 'sanctum')
            ->postJson('/api/v1/progress-workorder/start', [
                'workorder_id' => $this->workorder->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'nama_aset' => 'Reservoir A',
                'jenis_aset' => 'reservoir',
            ])->assertStatus(422);

        // resubmit ditolak
        $this->actingAs($this->pic, 'sanctum')
            ->postJson('/api/v1/progress-workorder/resubmit', [
                'progress_id' => $progress->id,
                'hasil_pengerjaan' => 'Coba hidupkan WO final',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'kondisi_akhir' => 'x',
                'jadwal_pemeliharaan' => '2026-07-01',
                'tindakan' => 'x',
            ])->assertStatus(422);

        // review ditolak
        $this->actingAs($this->spv, 'sanctum')
            ->postJson('/api/v1/progress-workorder/review', [
                'progress_id' => $progress->id,
                'decision' => 'revisi',
                'alasan_penolakan' => 'x',
            ])->assertStatus(422);

        $this->workorder->refresh();
        $this->assertSame(Status::where('kode', 'DITOLAK_SPV')->value('id'), $this->workorder->status_id);
    }

    public function test_revisi_still_works_and_marker_row_not_submitted(): void
    {
        $progress = $this->createProgress('SELESAI', 'SUBMITTED');

        $response = $this->actingAs($this->spv, 'sanctum')
            ->postJson('/api/v1/progress-workorder/review', [
                'progress_id' => $progress->id,
                'decision' => 'revisi',
                'alasan_penolakan' => 'Perbaiki foto',
            ]);

        $response->assertStatus(200);

        $this->workorder->refresh();
        $this->assertSame(Status::where('kode', 'IN_PROGRESS')->value('id'), $this->workorder->status_id);

        $submittedId = Status::where('kode', 'SUBMITTED')->value('id');
        $revisiTipeId = TipeProgress::where('kode', 'REVISI')->value('id');
        $markerSubmitted = ProgressWorkorder::where('workorder_id', $this->workorder->id)
            ->where('tipe_progress_id', $revisiTipeId)
            ->where('status_id', $submittedId)
            ->exists();
        $this->assertFalse($markerSubmitted, 'Baris penanda REVISI tidak boleh berstatus SUBMITTED');

        // Baris asli yang direvisi siap diresubmit.
        $progress->refresh();
        $this->assertSame(Status::where('kode', 'REVISI_REQUESTED')->value('id'), $progress->status_id);
    }

    public function test_deprecated_progress_detail_reject_returns_410(): void
    {
        $progress = $this->createProgress('SELESAI', 'SUBMITTED');
        $detail = \App\Models\ProgressDetail::create([
            'progress_workorder_id' => $progress->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->spv, 'sanctum')
            ->postJson("/api/v1/progress-detail/{$detail->id}/reject", [
                'alasan_penolakan' => 'x',
            ])->assertStatus(410);
    }
}
