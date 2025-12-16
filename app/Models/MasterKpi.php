<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKpi extends Model
{
    use HasFactory;
    protected $table = 'master_kpi';
    protected $guarded = [];

    public function formWorkorder()
    {
        return $this->hasMany(FormWorkorder::class, 'kpi_id');
    }
}
