<?php

namespace App\Services;

use App\Models\MasterAction;
use App\Models\MasterLocation;
use App\Models\Status;
use App\Models\WoAssignmentMember;
use App\Models\WoInfrastruktur;
use App\Models\WoJaringan;
use App\Models\WoMeter;
use App\Models\Workorder;
use App\Models\WorkorderAssignment;
use Illuminate\Support\Facades\DB;

/**
 * AssignmentService
 *
 * Menangani proses assign WO oleh SPV ke Staff.
 * Dipisahkan dari WorkorderService karena:
 *   - WorkorderService = domain Superadmin (create WO + assign SPV)
 *   - AssignmentService = domain SPV (isi form kategori + assign Staff)
 *
 * Operasi utama:
 *   1. Validasi state WO (harus DITUGASKAN_KE_SPV, belum pernah di-assign)
 *   2. Insert form kategori (wo_meter / wo_jaringan / wo_infrastruktur)
 *   3. Insert workorder_assignment + wo_assignment_member
 *   4. Transisi status WO → DITUGASKAN_KE_STAFF
 *   5. Catat workorder_action (audit trail)
 */
class AssignmentService
{
    // ------------------------------------------------------------------
    // PUBLIC
    // ------------------------------------------------------------------

    /**
     * SPV assign staff ke WO.
     *
     * @param  Workorder  $workorder  WO yang sudah di-load (eager: jenisWorkorder, workorderAssignment.members)
     * @param  array      $data       Payload dari controller (sudah tervalidasi)
     * @param  int        $spvUserId  ID user SPV yang melakukan assign
     * @return WorkorderAssignment
     *
     * @throws \InvalidArgumentException  Jika field wajib form kategori kosong
     * @throws \LogicException            Jika state WO tidak valid untuk assign
     */
    public function assignStaff(Workorder $workorder, array $data, int $spvUserId): WorkorderAssignment
    {
        return DB::transaction(function () use ($workorder, $data, $spvUserId) {
            $this->guardAssignability($workorder, $spvUserId);

            $kategori = optional($workorder->jenisWorkorder)->kategori_form;
            $this->createKategoriForm($kategori, $workorder->id, $data['form_kategori']);

            $locationId = $this->resolveLocation($workorder, $data);

            $assignment = WorkorderAssignment::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'spv_user_id'      => $spvUserId,
                    'assigned_at'      => now(),
                    'deskripsi'        => $data['deskripsi'] ?? null,
                    'tipe_workorder'   => $data['tipe_workorder'] ?? null,
                    'tanggal_mulai'    => $data['tanggal_mulai'] ?? null,
                    'tanggal_selesai'  => $data['tanggal_selesai'] ?? null,
                    'estimasi_selesai' => $data['estimasi_selesai'] ?? null,
                    'latitude'         => $data['latitude'] ?? null,
                    'longitude'        => $data['longitude'] ?? null,
                    'accuracy'         => $data['accuracy'] ?? null,
                    'location_id'      => $locationId,
                ]
            );

            $this->attachMembers($assignment, $data['petugas']);

            $statusStaff = $this->statusIdByKode('DITUGASKAN_KE_STAFF');
            $workorder->update(['status_id' => $statusStaff]);

            $this->recordAction($workorder, $spvUserId);

            return $assignment;
        });
    }

    // ------------------------------------------------------------------
    // GUARD
    // ------------------------------------------------------------------

    /**
     * Validasi bahwa WO bisa di-assign:
     *   - SPV yang assign = SPV yang ditugaskan di WO
     *   - Status WO = DITUGASKAN_KE_SPV
     *   - Belum pernah di-assign ke staff
     *   - kategori_form valid
     */
    private function guardAssignability(Workorder $workorder, int $spvUserId): void
    {
        if ((int) $workorder->assigned_to !== $spvUserId) {
            throw new \LogicException('Hanya SPV assigned yang bisa assign staff');
        }

        $statusSpv = $this->statusIdByKode('DITUGASKAN_KE_SPV');
        if ((int) $workorder->status_id !== (int) $statusSpv) {
            throw new \LogicException('WO bukan pada status DITUGASKAN_KE_SPV');
        }

        if ($workorder->workorderAssignment && $workorder->workorderAssignment->members()->exists()) {
            throw new \LogicException('WO sudah pernah di-assign ke staff');
        }

        $kategori = optional($workorder->jenisWorkorder)->kategori_form;
        if (! in_array($kategori, ['meter', 'jaringan', 'infrastruktur'], true)) {
            throw new \LogicException('kategori_form belum valid pada jenis workorder');
        }
    }

    // ------------------------------------------------------------------
    // FORM KATEGORI
    // ------------------------------------------------------------------

    private function createKategoriForm(string $kategori, int $workorderId, array $payload): void
    {
        match ($kategori) {
            'meter'         => $this->createMeterForm($workorderId, $payload),
            'jaringan'      => $this->createJaringanForm($workorderId, $payload),
            'infrastruktur' => $this->createInfrastrukturForm($workorderId, $payload),
        };
    }

    private function createMeterForm(int $workorderId, array $payload): void
    {
        $this->ensureRequiredFields($payload, ['nomor_meter']);
        WoMeter::create([
            'workorder_id'       => $workorderId,
            'nomor_meter'        => $payload['nomor_meter'],
            'kondisi_meter_awal' => $payload['kondisi_meter_awal'] ?? null,
            'kondisi_meter_akhir'=> $payload['kondisi_meter_akhir'] ?? null,
            'hasil_kalibrasi'    => $payload['hasil_kalibrasi'] ?? null,
        ]);
    }

    private function createJaringanForm(int $workorderId, array $payload): void
    {
        $this->ensureRequiredFields($payload, ['jenis_pipa']);
        WoJaringan::create([
            'workorder_id'       => $workorderId,
            'jenis_pipa'         => $payload['jenis_pipa'],
            'diameter_pipa'      => $payload['diameter_pipa'] ?? null,
            'panjang_pipa'       => $payload['panjang_pipa'] ?? null,
            'tingkat_kerusakan'  => $payload['tingkat_kerusakan'] ?? null,
            'tindakan_perbaikan' => $payload['tindakan_perbaikan'] ?? null,
            'hasil_inspeksi'     => $payload['hasil_inspeksi'] ?? null,
        ]);
    }

    private function createInfrastrukturForm(int $workorderId, array $payload): void
    {
        $this->ensureRequiredFields($payload, ['nama_aset', 'jenis_aset']);
        WoInfrastruktur::create([
            'workorder_id'          => $workorderId,
            'nama_aset'             => $payload['nama_aset'],
            'jenis_aset'            => $payload['jenis_aset'],
            'kapasitas'             => $payload['kapasitas'] ?? null,
            'kondisi_awal'          => $payload['kondisi_awal'] ?? null,
            'kondisi_akhir'         => $payload['kondisi_akhir'] ?? null,
            'jadwal_pemeliharaan'   => $payload['jadwal_pemeliharaan'] ?? null,
            'tindakan'              => $payload['tindakan'] ?? null,
        ]);
    }

    // ------------------------------------------------------------------
    // MEMBERS
    // ------------------------------------------------------------------

    private function attachMembers(WorkorderAssignment $assignment, array $petugasList): void
    {
        foreach ($petugasList as $staff) {
            WoAssignmentMember::create([
                'assignment_id' => $assignment->id,
                'user_id'       => (int) $staff['user_id'],
                'is_pic'        => ($staff['peran'] ?? null) === 'koordinator',
            ]);
        }
    }

    // ------------------------------------------------------------------
    // LOCATION
    // ------------------------------------------------------------------

    /**
     * Auto-create m_location dari lat/lng jika dikirim FE.
     */
    private function resolveLocation(Workorder $workorder, array $data): ?int
    {
        if (empty($data['latitude']) || empty($data['longitude'])) {
            return null;
        }

        $location = MasterLocation::create([
            'nama'         => $workorder->lokasi ?? $workorder->nama_workorder,
            'latitude'     => $data['latitude'],
            'longitude'    => $data['longitude'],
            'radius_meter' => 100,
        ]);

        return $location->id;
    }

    // ------------------------------------------------------------------
    // AUDIT TRAIL
    // ------------------------------------------------------------------

    private function recordAction(Workorder $workorder, int $actorId): void
    {
        $penugasanActionId = MasterAction::where('kode', 'PENUGASAN')->value('id');
        if (! $penugasanActionId) {
            return;
        }

        (new WorkorderActionService())->createAction([
            'workorder_id'     => $workorder->id,
            'action_id'        => $penugasanActionId,
            'actor_id'         => $actorId,
            'keterangan'       => 'SPV mengisi form kategori dan assign staff',
            'waktu_mulai'      => now(),
            'estimasi_selesai' => $workorder->workorderAssignment?->estimasi_selesai,
        ]);
    }

    // ------------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------------

    private function statusIdByKode(string $kode): ?int
    {
        return Status::where('kode', $kode)->value('id');
    }

    private function ensureRequiredFields(array $payload, array $required): void
    {
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                throw new \InvalidArgumentException("Field {$field} wajib diisi");
            }
        }
    }
}
