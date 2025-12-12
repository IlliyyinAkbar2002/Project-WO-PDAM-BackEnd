<?php

namespace App\Http\Controllers;

use App\Models\OptionForm;
use Illuminate\Http\Request;

class OptionFormController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($detail_form_id)
    {
        $optionForms = OptionForm::where('detail_form_id', $detail_form_id)->orderBy('order')->get();
        return response()->json($optionForms, 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $detail_form_id)
    {
        $validated = $request->validate([
            'nama_opsi' => 'required|string|max:255',
            'parent'    => 'nullable|integer',
            'order'     => 'required|integer',
        ]);

        $option = OptionForm::create([
            'detail_form_id' => $detail_form_id,
            'nama_opsi'      => $validated['nama_opsi'],
            'parent'         => $validated['parent'] ?? null,
            'order'          => $validated['order'],
        ]);

        return response()->json($option, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($detail_form_id, $id)
    {
        $optionForms = OptionForm::where('detail_form_id', $detail_form_id)->findOrFail($id);
        return response()->json($optionForms, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $detail_form_id, $id)
    {
        $optionForms = OptionForm::where('detail_form_id', $detail_form_id)->findOrFail($id);

        $validated = $request->validate([
            'nama_opsi' => 'required|string|max:255',
            'parent'    => 'nullable|integer',
            'order'     => 'required|integer',
        ]);

        $optionForms->update($validated);

        return response()->json($optionForms, 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($detail_form_id, $id)
    {
        $optionForms = OptionForm::where('detail_form_id', $detail_form_id)->findOrFail($id);
        $optionForms->delete();

        return response()->json([
            'message' => 'Option form deleted successfully'
        ]);

    }
}
