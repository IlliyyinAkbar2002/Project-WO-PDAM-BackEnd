<?php

namespace Tests\Feature;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\ProgressWorkorder;
use App\Models\Role;
use App\Models\Status;
use App\Models\TipeProgress;
use App\Models\User;
use App\Models\WoAssignmentMember;
use App\Models\Workorder;
use App\Models\WorkorderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProgressCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $otherUser;
    private Workorder $workorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        $this->createUsersAndWorkorder();
    }

    private function seedMasterData(): void
    {
        $statuses = [
            ['id' => 7, 'kode' => 'IN_PROGRESS', 'nama' => 'In Progress', 'keterangan' => '', 'aktif' => true],
            ['id' => 10, 'kode' => 'SUBMITTED', 'nama' => 'Submitted', 'keterangan' => '', 'aktif' => true],
            ['id' => 11, 'kode' => 'VERIFIED', 'nama' => 'Verified', 'keterangan' => '', 'aktif' => true],
            ['id' => 13, 'kode' => 'DITUGASKAN_KE_STAFF', 'nama' => 'Ditugaskan ke Staff', 'keterangan' => '', 'aktif' => true],
            ['id' => 5, 'kode' => 'PENGECEKAN', 'nama' => 'Pengecekan', 'keterangan' => '', 'aktif' => true],
            ['id' => 18, 'kode' => 'DIBATALKAN', 'nama' => 'Dibatalkan', 'keterangan' => '', 'aktif' => true],
        ];
        foreach ($statuses as $s) {
            Status::updateOrCreate(['id' => $s['id']], $s);
        }

        $tipes = [
            ['id' => 1, 'kode' => 'MULAI', 'nama' => 'Mulai'],
            ['id' => 2, 'kode' => 'PROGRESS', 'nama' => 'Progress'],
            ['id' => 3, 'kode' => 'SELESAI', 'nama' => 'Selesai'],
        ];
        foreach ($tipes as $t) {
            TipeProgress::updateOrCreate(['id' => $t['id']], $t);
        }
    }

    private function createUsersAndWorkorder(): void
    {
        $role = Role::forceCreate(['id' => 3, 'nama' => 'Staff']);
        $dept = Departemen::forceCreate(['nama' => 'Dept Test']);
        $jabatan = Jabatan::forceCreate(['nama' => 'Staff Test']);
        $jenisWo = \App\Models\JenisWorkorder::factory()->create();

        $pegawai1 = Pegawai::factory()->create(['departemen_id' => $dept->id, 'jabatan_id' => $jabatan->id]);
        $pegawai2 = Pegawai::factory()->create(['departemen_id' => $dept->id, 'jabatan_id' => $jabatan->id]);

        $this->staff = User::factory()->create(['role_id' => $role->id, 'pegawai_id' => $pegawai1->id]);
        $this->otherUser = User::factory()->create(['role_id' => $role->id, 'pegawai_id' => $pegawai2->id]);

        $this->workorder = Workorder::create([
            'nama_workorder' => 'Test WO',
            'status_id' => Status::where('kode', 'IN_PROGRESS')->value('id'),
            'assigned_to' => $this->staff->id,
            'tanggal_mulai' => now()->toDateString(),
            'jenis_workorder_id' => $jenisWo->id,
        ]);

        $assignment = WorkorderAssignment::create([
            'workorder_id' => $this->workorder->id,
            'spv_user_id' => $this->staff->id,
            'assigned_at' => now(),
            'tanggal_mulai' => now()->toDateString(),
            'estimasi_selesai' => now()->addDay()->toDateString(),
        ]);

        WoAssignmentMember::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->staff->id,
        ]);
    }

    private function createProgress(array $overrides = []): ProgressWorkorder
    {
        return ProgressWorkorder::create(array_merge([
            'workorder_id' => $this->workorder->id,
            'tipe_progress_id' => TipeProgress::where('kode', 'PROGRESS')->value('id'),
            'status_id' => Status::where('kode', 'SUBMITTED')->value('id'),
            'submitted_by_user_id' => $this->staff->id,
            'hasil_pengerjaan' => 'Test progress',
            'waktu_submit' => now(),
            'order' => 1,
            'latitude' => -6.2,
            'longitude' => 106.8,
        ], $overrides));
    }

    public function test_staff_can_cancel_within_5_minutes()
    {
        $progress = $this->createProgress(['waktu_submit' => now()->subMinutes(3)]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/progress-workorder/{$progress->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Progress berhasil dibatalkan']);

        $progress->refresh();
        $this->assertNull($progress->waktu_submit);
        $this->assertEquals(Status::where('kode', 'DIBATALKAN')->value('id'), $progress->status_id);
    }

    public function test_staff_cannot_cancel_after_5_minutes()
    {
        $progress = $this->createProgress(['waktu_submit' => now()->subMinutes(6)]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/progress-workorder/{$progress->id}/cancel");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Batas waktu pembatalan (5 menit) telah lewat']);
    }

    public function test_other_user_cannot_cancel()
    {
        $progress = $this->createProgress(['waktu_submit' => now()->subMinute()]);

        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->postJson("/api/v1/progress-workorder/{$progress->id}/cancel");

        $response->assertStatus(403);
    }

    public function test_selesai_bypasses_quota()
    {
        for ($i = 0; $i < 8; $i++) {
            $this->createProgress([
                'waktu_submit' => now(),
                'order' => $i + 1,
            ]);
        }

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/progress-workorder/submit', [
                'workorder_id' => $this->workorder->id,
                'tipe_progress_kode' => 'SELESAI',
                'hasil_pengerjaan' => 'Pekerjaan selesai',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ]);

        $response->assertStatus(201);
    }

    public function test_cancelled_progress_does_not_count_toward_quota()
    {
        for ($i = 0; $i < 7; $i++) {
            $this->createProgress(['waktu_submit' => now(), 'order' => $i + 1]);
        }

        $cancelled = $this->createProgress([
            'waktu_submit' => null,
            'status_id' => Status::where('kode', 'DIBATALKAN')->value('id'),
            'order' => 8,
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/progress-workorder/submit', [
                'workorder_id' => $this->workorder->id,
                'tipe_progress_kode' => 'PROGRESS',
                'hasil_pengerjaan' => 'Laporan ke-8 setelah cancel',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ]);

        $response->assertStatus(201);
    }
}
