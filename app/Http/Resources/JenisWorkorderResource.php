<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
            'id' => $this->id,
            'nama' => $this->nama,
            'kpi_id' => $this->kpi_id,
            'detail_form' => DetailFormResource::collection($this->detailForm),
        ];
    }
}

class DetailFormResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_field' => $this->nama_field,
            'tipe_data' => $this->tipe_data,
            'unit_satuan' => $this->unit_satuan,
            'min' => $this->min,
            'max' => $this->max,
            'tipe_field' => $this->tipe_field,
            'sifat' => $this->sifat,
            'keterangan' => $this->keterangan,
            'parent' => $this->parent,
            'order' => $this->order,
            'option_form' => OptionFormResource::collection($this->optionForm),
        ];
    }
}

class OptionFormResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_opsi' => $this->nama_opsi,
            'parent' => $this->parent,
            'order' => $this->order,
        ];
    }
}
