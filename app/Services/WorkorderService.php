<?php

namespace App\Services;

use App\Models\MasterAction;
use App\Models\Status;
use App\Models\Workorder;
use Illuminate\Support\Facades\DB;

class WorkorderService
{
  public function createWorkorders(array $data)
  {
    return DB::transaction(function () use ($data) {

      $penugasanActionId = $this->ensureDefaultActionExists();

      $statusId    = $this->statusIdByKode('DITUGASKAN_KE_SPV')
        ?? $this->statusIdByKode('DISETUJUI')
        ?? Status::query()->min('id');
      $assignedTo  = (int) ($data['assigned_to'] ?? $data['pic_id']);

      $workorder = Workorder::create([
        'nama_workorder'     => $data['nama_workorder'] ?? $data['judul_pekerjaan'],
        'deskripsi'          => $data['deskripsi'] ?? null,
        'tanggal_mulai'      => $data['tanggal_mulai'] ?? $data['waktu_penugasan'],
        'tanggal_laporan'    => $data['tanggal_laporan'] ?? null,
        'lokasi'             => $data['lokasi'] ?? null,
        'assigned_to'        => $assignedTo,
        'created_by_user_id' => $data['created_by_user_id'] ?? null,
        'lembur_spl_id'      => $data['lembur_spl_id'] ?? null,
        'status_id'          => $statusId,
        'jenis_workorder_id' => $data['jenis_workorder_id'],
        'departemen_id'      => $data['departemen_id'] ?? null,
        'pengaduan_id'       => $data['pengaduan_id'] ?? null,
        'kpi_id'             => $data['kpi_id'] ?? null,
        'prioritas'          => $data['prioritas'] ?? null,
      ]);

      (new WorkorderActionService())->createAction([
        'workorder_id'     => $workorder->id,
        'action_id'        => $penugasanActionId,
        'actor_id'         => $data['created_by_user_id'] ?? $assignedTo,
        'keterangan'       => 'Superadmin membuat WO dan menugaskan SPV',
        'waktu_mulai'      => $data['tanggal_mulai'] ?? $data['waktu_penugasan'],
      ]);

      return [
        $workorder->load(
          'assignmentMembers',
          'pic',
          'status',
          'jenisWorkorder',
          'lemburSpl'
        ),
      ];
    });
  }

  private function ensureDefaultActionExists(): int
  {
    $action = MasterAction::firstOrCreate(
      ['kode' => 'PENUGASAN'],
      ['nama' => 'Penugasan', 'keterangan' => 'Penugasan kepada pegawai']
    );

    return (int) $action->id;
  }

  private function statusIdByKode(string $kode): ?int
  {
    return Status::where('kode', $kode)->value('id');
  }
}
