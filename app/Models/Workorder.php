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
        'pic_id',
        'user_id',
    ];

    protected $guarded = [];

    // public function pic()
    // {
    //     return $this->belongsTo(User::class, 'pic_id')->with(['pegawai:id,nama,nip']);
    // }

    // public function petugas()
    // {
    //     return $this->belongsTo(User::class, 'petugas_id')->with(['pegawai:id,nama,nip']);
    // }

    // public function status()
    // {
    //     return $this->belongsTo(Status::class, 'status_id');
    // }

    // public function jenisWorkorder()
    // {
    //     return $this->belongsTo(JenisWorkorder::class, 'jenis_workorder_id');
    // }

    // public function tipeWorkorder()
    // {
    //     return $this->belongsTo(TipeWorkorder::class, 'tipe_workorder_id');
    // }

    // public function lemburSpl()
    // {
    //     return $this->belongsTo(LemburSpl::class, 'lembur_spl_id');
    // }

    // public function jenisLokasi()
    // {
    //     return $this->belongsTo(JenisLokasi::class, 'jenis_lokasi_id');
    // }

    // public function workorderAction()
    // {
    //     return $this->hasMany(WorkorderAction::class, 'workorder_id');
    // }

    // public function latestFreeze()
    // {
    //     return $this->hasOne(WorkorderAction::class, 'workorder_id')
    //         ->where('action_id', 2)->latest();
    // }

    // public function progressWorkorder()
    // {
    //     return $this->hasMany(ProgressWorkorder::class, 'workorder_id');
    // }

    // geo menambahkan ini ya, sisanya bisa akbar sesuaikan lagi
    public function pengaduan()
    {
        return $this->belongsTo(
            Pengaduan::class,
            'pengaduan_id',
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

    // SPV / PIC
    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    // Pembuat workorder
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
