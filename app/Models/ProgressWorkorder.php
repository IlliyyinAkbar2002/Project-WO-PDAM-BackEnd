<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressWorkorder extends Model
{
    use HasFactory;

    protected $table = 'progress_workorder';
    protected $guarded = [];

    public function detailProgress()
    {
        return $this->hasMany(DetailProgress::class, 'progress_workorder_id');
    }

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function dokumentasiProgress()
    {
        return $this->hasMany(DokumentasiProgress::class, 'progress_workorder_id');
    }
}
