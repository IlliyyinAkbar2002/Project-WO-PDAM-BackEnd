<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Pegawai;
use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\JenisWorkorder;
use App\Models\JenisLokasi;
use App\Models\TipeWorkorder;
use App\Models\MasterLocation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkorderCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Superadmin can create a Work Order for an SPV.
     */
    public function test_superadmin_can_create_workorder_for_spv()
    {
        // 1. Setup Roles (Assuming role_id 1 = Superadmin, 2 = SPV)
        $superadminRole = Role::create(['id' => 1, 'nama' => 'Superadmin']);
        $spvRole = Role::create(['id' => 2, 'nama' => 'SPV']);

        // 2. Setup Master Data for Pegawai
        $dept = Departemen::factory()->create();
        $jabatan = Jabatan::factory()->create();

        // 3. Create Pegawai first to avoid FK violation
        $superadminPegawai = Pegawai::factory()->create([
            'departemen_id' => $dept->id,
            'jabatan_id' => $jabatan->id,
        ]);
        $spvPegawai = Pegawai::factory()->create([
            'departemen_id' => $dept->id,
            'jabatan_id' => $jabatan->id,
        ]);

        // 4. Create Users
        $superadmin = User::factory()->create([
            'role_id' => $superadminRole->id,
            'pegawai_id' => $superadminPegawai->id,
            'email' => 'superadmin@example.com',
        ]);

        $spv = User::factory()->create([
            'role_id' => $spvRole->id,
            'pegawai_id' => $spvPegawai->id,
            'email' => 'spv@example.com',
        ]);

        // 5. Create required master data
        $jenisWo = JenisWorkorder::factory()->create();
        $jenisLokasi = JenisLokasi::factory()->create();
        $tipeWo = TipeWorkorder::factory()->create();
        $location = MasterLocation::factory()->create();
        $status = Status::factory()->create();

        // 6. Prepare payload based on WorkorderController@store validation
        $payload = [
            'nama_workorder' => 'Test WO by Superadmin',
            'deskripsi' => 'This is a test work order created by superadmin for SPV',
            'tanggal_mulai' => now()->toDateString(),
            'estimasi_durasi' => 2,
            'unit_waktu' => 'jam',
            'estimasi_selesai' => now()->addDays(1)->toDateString(),
            'location_id' => $location->id,
            'jenis_workorder_id' => $jenisWo->id,
            'jenis_lokasi_id' => $jenisLokasi->id,
            'tipe_workorder_id' => $tipeWo->id,
            'assigned_to' => $spv->id, // Assigned to SPV
            'status_id' => $status->id,
            'prioritas' => 'sedang',
            'petugas_id' => [$spv->id], // Including SPV as staff
        ];

        // 6. Act: Authenticate as Superadmin and call the API
        $response = $this->actingAs($superadmin, 'sanctum')
                         ->postJson('/api/v1/workorder', $payload);

        // Debugging: print response if not 201
        if ($response->status() !== 201) {
            dump($response->json());
        }

        // 7. Assert
        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Work Order berhasil disimpan',
                 ]);

        $this->assertDatabaseHas('workorder', [
            'nama_workorder' => 'Test WO by Superadmin',
            'assigned_to' => $spv->id,
            'created_by_user_id' => $superadmin->id,
        ]);
    }
}
