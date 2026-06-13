<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisWorkorder extends Model
{
    use HasFactory;
    protected $table = 'm_jenis_workorder';
    protected $guarded = [];

    protected $casts = [
        'kategori_form' => 'string',
    ];

    public function workorder()
    {
        return $this->hasMany(Workorder::class, 'jenis_workorder_id');
    }

    public function resolveKategoriModel(): ?string
    {
        return [
            'meter'         => WoMeter::class,
            'jaringan'      => WoJaringan::class,
            'infrastruktur' => WoInfrastruktur::class,
        ][$this->kategori_form] ?? null;
    }
}
