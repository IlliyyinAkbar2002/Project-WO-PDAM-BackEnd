<?php

namespace App\Http\Controllers;

use App\Services\WorkorderActionService;
use Illuminate\Http\Request;

class WorkorderActionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'workorder_id' => 'required|exists:workorder,id',
            'action_id' => 'required|exists:m_action,id',
            'keterangan' => 'nullable|string|max:255',
            'waktu_mulai' => 'required|date',
            'sisa_durasi_menit' => 'nullable|integer',
            'estimasi_selesai' => 'nullable|date',
        ]);
        try {
            $action = (new WorkorderActionService())->createAction($validatedData);
            return response()->json([
                'message' => 'Workorder action created successfully',
                'data' => $action
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create workorder action',
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
