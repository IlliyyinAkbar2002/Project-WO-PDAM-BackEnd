<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisWorkorder extends Model
{
    use HasFactory;
    protected $table = 'm_jenis_workorder';
    protected $guarded = [];

    public function formWorkorder()
    {
        return $this->hasMany(FormWorkorder::class, 'jenis_workorder_id');
    }

    public function workorder()
    {
        return $this->hasMany(Workorder::class, 'jenis_workorder_id');
    }

    /**
     * Semua field dinamis (detail_form) yang dimiliki jenis WO ini.
     *
     * Relasi ini mengikuti struktur DB aktual: tabel `detail_form` memiliki
     * kolom FK langsung `jenis_workorder_id` (lihat migration
     * 2025_03_08_074202_create_detail_forms_table.php). Dipakai oleh
     * `ProgressWorkorderService::createInitialProgress()` untuk men-spawn
     * row `detail_progress` awal saat SPV meng-assign WO.
     *
     * Catatan: ada pola lain yang mengasumsikan `detail_form.form_workorder_id`
     * (lihat FormWorkorder::detailForm() & JenisWorkorderService), tapi kolom
     * itu tidak pernah ada di migration — pola tersebut berstatus broken dan
     * di luar scope ticket ini.
     */
    public function detailForm()
    {
        return $this->hasMany(DetailForm::class, 'jenis_workorder_id');
    }
}
