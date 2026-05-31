<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengaduan extends Model
{
    use HasFactory;

    protected $table = 'pengaduan';

    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $primaryKey = 'kode_pengaduan';

    protected $casts = [
    'tanggal_pengaduan' => 'datetime',
    ];

    protected $guarded = [];

    // status constants
    public const STATUS_PENDING = 'Pending';
    public const STATUS_PROSES = 'Proses';
    public const STATUS_SELESAI = 'Selesai';
    public const STATUS_DITOLAK = 'Ditolak';

    public function workorders()
    {
        return $this->hasMany(Workorder::class, 'pengaduan_id', 'kode_pengaduan');
    }
}
