<?php

namespace App\Services;

use App\Models\Pengaduan;
use App\Models\Workorder;
use App\Models\Material;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

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
        return $this->user?->role === 'superadmin';
    }

    // =========================
    // SAFE DEPARTEMEN FILTER
    // =========================
    private function applyDepartemenFilter(Builder $query): Builder
    {
        if ($this->isSuperAdmin()) {
            return $query;
        }
        // IMPORTANT: pastikan kolom ada sebelum filter
        if (in_array('departemen_id', $query->getModel()->getFillable())
            || \Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'departemen_id')) {

            return $query->where('departemen_id', $this->user->departemen_id);
        }
        return $query;
    }

    // =========================
    // SUMMARY KPI
    // =========================
    public function getSummary(): array
    {
        $pengaduan = $this->applyDepartemenFilter(Pengaduan::query());
        $workorder = $this->applyDepartemenFilter(Workorder::query());
        return [
            // ================= PENGADUAN =================
            'pengaduan_total'   => (clone $pengaduan)->count(),
            'pengaduan_pending' => (clone $pengaduan)->where('status', 'Pending')->count(),
            'pengaduan_proses'  => (clone $pengaduan)->where('status', 'Proses')->count(),
            'pengaduan_selesai' => (clone $pengaduan)->where('status', 'Selesai')->count(),
            'pengaduan_ditolak' => (clone $pengaduan)->where('status', 'Ditolak')->count(),

            // ================= WORKORDER =================
            'workorder_total'   => (clone $workorder)->count(),
            'workorder_pending' => (clone $workorder)->where('status', 'Pending')->count(),
            'workorder_proses'  => (clone $workorder)->where('status', 'Proses')->count(),
            'workorder_selesai' => (clone $workorder)->where('status', 'Selesai')->count(),
            'workorder_ditolak' => (clone $workorder)->where('status', 'Ditolak')->count(),

            // ================= MATERIAL (GLOBAL) =================
            'material_total' => Material::query()->sum('jumlah_stok'),
            'material_terpakai' => Material::query()->sum('terpakai'),
            'material_tersedia' => Material::query()
                ->selectRaw('COALESCE(SUM(jumlah_stok - terpakai), 0) as tersedia')
                ->value('tersedia'),
        ];
    }

    // =========================
    // COMPLETION RATE
    // =========================
    public function completionRate(): float
    {
        $query = $this->applyDepartemenFilter(Workorder::query());
        $total = (clone $query)->count();
        $done  = (clone $query)->where('status', 'Selesai')->count();
        return $total === 0 ? 0 : round(($done / $total) * 100, 2);
    }

    // =========================
    // KPI PER DEPARTEMEN
    // =========================
    public function getByDepartemen($departemenId): array
    {
        return [
            'workorder_total' => Workorder::where('departemen_id', $departemenId)->count(),
            'selesai'         => Workorder::where('departemen_id', $departemenId)->where('status', 'Selesai')->count(),
            'proses'          => Workorder::where('departemen_id', $departemenId)->where('status', 'Proses')->count(),
            'pending'         => Workorder::where('departemen_id', $departemenId)->where('status', 'Pending')->count(),
        ];
    }
}