<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LemburSpl extends Model
{
    use HasFactory;
    protected $table = 'lembur_spl';
    protected $guarded = [];

    public function workorder()
    {
        return $this->hasOne(Workorder::class, 'lembur_spl_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}
