<?php

namespace App\Http\Controllers;

use App\Models\LemburSpl;

use App\Services\ProgressWorkorderService;
use App\Services\WorkorderActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LemburSplController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $lemburSpl = LemburSpl::with('workorder')->get();
            return response()->json($lemburSpl, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data lembur spl',
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
$validated = $request->validate([
'workorder_id' => 'required|exists:workorders,id',
'user_id' => 'required|exists:users,id'
]);

try {
    $lembur = LemburSpl::create([
        'workorder_id' => $validated['workorder_id'],
        'user_id' => $validated['user_id'],
        'status_id' => 2, // langsung sedang berjalan
        'waktu_verifikasi' => now()
    ]);

    return response()->json($lembur, 201);
} catch (\Exception $e) {
    return response()->json(['error' => $e->getMessage()], 500);
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
            $lemburSpl = LemburSpl::with('workorder')->findOrFail($id);
            return response()->json($lemburSpl, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data lembur spl',
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
        $validatedData = $request->validate([
            'verifikator_id' => 'nullable|exists:users,id',
            'status_id' => 'required|exists:m_status,id',
            'alasan_ditolak' => 'nullable|string'
        ]);
        DB::beginTransaction();
        try {
            $lemburSpl = LemburSpl::with('workorder')->findOrFail($id);
            $previousStatusId = $lemburSpl->status_id;
            $lemburSpl->update([
                'verifikator_id' => $validatedData['verifikator_id'],
                'status_id' => $validatedData['status_id'],
                'waktu_verifikasi' => now(),
                'alasan_ditolak' => $validatedData['alasan_ditolak'] ?? null,
            ]);
            if ($lemburSpl->workorder) {
                $lemburSpl->workorder->update([
                    'status_id' => $validatedData['status_id']
                ]);

                if ((int) $validatedData['status_id'] === 2 && (int) $previousStatusId !== 2) {
                    (new ProgressWorkorderService())->createInitialProgress($lemburSpl->workorder->id);
                    (new WorkorderActionService())->createAction([
                        'workorder_id' => $lemburSpl->workorder->id,
                        'action_id' => 1,
                        'keterangan' => 'Penugasan awal',
                        'waktu_mulai' => $lemburSpl->workorder->waktu_penugasan,
                        'estimasi_selesai' => $lemburSpl->workorder->estimasi_selesai,
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Data lembur SPL berhasil diupdate',
                'data' => $lemburSpl
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
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
            $lemburSpl = LemburSpl::findOrFail($id);
            $lemburSpl->delete();
            return response()->json(['message' => 'Data lembur SPL berhasil dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
