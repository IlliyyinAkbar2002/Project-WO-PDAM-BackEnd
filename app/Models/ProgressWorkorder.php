<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressWorkorder extends Model
{
    use HasFactory;

    protected $table = 'progress_workorder';
    protected $guarded = [];
    protected $casts = [
        'field_to_revise' => 'array',
    ];

    public function detailProgress()
    {
        return $this->hasMany(DetailProgress::class, 'progress_workorder_id');
    }

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function dokumentasiProgress()
    {
        return $this->hasMany(DokumentasiProgress::class, 'progress_workorder_id');
    }

    /**
     * Tipe progres (MULAI / PROGRESS / SELESAI) — hasil TKT-05.
     *
     * Sebelumnya `tipe_progress` disimpan sebagai string bebas di baris ini,
     * sehingga logic service / controller harus membandingkan string literal
     * ('Mulai' / 'Selesai') yang rapuh terhadap typo. Sekarang perbandingan
     * dilakukan via `->tipeProgress->kode` (mis. 'MULAI' / 'SELESAI').
     */
    public function tipeProgress()
    {
        return $this->belongsTo(TipeProgress::class, 'tipe_progress_id');
    }

    /**
     * Status progres (DRAFT / SUBMITTED / VERIFIED).
     *
     * Berbeda dari `workorder()->status` (status keseluruhan WO). Status di
     * sini menggambarkan siklus hidup satu langkah progres.
     */
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    /**
     * User yang men-submit progres ini.
     *
     * Sengaja diberi nama `submitter()`, bukan `petugas()`, supaya tidak
     * bingung dengan `Workorder::petugas()` (yang akan dipindah ke pivot
     * `workorder_petugas` di TKT-07). Eager-load relasi `pegawai` untuk
     * memudahkan response API menampilkan nama & NIP submitter.
     */
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id')
            ->with(['pegawai:id,nama,nip']);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')
            ->with(['pegawai:id,nama,nip']);
    }
}
