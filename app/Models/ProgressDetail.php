<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressDetail extends Model
{
    use HasFactory;

    protected $table = 'progress_detail';
    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Parent progress workorder.
     */
    public function progressWorkorder()
    {
        return $this->belongsTo(ProgressWorkorder::class, 'progress_workorder_id');
    }

    /**
     * User who reviewed this detail (SPV).
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')
            ->with(['pegawai:id,nama,nip']);
    }
}
