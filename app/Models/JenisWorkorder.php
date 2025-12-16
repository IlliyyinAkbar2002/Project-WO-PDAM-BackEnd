<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisWorkorder extends Model
{
    use HasFactory;
    protected $table = 'm_jenis_workorder';
    protected $guarded = [];

    public function formWorkorder()
    {
        return $this->hasMany(FormWorkorder::class, 'jenis_workorder_id');
    }

    public function workorder()
    {
        return $this->hasMany(Workorder::class, 'jenis_workorder_id');
    }
}
