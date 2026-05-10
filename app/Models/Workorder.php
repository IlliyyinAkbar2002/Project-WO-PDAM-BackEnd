<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workorder extends Model
{
    use HasFactory;
    protected $table = 'workorder';
    protected $guarded = [];
    protected $appends = ['progres_persen'];

    protected $casts = [];

    public function pic()
    {
        return $this->belongsTo(User::class, 'assigned_to')->with(['pegawai:id,nama,nip']);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to')->with(['pegawai:id,nama,nip']);
    }

    /**
     * Daftar anggota tim yang ditugaskan ke WO ini.
     *
     * Menggantikan relasi lama `petugasList()` yang menggunakan tabel pivot
     * `workorder_petugas`. Sekarang anggota tim disimpan di `wo_assignment_member`
     * yang ter-relasi ke `workorder_assignment` (bukan langsung ke `workorder`).
     *
     * Akses via: $workorder->workorderAssignment->members
     * Atau gunakan helper ini untuk shortcut HasManyThrough.
     */
    public function assignmentMembers()
    {
        return $this->hasManyThrough(
            WoAssignmentMember::class,
            WorkorderAssignment::class,
            'workorder_id',    // FK di workorder_assignment
            'assignment_id',   // FK di wo_assignment_member
            'id',              // PK di workorder
            'id'               // PK di workorder_assignment
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
     * Menghitung persentase progres secara dinamis menggunakan pendekatan hybrid:
     * state-based (dari status_id) + time-based clamping (untuk IN_PROGRESS).
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
}
