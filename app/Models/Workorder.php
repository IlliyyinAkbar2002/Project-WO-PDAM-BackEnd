<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workorder extends Model
{
    use HasFactory;
    protected $table = 'workorder';

    protected $fillable = [
        'nama_workorder',
        'deskripsi',
        'lokasi',
        'prioritas',
        'status',
        'kode_pengaduan',
        'departemen_id',
        'jenis_workorder_id',
        'assigned_to',
        'created_by',
        'lembur_spl_id',
    ];

    protected $guarded = [];

    // Field turunan yang ikut diserialisasi ke JSON (dipakai FE Web/Mobile).
    protected $appends = ['progres_persen'];

    /**
     * Persentase progress berbasis milestone (tahapan 1..4 -> 25/50/75/100).
     *
     * Sumber tahapan: kolom `tahapan` pada progress_workorder (diisi saat submit).
     * Status enum `Selesai` meng-override ke 100%. Tanpa milestone -> 0%.
     */
    public function getProgresPersenAttribute(): int
    {
        if ($this->status === 'Selesai') {
            return 100;
        }

        $maxTahapan = ProgressWorkorder::where('workorder_id', $this->id)
            ->whereNotNull('waktu_submit')
            ->max('tahapan');

        if ($maxTahapan !== null) {
            return (int) round(($maxTahapan / 4) * 100);
        }

        return 0;
    }


    // geo menambahkan ini ya, sisanya bisa akbar sesuaikan lagi
    public function pengaduan()
    {
        return $this->belongsTo(
            Pengaduan::class,
            'kode_pengaduan',
            'kode_pengaduan'
        );
    }

    public function meter()
    {
        return $this->hasOne(WoMeter::class);
    }

    public function jaringan()
    {
        return $this->hasOne(WoJaringan::class);
    }

    public function infrastruktur()
    {
        return $this->hasOne(WoInfrastruktur::class);
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class);
    }

    public function jenisWorkorder()
    {
        return $this->belongsTo(JenisWorkorder::class);
    }

    // ditujukan ke SPV
    public function assignedTo()
    {
        return $this->belongsTo(Pegawai::class, 'assigned_to');
    }

    // Pembuat workorder
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Pengajuan lembur untuk WO ini (1:1, nullable). NULL = WO non-lembur.
    public function lemburSpl()
    {
        return $this->belongsTo(LemburSpl::class, 'lembur_spl_id');
    }

    public function workorderAssignment()
    {
        return $this->hasOne(WorkorderAssignment::class, 'workorder_id');
    }

    public function assignmentMembers()
    {
        return $this->hasManyThrough(
            WoAssignmentMember::class,
            WorkorderAssignment::class,
            'workorder_id', // Foreign key on WorkorderAssignment table
            'assignment_id', // Foreign key on WoAssignmentMember table
            'id', // Local key on Workorder table
            'id'  // Local key on WorkorderAssignment table
        );
    }

    public function progressWorkorder()
    {
        return $this->hasMany(ProgressWorkorder::class, 'workorder_id');
    }

    public function laporanWorkorder()
    {
        return $this->hasOne(LaporanWorkorder::class, 'workorder_id');
    }
}
