<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WoAssignmentMember extends Model
{
    use HasFactory;

    protected $table = 'wo_assignment_member';
    protected $guarded = [];

    protected $casts = [
        'is_pic' => 'boolean',
    ];

    protected $appends = ['peran'];

    public function getPeranAttribute(): string
    {
        return $this->is_pic ? 'koordinator' : 'anggota';
    }

    public function assignment()
    {
        return $this->belongsTo(WorkorderAssignment::class, 'assignment_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    /**
     * Alias backward-compat — anggota tim dimodelkan sebagai Pegawai.
     */
    public function user()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
