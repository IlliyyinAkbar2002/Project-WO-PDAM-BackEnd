<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Anggota tim per pengajuan lembur SPL.
 *
 * Mirror dari WoAssignmentMember (untuk WO post-approval), tapi
 * tabel ini hidup di fase pengajuan (sebelum WO terbentuk).
 */
class LemburSplMember extends Model
{
    use HasFactory;

    protected $table = 'lembur_spl_member';

    protected $guarded = [];

    public function lemburSpl()
    {
        return $this->belongsTo(LemburSpl::class, 'lembur_spl_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->with(['pegawai:id,nama,nip']);
    }
}
