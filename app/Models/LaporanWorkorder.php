<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanWorkorder extends Model
{
    use HasFactory;

    protected $table = 'laporan_workorder';

    protected $guarded = [];

    protected $casts = [
        'tanggal_terbit' => 'datetime',
        'approved_at' => 'datetime',
        'hasil_akhir_snapshot' => 'array',
        'petugas_snapshot' => 'array',
    ];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
