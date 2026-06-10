<?php

namespace Tests\Feature;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\JenisWorkorder;
use App\Models\LemburSpl;
use App\Models\MasterAction;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Models\WoAssignmentMember;
use App\Models\Workorder;
use App\Models\WorkorderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration test untuk parity Pengajuan Lembur — memastikan submission +
 * approval mengisi workorder_assignment selengkap alur Assign normal:
 * prioritas, lokasi, koordinat (lat/lng/accuracy), location_id, dan koordinator.
 *
 * Mengunci kontrak yang diserahkan ke FE:
 *  - Payload penuh → assignment terisi + koordinator (is_pic) sesuai pilihan SPV.
 *  - Payload lama (tanpa field baru) → assignment coords/location NULL, tanpa
 *    fabrikasi; PIC fallback ke anggota id terkecil.
 *  - koordinator_user_id di luar anggota → 422.
 *  - REJECT → workorder TIDAK tersentuh (prioritas/lokasi tetap, tanpa assignment).
 */
class LemburAssignmentParityTest extends TestCase
{
    use RefreshDatabase;

    private User $spv;
    private User $staffA;   // id lebih kecil
    private User $staffB;   // id lebih besar
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
            ['id' => 1,  'kode' => 'BELUM_DISETUJUI',     'nama' => 'Belum disetujui', 'keterangan' => '', 'aktif' => true],
            ['id' => 2,  'kode' => 'DISETUJUI',           'nama' => 'Disetujui',       'keterangan' => '', 'aktif' => true],
            ['id' => 12, 'kode' => 'DITUGASKAN_KE_SPV',   'nama' => 'Ditugaskan ke SPV',   'keterangan' => '', 'aktif' => true],
            ['id' => 13, 'kode' => 'DITUGASKAN_KE_STAFF', 'nama' => 'Ditugaskan ke Staff', 'keterangan' => '', 'aktif' => true],
            ['id' => 16, 'kode' => 'DITOLAK',             'nama' => 'Ditolak',         'keterangan' => '', 'aktif' => true],
        ];
        foreach ($statuses as $s) {
            Status::updateOrCreate(['id' => $s['id']], $s);
        }

        MasterAction::updateOrCreate(['kode' => 'PENUGASAN'], ['nama' => 'Penugasan', 'keterangan' => '']);
    }

    private function createUsersAndWorkorder(): void
    {
        Role::forceCreate(['id' => 3, 'nama' => 'Employee']);
        $dept = Departemen::forceCreate(['nama' => 'Dept Test']);
        $jabatan = Jabatan::forceCreate(['id' => 3, 'kode' => 'SENIOR_STAFF', 'nama' => 'Senior Staff']);

        $mkUser = function () use ($dept, $jabatan) {
            $pegawai = Pegawai::factory()->create([
                'departemen_id' => $dept->id,
                'jabatan_id'    => $jabatan->id,
            ]);
            return User::factory()->create(['role_id' => 3, 'pegawai_id' => $pegawai->id]);
        };

        $this->spv    = $mkUser();
        $this->staffA = $mkUser();
        $this->staffB = $mkUser();

        $jenis = JenisWorkorder::create([
            'nama'          => 'Perbaikan Pipa Bocor Test',
            'kategori_form' => 'jaringan',
        ]);

        $this->workorder = Workorder::create([
            'nama_workorder'     => 'WO Lembur Parity Test',
            'status_id'          => Status::where('kode', 'DITUGASKAN_KE_SPV')->value('id'),
            'assigned_to'        => $this->spv->id,
            'tanggal_mulai'      => now()->toDateString(),
            'jenis_workorder_id' => $jenis->id,
            'lokasi'             => 'Lokasi Awal WO',
            'prioritas'          => 'rendah',
        ]);
    }

    /** Approve pengajuan lembur lewat endpoint update (web flow). */
    private function approve(LemburSpl $lembur): void
    {
        $this->actingAs($this->spv, 'sanctum')
            ->putJson("/api/v1/lembur-spl/{$lembur->id}", [
                'verifikator_id' => $this->spv->id,
                'status_id' => Status::where('kode', 'DISETUJUI')->value('id'),
            ])->assertStatus(200);
    }

    public function test_full_payload_populates_assignment_and_koordinator(): void
    {
        $response = $this->actingAs($this->spv, 'sanctum')
            ->postJson('/api/v1/lembur-spl', [
                'workorder_id'        => $this->workorder->id,
                'judul_pekerjaan'     => 'Lembur Pipa',
                'jenis_pekerjaan'     => 'Emergency Maintainance',
                'tanggal_lembur'      => now()->toDateString(),
                'jam_mulai'           => '16:00',
                'estimasi_jam'        => 5,
                'alasan_lembur'       => 'Pipa bocor mendesak',
                'members'             => [$this->staffA->id, $this->staffB->id],
                'prioritas'           => 'darurat',
                'lokasi'              => 'Jl. Sudirman No. 1',
                'latitude'            => -6.2000000,
                'longitude'           => 106.8166667,
                'accuracy'            => 12.5,
                'koordinator_user_id' => $this->staffB->id,
            ]);

        $response->assertStatus(201);
        $lembur = LemburSpl::firstOrFail();

        $this->approve($lembur);

        $assignment = WorkorderAssignment::where('workorder_id', $this->workorder->id)->firstOrFail();
        $this->assertEqualsWithDelta(-6.2, (float) $assignment->latitude, 0.0000001);
        $this->assertEqualsWithDelta(106.8166667, (float) $assignment->longitude, 0.0000001);
        $this->assertEqualsWithDelta(12.5, (float) $assignment->accuracy, 0.0001);
        $this->assertNotNull($assignment->location_id);

        // m_location.nama mengikuti lokasi yang disubmit (sudah di-update ke WO dulu).
        $this->assertSame('Jl. Sudirman No. 1', $assignment->location->nama);

        // WO menerima prioritas & lokasi dari pengajuan.
        $this->workorder->refresh();
        $this->assertSame('darurat', $this->workorder->prioritas);
        $this->assertSame('Jl. Sudirman No. 1', $this->workorder->lokasi);

        // Koordinator yang ditunjuk SPV (staffB) menjadi PIC — bukan id terkecil.
        $pic = WoAssignmentMember::where('assignment_id', $assignment->id)->where('is_pic', true)->firstOrFail();
        $this->assertSame($this->staffB->id, (int) $pic->user_id);
        $this->assertSame(
            1,
            WoAssignmentMember::where('assignment_id', $assignment->id)->where('is_pic', true)->count(),
            'Hanya satu PIC yang boleh ada'
        );
    }

    public function test_legacy_payload_leaves_coords_null_and_pic_falls_back_to_first_member(): void
    {
        // Submit dengan staffB lebih dulu untuk menegaskan fallback = anggota
        // pertama pada payload (urutan insertion lembur_spl_member), bukan
        // berdasarkan user_id terkecil.
        $this->actingAs($this->spv, 'sanctum')
            ->postJson('/api/v1/lembur-spl', [
                'workorder_id'    => $this->workorder->id,
                'judul_pekerjaan' => 'Lembur Pipa',
                'jenis_pekerjaan' => 'Emergency Maintainance',
                'tanggal_lembur'  => now()->toDateString(),
                'estimasi_jam'    => 5,
                'alasan_lembur'   => 'Pipa bocor mendesak',
                'members'         => [$this->staffB->id, $this->staffA->id],
            ])->assertStatus(201);

        $this->approve(LemburSpl::firstOrFail());

        $assignment = WorkorderAssignment::where('workorder_id', $this->workorder->id)->firstOrFail();
        $this->assertNull($assignment->latitude);
        $this->assertNull($assignment->longitude);
        $this->assertNull($assignment->accuracy);
        $this->assertNull($assignment->location_id);

        // WO tidak berubah (tidak ditimpa null).
        $this->workorder->refresh();
        $this->assertSame('rendah', $this->workorder->prioritas);
        $this->assertSame('Lokasi Awal WO', $this->workorder->lokasi);

        // Fallback PIC = anggota pertama pada payload (staffB).
        $pic = WoAssignmentMember::where('assignment_id', $assignment->id)->where('is_pic', true)->firstOrFail();
        $this->assertSame($this->staffB->id, (int) $pic->user_id);
    }

    public function test_koordinator_outside_members_is_rejected_422(): void
    {
        $this->actingAs($this->spv, 'sanctum')
            ->postJson('/api/v1/lembur-spl', [
                'workorder_id'        => $this->workorder->id,
                'judul_pekerjaan'     => 'Lembur Pipa',
                'jenis_pekerjaan'     => 'Emergency Maintainance',
                'tanggal_lembur'      => now()->toDateString(),
                'estimasi_jam'        => 5,
                'alasan_lembur'       => 'Pipa bocor mendesak',
                'members'             => [$this->staffA->id],
                'koordinator_user_id' => $this->staffB->id, // bukan anggota
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'Koordinator harus salah satu anggota tim yang diajukan.']);

        $this->assertSame(0, LemburSpl::count(), 'Pengajuan tidak boleh tersimpan saat koordinator invalid');
    }

    public function test_rejection_leaves_workorder_untouched(): void
    {
        $this->actingAs($this->spv, 'sanctum')
            ->postJson('/api/v1/lembur-spl', [
                'workorder_id'    => $this->workorder->id,
                'judul_pekerjaan' => 'Lembur Pipa',
                'jenis_pekerjaan' => 'Emergency Maintainance',
                'tanggal_lembur'  => now()->toDateString(),
                'estimasi_jam'    => 5,
                'alasan_lembur'   => 'Pipa bocor mendesak',
                'members'         => [$this->staffA->id, $this->staffB->id],
                'prioritas'       => 'darurat',
                'lokasi'          => 'Jl. Sudirman No. 1',
                'latitude'        => -6.2,
                'longitude'       => 106.8,
            ])->assertStatus(201);

        $lembur = LemburSpl::firstOrFail();

        // REJECT: status DITOLAK.
        $this->actingAs($this->spv, 'sanctum')
            ->putJson("/api/v1/lembur-spl/{$lembur->id}", [
                'verifikator_id' => $this->spv->id,
                'status_id'      => Status::where('kode', 'DITOLAK')->value('id'),
                'alasan_ditolak' => 'Tidak disetujui',
            ])->assertStatus(200);

        // WO sama sekali tidak tersentuh: prioritas/lokasi tetap, tanpa assignment.
        $this->workorder->refresh();
        $this->assertSame('rendah', $this->workorder->prioritas);
        $this->assertSame('Lokasi Awal WO', $this->workorder->lokasi);
        $this->assertSame(
            Status::where('kode', 'DITUGASKAN_KE_SPV')->value('id'),
            $this->workorder->status_id
        );
        $this->assertNull(WorkorderAssignment::where('workorder_id', $this->workorder->id)->first());
    }
}
