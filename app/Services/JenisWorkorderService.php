<?php

namespace App\Services;

use App\Models\JenisWorkorder;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk master Jenis Workorder.
 *
 * Revisi Mei 2026: Logic create/update field dinamis (form_workorder +
 * detail_form) di-DROP karena tabel EAV sudah tidak ada. Jenis WO sekarang
 * hanya nama + kategori_form. Field kategori yang sebenarnya di-INSERT oleh
 * SPV di Tahap 5 ke tabel wo_meter / wo_jaringan / wo_infrastruktur — bukan
 * lagi lewat master Jenis WO.
 */
class JenisWorkorderService
{
    public function store(array $data): JenisWorkorder
    {
        return DB::transaction(function () use ($data) {
            return JenisWorkorder::create([
                'nama'          => $data['nama'],
                'kategori_form' => $data['kategori_form'],
            ]);
        });
    }

    public function update($id, array $data): JenisWorkorder
    {
        return DB::transaction(function () use ($id, $data) {
            $jenisWorkorder = JenisWorkorder::findOrFail($id);

            $updateData = [];
            if (array_key_exists('nama', $data)) {
                $updateData['nama'] = $data['nama'];
            }
            if (array_key_exists('kategori_form', $data)) {
                $updateData['kategori_form'] = $data['kategori_form'];
            }

            if (! empty($updateData)) {
                $jenisWorkorder->update($updateData);
            }

            return $jenisWorkorder->fresh();
        });
    }
}
