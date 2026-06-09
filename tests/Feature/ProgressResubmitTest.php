<?php

namespace Tests\Feature;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\JenisWorkorder;
use App\Models\Pegawai;
use App\Models\ProgressDetail;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integration test untuk endpoint resubmit (perkaya payload penuh).
 *
 * Memastikan:
 * - Resubmit baris SELESAI yang sedang REVISI_REQUESTED mengembalikan WO ke
 *   PENGECEKAN, menyimpan field kategori hasil akhir, dan meng-append foto baru.
 * - Resubmit baris PROGRESS mengembalikan WO ke IN_PROGRESS.
 * - Guard: progres berstatus DITOLAK_SPV (terminal) TIDAK bisa diresubmit (422).
 * - Non-anggota tim ditolak (403).
 */
class ProgressResubmitTest extends TestCase
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
            'nama_workorder' => 'WO Resubmit Test',
            'status_id' => Status::where('kode', 'REVISI_REQUESTED')->value('id'),
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

    private function createProgress(string $tipeKode, string $statusKode): ProgressWorkorder
    {
        return ProgressWorkorder::create([
            'workorder_id' => $this->workorder->id,
            'tipe_progress_id' => TipeProgress::where('kode', $tipeKode)->value('id'),
            'status_id' => Status::where('kode', $statusKode)->value('id'),
            'submitted_by_user_id' => $this->pic->id,
            'hasil_pengerjaan' => 'Hasil lama',
            'waktu_submit' => now(),
            'order' => 1,
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    public function test_resubmit_selesai_returns_wo_to_pengecekan_and_appends_foto(): void
    {
        Storage::fake('public');

        $progress = $this->createProgress('SELESAI', 'REVISI_REQUESTED');
        $progress->dokumentasiProgress()->create(['url' => 'dokumentasi_progress/old.jpg', 'jenis' => 'HASIL_KERJA']);

        $response = $this->actingAs($this->pic, 'sanctum')
            ->post('/api/v1/progress-workorder/resubmit', [
                'progress_id' => $progress->id,
                'hasil_pengerjaan' => 'Sudah diperbaiki sesuai revisi',
                'latitude' => -7.27,
                'longitude' => 112.56,
                'kondisi_akhir' => 'Baik setelah perbaikan',
                'jadwal_pemeliharaan' => '2026-07-01',
                'tindakan' => 'Penggantian komponen',
                'foto' => [UploadedFile::fake()->create('baru.jpg', 100, 'image/jpeg')],
            ], ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $this->workorder->refresh();
        $progress->refresh();

        $this->assertSame(Status::where('kode', 'PENGECEKAN')->value('id'), $this->workorder->status_id);
        $this->assertSame(Status::where('kode', 'SUBMITTED')->value('id'), $progress->status_id);
        $this->assertSame('Sudah diperbaiki sesuai revisi', $progress->hasil_pengerjaan);
        // Submitter asli tidak boleh berubah.
        $this->assertSame($this->pic->id, (int) $progress->submitted_by_user_id);
        // Foto baru di-append (lama + baru = 2).
        $this->assertSame(2, $progress->dokumentasiProgress()->count());
        // Field kategori hasil akhir tersimpan.
        $this->assertSame('Baik setelah perbaikan', WoInfrastruktur::where('workorder_id', $this->workorder->id)->value('kondisi_akhir'));
        // Siklus review baru dibuat.
        $this->assertSame('pending', ProgressDetail::where('progress_workorder_id', $progress->id)->latest('id')->value('status'));
    }

    public function test_resubmit_progress_returns_wo_to_in_progress(): void
    {
        $progress = $this->createProgress('PROGRESS', 'REVISI_REQUESTED');

        $response = $this->actingAs($this->pic, 'sanctum')
            ->postJson('/api/v1/progress-workorder/resubmit', [
                'progress_id' => $progress->id,
                'hasil_pengerjaan' => 'Progres diperbaiki',
                'latitude' => -7.27,
                'longitude' => 112.56,
            ]);

        $response->assertStatus(200);

        $this->workorder->refresh();
        $progress->refresh();

        $this->assertSame(Status::where('kode', 'IN_PROGRESS')->value('id'), $this->workorder->status_id);
        $this->assertSame(Status::where('kode', 'SUBMITTED')->value('id'), $progress->status_id);
    }

    public function test_resubmit_on_ditolak_spv_is_blocked_422(): void
    {
        $progress = $this->createProgress('SELESAI', 'DITOLAK_SPV');

        $response = $this->actingAs($this->pic, 'sanctum')
            ->postJson('/api/v1/progress-workorder/resubmit', [
                'progress_id' => $progress->id,
                'hasil_pengerjaan' => 'Coba hidupkan WO yang sudah ditolak final',
                'latitude' => -7.27,
                'longitude' => 112.56,
                'kondisi_akhir' => 'x',
                'jadwal_pemeliharaan' => '2026-07-01',
                'tindakan' => 'x',
            ]);

        $response->assertStatus(422);

        $progress->refresh();
        $this->assertSame(Status::where('kode', 'DITOLAK_SPV')->value('id'), $progress->status_id);
    }

    public function test_resubmit_by_non_member_is_forbidden_403(): void
    {
        $outsiderPegawai = Pegawai::factory()->create([
            'departemen_id' => $this->dept->id,
            'jabatan_id' => $this->jabatanSenior->id,
        ]);
        $outsider = User::factory()->create(['role_id' => 3, 'pegawai_id' => $outsiderPegawai->id]);

        $progress = $this->createProgress('PROGRESS', 'REVISI_REQUESTED');

        $response = $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/progress-workorder/resubmit', [
                'progress_id' => $progress->id,
                'hasil_pengerjaan' => 'Bukan tim',
                'latitude' => -7.27,
                'longitude' => 112.56,
            ]);

        $response->assertStatus(403);
    }
}
