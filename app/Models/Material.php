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
    protected $keyType = 'string';
    
    protected $fillable = [
        'kode_material',
        'nama',
        'jumlah_stok',
        'rusak',
        'pegawai_id',
    ];

    protected $appends = [
        'terpakai',
        'tersedia',
    ];

    public function getTerpakaiAttribute()
    {
        $aktif = WoPeminjamanMaterial::where(
            'material_kode',
            $this->kode_material
        )
            ->whereIn('status', [
                'DIPINJAM',
                'PENDING_KEMBALI'
            ])
            ->sum('jumlah_pinjam');

        // Material yang terpasang/dikonsumsi permanen pada peminjaman yang
        // sudah dikembalikan — ikut mengurangi stok tersedia.
        $terpasang = WoPeminjamanMaterial::where(
            'material_kode',
            $this->kode_material
        )
            ->where('status', 'DIKEMBALIKAN')
            ->sum('jumlah_terpasang');

        return $aktif + $terpasang;
    }

    public function getTersediaAttribute()
    {
        return max(
            0,
            $this->jumlah_stok
            - $this->terpakai
            - $this->rusak
        );
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
