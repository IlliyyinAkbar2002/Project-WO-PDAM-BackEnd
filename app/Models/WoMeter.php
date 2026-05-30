<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WoMeter extends Model
{
    use HasFactory;

    protected $table = 'wo_meter';

    protected $guarded = [];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class);
    }
}
