<?php

namespace App\Services;

use App\Models\WoAssignmentMember;
use Illuminate\Support\Collection;

class StaffAvailabilityService
{
    /**
     * Satu-satunya status yang membebaskan pegawai (selain is_active = false).
     *
     * PENTING: 'Tutup' SENGAJA tidak ada di sini — WO yang ditutup tetap
     * menahan pegawainya. Ini keputusan produk, bukan bug: jangan ubah menjadi
     * ['Selesai', 'Tutup'].
     */
    private const STATUS_FREES_STAFF = 'Selesai';
    
    public function busyMap(array $pegawaiIds, ?int $exceptWorkorderId = null): Collection
    {
        $ids = collect($pegawaiIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return WoAssignmentMember::query()
            ->whereIn('pegawai_id', $ids->all())
            ->whereHas('assignment.workorder', function ($query) use ($exceptWorkorderId) {
                $query->where('workorder.status', '!=', self::STATUS_FREES_STAFF)
                    ->where('workorder.is_active', true);

                if ($exceptWorkorderId !== null) {
                    $query->where('workorder.id', '!=', $exceptWorkorderId);
                }
            })
            ->with(['pegawai:id,nama', 'assignment.workorder:id,nama_workorder'])
            ->orderBy('id')
            ->get()
            // Sibuk di >1 WO → cukup kembalikan salah satu (yang terbaru).
            ->mapWithKeys(function (WoAssignmentMember $member) {
                $workorder = $member->assignment?->workorder;

                return [
                    (int) $member->pegawai_id => [
                        'pegawai_nama'   => $member->pegawai?->nama,
                        'workorder_id'   => $workorder ? (int) $workorder->id : null,
                        'workorder_nama' => $workorder?->nama_workorder,
                    ],
                ];
            });
    }

    public function assertAllFree(array $pegawaiIds, ?int $exceptWorkorderId = null): void
    {
        $busy = $this->busyMap($pegawaiIds, $exceptWorkorderId);

        if ($busy->isEmpty()) {
            return;
        }

        // Urut mengikuti urutan payload supaya pesan deterministik.
        $detail = collect($pegawaiIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $busy->has($id))
            ->map(function (int $id) use ($busy) {
                $nama   = $busy[$id]['pegawai_nama'] ?? "Pegawai #{$id}";
                $woNama = $busy[$id]['workorder_nama'] ?? 'WO tanpa nama';

                return "{$nama} ({$woNama})";
            })
            ->implode(', ');

        throw new \LogicException(
            "Petugas berikut masih memiliki WO yang belum selesai: {$detail}. "
            . 'Selesaikan WO tersebut lebih dulu.'
        );
    }
}
