<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WorkorderAssignment
 *
 * Event assign WO oleh SPV — 1 row per workorder (1:1). Di-INSERT di
 * Tahap 5 (SPV klik "Tugaskan") bersamaan dengan insert `wo_{kategori}`
 * + `wo_assignment_member` dalam satu DB transaction.
 *
 * Menjawab pertanyaan bisnis "kapan + siapa SPV yang melakukan assign +
 * catatan apa yang diberikan" tanpa perlu JOIN ke tabel audit
 * `workorder_action`.
 *
 * Revisi Mei 2026: menampung latitude/longitude dari FE untuk
 * disimpan + berelasi ke m_location (geofencing).
 *
 * Lihat:
 *   - .vscode_ai/ERD_Physical.dbml (Group 4, Table workorder_assignment)
 */
class WorkorderAssignment extends Model
{
    use HasFactory;

    protected $table = 'workorder_assignment';

    protected $guarded = [];

    protected $casts = [
        'assigned_at'      => 'datetime',
        'tanggal_mulai'    => 'datetime',
        'tanggal_selesai'  => 'datetime',
        'estimasi_selesai' => 'datetime',
        'latitude'         => 'float',
        'longitude'        => 'float',
        'accuracy'         => 'float',
        'location_id'      => 'integer',
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
     * Lokasi geofencing yang terkait dengan assignment ini.
     * FK location_id → m_location.id.
     */
    public function location()
    {
        return $this->belongsTo(MasterLocation::class, 'location_id');
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
