<?php

namespace App\Http\Controllers;

use App\Models\DetailForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DetailFormController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * URL: /jenis-workorder/{jenis_workorder_id}/detail-form
     *   atau (legacy, form_workorder_id diabaikan):
     *      /jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form
     *
     * @return \Illuminate\Http\Response
     */
    public function index($jenis_workorder_id, $form_workorder_id = null)
    {
        try {
            $detailForms = DetailForm::where('jenis_workorder_id', $jenis_workorder_id)
                ->orderBy('order')
                ->get();

            return response()->json($detailForms, 200);
        } catch (\Exception $e) {
            Log::error('DetailForm index failed', [
                'jenis_workorder_id' => $jenis_workorder_id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $jenis_workorder_id, $form_workorder_id = null)
    {
        $validated = $request->validate([
            'nama_field'  => 'required|string|max:255',
            'tipe_field'  => 'required|string|max:50',
            'tipe_data'   => 'nullable|string|max:50',
            'unit_satuan' => 'nullable|string|max:50',
            'sifat'       => 'required|string|max:50',
            'min'         => 'nullable|integer',
            'max'         => 'nullable|integer',
            'parent'      => 'nullable|integer',
            'keterangan'  => 'nullable|string',
            'hint_text'   => 'required|string|max:255',
            'order'       => 'required|integer',
        ]);

        try {
            $detailForm = DetailForm::create(array_merge($validated, [
                'jenis_workorder_id' => $jenis_workorder_id,
                'parent'             => $validated['parent'] ?? 0,
            ]));

            return response()->json($detailForm, 201);
        } catch (\Exception $e) {
            Log::error('DetailForm store failed', [
                'jenis_workorder_id' => $jenis_workorder_id,
                'payload'            => $request->all(),
                'error'              => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($jenis_workorder_id, $form_workorder_id, $id = null)
    {
        // Dukung dua pola URL: nested di bawah form-workorder (legacy) atau
        // flat di bawah jenis-workorder (baru). Jika hanya 2 argumen yang
        // sampai, argumen ke-2 sebenarnya adalah id detail_form.
        if ($id === null) {
            $id = $form_workorder_id;
        }

        try {
            $detailForm = DetailForm::where('jenis_workorder_id', $jenis_workorder_id)
                ->findOrFail($id);

            return response()->json($detailForm, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $jenis_workorder_id, $form_workorder_id, $id = null)
    {
        if ($id === null) {
            $id = $form_workorder_id;
        }

        $validated = $request->validate([
            'nama_field'  => 'sometimes|required|string|max:255',
            'tipe_field'  => 'sometimes|required|string|max:50',
            'tipe_data'   => 'nullable|string|max:50',
            'unit_satuan' => 'nullable|string|max:50',
            'sifat'       => 'sometimes|required|string|max:50',
            'min'         => 'nullable|integer',
            'max'         => 'nullable|integer',
            'parent'      => 'nullable|integer',
            'keterangan'  => 'nullable|string',
            'hint_text'   => 'sometimes|required|string|max:255',
            'order'       => 'sometimes|required|integer',
        ]);

        try {
            $detailForm = DetailForm::where('jenis_workorder_id', $jenis_workorder_id)
                ->findOrFail($id);

            $detailForm->update($validated);

            return response()->json($detailForm, 200);
        } catch (\Exception $e) {
            Log::error('DetailForm update failed', [
                'jenis_workorder_id' => $jenis_workorder_id,
                'id'                 => $id,
                'error'              => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($jenis_workorder_id, $form_workorder_id, $id = null)
    {
        if ($id === null) {
            $id = $form_workorder_id;
        }

        try {
            $detailForm = DetailForm::where('jenis_workorder_id', $jenis_workorder_id)
                ->findOrFail($id);

            $detailForm->delete();

            return response()->json([
                'message' => 'Detail form deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
