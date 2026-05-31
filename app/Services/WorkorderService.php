<?php

namespace App\Services;

use App\Models\Workorder;
use Illuminate\Support\Facades\DB;

class WorkorderService
{
    public function createWorkorders(array $data)
    {
        return DB::transaction(function () use ($data) {
            $workorder = Workorder::create([
                'nama_workorder'     => $data['nama_workorder'],
                'deskripsi'          => $data['deskripsi'] ?? null,
                'lokasi'             => $data['lokasi'],
                'prioritas'          => $data['prioritas'],
                'status'             => $data['status'],
                'kode_pengaduan'     => $data['kode_pengaduan'],
                'departemen_id'      => $data['departemen_id'],
                'jenis_workorder_id' => $data['jenis_workorder_id'],
                'assigned_to'        => $data['assigned_to'],
                'created_by'         => $data['created_by'],
            ]);
            return $workorder;
        });
    }
}