<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisWorkorder extends Model
{
    use HasFactory;
    protected $table = 'm_jenis_workorder';
    protected $guarded = [];

    public function kpi()
    {
        return $this->belongsTo(MasterKpi::class, 'kpi_id');
    }

    // public function form()
    // {
    //     return $this->hasMany(Form::class, 'jenis_workorder_id');
    // }

    public function detailForm()
    {
        return $this->hasMany(DetailForm::class, 'jenis_workorder_id');
    }

    public function workorder()
    {
        return $this->hasMany(Workorder::class, 'jenis_workorder_id');
    }
}
