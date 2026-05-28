<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Status;
use App\Models\User;
use App\Models\WoAssignmentMember;
use App\Models\WoPeminjamanMaterial;
use App\Models\Workorder;
use App\Notifications\WorkOrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PeminjamanMaterialService
 *
 * Menangani lifecycle peminjaman material per WO sesuai spec
 * flow_peminjaman_dan_pengembalian_material.md:
 *   1. pinjam        — Staff ajukan, auto-approved, stok terpotong.
 *   2. submitReturn  — Staff lapor kembali, status PENDING_KEMBALI, stok TIDAK berubah.
 *   3. verify        — SPV approve/reject; approve → stok sisa kembali; reject → balik DIPINJAM.
 *
 * Convention exception:
 *   \DomainException   → 403 (otorisasi)
 *   \LogicException    → 400 (business rule)
 */
class PeminjamanMaterialService
{
    /**
     * Staff ajukan peminjaman material untuk WO.
     */
    public function pinjam(Workorder $workorder, int $userId, array $data): WoPeminjamanMaterial
    {
        return DB::transaction(function () use ($workorder, $userId, $data) {
            $this->assertWorkorderBorrowable($workorder);
            $this->assertMemberOfWorkorder($workorder, $userId);

            $material = Material::where('kode_material', $data['material_kode'])
                ->lockForUpdate()
                ->first();

            if (! $material) {
                throw new \LogicException('Material tidak ditemukan.');
            }

            $tersedia = $material->jumlah_stok - $material->terpakai;
            if ($data['jumlah_pinjam'] > $tersedia) {
                throw new \LogicException(
                    "Stok material tidak mencukupi. Tersedia: {$tersedia}."
                );
            }

            $material->terpakai += $data['jumlah_pinjam'];
            $material->save();

            $pinjaman = WoPeminjamanMaterial::create([
                'workorder_id'  => $workorder->id,
                'material_kode' => $data['material_kode'],
                'jumlah_pinjam' => $data['jumlah_pinjam'],
                'catatan'       => $data['catatan'] ?? null,
                'diajukan_oleh' => $userId,
                'diajukan_at'   => now(),
                'status'        => 'DIPINJAM',
            ]);

            return $pinjaman->load(['material:kode_material,nama,satuan']);
        });
    }

    /**
     * Staff lapor pengembalian. Status → PENDING_KEMBALI, stok belum berubah.
     */
    public function submitReturn(WoPeminjamanMaterial $pinjaman, int $userId, array $data): WoPeminjamanMaterial
    {
        return DB::transaction(function () use ($pinjaman, $userId, $data) {
            $pinjaman = WoPeminjamanMaterial::lockForUpdate()->find($pinjaman->id);

            if ($pinjaman->status === 'PENDING_KEMBALI') {
                throw new \LogicException('Pengembalian sudah diajukan, menunggu verifikasi supervisor.');
            }
            if ($pinjaman->status === 'DIKEMBALIKAN') {
                throw new \LogicException('Material ini sudah dikembalikan sebelumnya.');
            }
            if ($pinjaman->status !== 'DIPINJAM') {
                throw new \LogicException("Status peminjaman tidak valid untuk pengembalian: {$pinjaman->status}.");
            }

            $workorder = $pinjaman->workorder()->first();
            if (! $workorder) {
                throw new \LogicException('Work order terkait tidak ditemukan.');
            }
            $this->assertMemberOfWorkorder($workorder, $userId);

            if ($data['jumlah_kembali'] > $pinjaman->jumlah_pinjam) {
                throw new \LogicException('Jumlah kembali tidak boleh lebih dari jumlah yang dipinjam.');
            }

            $pinjaman->jumlah_kembali  = $data['jumlah_kembali'];
            $pinjaman->kondisi_kembali = $data['kondisi_kembali'] ?? null;
            $pinjaman->status          = 'PENDING_KEMBALI';
            $pinjaman->save();

            $this->notifyReturnSubmitted($workorder, $pinjaman, $userId);

            return $pinjaman->load(['material:kode_material,nama,satuan']);
        });
    }

