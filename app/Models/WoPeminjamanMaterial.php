<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WoPeminjamanMaterial extends Model
{
    use HasFactory;

    protected $table = 'wo_peminjaman_material';

    protected $guarded = [];

    protected $casts = [
        'diajukan_at'     => 'datetime',
        'dikembalikan_at' => 'datetime',
        'diverifikasi_at' => 'datetime',
    ];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_kode', 'kode_material');
    }

    public function pengaju()
    {
        return $this->belongsTo(Pegawai::class, 'diajukan_oleh')
            ->with(['pegawai:id,nama,nip']);
    }

    public function verifier()
    {
        return $this->belongsTo(Pegawai::class, 'diverifikasi_oleh')
            ->with(['pegawai:id,nama,nip']);
    }

    /**
     * Scope: hanya item yang belum dikembalikan.
     */
    public function scopeBelumDikembalikan($query)
    {
        return $query->where('status', 'DIPINJAM');
    }

    /**
     * Scope: hanya item yang sudah dikembalikan.
     */
    public function scopeSudahDikembalikan($query)
    {
        return $query->where('status', 'DIKEMBALIKAN');
    }
}
