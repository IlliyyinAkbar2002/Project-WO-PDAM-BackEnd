<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKpi extends Model
{
    use HasFactory;
    protected $table = 'master_kpi';
    protected $guarded = [];

    public function jenisWorkorder()
    {
        return $this->hasMany(JenisWorkorder::class, 'kpi_id');
    }
}
