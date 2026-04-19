<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkorderAction extends Model
{
    use HasFactory;
    protected $table = 'workorder_action';
    protected $guarded = [];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function action()
    {
        return $this->belongsTo(MasterAction::class, 'action_id');
    }

    /**
     * User yang melakukan aksi ini (TKT-06).
     *
     * - Untuk PENUGASAN (auto-spawn saat WO dibuat): SPV / PIC workorder.
     * - Untuk FREEZE / RESUME / EXTEND (via endpoint `POST /workorder-action`):
     *   user yang sedang login, diinject oleh controller — bukan dari payload
     *   client, supaya audit trail tidak bisa dipalsukan.
     *
     * Eager-load relasi `pegawai` supaya response API bisa langsung
     * menampilkan nama & NIP pelaku tanpa N+1 query tambahan.
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')
            ->with(['pegawai:id,nama,nip']);
    }
}
