<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Material extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'm_material';

    protected $primaryKey = 'kode_material';
    public $incrementing = false;
    protected $keyType = 'int';
    
    protected $fillable = [
        'kode_material',
        'nama',
        'jumlah_stok',
        'pegawai_id',
    ];

    public function getTersediaAttribute()
    {
        return $this->jumlah_stok - $this->terpakai;
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
