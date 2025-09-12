<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipeWorkorder extends Model
{
    use HasFactory;
    protected $table = 'm_tipe_workorder';

    public function workorder()
    {
        return $this->hasMany(Workorder::class, 'tipe_workorder_id');
    }
}
