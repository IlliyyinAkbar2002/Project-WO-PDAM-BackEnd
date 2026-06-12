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

    public function workorderAssignment()
    {
        return $this->hasOne(WorkorderAssignment::class, 'workorder_id');
    }

    public function peminjamanMaterial()
    {
        return $this->hasMany(WoPeminjamanMaterial::class, 'workorder_id');
    }

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

   
    public function getProgresPersenAttribute(): int
    {
        $statusKode = optional($this->status)->kode;

        // 1. 100%: Sudah di-approve final oleh SPV/Manager (tergantung alur)
        if ($statusKode === 'SELESAI') {
            return 100;
        }

        // 2. Milestone-based progress (Tahapan)
        $dibatalkanId = \App\Models\Status::where('kode', 'DIBATALKAN')->value('id');
        $maxTahapan = \App\Models\ProgressWorkorder::where('workorder_id', $this->id)
            ->whereNotNull('waktu_submit')
            ->when($dibatalkanId !== null, fn ($q) => $q->where('status_id', '!=', $dibatalkanId))
            ->max('tahapan');

        if ($maxTahapan !== null) {
            return (int) round(($maxTahapan / 4) * 100);
        }

        // 3. Legacy short-circuits
        // 0%: Belum dikerjakan oleh staff
        if (in_array($statusKode, ['DITUGASKAN_KE_SPV', 'DISETUJUI', 'DITUGASKAN_KE_STAFF'])) {
            return 0;
        }

        // 90%: Staff sudah klik Selesai, menunggu review SPV
        if ($statusKode === 'PENGECEKAN') {
            return 90;
        }

        // Individual-based progress: rata-rata progress semua anggota tim
        if (in_array($statusKode, ['IN_PROGRESS', 'REVISI_REQUESTED', 'DITOLAK_SPV'])) {
            $assignment = $this->workorderAssignment;
            $tanggalMulai = optional($assignment)->tanggal_mulai ?? $this->tanggal_mulai;
            $estimasiSelesai = optional($assignment)->estimasi_selesai;

            $maxPelaporanTotal = \App\Support\WorkorderQuota::quotaTotal($tanggalMulai, $estimasiSelesai);

            // Ambil semua anggota tim
            $members = $this->assignmentMembers;

            if ($members->isEmpty()) {
                return 0;
            }

            $memberProgressPercentages = $members->map(function ($member) use ($maxPelaporanTotal) {
                $totalPelaporan = \App\Support\WorkorderQuota::countableSubmissions($this->id, $member->user_id)->count();

                return min(90, round(($totalPelaporan / $maxPelaporanTotal) * 100));
            });

            return (int) min(90, round($memberProgressPercentages->avg()));
        }

        return 0;
    }

    public function getAvgCadenceMinutesAttribute(): ?float
    {
        $progressTipeId = \App\Models\TipeProgress::where('kode', 'PROGRESS')->value('id');

        $submitTimes = ProgressWorkorder::where('workorder_id', $this->id)
            ->whereNotNull('waktu_submit')
            ->where('tipe_progress_id', $progressTipeId)
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
