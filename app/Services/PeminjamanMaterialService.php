<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Pegawai;
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
 * Lifecycle peminjaman material per WO (adaptasi skema target MergerManual:
 * status ENUM `workorder.status` + identitas `m_pegawai`):
 *   1. pinjam        — Staff ajukan, auto-approved, stok terpotong.
 *   2. submitReturn  — Staff lapor kembali, status PENDING_KEMBALI, stok TIDAK berubah.
 *   3. verify        — SPV approve/reject; approve → stok sisa kembali; reject → balik DIPINJAM.
 *
 * Identitas aktor dikirim sebagai pegawai_id (lihat WoAssignmentMember.pegawai_id
 * & WorkorderAssignment.spv_pegawai_id). Jembatan ke User dilakukan saat notifikasi.
 *
 * Convention exception:
 *   \DomainException   → 403 (otorisasi)
 *   \LogicException    → 400 (business rule)
 */
class PeminjamanMaterialService
{
    /** Status WO (enum) yang memperbolehkan peminjaman material. */
    private const STATUS_BORROWABLE = ['Proses'];

    /**
     * Staff ajukan peminjaman material untuk WO.
     */
    public function pinjam(Workorder $workorder, int $pegawaiId, array $data): WoPeminjamanMaterial
    {
        return DB::transaction(function () use ($workorder, $pegawaiId, $data) {
            $this->assertWorkorderBorrowable($workorder);
            $this->assertMemberOfWorkorder($workorder, $pegawaiId);

            $material = Material::where('kode_material', $data['material_kode'])
                ->lockForUpdate()
                ->first();

            if (! $material) {
                throw new \LogicException('Material tidak ditemukan.');
            }

            $tersedia = $material->tersedia; // jumlah_stok - terpakai - rusak
            if ($data['jumlah_pinjam'] > $tersedia) {
                throw new \LogicException(
                    "Stok material tidak mencukupi. Tersedia: {$tersedia}."
                );
            }

            $pinjaman = WoPeminjamanMaterial::create([
                'workorder_id'  => $workorder->id,
                'material_kode' => $data['material_kode'],
                'jumlah_pinjam' => $data['jumlah_pinjam'],
                'catatan'       => $data['catatan'] ?? null,
                'diajukan_oleh' => $pegawaiId,
                'diajukan_at'   => now(),
                'status'        => 'DIPINJAM',
            ]);

            return $pinjaman->load(['material:kode_material,nama,jumlah_stok,terpakai,rusak']);
        });
    }

    /**
     * Staff lapor pengembalian. Status → PENDING_KEMBALI, stok belum berubah.
     */
    public function submitReturn(WoPeminjamanMaterial $pinjaman, int $pegawaiId, array $data): WoPeminjamanMaterial
    {
        return DB::transaction(function () use ($pinjaman, $pegawaiId, $data) {
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
            $this->assertMemberOfWorkorder($workorder, $pegawaiId);

            if ($data['jumlah_kembali'] > $pinjaman->jumlah_pinjam) {
                throw new \LogicException('Jumlah kembali tidak boleh lebih dari jumlah yang dipinjam.');
            }

            $jumlahRusak = (int) ($data['jumlah_rusak'] ?? 0);
            if ($jumlahRusak > $data['jumlah_kembali']) {
                throw new \LogicException('Jumlah rusak tidak boleh lebih dari jumlah yang dikembalikan.');
            }

            $pinjaman->jumlah_kembali  = $data['jumlah_kembali'];
            $pinjaman->jumlah_rusak    = $jumlahRusak;
            $pinjaman->kondisi_kembali = $data['kondisi_kembali'] ?? null;
            $pinjaman->status          = 'PENDING_KEMBALI';
            $pinjaman->save();

            $this->notifyReturnSubmitted($workorder, $pinjaman, $pegawaiId);

            return $pinjaman->load(['material:kode_material,nama,jumlah_stok,terpakai,rusak']);
        });
    }

