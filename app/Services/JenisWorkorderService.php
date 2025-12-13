<?php

namespace App\Services;

use App\Models\JenisWorkorder;
use App\Models\DetailForm;
use App\Models\OptionForm;
use Illuminate\Support\Facades\DB;

class JenisWorkorderService
{
  public function store(array $data): JenisWorkorder
  {
    return DB::transaction(function () use ($data) {
      $jenisWorkorder = JenisWorkorder::create([
        'nama' => $data['nama'],
        'kpi_id' => $data['kpi_id'],
      ]);

      // Jika detail_form tidak dikirim, return langsung
      $detailForms = $data['detail_form'] ?? [];
      if (empty($detailForms)) {
        return $jenisWorkorder->load('detailForm.optionForm');
      }

      // Mapping untuk ID dummy -> ID asli
      $idMap = [];
      $detailFormsToUpdate = [];
      $optionFormsToUpdate = [];

      // Tahap 1: Simpan DetailForm dan map ID dummy
      foreach ($detailForms as $index => $detail) {
        $dummyId = $detail['id'] ?? 'temp_' . $index;
        $detailForm = $jenisWorkorder->detailForm()->create([
          'nama_field' => $detail['nama_field'],
          'tipe_field' => $detail['tipe_field'],
          'tipe_data' => $detail['tipe_data'] ?? null,
          'unit_satuan' => $detail['unit_satuan'] ?? null,
          'min' => $detail['min'] ?? null,
          'max' => $detail['max'] ?? null,
          'sifat' => $detail['sifat'],
          'parent' => 0,
          'keterangan' => $detail['keterangan'] ?? null,
          'hint_text' => $detail['hint_text'],
          'order' => $detail['order'],
        ]);

        // Simpan mapping
        $idMap[$dummyId] = $detailForm->id;
        $detailFormsToUpdate[] = [
          'instance' => $detailForm,
          'dummy_parent' => $detail['parent'] ?? 0,
          'option_form_raw' => $detail['tipe_field'] === 'dropdown' ? ($detail['option_form'] ?? []) : [],
        ];
      }

      // Tahap 2: Simpan OptionForm dan map ID dummy
      foreach ($detailFormsToUpdate as $item) {
        $detailForm = $item['instance'];
        foreach ($item['option_form_raw'] as $optIndex => $option) {
          $dummyOptionId = $option['id'] ?? 'temp_opt_' . $optIndex;
          $dummyOptionParent = $option['parent'] ?? 0;
          $optionForm = $detailForm->optionForm()->create([
            'nama_opsi' => $option['nama_opsi'],
            'parent' => 0,
            'order' => $option['order'],
          ]);
          // Simpan mapping
          $idMap[$dummyOptionId] = $optionForm->id;
          $optionFormsToUpdate[] = [
            'instance' => $optionForm,
            'dummy_parent' => $dummyOptionParent,
          ];
        }
      }

      // Tahap 3: Update parent DetailForm
      foreach ($detailFormsToUpdate as $item) {
        $dummyParent = $item['dummy_parent'];
        if ($dummyParent !== 0 && !isset($idMap[$dummyParent])) {
        }
        $realParent = $dummyParent !== 0 && isset($idMap[$dummyParent]) ? $idMap[$dummyParent] : 0;
        $item['instance']->update(['parent' => $realParent]);
      }

      // Tahap 4: Update parent OptionForm
      foreach ($optionFormsToUpdate as $item) {
        $dummyParent = $item['dummy_parent'];
        if ($dummyParent !== 0 && !isset($idMap[$dummyParent])) {
        }
        $realParent = $dummyParent !== 0 && isset($idMap[$dummyParent]) ? $idMap[$dummyParent] : 0;
        $item['instance']->update(['parent' => $realParent]);
      }

      return $jenisWorkorder->load('detailForm.optionForm');
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
      if (isset($data['kpi_id'])) {
        $updateData['kpi_id'] = $data['kpi_id'];
      }
      if (!empty($updateData)) {
        $jenisWorkorder->update($updateData);
      }

      // Jika detail_form tidak dikirim, return langsung tanpa modifikasi detail
      $detailForms = $data['detail_form'] ?? null;
      if ($detailForms === null) {
        return $jenisWorkorder->load('detailForm.optionForm');
      }

      $detailIds = [];
      foreach ($detailForms as $detailData) {
        if ($detailData['id'] > 0) {
          $detail = DetailForm::where('jenis_workorder_id', $jenisWorkorder->id)
            ->where('id', $detailData['id'])
            ->firstOrFail();
          $detail->update([
            'nama_field' => $detailData['nama_field'],
            'tipe_field' => $detailData['tipe_field'],
            'tipe_data' => $detailData['tipe_data'] ?? null,
            'unit_satuan' => $detailData['unit_satuan'] ?? null,
            'sifat' => $detailData['sifat'],
            'min' => $detailData['min'] ?? null,
            'max' => $detailData['max'] ?? null,
            'parent' => $detailData['parent'],
            'keterangan' => $detailData['keterangan'] ?? null,
            'hint_text' => $detailData['hint_text'],
            'order' => $detailData['order'],
          ]);
        } else {
          $detail = $jenisWorkorder->detailForm()->create([
            'nama_field' => $detailData['nama_field'],
            'tipe_field' => $detailData['tipe_field'],
            'tipe_data' => $detailData['tipe_data'],
            'unit_satuan' => $detailData['unit_satuan'] ?? null,
            'sifat' => $detailData['sifat'],
            'min' => $detailData['min'] ?? null,
            'max' => $detailData['max'] ?? null,
            'parent' => $detailData['parent'],
            'keterangan' => $detailData['keterangan'] ?? null,
            'hint_text' => $detailData['hint_text'],
            'order' => $detailData['order'],
          ]);
        }
        $detailIds[] = $detail->id;
        $optionIds = [];
        if ($detailData['tipe_field'] === 'dropdown' && !empty($detailData['option_form'])) {
          foreach ($detailData['option_form'] as $optionData) {
            if ($optionData['id'] > 0) {
              $option = OptionForm::where('detail_form_id', $detail->id)
                ->where('id', $optionData['id'])
                ->firstOrFail();
              $option->update([
                'nama_opsi' => $optionData['nama_opsi'],
                'parent' => $optionData['parent'],
                'order' => $optionData['order'],
              ]);
            } else {
              $option = $detail->optionForm()->create([
                'nama_opsi' => $optionData['nama_opsi'],
                'parent' => $optionData['parent'],
                'order' => $optionData['order'],
              ]);
            }
            $optionIds[] = $option->id;
          }
          OptionForm::where('detail_form_id', $detail->id)
            ->whereNotIn('id', $optionIds)
            ->delete();
        } else {
          OptionForm::where('detail_form_id', $detail->id)->delete();
        }
      }
      DetailForm::where('jenis_workorder_id', $jenisWorkorder->id)
        ->whereNotIn('id', $detailIds)
        ->delete();
      return $jenisWorkorder->load('detailForm.optionForm');
    });
  }
}
