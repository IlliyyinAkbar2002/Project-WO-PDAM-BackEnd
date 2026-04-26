<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengaduan extends Model
{
    use HasFactory;

    protected $table = 'pengaduan';

    protected $guarded = [];

    protected $casts = [
        'tanggal_lapor' => 'datetime',
        'fetched_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function jenisPengaduan()
    {
        return $this->belongsTo(JenisPengaduan::class, 'jenis_pengaduan_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'departemen_id');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(Pengaduan::class, 'duplicate_of_id');
    }

    public function duplicates()
    {
        return $this->hasMany(Pengaduan::class, 'duplicate_of_id');
    }

    public function workorders()
    {
        return $this->hasMany(Workorder::class, 'pengaduan_id');
    }
}