    /**
     * SPV verifikasi pengembalian. APPROVED → stok sisa kembali; REJECTED → balik DIPINJAM.
     */
    public function verify(WoPeminjamanMaterial $pinjaman, int $spvUserId, array $data): WoPeminjamanMaterial
    {
        return DB::transaction(function () use ($pinjaman, $spvUserId, $data) {
            $pinjaman = WoPeminjamanMaterial::lockForUpdate()->find($pinjaman->id);

            if ($pinjaman->status !== 'PENDING_KEMBALI') {
                throw new \LogicException(
                    "Hanya peminjaman dengan status PENDING_KEMBALI yang dapat diverifikasi. Status saat ini: {$pinjaman->status}."
                );
            }

            $workorder = $pinjaman->workorder()->with('workorderAssignment')->first();
            $assignment = $workorder?->workorderAssignment;
            if (! $assignment || (int) $assignment->spv_user_id !== $spvUserId) {
                throw new \DomainException('Hanya SPV WO terkait yang dapat memverifikasi pengembalian.');
            }

            $statusInput = strtoupper((string) $data['status']);
            if (! in_array($statusInput, ['APPROVED', 'REJECTED'], true)) {
                throw new \LogicException('Status verifikasi harus APPROVED atau REJECTED.');
            }

            $pinjaman->diverifikasi_oleh    = $spvUserId;
            $pinjaman->diverifikasi_at      = now();
            $pinjaman->catatan_verifikator  = $data['catatan_verifikator'] ?? null;

            if ($statusInput === 'APPROVED') {
                $material = Material::where('kode_material', $pinjaman->material_kode)
                    ->lockForUpdate()
                    ->first();
                if ($material && $pinjaman->jumlah_kembali > 0) {
                    $material->terpakai -= $pinjaman->jumlah_kembali;
                    if ($material->terpakai < 0) {
                        $material->terpakai = 0;
                    }
                    $material->save();
                }

                $pinjaman->status          = 'DIKEMBALIKAN';
                $pinjaman->dikembalikan_at = now();
            } else {
                $pinjaman->status          = 'DIPINJAM';
                $pinjaman->jumlah_kembali  = null;
                $pinjaman->kondisi_kembali = null;
                $pinjaman->dikembalikan_at = null;
            }

            $pinjaman->save();

            $this->notifyVerified($workorder, $pinjaman, $statusInput);

            return $pinjaman->load(['material:kode_material,nama,satuan']);
        });
    }

    // ------------------------------------------------------------------
    // GUARDS
    // ------------------------------------------------------------------

    private function assertWorkorderBorrowable(Workorder $workorder): void
    {
        $statusKode = optional(Status::find($workorder->status_id))->kode;
        if (! in_array($statusKode, ['DITUGASKAN_KE_STAFF', 'IN_PROGRESS'], true)) {
            throw new \LogicException(
                'WO tidak dalam status yang valid untuk peminjaman material (DITUGASKAN_KE_STAFF / IN_PROGRESS).'
            );
        }
    }

    private function assertMemberOfWorkorder(Workorder $workorder, int $userId): void
    {
        $assignment = $workorder->workorderAssignment()->first();
        if (! $assignment) {
            throw new \DomainException('Workorder belum memiliki tim. Tidak dapat melakukan peminjaman.');
        }

        $isMember = WoAssignmentMember::where('assignment_id', $assignment->id)
            ->where('user_id', $userId)
            ->exists();

        if (! $isMember) {
            throw new \DomainException('Hanya anggota tim WO yang dapat melakukan operasi ini.');
        }
    }

    // ------------------------------------------------------------------
    // NOTIFIKASI (best-effort)
    // ------------------------------------------------------------------

    private function notifyReturnSubmitted(Workorder $workorder, WoPeminjamanMaterial $pinjaman, int $staffUserId): void
    {
        try {
            $assignment = $workorder->workorderAssignment()->first();
            $spvId = optional($assignment)->spv_user_id;
            if (! $spvId) {
                return;
            }

            $staff = User::with('pegawai:id,nama')->find($staffUserId);
            $senderName = optional($staff?->pegawai)->nama ?? $staff?->name ?? 'Staff';

            $spv = User::find($spvId);
            if ($spv) {
                $spv->notify(new WorkOrderNotification(
                    'Pengajuan Pengembalian Material',
                    "{$senderName} mengajukan pengembalian material pada WO #{$workorder->nama_workorder}.",
                    (int) $workorder->id,
                    'material_return_submitted',
                    $senderName
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('notifyReturnSubmitted failed', [
                'peminjaman_id' => $pinjaman->id,
                'workorder_id'  => $workorder->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function notifyVerified(Workorder $workorder, WoPeminjamanMaterial $pinjaman, string $verifyStatus): void
    {
        try {
            $spv = User::with('pegawai:id,nama')->find($pinjaman->diverifikasi_oleh);
            $senderName = optional($spv?->pegawai)->nama ?? $spv?->name ?? 'SPV';

            $staff = User::find($pinjaman->diajukan_oleh);
            if (! $staff) {
                return;
            }

            $label = $verifyStatus === 'APPROVED' ? 'disetujui' : 'ditolak';
            $staff->notify(new WorkOrderNotification(
                'Verifikasi Pengembalian Material',
                "Pengembalian material pada WO #{$workorder->nama_workorder} telah {$label} oleh {$senderName}.",
                (int) $workorder->id,
                'material_return_verified',
                $senderName
            ));
        } catch (\Throwable $e) {
            Log::warning('notifyVerified failed', [
                'peminjaman_id' => $pinjaman->id,
                'workorder_id'  => $workorder->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
