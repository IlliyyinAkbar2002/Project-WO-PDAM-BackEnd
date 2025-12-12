<?php

namespace App\Http\Controllers;

use App\Models\DetailForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DetailFormController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = DetailForm::with('optionForm');

            if ($request->has('jenis_workorder_id')) {
                $query->where('jenis_workorder_id', $request->query('jenis_workorder_id'));
                $item = $query->get();
                return response()->json($item, 200);
            }

            $list = $query->get();
            return response()->json($list, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data progress workorder',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'jenis_workorder_id' => 'required|integer|exists:jenis_workorders,id',
                'nama_field' => 'required|string|max:255',
                'tipe_field' => 'required|string',
                'tipe_data' => 'nullable|string',
                'unit_satuan' => 'nullable|string',
                'sifat' => 'required|string',
                'min' => 'nullable|integer',
                'max' => 'nullable|integer',
                'parent' => 'nullable|integer',
                'keterangan' => 'nullable|string',
                'hint_text' => 'required|string',
                'order' => 'required|integer',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            $detailForm = DetailForm::create($validator->validated());
    
            return response()->json([
                'message' => 'Data detail form berhasil dibuat',
                'data' => $detailForm
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat menyimpan data detail form',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $detailForm = DetailForm::with('optionForm')->findOrFail($id);
            return response()->json($detailForm, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data detail form',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'jenis_workorder_id' => 'required|integer|exists:jenis_workorders,id',
                'nama_field' => 'required|string|max:255',
                'tipe_field' => 'required|string',
                'tipe_data' => 'nullable|string',
                'unit_satuan' => 'nullable|string',
                'sifat' => 'required|string',
                'min' => 'nullable|integer',
                'max' => 'nullable|integer',
                'parent' => 'nullable|integer',
                'keterangan' => 'nullable|string',
                'hint_text' => 'required|string',
                'order' => 'required|integer',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            $detailForm = DetailForm::findOrFail($id);
            $detailForm->update($validator->validated());
    
            return response()->json([
                'message' => 'Data detail form berhasil diperbarui',
                'data' => $detailForm
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat memperbarui data detail form',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $detailForm = DetailForm::findOrFail($id);
            $detailForm->delete();

            return response()->json([
                'message' => 'Data detail form berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat menghapus data detail form',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
