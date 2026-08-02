<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DokumentasiProgress extends Model
{
    use HasFactory;
    protected $table = 'dokumentasi_progress';
    protected $guarded = [];

    /**
     * `url_lengkap` sengaja di-append (bukan mengganti `url`) supaya klien lama
     * yang sudah merakit host sendiri dari path relatif tidak ikut pecah.
     */
    protected $appends = ['url_lengkap'];

    /**
     * Kolom `url` menyimpan path relatif disk 'public' (hasil
     * UploadedFile::store('dokumentasi_progress', 'public')), bukan URL.
     * Accessor ini yang mengubahnya jadi URL siap-pakai untuk FE.
     *
     * Jangan dinamai getUrlAttribute — itu akan menutupi kolom `url` asli.
     */
    public function getUrlLengkapAttribute(): ?string
    {
        $path = $this->url;

        if (blank($path)) {
            return null;
        }

        // Namanya "url", jadi data lama/manual bisa saja sudah absolut.
        // Jangan diprefiks dua kali.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public function progressWorkorder()
    {
        return $this->belongsTo(ProgressWorkorder::class, 'progress_workorder_id');
    }
}
