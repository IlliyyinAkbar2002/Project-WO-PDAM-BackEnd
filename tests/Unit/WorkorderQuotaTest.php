<?php

namespace Tests\Unit;

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
use App\Support\WorkorderQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Menguji semantik WorkorderQuota::countableSubmissions(): hanya laporan PROGRESS
 * yang dihitung terhadap kuota & progress %. MULAI/SELESAI (transisi status) dan
 * REVISI/DITOLAK (baris sistem) tidak boleh ikut terhitung — meskipun baris
 * SELESAI/REVISI/DITOLAK memiliki waktu_submit (filter tipe positif yang mengecualikan,
 * bukan sekadar whereNotNull).
 */
class WorkorderQuotaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
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
        $this->user = User::factory()->create(['role_id' => $role->id, 'pegawai_id' => $pegawai->id]);

        $this->workorder = Workorder::create([
            'nama_workorder' => 'Test WO',
            'status_id' => Status::where('kode', 'IN_PROGRESS')->value('id'),
            'assigned_to' => $this->user->id,
            'tanggal_mulai' => now()->toDateString(),
            'jenis_workorder_id' => $jenisWo->id,
        ]);

        $assignment = WorkorderAssignment::create([
            'workorder_id' => $this->workorder->id,
            'spv_user_id' => $this->user->id,
            'assigned_at' => now(),
            'tanggal_mulai' => now()->toDateString(),
            'estimasi_selesai' => now()->toDateString(), // 1 hari → kuota 8
        ]);

        WoAssignmentMember::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->user->id,
            'is_pic' => true,
        ]);
    }

    /**
     * Buat 1 baris progress untuk user/WO ini dengan tipe & waktu_submit tertentu.
     */
    private function makeProgress(string $tipeKode, $waktuSubmit, int $order): void
    {
        ProgressWorkorder::create([
            'workorder_id' => $this->workorder->id,
            'tipe_progress_id' => TipeProgress::where('kode', $tipeKode)->value('id'),
            'status_id' => Status::where('kode', 'SUBMITTED')->value('id'),
            'submitted_by_user_id' => $this->user->id,
            'hasil_pengerjaan' => "Row {$tipeKode} #{$order}",
            'waktu_submit' => $waktuSubmit,
            'order' => $order,
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    private function countable(): int
    {
        return WorkorderQuota::countableSubmissions($this->workorder->id, $this->user->id)->count();
    }

    public function test_mulai_only_counts_as_zero(): void
    {
        $this->makeProgress('MULAI', now(), 1);

        $this->assertSame(0, $this->countable());
    }

    public function test_mulai_plus_five_progress_counts_as_five(): void
    {
        $this->makeProgress('MULAI', now(), 1);
        for ($i = 0; $i < 5; $i++) {
            $this->makeProgress('PROGRESS', now(), $i + 2);
        }

        $this->assertSame(5, $this->countable());
    }

    public function test_eight_progress_plus_selesai_counts_as_eight(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->makeProgress('PROGRESS', now(), $i + 1);
        }
        // SELESAI punya waktu_submit (transisi status, bukan laporan) — harus dikecualikan.
        $this->makeProgress('SELESAI', now(), 9);

        $this->assertSame(8, $this->countable());
    }

    public function test_revisi_and_ditolak_rows_are_not_counted(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->makeProgress('PROGRESS', now(), $i + 1);
        }
        // Beri waktu_submit non-null supaya yang mengecualikan adalah filter TIPE,
        // bukan whereNotNull. Membuktikan filter positif PROGRESS bekerja.
        $this->makeProgress('REVISI', now(), 8);
        $this->makeProgress('DITOLAK', now(), 9);

        $this->assertSame(7, $this->countable());
    }

    public function test_progress_with_null_waktu_submit_is_not_counted(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeProgress('PROGRESS', null, $i + 1);
        }

        $this->assertSame(0, $this->countable());
    }

    public function test_today_filter_counts_only_today(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeProgress('PROGRESS', now(), $i + 1);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->makeProgress('PROGRESS', now()->subDay(), $i + 4);
        }

        $this->assertSame(5, $this->countable());

        $today = WorkorderQuota::countableSubmissions($this->workorder->id, $this->user->id)
            ->whereDate('waktu_submit', now()->toDateString())
            ->count();
        $this->assertSame(3, $today);
    }
}
