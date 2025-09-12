<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokumentasiProgress extends Model
{
    use HasFactory;
    protected $table = 'dokumentasi_progress';
    protected $guarded = [];

    public function progressWorkorder()
    {
        return $this->belongsTo(ProgressWorkorder::class, 'progress_workorder_id');
    }
}
