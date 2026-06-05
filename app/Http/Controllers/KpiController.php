<?php

namespace App\Http\Controllers;

use App\Services\KpiService;

class KpiController extends Controller
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json([
            // 'auth_user' => auth()->user(),
            // 'workorder_count' => \App\Models\Workorder::count(),
            // 'workorder_departemen_count' => \App\Models\Workorder::where(
            //     'departemen_id',
            //     auth()->user()?->departemen_id
            // )->count(),
            'success' => true,
            'data' => $this->kpiService->getSummary(),
            'completion_rate' => $this->kpiService->completionRate()
        ]);
    }

    public function departemen($id)
    {
        return response()->json([
            'success' => true,
            'data' => $this->kpiService->getByDepartemen($id)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store()
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
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * @param  int  $id
     */
    public function update()
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
