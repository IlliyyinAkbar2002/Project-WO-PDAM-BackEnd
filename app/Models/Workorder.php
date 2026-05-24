<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workorder extends Model
{
    use HasFactory;
    protected $table = 'workorder';
    protected $guarded = [];
    protected $appends = ['progres_persen', 'kategori_form', 'avg_cadence_minutes'];

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

        // Quota-based: (total PROGRESS reports / max quota) * 100, capped at 90%
        if (in_array($statusKode, ['IN_PROGRESS', 'REVISI_REQUESTED', 'DITOLAK_SPV'])) {
            $assignment = $this->workorderAssignment;
            $tanggalMulai = optional($assignment)->tanggal_mulai ?? $this->tanggal_mulai;
            $estimasiSelesai = optional($assignment)->estimasi_selesai;

            $totalDays = 1;
            if ($tanggalMulai && $estimasiSelesai) {
                $start = \Illuminate\Support\Carbon::parse($tanggalMulai)->startOfDay();
                $end = \Illuminate\Support\Carbon::parse($estimasiSelesai)->startOfDay();
                $totalDays = max(1, (int) $start->diffInDays($end, false) + 1);
            }

            $maxPelaporanTotal = $totalDays * 8;

            $mulaiTipeId = \App\Models\TipeProgress::where('kode', 'MULAI')->value('id');
            $totalPelaporan = ProgressWorkorder::where('workorder_id', $this->id)
                ->whereNotNull('waktu_submit')
                ->where('tipe_progress_id', '!=', $mulaiTipeId)
                ->count();

            return (int) min(90, round(($totalPelaporan / $maxPelaporanTotal) * 100));
        }

        return 0;
    }

    /**
     * Rata-rata jeda (menit) antar submission progres yang berturutan.
     *
     * Dipakai oleh dashboard superadmin untuk mengurutkan tim berdasarkan
     * kecepatan upload progres — semakin kecil nilai cadence, semakin cepat
     * tim merespons. Tipe MULAI dikecualikan (sama dengan progres_persen dan
     * validateProgressLimit) karena bukan submission lapor pekerjaan.
     * Baris REVISI/DITOLAK auto-generated SPV tidak memiliki waktu_submit
     * sehingga otomatis terfilter oleh whereNotNull('waktu_submit').
     *
     * Return null jika data belum cukup (< 2 submission terhitung).
     */
    public function getAvgCadenceMinutesAttribute(): ?float
    {
        $mulaiTipeId = \App\Models\TipeProgress::where('kode', 'MULAI')->value('id');

        $submitTimes = ProgressWorkorder::where('workorder_id', $this->id)
            ->whereNotNull('waktu_submit')
            ->where('tipe_progress_id', '!=', $mulaiTipeId)
            ->orderBy('waktu_submit', 'asc')
            ->pluck('waktu_submit');

        if ($submitTimes->count() < 2) {
            return null;
        }

        $deltas = [];
        $previous = null;
        foreach ($submitTimes as $waktu) {
            $current = \Illuminate\Support\Carbon::parse($waktu);
            if ($previous !== null) {
                $deltas[] = $previous->diffInSeconds($current) / 60.0;
            }
            $previous = $current;
        }

        if (empty($deltas)) {
            return null;
        }

        return round(array_sum($deltas) / count($deltas), 2);
    }
}