    /**
     * SPV verifikasi pengembalian. APPROVED → stok sisa kembali; REJECTED → balik DIPINJAM.
     */
    public function verify(WoPeminjamanMaterial $pinjaman, int $spvPegawaiId, array $data): WoPeminjamanMaterial
    {
        return DB::transaction(function () use ($pinjaman, $spvPegawaiId, $data) {
            $pinjaman = WoPeminjamanMaterial::lockForUpdate()->find($pinjaman->id);

            if ($pinjaman->status !== 'PENDING_KEMBALI') {
                throw new \LogicException(
                    "Hanya peminjaman dengan status PENDING_KEMBALI yang dapat diverifikasi. Status saat ini: {$pinjaman->status}."
                );
            }

            $workorder = $pinjaman->workorder()->with('workorderAssignment')->first();
            $assignment = $workorder?->workorderAssignment;
            if (! $assignment || (int) $assignment->spv_pegawai_id !== $spvPegawaiId) {
                throw new \DomainException('Hanya SPV WO terkait yang dapat memverifikasi pengembalian.');
            }

            $statusInput = strtoupper((string) $data['status']);
            if (! in_array($statusInput, ['APPROVED', 'REJECTED'], true)) {
                throw new \LogicException('Status verifikasi harus APPROVED atau REJECTED.');
            }

            $pinjaman->diverifikasi_oleh    = $spvPegawaiId;
            $pinjaman->diverifikasi_at      = now();
            $pinjaman->catatan_verifikator  = $data['catatan_verifikator'] ?? null;

            if ($statusInput === 'APPROVED') {
                $material = Material::where('kode_material', $pinjaman->material_kode)
                    ->lockForUpdate()
                    ->first();
                if ($material) {
                    // Bagian yang rusak tidak balik ke stok tersedia: parkir di kolom rusak.
                    $rusak = (int) ($pinjaman->jumlah_rusak ?? 0);
                    if ($rusak > 0) {
                        $material->increment('rusak', $rusak);
                    }

                    $material->save();
                }

                $pinjaman->status          = 'DIKEMBALIKAN';
                $pinjaman->dikembalikan_at = now();
            } else {
                $pinjaman->status          = 'DIPINJAM';
                $pinjaman->jumlah_kembali  = null;
                $pinjaman->jumlah_rusak    = null;
                $pinjaman->kondisi_kembali = null;
                $pinjaman->dikembalikan_at = null;
            }

            $pinjaman->save();

            $this->notifyVerified($workorder, $pinjaman, $statusInput);

            return $pinjaman->load(['material:kode_material,nama,jumlah_stok,terpakai,rusak']);
        });
    }

    // ------------------------------------------------------------------
    // GUARDS
    // ------------------------------------------------------------------

    private function assertWorkorderBorrowable(Workorder $workorder): void
    {
        if (! in_array($workorder->status, self::STATUS_BORROWABLE, true)) {
            throw new \LogicException(
                'WO tidak dalam status yang valid untuk peminjaman material (harus Proses).'
            );
        }
    }

    private function assertMemberOfWorkorder(Workorder $workorder, int $pegawaiId): void
    {
        $assignment = $workorder->workorderAssignment()->first();
        if (! $assignment) {
            throw new \DomainException('Workorder belum memiliki tim. Tidak dapat melakukan peminjaman.');
        }

        $isMember = WoAssignmentMember::where('assignment_id', $assignment->id)
            ->where('pegawai_id', $pegawaiId)
            ->exists();

        if (! $isMember) {
            throw new \DomainException('Hanya anggota tim WO yang dapat melakukan operasi ini.');
        }
    }

    // ------------------------------------------------------------------
    // NOTIFIKASI (best-effort)
    // ------------------------------------------------------------------

    private function notifyReturnSubmitted(Workorder $workorder, WoPeminjamanMaterial $pinjaman, int $staffPegawaiId): void
    {
        try {
            $assignment = $workorder->workorderAssignment()->first();
            $spvPegawaiId = optional($assignment)->spv_pegawai_id;
            if (! $spvPegawaiId) {
                return;
            }

            $senderName = optional(Pegawai::find($staffPegawaiId))->nama ?? 'Staff';

            $spvUser = User::where('pegawai_id', $spvPegawaiId)->first();
            if ($spvUser) {
                $spvUser->notify(new WorkOrderNotification(
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
            $senderName = optional(Pegawai::find($pinjaman->diverifikasi_oleh))->nama ?? 'SPV';

            $staffUser = User::where('pegawai_id', $pinjaman->diajukan_oleh)->first();
            if (! $staffUser) {
                return;
            }

            $label = $verifyStatus === 'APPROVED' ? 'disetujui' : 'ditolak';
            $staffUser->notify(new WorkOrderNotification(
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
