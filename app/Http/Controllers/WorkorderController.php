<?php

namespace App\Http\Controllers;

use App\Models\LemburSpl;
use App\Models\Workorder;
use App\Models\WorkorderAction;

use App\Services\ProgressWorkorderService;
use App\Services\WorkorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkorderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $type = $request->query('type');
            $search = $request->query('search');
            $status = $request->query('status') ? explode(',', $request->query('status')) : null;
            $excludeStatus = $request->query('exclude_status') ? explode(',', $request->query('exclude_status')) : null;
            $picId = $request->query('pic_id');
            $userId = $request->query('user_id');
            $dateRange = $request->query('date_range');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);
            $sort = $request->query('sort', 'desc');
            $all = $request->query('all', false);

            $query = Workorder::with('petugas', 'pic', 'jenisWorkorder', 'jenisLokasi', 'tipeWorkorder', 'status', 'lemburSpl');
            if ($type) {
                $query->where('tipe_workorder_id', $type);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('judul_pekerjaan', 'ILIKE', "%{$search}%")
                        ->orWhereHas(
                            'jenisWorkorder',
                            fn($q) =>
                            $q->where('nama', 'ILIKE', "%{$search}%")
                        )
                        ->orWhereHas(
                            'jenisLokasi',
                            fn($q) =>
                            $q->where('nama', 'ILIKE', "%{$search}%")
                        )
                        ->orWhere('waktu_penugasan', 'ILIKE', "%{$search}%")
                        ->orWhere('estimasi_selesai', 'ILIKE', "%{$search}%")
                        ->orWhere('estimasi_durasi', 'ILIKE', "%{$search}%")
                        ->orWhere('unit_waktu', 'ILIKE', "%{$search}%")
                        ->orWhereHas(
                            'petugas.pegawai',
                            fn($q) =>
                            $q->where('nama', 'ILIKE', "%{$search}%")
                                ->orWhere('nip', 'ILIKE', "%{$search}%")
                        )
                        ->orWhereHas(
                            'status',
                            fn($q) =>
                            $q->where('nama', 'ILIKE', "%{$search}%")
                        );
                });
            }
            if ($status) {
                $query->whereIn('status_id', $status);
            }
            if ($excludeStatus) {
                $query->whereNotIn('status_id', $excludeStatus);
            }
            if ($picId) {
                $query->where('pic_id', $picId);
            }
            if ($userId) {
                $query->where('petugas_id', $userId);
            }
            if ($dateRange) {
                switch ($dateRange) {
                    case 'hari_ini':
                        $query->whereBetween('created_at', [
                            Carbon::now()->startOfDay(),
                            Carbon::now()->endOfDay(),
                        ]);
                        break;
                    case 'minggu_ini':
                        $query->whereBetween('created_at', [
                            Carbon::now()->startOfWeek(),
                            Carbon::now()->endOfWeek(),
                        ]);
                        break;
                    case 'bulan_ini':
                        $query->whereBetween('created_at', [
                            Carbon::now()->startOfMonth(),
                            Carbon::now()->endOfMonth(),
                        ]);
                        break;
                    case '3_bulan':
                        $query->whereBetween('created_at', [
                            Carbon::now()->subMonths(3)->startOfDay(),
                            Carbon::now()->endOfDay(),
                        ]);
                        break;
                    case 'custom':
                        if ($startDate && $endDate) {
                            $query->whereBetween('created_at', [
                                Carbon::parse($startDate)->startOfDay(),
                                Carbon::parse($endDate)->endOfDay(),
                            ]);
                        }
                        break;
                }
            }

            $query->orderBy('created_at', $sort)->orderBy('id', $sort);

            if ($all) {
                return response()->json([
                    'data' => $query->get(),
                ]);
            }

            $workorders = $query->paginate($limit, ['*'], 'page', $page);
            return response()->json([
                'data' => $workorders->items(),
                'totalPages' => $workorders->lastPage(),
                'currentPage' => $workorders->currentPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data workorder',
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
        $validatedData = $request->validate([
            'judul_pekerjaan' => 'required|string',
            'waktu_penugasan' => 'required|date',
            'estimasi_durasi' => 'required|integer',
            'unit_waktu' => 'required|in:Jam,Hari,Bulan',
            'estimasi_selesai' => 'required|date',
            'longitude' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'pic_id' => 'required|exists:users,id',
            'jenis_workorder_id' => 'required|exists:m_jenis_workorder,id',
            'jenis_lokasi_id' => 'required|exists:m_jenis_lokasi,id',
            'tipe_workorder_id' => 'required|exists:m_tipe_workorder,id',
            'petugas_id' => 'required|array|min:1',
            'petugas_id.*' => 'exists:users,id',
        ]);
        try {
            $workorder = (new WorkorderService())->createWorkorders($validatedData);

            return response()->json([
                'message' => 'Work Order berhasil disimpan',
                'data' => $workorder,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
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
            $workorder = Workorder::with('petugas', 'pic', 'jenisWorkorder', 'jenisLokasi', 'tipeWorkorder', 'status', 'lemburSpl', 'latestFreeze')->findOrFail($id);
            return response()->json($workorder, 200);
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
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'estimasi_selesai' => 'nullable|date',
            'status_id' => 'nullable|exists:m_status,id',
        ]);
        try {
            $workorder = Workorder::findOrFail($id);
            $workorder->update($validatedData);
            return response()->json(['message' => 'Workorder berhasil diupdate', 'data' => $workorder], 200);
        } catch (\Exception $e) {
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
            $workorder = Workorder::findOrFail($id);
            $workorder->delete();
            return response()->json(['message' => 'Workorder berhasil dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
