<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipeProgress extends Model
{
    use HasFactory;

    protected $table = 'm_tipe_progress';
    protected $guarded = [];

    /**
     * Progres yang merujuk ke tipe ini.
     *
     * Catatan: kolom FK `tipe_progress_id` di `progress_workorder` baru
     * ditambahkan di TKT-05. Sampai migration itu dijalankan, relasi ini
     * akan menghasilkan koleksi kosong — itu wajar, bukan bug.
     */
    public function progressWorkorder()
    {
        return $this->hasMany(ProgressWorkorder::class, 'tipe_progress_id');
    }
}
