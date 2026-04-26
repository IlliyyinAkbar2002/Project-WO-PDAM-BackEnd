<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisPengaduan extends Model
{
    use HasFactory;

    protected $table = 'm_jenis_pengaduan';

    protected $guarded = [];

    public function pengaduan()
    {
        return $this->hasMany(Pengaduan::class, 'jenis_pengaduan_id');
    }
}
