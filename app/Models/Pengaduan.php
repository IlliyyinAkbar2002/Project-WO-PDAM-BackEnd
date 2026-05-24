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

    public function workorders()
    {
        return $this->hasMany(Workorder::class, 'pengaduan_id', 'kode_pengaduan');
    }
}
