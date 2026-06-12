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
use Tests\TestCase;

/**
 * Integration test untuk submit progress harian.
 * Kuota/limit pelaporan sudah dihapus sehingga submission ke-9+ tetap sukses (201).
 */
class ProgressSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private Workorder $workorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        $this->createUserAndWorkorder();
    }

    private function seedMasterData(): void
    {
        $statuses = [
            ['id' => 7, 'kode' => 'IN_PROGRESS', 'nama' => 'In Progress', 'keterangan' => '', 'aktif' => true],
            ['id' => 10, 'kode' => 'SUBMITTED', 'nama' => 'Submitted', 'keterangan' => '', 'aktif' => true],
            ['id' => 13, 'kode' => 'DITUGASKAN_KE_STAFF', 'nama' => 'Ditugaskan ke Staff', 'keterangan' => '', 'aktif' => true],
            ['id' => 5, 'kode' => 'PENGECEKAN', 'nama' => 'Pengecekan', 'keterangan' => '', 'aktif' => true],
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

    private function createUserAndWorkorder(): void
    {
        $role = Role::forceCreate(['id' => 3, 'nama' => 'Staff']);
        $dept = Departemen::forceCreate(['nama' => 'Dept Test']);
        $jabatan = Jabatan::forceCreate(['nama' => 'Staff Test']);
        $jenisWo = \App\Models\JenisWorkorder::factory()->create();

        $pegawai = Pegawai::factory()->create(['departemen_id' => $dept->id, 'jabatan_id' => $jabatan->id]);
        $this->staff = User::factory()->create(['role_id' => $role->id, 'pegawai_id' => $pegawai->id]);

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
            'estimasi_selesai' => now()->toDateString(), // 1 hari → kuota total 8
        ]);

        WoAssignmentMember::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->staff->id,
            'is_pic' => true,
        ]);
    }

    private function seedProgressRows(string $tipeKode, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            ProgressWorkorder::create([
                'workorder_id' => $this->workorder->id,
                'tipe_progress_id' => TipeProgress::where('kode', $tipeKode)->value('id'),
                'status_id' => Status::where('kode', 'SUBMITTED')->value('id'),
                'submitted_by_user_id' => $this->staff->id,
                'hasil_pengerjaan' => "Seeded {$tipeKode} #{$i}",
                'waktu_submit' => now(),
                'order' => $i + 1,
                'latitude' => -6.2,
                'longitude' => 106.8,
            ]);
        }
    }

    public function test_ninth_progress_in_one_day_is_accepted_201(): void
    {
        $this->seedProgressRows('PROGRESS', 8);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/progress-workorder/submit', [
                'workorder_id' => $this->workorder->id,
                'tipe_progress_kode' => 'PROGRESS',
                'hasil_pengerjaan' => 'Laporan ke-9 dalam sehari',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ]);

        $response->assertStatus(201);
    }

    public function test_eighth_progress_in_one_day_is_accepted(): void
    {
        $this->seedProgressRows('PROGRESS', 7);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/progress-workorder/submit', [
                'workorder_id' => $this->workorder->id,
                'tipe_progress_kode' => 'PROGRESS',
                'hasil_pengerjaan' => 'Laporan ke-8 dalam sehari',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ]);

        $response->assertStatus(201);
    }
}
