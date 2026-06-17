<?php

namespace App\Services;

use App\Models\MasterLocation;
use App\Models\User;
use App\Models\WoAssignmentMember;
use App\Models\WoInfrastruktur;
use App\Models\WoJaringan;
use App\Models\WoMeter;
use App\Models\Workorder;
use App\Models\WorkorderAssignment;
use App\Notifications\WorkOrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentService
{
    // Pengganti status_id referensi (DITUGASKAN_KE_STAFF) → enum current.
    private const STATUS_AFTER_ASSIGN = 'Proses';
    private const STATUS_CLOSED = ['Selesai', 'Tutup'];

    public function assignStaff(Workorder $workorder, array $data, User $actor): WorkorderAssignment
    {
        return DB::transaction(function () use ($workorder, $data, $actor) {
            $this->guardAssignability($workorder, $actor);

            // Prioritas: payload FE > jenis_workorder.kategori_form (kolom ini
            // belum ada di current → praktis selalu dari payload).
            $kategori = $data['kategori_form'] ?? optional($workorder->jenisWorkorder)->kategori_form;
            if (! in_array($kategori, ['meter', 'jaringan', 'infrastruktur'], true)) {
                throw new \LogicException('kategori_form tidak valid: ' . ($kategori ?? 'null'));
            }

            $formKategori = $data['form_kategori'] ?? [];
            if (! empty($formKategori)) {
                $this->createKategoriForm($kategori, $workorder->id, $formKategori);
            }

            $locationId = $this->resolveLocation($workorder, $data);

            $assignment = WorkorderAssignment::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'spv_pegawai_id'   => $actor->pegawai_id,
                    'assigned_at'      => now(),
                    'deskripsi'        => $data['deskripsi'] ?? null,
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

            $workorder->update(['status' => self::STATUS_AFTER_ASSIGN]);

            // TODO(audit): referensi catat WorkorderAction kode 'PENUGASAN'.
            //   m_action current belum punya kolom `kode` → di-skip.

            $this->notifyStaffAssigned($workorder, $data['petugas'], $actor);

            return $assignment;
        });
    }

    /**
     * Kirim notifikasi database ke staff yang baru di-assign.
     *
     * Desain current pegawai-based: anggota disimpan sbg pegawai_id, sedangkan
     * Notifiable adalah User. Jadi pegawai_id dipetakan ke User (via
     * users.pegawai_id) lalu User-nya yang di-notify. Pegawai tanpa akun User
     * otomatis terlewati (tidak bisa menerima notif in-app).
     */
    private function notifyStaffAssigned(Workorder $workorder, array $petugasList, User $actor): void
    {
        try {
            $senderName = optional($actor->pegawai)->nama ?? $actor->name ?? 'SPV';

            $pegawaiIds = collect($petugasList)
                ->pluck('pegawai_id')
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->unique();

            $staffUsers = User::whereIn('pegawai_id', $pegawaiIds)->get();

            foreach ($staffUsers as $staff) {
                $staff->notify(new WorkOrderNotification(
                    'Tugas Work Order Baru',
                    "{$senderName} menugaskan Anda pada WO #{$workorder->nama_workorder}.",
                    (int) $workorder->id,
                    'wo_assigned',
                    $senderName
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('notifyStaffAssigned failed', [
                'workorder_id' => $workorder->id,
                'actor_id'     => $actor->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    private function guardAssignability(Workorder $workorder, User $actor): void
    {
        // SPV = user yang pegawai_id-nya == workorder.assigned_to (m_pegawai).
        if ((int) $actor->pegawai_id !== (int) $workorder->assigned_to) {
            throw new \LogicException('Hanya SPV yang ditugaskan yang bisa assign staff');
        }

        if (in_array($workorder->status, self::STATUS_CLOSED, true)) {
            throw new \LogicException('WO sudah selesai/tutup, tidak bisa di-assign');
        }

        if ($workorder->workorderAssignment && $workorder->workorderAssignment->members()->exists()) {
            throw new \LogicException('WO sudah pernah di-assign ke staff');
        }
    }

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
            'workorder_id'        => $workorderId,
            'nomor_meter'         => $payload['nomor_meter'],
            'kondisi_meter_awal'  => $payload['kondisi_meter_awal'] ?? null,
            'kondisi_meter_akhir' => $payload['kondisi_meter_akhir'] ?? null,
            'hasil_kalibrasi'     => $payload['hasil_kalibrasi'] ?? null,
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
            'workorder_id'        => $workorderId,
            'nama_aset'           => $payload['nama_aset'],
            'jenis_aset'          => $payload['jenis_aset'],
            'kapasitas'           => $payload['kapasitas'] ?? null,
            'kondisi_awal'        => $payload['kondisi_awal'] ?? null,
            'kondisi_akhir'       => $payload['kondisi_akhir'] ?? null,
            'jadwal_pemeliharaan' => $payload['jadwal_pemeliharaan'] ?? null,
            'tindakan'            => $payload['tindakan'] ?? null,
        ]);
    }

    private function attachMembers(WorkorderAssignment $assignment, array $petugasList): void
    {
        foreach ($petugasList as $staff) {
            WoAssignmentMember::create([
                'assignment_id' => $assignment->id,
                'pegawai_id'    => (int) $staff['pegawai_id'],
                'is_pic'        => ($staff['peran'] ?? null) === 'koordinator',
            ]);
        }
    }

    private function resolveLocation(Workorder $workorder, array $data): ?int
    {
        if (empty($data['latitude']) || empty($data['longitude'])) {
            return null;
        }

        $namaLokasi = $data['nama_lokasi'] ?? $workorder->lokasi ?? $workorder->nama_workorder;

        $location = MasterLocation::create([
            'nama'         => $namaLokasi,
            'latitude'     => $data['latitude'],
            'longitude'    => $data['longitude'],
            'radius_meter' => 100,
        ]);

        return $location->id;
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
