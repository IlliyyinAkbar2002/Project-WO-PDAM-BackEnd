<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Workorder extends Model
{
    use HasFactory;
    protected $table = 'workorder';
    protected $guarded = [];
    protected $appends = ['progres_persen', 'kategori_form'];

    protected $casts = [];

    public function pic()
    {
        return $this->belongsTo(User::class, 'assigned_to')->with(['pegawai:id,nama,nip']);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to')->with(['pegawai:id,nama,nip']);
    }

    public function assignmentMembers()
    {
        return $this->hasManyThrough(
            WoAssignmentMember::class,
            WorkorderAssignment::class,
            'workorder_id',    
            'assignment_id',   
            'id',              
            'id'               
        );
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function jenisWorkorder()
    {
        return $this->belongsTo(JenisWorkorder::class, 'jenis_workorder_id');
    }

    public function pengaduan()
    {
        return $this->belongsTo(Pengaduan::class, 'pengaduan_id');
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'departemen_id');
    }

    public function lemburSpl()
    {
        return $this->belongsTo(LemburSpl::class, 'lembur_spl_id');
    }

    public function workorderAction()
    {
        return $this->hasMany(WorkorderAction::class, 'workorder_id');
    }

    public function latestFreeze()
    {
        return $this->hasOne(WorkorderAction::class, 'workorder_id')
            ->whereHas('action', fn ($q) => $q->where('kode', 'FREEZE'))
            ->latest();
    }

    public function progressWorkorder()
    {
        return $this->hasMany(ProgressWorkorder::class, 'workorder_id');
    }

    public function woMeter()
    {
        return $this->hasOne(WoMeter::class, 'workorder_id');
    }

    public function woJaringan()
    {
        return $this->hasOne(WoJaringan::class, 'workorder_id');
    }

    public function woInfrastruktur()
    {
        return $this->hasOne(WoInfrastruktur::class, 'workorder_id');
    }

    public function laporanWorkorder()
    {
        return $this->hasOne(LaporanWorkorder::class, 'workorder_id');
    }

    /**
     * Event assign WO oleh SPV (Tahap 5). 1:1 — 1 WO = 1 row assignment.
     * Menjawab "kapan SPV melakukan assign?" tanpa JOIN ke workorder_action.
     *
     * Di-INSERT di transaction yang sama dengan wo_{kategori} +
     * workorder_petugas saat SPV klik "Tugaskan".
     */
    public function workorderAssignment()
    {
        return $this->hasOne(WorkorderAssignment::class, 'workorder_id');
    }

    /**
     * Peminjaman material/aset untuk WO ini. Opsional — hanya ada
     * kalau WO membutuhkan material.
     */
    public function peminjamanMaterial()
    {
        return $this->hasMany(WoPeminjamanMaterial::class, 'workorder_id');
    }

    /**
     * Resolve kategori form WO berdasarkan prioritas:
     * 1. Keberadaan relasi wo_jaringan / wo_infrastruktur / wo_meter
     * 2. Kolom kategori_form dari jenis_workorder (m_jenis_workorder)
     * 3. Default 'meter'
     *
     * Ini memastikan FE selalu mendapat kategori yang benar di root level
     * response, terlepas dari jenis_workorder_id yang dipakai saat create.
     */
    public function getKategoriFormAttribute(): string
    {
        // Prioritas 1: deteksi dari keberadaan relasi detail
        if ($this->relationLoaded('woJaringan') && $this->woJaringan !== null) {
            return 'jaringan';
        }
        if ($this->relationLoaded('woInfrastruktur') && $this->woInfrastruktur !== null) {
            return 'infrastruktur';
        }
        if ($this->relationLoaded('woMeter') && $this->woMeter !== null) {
            return 'meter';
        }

        // Prioritas 2: dari jenis_workorder.kategori_form
        if ($this->relationLoaded('jenisWorkorder') && $this->jenisWorkorder) {
            return $this->jenisWorkorder->kategori_form ?? 'meter';
        }

        // Fallback: query langsung jika relasi belum di-load
        if ($this->jenis_workorder_id) {
            $jenis = JenisWorkorder::find($this->jenis_workorder_id);
            return $jenis->kategori_form ?? 'meter';
        }

        return 'meter';
    }

    /**
     * Menghitung persentase progres secara dinamis menggunakan pendekatan hybrid:
     * state-based (dari status_id) + time-based clamping (untuk IN_PROGRESS).
     *
     * Tambahan: jika kuota pelaporan harian (8x/hari) ATAU kuota total habis,
     * langsung return 100% karena pekerjaan dianggap selesai secara operasional.
     */
    public function getProgresPersenAttribute(): int
    {
        $statusKode = optional($this->status)->kode;

        // 0%: Belum dikerjakan oleh staff
        if (in_array($statusKode, ['DITUGASKAN_KE_SPV', 'DISETUJUI', 'DITUGASKAN_KE_STAFF'])) {
            return 0;
        }

        // 100%: Sudah di-approve final oleh SPV/Manager (tergantung alur)
        if ($statusKode === 'SELESAI') {
            return 100;
        }

        // 90%: Staff sudah klik Selesai, menunggu review SPV
        if ($statusKode === 'PENGECEKAN') {
            return 90;
        }

        // Cek kuota habis → paksa 100% (pekerjaan dianggap selesai secara operasional)
        if (in_array($statusKode, ['IN_PROGRESS', 'REVISI_REQUESTED', 'DITOLAK_SPV', 'MENUNGGU_APPROVAL_MANAGER'])) {
            if ($this->isQuotaExhausted()) {
                return 100;
            }
        }

        // 10% - 80%: Dalam pengerjaan (linear interpolasi berdasarkan waktu)
        if (in_array($statusKode, ['IN_PROGRESS', 'REVISI_REQUESTED', 'DITOLAK_SPV'])) {
            $assignment = $this->workorderAssignment;
            $tanggalMulai = optional($assignment)->tanggal_mulai ?? $this->tanggal_mulai;
            $estimasiSelesai = optional($assignment)->estimasi_selesai;

            if (!$tanggalMulai || !$estimasiSelesai) {
                return 50;
            }

            $start = \Illuminate\Support\Carbon::parse($tanggalMulai);
            $end = \Illuminate\Support\Carbon::parse($estimasiSelesai);
            $now = \Illuminate\Support\Carbon::now();

            $totalMinutes = $start->diffInMinutes($end, false);
            if ($totalMinutes <= 0) {
                return 50;
            }

            $elapsedMinutes = $start->diffInMinutes($now, false);
            if ($elapsedMinutes < 0) {
                $elapsedMinutes = 0; // belum mulai
            }

            $ratio = $elapsedMinutes / $totalMinutes;
            if ($ratio > 1) {
                $ratio = 1; // mentok di estimasi selesai
            }

            return (int) (10 + ($ratio * 70));
        }

        return 0;
    }

    /**
     * Cek apakah kuota pelaporan sudah habis (harian 8x ATAU total berdasarkan estimasi hari).
     * Digunakan oleh getProgresPersenAttribute untuk force 100%.
     */
    private function isQuotaExhausted(): bool
    {
        // Cek kuota harian: maksimal 8 pelaporan per hari (hari ini ATAU hari sebelumnya).
        // Jika kuota harian pernah habis di hari manapun, pekerjaan dianggap selesai secara
        // operasional dan status 100% harus tetap bertahan di hari-hari berikutnya.
        $dailyQuotaEverExhausted = DB::table('progress_workorder')
            ->selectRaw('1')
            ->where('workorder_id', $this->id)
            ->whereNotNull('waktu_submit')
            ->groupByRaw('waktu_submit::date')
            ->havingRaw('COUNT(*) >= 8')
            ->exists();

        if ($dailyQuotaEverExhausted) {
            return true;
        }

        // Cek kuota total: totalDays * 8
        $assignment = $this->workorderAssignment;
        $tanggalMulai = optional($assignment)->tanggal_mulai ?? $this->tanggal_mulai;
        $estimasiSelesai = optional($assignment)->estimasi_selesai;

        if ($tanggalMulai && $estimasiSelesai) {
            $start = \Illuminate\Support\Carbon::parse($tanggalMulai)->startOfDay();
            $end = \Illuminate\Support\Carbon::parse($estimasiSelesai)->startOfDay();
            $totalDays = max(1, (int) $start->diffInDays($end, false) + 1);

            $maxPelaporanTotal = $totalDays * 8;
            $totalPelaporan = ProgressWorkorder::where('workorder_id', $this->id)
                ->whereNotNull('waktu_submit')
                ->count();

            if ($totalPelaporan >= $maxPelaporanTotal) {
                return true;
            }
        }

        return false;
    }
}
