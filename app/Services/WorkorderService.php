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
                'lokasi'             => $data['lokasi'] ?? null,
                'prioritas'          => $data['prioritas'],
                'status'             => $data['status'],
                'kode_pengaduan'     => $data['kode_pengaduan'] ?? null,
                'departemen_id'      => $data['departemen_id'],
                'jenis_workorder_id' => $data['jenis_workorder_id'],
                'pic_id'             => $data['pic_id'],
                'user_id'            => $data['user_id'],
            ]);
            return $workorder;
        });
    }
}