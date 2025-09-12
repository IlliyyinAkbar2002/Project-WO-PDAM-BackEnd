<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departemen extends Model
{
    use HasFactory;
    protected $table = 'm_departemen';

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class, 'departemen_id');
    }
}
