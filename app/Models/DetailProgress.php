<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailProgress extends Model
{
    use HasFactory;

    protected $table = 'detail_progress';
    protected $guarded = [];

    public function progressWorkorder()
    {
        return $this->belongsTo(ProgressWorkorder::class, 'progress_workorder_id');
    }

    public function detailForm()
    {
        return $this->belongsTo(DetailForm::class, 'detail_form_id');
    }
}
