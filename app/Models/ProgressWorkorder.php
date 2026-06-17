<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressWorkorder extends Model
{
    use HasFactory;

    protected $table = 'progress_workorder';
    protected $guarded = [];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'accuracy'  => 'float',
    ];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function dokumentasiProgress()
    {
        return $this->hasMany(DokumentasiProgress::class, 'progress_workorder_id');
    }

    /**
     * Riwayat review (pending/approved/rejected) untuk progress ini.
     */
    public function progressDetails()
    {
        return $this->hasMany(ProgressDetail::class, 'progress_workorder_id');
    }

    /**
     * Baris review terakhir — sumber kebenaran status siklus review.
     */
    public function latestDetail()
    {
        return $this->hasOne(ProgressDetail::class, 'progress_workorder_id')->latestOfMany();
    }

    /**
     * Pegawai (anggota tim) yang men-submit progress ini.
     */
    public function submitter()
    {
        return $this->belongsTo(Pegawai::class, 'submitted_by_pegawai_id');
    }
}
