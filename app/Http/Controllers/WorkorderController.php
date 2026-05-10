<?php

namespace App\Http\Controllers;

use App\Models\Workorder;
use App\Services\WorkorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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
            $assignedTo = $request->query('assigned_to', $request->query('pic_id'));
            $userId = $request->query('user_id');
            $dateRange = $request->query('date_range');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);
            $sort = $request->query('sort', 'desc');
            $all = $request->query('all', false);

            $query = Workorder::with('assignmentMembers', 'pic', 'jenisWorkorder', 'status', 'lemburSpl');

            // Filter berdasarkan role authenticated user.
            $user = $request->user();

            if ($user->role_id != 1) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereHas('assignmentMembers', fn ($qq) => $qq->where('user_id', $user->id));
                });
            }
            

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_workorder', 'ILIKE', "%{$search}%")
                        ->orWhereHas(
                            'jenisWorkorder',
                            fn($q) =>
                            $q->where('nama', 'ILIKE', "%{$search}%")
                        )
                        ->orWhere('tanggal_mulai', 'ILIKE', "%{$search}%")
                        ->orWhere('lokasi', 'ILIKE', "%{$search}%")
                        ->orWhereHas(
                            'assignmentMembers.user.pegawai',
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
            if ($assignedTo) {
                $query->where('assigned_to', $assignedTo);
            }
            if ($userId) {
                $query->whereHas('assignmentMembers', fn ($q) => $q->where('user_id', $userId));
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
        // Field timeline (estimasi_durasi, unit_waktu, estimasi_selesai,
        // tanggal_selesai) sudah dipindahkan ke workorder_assignment —
        // diisi oleh SPV saat assign staff via AssignmentService.
        $validatedData = $request->validate([
            'nama_workorder' => 'required|string',
            'deskripsi' => 'nullable|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_laporan' => 'nullable|date',
            'lokasi' => 'nullable|string|max:255',
            'jenis_workorder_id' => 'required|exists:m_jenis_workorder,id',
            'assigned_to' => 'required|exists:users,id',
            'departemen_id' => 'nullable|exists:m_departemen,id',
            'pengaduan_id' => 'nullable|exists:pengaduan,id',
            'kpi_id' => 'nullable|exists:m_kpi,id',
            'prioritas' => 'nullable|in:rendah,sedang,tinggi,darurat',
        ]);

        $validatedData['created_by_user_id'] = $request->user()->id;
        
        try {
            $workorder = (new WorkorderService())->createWorkorders($validatedData);

            return response()->json([
                'message' => 'Work Order berhasil disimpan',
                'data' => $workorder,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Work Order store failed', [
                'user_id' => optional($request->user())->id,
                'payload' => $request->except(['password', 'password_confirmation']),
                'error'   => $e->getMessage(),
                'trace'   => collect($e->getTrace())->take(5)->all(),
            ]);

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
            $workorder = Workorder::with('assignmentMembers', 'assignedTo', 'jenisWorkorder', 'status', 'lemburSpl', 'latestFreeze')->findOrFail($id);
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
