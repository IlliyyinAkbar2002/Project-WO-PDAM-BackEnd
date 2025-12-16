<?php

namespace App\Services;

use App\Models\JenisWorkorder;
use App\Models\FormWorkorder;
use App\Models\DetailForm;
use Illuminate\Support\Facades\DB;

class JenisWorkorderService
{
  public function store(array $data): JenisWorkorder
  {
    return DB::transaction(function () use ($data) {
      $jenisWorkorder = JenisWorkorder::create([
        'nama' => $data['nama'],
      ]);

      // Jika form_workorder tidak dikirim, return langsung
      $formWorkorders = $data['form_workorder'] ?? [];
      if (empty($formWorkorders)) {
        return $jenisWorkorder->load('formWorkorder.detailForm');
      }

      // Mapping untuk ID dummy -> ID asli
      $idMap = [];
      $formWorkordersToUpdate = [];
      $detailFormsToUpdate = [];

      // Tahap 1: Simpan FormWorkorder dan map ID dummy
      foreach ($formWorkorders as $index => $form) {
        $dummyId = $form['id'] ?? 'temp_' . $index;
        $formWorkorder = $jenisWorkorder->formWorkorder()->create([
          'kpi_id' => $form['kpi_id'],
          'nama_field' => $form['nama_field'],
          'tipe_field' => $form['tipe_field'],
          'tipe_data' => $form['tipe_data'] ?? null,
          'unit_satuan' => $form['unit_satuan'] ?? null,
          'min' => $form['min'] ?? null,
          'max' => $form['max'] ?? null,
          'sifat' => $form['sifat'],
          'parent' => 0,
          'keterangan' => $form['keterangan'] ?? null,
          'hint_text' => $form['hint_text'],
          'order' => $form['order'],
        ]);

        // Simpan mapping
        $idMap[$dummyId] = $formWorkorder->id;
        $formWorkordersToUpdate[] = [
          'instance' => $formWorkorder,
          'dummy_parent' => $form['parent'] ?? 0,
          'detail_form_raw' => $form['tipe_field'] === 'dropdown' ? ($form['detail_form'] ?? []) : [],
        ];
      }

      // Tahap 2: Simpan DetailForm dan map ID dummy
      foreach ($formWorkordersToUpdate as $item) {
        $formWorkorder = $item['instance'];
        foreach ($item['detail_form_raw'] as $optIndex => $detail) {
          $dummyDetailId = $detail['id'] ?? 'temp_opt_' . $optIndex;
          $dummyDetailParent = $detail['parent'] ?? 0;
          $detailForm = $formWorkorder->detailForm()->create([
            'nama_opsi' => $detail['nama_opsi'],
            'parent' => 0,
            'order' => $detail['order'],
          ]);
          // Simpan mapping
          $idMap[$dummyDetailId] = $detailForm->id;
          $detailFormsToUpdate[] = [
            'instance' => $detailForm,
            'dummy_parent' => $dummyDetailParent,
          ];
        }
      }

      // Tahap 3: Update parent FormWorkorder
      foreach ($formWorkordersToUpdate as $item) {
        $dummyParent = $item['dummy_parent'];
        if ($dummyParent !== 0 && !isset($idMap[$dummyParent])) {
        }
        $realParent = $dummyParent !== 0 && isset($idMap[$dummyParent]) ? $idMap[$dummyParent] : 0;
        $item['instance']->update(['parent' => $realParent]);
      }

      // Tahap 4: Update parent DetailForm
      foreach ($detailFormsToUpdate as $item) {
        $dummyParent = $item['dummy_parent'];
        if ($dummyParent !== 0 && !isset($idMap[$dummyParent])) {
        }
        $realParent = $dummyParent !== 0 && isset($idMap[$dummyParent]) ? $idMap[$dummyParent] : 0;
        $item['instance']->update(['parent' => $realParent]);
      }

      return $jenisWorkorder->load('formWorkorder.detailForm');
    });
  }

  public function update($id, array $data): JenisWorkorder
  {
    return DB::transaction(function () use ($id, $data) {
      $jenisWorkorder = JenisWorkorder::findOrFail($id);
      
      // Update hanya field yang dikirim
      $updateData = [];
      if (isset($data['nama'])) {
        $updateData['nama'] = $data['nama'];
      }
      if (!empty($updateData)) {
        $jenisWorkorder->update($updateData);
      }

      // Jika form_workorder tidak dikirim, return langsung tanpa modifikasi
      $formWorkorders = $data['form_workorder'] ?? null;
      if ($formWorkorders === null) {
        return $jenisWorkorder->load('formWorkorder.detailForm');
      }

      $formIds = [];
      foreach ($formWorkorders as $formData) {
        if ($formData['id'] > 0) {
          $form = FormWorkorder::where('jenis_workorder_id', $jenisWorkorder->id)
            ->where('id', $formData['id'])
            ->firstOrFail();
          $form->update([
            'kpi_id' => $formData['kpi_id'],
            'nama_field' => $formData['nama_field'],
            'tipe_field' => $formData['tipe_field'],
            'tipe_data' => $formData['tipe_data'] ?? null,
            'unit_satuan' => $formData['unit_satuan'] ?? null,
            'sifat' => $formData['sifat'],
            'min' => $formData['min'] ?? null,
            'max' => $formData['max'] ?? null,
            'parent' => $formData['parent'],
            'keterangan' => $formData['keterangan'] ?? null,
            'hint_text' => $formData['hint_text'],
            'order' => $formData['order'],
          ]);
        } else {
          $form = $jenisWorkorder->formWorkorder()->create([
            'kpi_id' => $formData['kpi_id'],
            'nama_field' => $formData['nama_field'],
            'tipe_field' => $formData['tipe_field'],
            'tipe_data' => $formData['tipe_data'],
            'unit_satuan' => $formData['unit_satuan'] ?? null,
            'sifat' => $formData['sifat'],
            'min' => $formData['min'] ?? null,
            'max' => $formData['max'] ?? null,
            'parent' => $formData['parent'],
            'keterangan' => $formData['keterangan'] ?? null,
            'hint_text' => $formData['hint_text'],
            'order' => $formData['order'],
          ]);
        }
        $formIds[] = $form->id;
        $detailIds = [];
        if ($formData['tipe_field'] === 'dropdown' && !empty($formData['detail_form'])) {
          foreach ($formData['detail_form'] as $detailData) {
            if ($detailData['id'] > 0) {
              $detail = DetailForm::where('form_workorder_id', $form->id)
                ->where('id', $detailData['id'])
                ->firstOrFail();
              $detail->update([
                'nama_opsi' => $detailData['nama_opsi'],
                'parent' => $detailData['parent'],
                'order' => $detailData['order'],
              ]);
            } else {
              $detail = $form->detailForm()->create([
                'nama_opsi' => $detailData['nama_opsi'],
                'parent' => $detailData['parent'],
                'order' => $detailData['order'],
              ]);
            }
            $detailIds[] = $detail->id;
          }
          DetailForm::where('form_workorder_id', $form->id)
            ->whereNotIn('id', $detailIds)
            ->delete();
        } else {
          DetailForm::where('form_workorder_id', $form->id)->delete();
        }
      }
      FormWorkorder::where('jenis_workorder_id', $jenisWorkorder->id)
        ->whereNotIn('id', $formIds)
        ->delete();
      return $jenisWorkorder->load('formWorkorder.detailForm');
    });
  }
}
