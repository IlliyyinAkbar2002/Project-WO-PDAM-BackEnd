<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return $this->belongsTo(Pegawai::class, 'spv_pegawai_id');
    }

    public function location()
    {
        return $this->belongsTo(MasterLocation::class, 'location_id');
    }

    public function members()
    {
        return $this->hasMany(WoAssignmentMember::class, 'assignment_id');
    }

    public function picMember()
    {
        return $this->hasOne(WoAssignmentMember::class, 'assignment_id')
            ->where('is_pic', true);
    }
}
