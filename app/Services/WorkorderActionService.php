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
      switch ((int) $action->action_id) {
        case 2:
          $this->handleFreeze($action, $workorder, $data);
          break;
        case 3:
          $this->handleResume($action, $workorder, $data);
          break;
        case 4:
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
      ->where('action_id', 2)
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
