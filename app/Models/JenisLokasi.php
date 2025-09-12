<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisLokasi extends Model
{
    use HasFactory;
    protected $table = 'm_jenis_lokasi';

    public function workorder()
    {
        return $this->hasMany(Workorder::class, 'jenis_lokasi_id');
    }
}
