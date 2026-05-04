<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKpi extends Model
{
    use HasFactory;
    protected $table = 'm_kpi';
    protected $guarded = [];

    public function workorder()
    {
        return $this->hasMany(Workorder::class, 'kpi_id');
    }
}
