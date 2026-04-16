<?php

namespace App\Http\Controllers;

use App\Models\DetailForm;
use Illuminate\Http\Request;

class DetailFormController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($form_workorder_id)
    {
        $detailForms = DetailForm::where('form_workorder_id', $form_workorder_id)->orderBy('order')->get();
        return response()->json($detailForms, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $form_workorder_id)
    {
        $validated = $request->validate([
            'nama_opsi' => 'required|string|max:255',
            'parent'    => 'nullable|integer',
            'order'     => 'required|integer',
        ]);

        $detailForm = DetailForm::create([
            'form_workorder_id' => $form_workorder_id,
            'nama_opsi'         => $validated['nama_opsi'],
            'parent'            => $validated['parent'] ?? 0,
            'order'             => $validated['order'],
        ]);

        return response()->json($detailForm, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($form_workorder_id, $id)
    {
        $detailForm = DetailForm::where('form_workorder_id', $form_workorder_id)->findOrFail($id);
        return response()->json($detailForm, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $form_workorder_id, $id)
    {
        $detailForm = DetailForm::where('form_workorder_id', $form_workorder_id)->findOrFail($id);

        $validated = $request->validate([
            'nama_opsi' => 'required|string|max:255',
            'parent'    => 'nullable|integer',
            'order'     => 'required|integer',
        ]);

        $detailForm->update($validated);

        return response()->json($detailForm, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($form_workorder_id, $id)
    {
        $detailForm = DetailForm::where('form_workorder_id', $form_workorder_id)->findOrFail($id);
        $detailForm->delete();

        return response()->json([
            'message' => 'Detail form deleted successfully'
        ]);
    }
}
