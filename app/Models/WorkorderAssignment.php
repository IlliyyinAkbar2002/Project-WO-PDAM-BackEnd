<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WorkorderAssignment
 *
 * Event assign WO oleh SPV — 1 row per workorder (1:1). Di-INSERT di
 * Tahap 5 (SPV klik "Tugaskan") bersamaan dengan insert `wo_{kategori}`
 * + `workorder_petugas` dalam satu DB transaction.
 *
 * Menjawab pertanyaan bisnis "kapan + siapa SPV yang melakukan assign +
 * catatan apa yang diberikan" tanpa perlu JOIN ke tabel audit
 * `workorder_action`.
 *
 * Lihat:
 *   - .cursor/ERD_Physical.dbml (Group 4, Table workorder_assignment)
 *   - .cursor/Flow_WO.md Section 3.5 (contoh transaction)
 */
class WorkorderAssignment extends Model
{
    use HasFactory;

    protected $table = 'workorder_assignment';

    protected $guarded = [];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function spv()
    {
        return $this->belongsTo(User::class, 'spv_user_id')
            ->with(['pegawai:id,nama,nip']);
    }

    /**
     * Anggota tim yang ditugaskan untuk WO ini.
     * Relasi 1:N dari workorder_assignment ke wo_assignment_member.
     */
    public function members()
    {
        return $this->hasMany(WoAssignmentMember::class, 'assignment_id');
    }

    /**
     * PIC / koordinator tim (is_pic = true).
     */
    public function picMember()
    {
        return $this->hasOne(WoAssignmentMember::class, 'assignment_id')
            ->where('is_pic', true);
    }
}
