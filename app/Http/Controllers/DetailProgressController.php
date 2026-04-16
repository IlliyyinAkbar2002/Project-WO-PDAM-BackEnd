<?php

namespace App\Http\Controllers;
use App\Models\DetailProgress;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class DetailProgressController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $query = DetailProgress::with('progressWorkorder.dokumentasiProgress', 'detailForm.formWorkorder');
            if (request()->has('progress_workorder_id')) {
                $query->where('progress_workorder_id', request()->query('progress_workorder_id'));
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // try {
        //     $detailProgress = DetailProgress::findOrFail($id);
        //     return response()->json($detailProgress);
        // } catch (ModelNotFoundException $e) {
        //     return response()->json(['error' => 'Detail progress not found'], 404);
        // }
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
        $request->validate([
            'value' => 'nullable|string|max:255',
        ]);

        try {
            $detailProgress = DetailProgress::findOrFail($id);
            $detailProgress->update($request->all());

            return response()->json($detailProgress);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Detail progress not found'], 404);
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
        //
    }
}
