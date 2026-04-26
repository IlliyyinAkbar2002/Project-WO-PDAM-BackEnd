<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workorder extends Model
{
    use HasFactory;
    protected $table = 'workorder';
    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'location_id' => 'integer',
    ];

    public function pic()
    {
        return $this->belongsTo(User::class, 'assigned_to')->with(['pegawai:id,nama,nip']);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to')->with(['pegawai:id,nama,nip']);
    }

    /**
     * Daftar petugas yang ditugaskan ke WO ini (TKT-07).
     *
     * Menggantikan relasi lama `petugas()` `belongsTo` yang mengasumsikan
     * 1 WO = 1 petugas. Setelah migration `workorder_petugas` + drop kolom
     * `petugas_id`, hubungan WO ↔ user bersifat many-to-many via tabel
     * pivot `workorder_petugas`.
     *
     * `withPivot('peran')` untuk menyimpan peran per-assignment (mis.
     * "koordinator" / "anggota") — nullable untuk kompatibilitas backfill.
     * Eager-load `pegawai` supaya response API bisa langsung menampilkan
     * nama & NIP tiap petugas tanpa N+1 query.
     *
     * Breaking untuk FE Web (Next.js): response key berubah dari
     * `petugas` (object) → `petugas_list` (array) via relasi ini.
     */
    public function petugasList()
    {
        return $this->belongsToMany(
            User::class,
            'workorder_petugas',
            'workorder_id',
            'user_id'
        )
            ->withPivot('peran')
            ->withTimestamps()
            ->with(['pegawai:id,nama,nip']);
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

    public function tipeWorkorder()
    {
        return $this->belongsTo(TipeWorkorder::class, 'tipe_workorder_id');
    }

    public function lemburSpl()
    {
        return $this->belongsTo(LemburSpl::class, 'lembur_spl_id');
    }

    public function jenisLokasi()
    {
        return $this->belongsTo(JenisLokasi::class, 'jenis_lokasi_id');
    }

    public function location()
    {
        return $this->belongsTo(MasterLocation::class, 'location_id');
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
}
