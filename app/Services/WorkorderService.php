<?php

namespace App\Services;

use App\Models\LemburSpl;
use App\Models\Workorder;
use Illuminate\Support\Facades\DB;

class WorkorderService
{
  public function createWorkorders(array $data)
  {
    return DB::transaction(function () use ($data) {
      $createdWorkorders = [];
      foreach ($data['petugas_id'] as $petugasId) {
        $lemburSplId = null;
        $statusId = 2;

        if ((int) $data['tipe_workorder_id'] === 2) {
          $lemburSpl = LemburSpl::create([
            'status_id' => 1,
            'waktu_pengajuan' => now(),
          ]);
          $lemburSplId = $lemburSpl->id;
          $statusId = 1;
        }
        $workorder = Workorder::create([
          'judul_pekerjaan'    => $data['judul_pekerjaan'],
          'waktu_penugasan'    => $data['waktu_penugasan'],
          'estimasi_durasi'    => $data['estimasi_durasi'],
          'unit_waktu'         => $data['unit_waktu'],
          'estimasi_selesai'   => $data['estimasi_selesai'],
          'longitude'          => $data['longitude'],
          'latitude'           => $data['latitude'],
          'petugas_id'         => $petugasId,
          'pic_id'             => $data['pic_id'],
          'lembur_spl_id'      => $lemburSplId,
          'status_id'          => $statusId,
          'jenis_workorder_id' => $data['jenis_workorder_id'],
          'jenis_lokasi_id'    => $data['jenis_lokasi_id'],
          'tipe_workorder_id'  => $data['tipe_workorder_id'],
        ]);

        if ($statusId === 2) {
          (new ProgressWorkorderService())->createInitialProgress($workorder->id);
          (new WorkorderActionService())->createAction([
            'workorder_id'      => $workorder->id,
            'action_id'         => 1,
            'keterangan'        => 'Penugasan awal',
            'waktu_mulai'       => $data['waktu_penugasan'],
            'estimasi_selesai'  => $data['estimasi_selesai'],
          ]);
        }
        $createdWorkorders[] = $workorder;
      }

      return $createdWorkorders;
    });
  }
}
