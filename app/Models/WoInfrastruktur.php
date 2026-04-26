<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WoInfrastruktur extends Model
{
    use HasFactory;

    protected $table = 'wo_infrastruktur';

    protected $guarded = [];

    protected $casts = [
        'jadwal_pemeliharaan' => 'date',
    ];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }
}
