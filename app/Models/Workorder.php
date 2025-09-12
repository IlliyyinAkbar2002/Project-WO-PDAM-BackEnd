<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workorder extends Model
{
    use HasFactory;
    protected $table = 'workorder';
    protected $guarded = [];

    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id')->with(['pegawai:id,nama,nip']);
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id')->with(['pegawai:id,nama,nip']);
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function jenisWorkorder()
    {
        return $this->belongsTo(JenisWorkorder::class, 'jenis_workorder_id');
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

    public function workorderAction()
    {
        return $this->hasMany(WorkorderAction::class, 'workorder_id');
    }

    public function latestFreeze()
    {
        return $this->hasOne(WorkorderAction::class, 'workorder_id')
            ->where('action_id', 2)->latest();
    }

    public function progressWorkorder()
    {
        return $this->hasMany(ProgressWorkorder::class, 'workorder_id');
    }
}
