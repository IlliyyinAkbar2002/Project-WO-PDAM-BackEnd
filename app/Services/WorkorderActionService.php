<?php

namespace App\Services;

use App\Models\Workorder;
use App\Models\WorkorderAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WorkorderActionService
{
  public function createAction(array $data)
  {
    if (DB::transactionLevel() > 0) {
      return $this->processAction($data);
    }

    return DB::transaction(function () use ($data) {
      return $this->processAction($data);
    });
  }
  public function processAction(array $data)
  {
    return DB::transaction(function () use ($data) {

      $workorder = Workorder::findOrFail($data['workorder_id']);
      $action = WorkorderAction::create($data);

      // Branching pakai slug `kode` (PENUGASAN / FREEZE / RESUME / EXTEND)
      // bukan id numerik — id bisa berubah kalau master data di-re-seed.
      switch ($action->action->kode ?? null) {
        case 'FREEZE':
          $this->handleFreeze($action, $workorder, $data);
          break;
        case 'RESUME':
          $this->handleResume($action, $workorder, $data);
          break;
        case 'EXTEND':
          $this->handleExtend($action, $workorder, $data);
          break;
      }
      return $action;
    });
  }

  public function handleFreeze($action, $workorder, $data): void
  {
    $sisaDurasi = Carbon::parse($workorder->estimasi_selesai)
      ->diffInMinutes(Carbon::parse($data['waktu_mulai']));
    $statusSebelumnya = $workorder->status_id;

    $action->update([
      'sisa_durasi_menit' => $sisaDurasi,
      'status_workorder'  => $statusSebelumnya,
    ]);

    $workorder->update([
      'status_id' => 8,
    ]);
  }

  public function handleResume($action, $workorder, $data): void
  {
    $freezeAction = WorkorderAction::where('workorder_id', $workorder->id)
      ->whereHas('action', fn ($q) => $q->where('kode', 'FREEZE'))
      ->latest('waktu_mulai')
      ->first();

    if ($freezeAction) {
      $sisaDurasi = $freezeAction->sisa_durasi_menit;
      $statusSebelumnya = $freezeAction->status_workorder;
      $estimasiBaru = Carbon::parse($data['waktu_mulai'])->addMinutes($sisaDurasi);

      $workorder->update([
        'estimasi_selesai' => $estimasiBaru,
        'status_id'        => $statusSebelumnya,
      ]);

      $action->update([
        'estimasi_selesai' => $estimasiBaru,
      ]);
    }
  }

  public function handleExtend($action, $workorder, $data): void
  {
    $workorder->update([
      'estimasi_selesai' => $data['estimasi_selesai'],
    ]);
  }
}
