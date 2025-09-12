<?php

namespace App\Services;

use App\Models\DetailProgress;
use App\Models\ProgressWorkorder;
use App\Models\Workorder;

class ProgressWorkorderService
{
    public function createInitialProgress(int $workOrderId): void
    {
        ProgressWorkorder::create([
            'workorder_id' => $workOrderId,
            'tipe_progress' => 'Mulai',
            'order' => 0,
        ]);

        $progressSelesai = ProgressWorkorder::create([
            'workorder_id' => $workOrderId,
            'tipe_progress' => 'Selesai',
            'order' => 1,
        ]);

        $workorder = Workorder::with('jenisWorkorder.detailForm')->findOrFail($workOrderId);

        $detailForms = $workorder->jenisWorkorder->detailForm;

        foreach ($detailForms as $detailForm) {
            DetailProgress::create([
                'progress_workorder_id' => $progressSelesai->id,
                'detail_form_id' => $detailForm->id,
                'value' => '',
            ]);
        }
    }

    public function addWorkorderProgress(int $workOrderId)
    {
        $maxOrder = ProgressWorkorder::where('workorder_id', $workOrderId)
            ->max('order');

        if (is_null($maxOrder)) {
            $maxOrder = 0;
        }

        // geser finish ke belakang
        ProgressWorkorder::where('workorder_id', $workOrderId)
            ->where('order', $maxOrder)
            ->increment('order');

        // insert progress baru di posisi maxSeq (sebelum finish)
        ProgressWorkorder::create([
            'workorder_id' => $workOrderId,
            'tipe_progress' => 'Progress ' . ($maxOrder),
            'order' => $maxOrder,
        ]);

        return response()->json(['message' => 'Progress ditambahkan'], 201);
    }

    public function updateStatusOnSubmit(int $progressId): void
    {
        $progress = ProgressWorkorder::with('workorder')->findOrFail($progressId);
        if ($progress->tipe_progress === 'Mulai') {
            $progress->workorder->update(['status_id' => 7]);
        }
        if ($progress->tipe_progress === 'Selesai') {
            $progress->workorder->update(['status_id' => 5]);
        }
    }
}
