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

    /**
     * Resolve nama kelas Eloquent untuk tabel kategori form (Class Table
     * Inheritance) berdasarkan kolom `kategori_form`.
     *
     * Dipakai di endpoint `POST /v1/workorder/{id}/assign-staff` untuk
     * dispatch create form kategori yang sesuai (WoMeter / WoJaringan /
     * WoInfrastruktur) — gantinya pola EAV lama (detail_form) yang sudah
     * di-drop per Mei 2026.
     *
     * @return string|null FQCN model kategori, atau null kalau kategori
     *                     tidak dikenali.
     */
    public function resolveKategoriModel(): ?string
    {
        return [
            'meter'         => WoMeter::class,
            'jaringan'      => WoJaringan::class,
            'infrastruktur' => WoInfrastruktur::class,
        ][$this->kategori_form] ?? null;
    }
}
