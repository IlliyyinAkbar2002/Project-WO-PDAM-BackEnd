<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LemburSpl extends Model
{
    use HasFactory;

    protected $table = 'lembur_spl';
    protected $guarded = [];

    protected $casts = [
        'waktu_pengajuan'   => 'datetime',
        'waktu_verifikasi'  => 'datetime',
        'tanggal_lembur'    => 'date',
        'estimasi_jam'      => 'integer',
    ];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    /**
     * Pemohon (SPV / pegawai yang mengajukan lembur).
     * Diisi otomatis dari auth user di endpoint store.
     */
    public function pemohon()
    {
        return $this->belongsTo(User::class, 'pemohon_id')
            ->with(['pegawai:id,nama,nip']);
    }

    /**
     * Verifikator (Superadmin/Manager yang approve atau reject).
     * Diisi saat update (approval flow).
     */
    public function verifikator()
    {
        return $this->belongsTo(User::class, 'verifikator_id')
            ->with(['pegawai:id,nama,nip']);
    }

    /**
     * Anggota tim yang diajukan untuk lembur ini (1:N).
     * Disimpan di tabel `lembur_spl_member`.
     */
    public function members()
    {
        return $this->hasMany(LemburSplMember::class, 'lembur_spl_id');
    }
}
