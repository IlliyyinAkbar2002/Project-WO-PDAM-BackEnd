<?php

namespace App\Services;

use App\Models\JenisWorkorder;
use App\Models\Material;
use App\Models\Pengaduan;
use App\Models\Workorder;
use App\Models\WoPeminjamanMaterial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiService
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth::user();
    }

    // =========================
    // ROLE CHECK
    // =========================
    private function isSuperAdmin(): bool
    {
        return $this->user?->role_id === 1;
    }

    // =========================
    // FILTER DEPARTEMEN
    // =========================
    private function applyDepartemenFilter(Builder $query): Builder
    {
        // SUPERADMIN = semua data
        if ($this->isSuperAdmin()) {
            return $query;
        }

        $departemenId = $this->user?->pegawai?->departemen_id;

        if (!$departemenId) {
            return $query;
        }
        if (
            in_array('departemen_id', $query->getModel()->getFillable()) ||
            \Illuminate\Support\Facades\Schema::hasColumn(
                $query->getModel()->getTable(),
                'departemen_id'
            )
        ) {
            return $query->where('departemen_id', $departemenId);
        }
        return $query;
    }

    // =========================
    // KPI SUMMARY
    // =========================
    public function getSummary(): array
    {
        $pengaduanQuery = $this->applyDepartemenFilter(
            Pengaduan::query()
        );

        $workorderQuery = $this->applyDepartemenFilter(
            Workorder::query()
        );

        $materialTotal = Material::sum('jumlah_stok');
        $materialRusak = Material::sum('rusak');
        $materialTerpakai = WoPeminjamanMaterial::whereIn(
            'status',
            [
                'DIPINJAM',
                'PENDING_KEMBALI',
            ]
        )->sum('jumlah_pinjam')
            // Tambahkan material terpasang/dikonsumsi permanen dari peminjaman
            // yang sudah dikembalikan (konsisten dengan Material::getTerpakaiAttribute).
            + WoPeminjamanMaterial::where('status', 'DIKEMBALIKAN')
                ->sum('jumlah_terpasang');

        $materialTersedia =
            $materialTotal
            - $materialTerpakai
            - $materialRusak;

        return [
            // ================= PENGADUAN =================
            'pengaduan_total' =>
                (clone $pengaduanQuery)->count(),

            'pengaduan_pending' =>
                (clone $pengaduanQuery)
                    ->where('status', 'Pending')
                    ->count(),

            'pengaduan_proses' =>
                (clone $pengaduanQuery)
                    ->where('status', 'Proses')
                    ->count(),

            'pengaduan_selesai' =>
                (clone $pengaduanQuery)
                    ->where('status', 'Selesai')
                    ->count(),

            'pengaduan_ditolak' =>
                (clone $pengaduanQuery)
                    ->where('status', 'Ditolak')
                    ->count(),

            // ================= WORKORDER =================
            'workorder_total' =>
                (clone $workorderQuery)->count(),

            'workorder_pending' =>
                (clone $workorderQuery)
                    ->where('status', 'Pending')
                    ->count(),

            'workorder_proses' =>
                (clone $workorderQuery)
                    ->where('status', 'Proses')
                    ->count(),

            'workorder_selesai' =>
                (clone $workorderQuery)
                    ->where('status', 'Selesai')
                    ->count(),

            'workorder_ditolak' =>
                (clone $workorderQuery)
                    ->where('status', 'Ditolak')
                    ->count(),

            // ================= MATERIAL =================
            'material_total' => $materialTotal,
            'material_terpakai' => $materialTerpakai,
            'material_rusak' => $materialRusak,
            'material_tersedia' => $materialTersedia,

            // ================= WORKORDER GRUP =================
            'workorder_by_jenis'  => $this->workorderByJenis(),
        ];
    }

    // =========================
    // COMPLETION RATE
    // =========================
    public function completionRate(): float
    {
        $query = $this->applyDepartemenFilter(
            Workorder::query()
        );

        $total = (clone $query)->count();

        $done = (clone $query)
            ->where('status', 'Selesai')
            ->count();

        return $total === 0
            ? 0
            : round(($done / $total) * 100, 2);
    }

    // =========================
    // KPI PER DEPARTEMEN
    // =========================
    public function getByDepartemen($departemenId): array
    {
        $query = Workorder::query()
            ->where('departemen_id', $departemenId);

        return [
            'workorder_total' =>
                (clone $query)->count(),

            'pending' =>
                (clone $query)
                    ->where('status', 'Pending')
                    ->count(),

            'proses' =>
                (clone $query)
                    ->where('status', 'Proses')
                    ->count(),

            'selesai' =>
                (clone $query)
                    ->where('status', 'Selesai')
                    ->count(),
        ];
    }

    public function workorderByJenis()
    {
        $query = $this->applyDepartemenFilter(
            Workorder::query()
        );
        return $query
            ->join(
                'm_jenis_workorder',
                'workorder.jenis_workorder_id',
                '=',
                'm_jenis_workorder.id'
            )
            ->select(
                'm_jenis_workorder.id',
                'm_jenis_workorder.nama',
                DB::raw('COUNT(workorder.id) as total')
            )
            ->groupBy(
                'm_jenis_workorder.id',
                'm_jenis_workorder.nama'
            )
            ->orderByDesc('total')
            ->get();
    }
}