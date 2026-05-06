<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WoAssignmentMember
 *
 * Anggota tim yang ditugaskan per WO via workorder_assignment.
 * Menggantikan tabel workorder_petugas (DROP).
 * Kolom is_pic=true menandai PIC/koordinator tim.
 *
 * Lihat:
 *   - .antigravity/ERD_Physical.dbml (Table wo_assignment_member)
 */
class WoAssignmentMember extends Model
{
    use HasFactory;

    protected $table = 'wo_assignment_member';

    protected $guarded = [];

    protected $casts = [
        'is_pic' => 'boolean',
    ];

    public function assignment()
    {
        return $this->belongsTo(WorkorderAssignment::class, 'assignment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->with(['pegawai:id,nama,nip']);
    }
}
