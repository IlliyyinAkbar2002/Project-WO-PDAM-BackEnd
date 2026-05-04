<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Response serializer untuk master Jenis Workorder.
 *
 * Revisi Mei 2026: Field `form_workorder` / `detail_form` (EAV lama) di-DROP.
 * Sekarang Jenis WO hanya mengekspos `nama` + `kategori_form`. Schema field
 * kategori yang sebenarnya (nomor_meter, diameter_pipa, dsb.) tinggal di
 * tabel `wo_meter` / `wo_jaringan` / `wo_infrastruktur`.
 */
class JenisWorkorderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'nama'          => $this->nama,
            'kategori_form' => $this->kategori_form,
        ];
    }
}
