<?php

namespace App\Services;

use App\Models\LemburSpl;
use App\Models\MasterAction;
use App\Models\Workorder;
use Illuminate\Support\Facades\DB;

class WorkorderService
{
  public function createWorkorders(array $data)
  {
    return DB::transaction(function () use ($data) {
      // Pastikan master action "Penugasan" (id=1) selalu ada. Jika seeder
      // sempat gagal di tengah jalan, m_action bisa kosong sehingga insert
      // workorder_action akan lempar FK violation dan transaksi ini rollback
      // (gejala: WO seolah "berhasil" di FE tapi list tetap kosong).
      $penugasanActionId = $this->ensureDefaultActionExists();

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
          'longitude'          => $data['longitude'] ?? null,
          'latitude'           => $data['latitude'] ?? null,
          'location_id'        => $data['location_id'] ?? null,
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
            'action_id'         => $penugasanActionId,
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

  /**
   * Memastikan record master action "Penugasan" selalu tersedia dan
   * mengembalikan id-nya untuk dipakai saat membuat workorder_action awal.
   */
  private function ensureDefaultActionExists(): int
  {
    $action = MasterAction::firstOrCreate(
      ['nama' => 'Penugasan'],
      ['keterangan' => 'Penugasan kepada pegawai']
    );

    return (int) $action->id;
  }
}
