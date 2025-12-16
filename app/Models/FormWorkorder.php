<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormWorkorder extends Model
{
    use HasFactory;
    protected $table = 'form_workorder';
    protected $guarded = [];

    public function jenisWorkorder()
    {
        return $this->belongsTo(JenisWorkorder::class, 'jenis_workorder_id');
    }

    public function kpi()
    {
        return $this->belongsTo(MasterKpi::class, 'kpi_id');
    }

    public function detailForm()
    {
        return $this->hasMany(DetailForm::class, 'form_workorder_id');
    }
}
