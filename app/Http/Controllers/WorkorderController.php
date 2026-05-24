<?php

namespace App\Http\Controllers;

use App\Models\LemburSpl;
use App\Models\JenisWorkorder;
use App\Models\Workorder;
use App\Models\WorkorderAction;

use App\Services\ProgressWorkorderService;
use App\Services\WorkorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkorderController extends Controller
{
    protected $workorderService;

    public function __construct(WorkorderService $workorderService)
    {
        $this->workorderService = $workorderService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');
            $status = $request->query('status');
            $prioritas = $request->query('prioritas');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);
            $sort = $request->query('sort', 'desc');

            $query = Workorder::with([
                'departemen',
                'jenisWorkorder',
                'pic',
                'user',
            ]);
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_workorder', 'ILIKE', "%{$search}%")
                        ->orWhere('deskripsi', 'ILIKE', "%{$search}%")
                        ->orWhere('kode_pengaduan', 'ILIKE', "%{$search}%")
                        ->orWhereHas('jenisWorkorder', function ($sub) use ($search) {

                            $sub->where('nama', 'ILIKE', "%{$search}%");
                        })
                        ->orWhereHas('pic', function ($sub) use ($search) {

                            $sub->where('name', 'ILIKE', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($sub) use ($search) {

                            $sub->where('name', 'ILIKE', "%{$search}%");
                        });
                });
            }
            // Filter status
            if ($status) {
                $query->where('status', $status);
            }
            // Filter prioritas
            if ($prioritas) {
                $query->where('prioritas', $prioritas);
            }
            $query->orderBy('created_at', $sort);
            // Pagination
            $workorders = $query->paginate(
                $limit,
                ['*'],
                'page',
                $page
            );
            return response()->json([
                'data' => $workorders->items(),
                'totalPages' => $workorders->lastPage(),
                'currentPage' => $workorders->currentPage(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal mengambil data workorder',
                'message' => $e->getMessage(),
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
        'nama_workorder' => 'required|string|max:255',
        'deskripsi' => 'nullable|string',
        'lokasi' => 'nullable|string|max:255',
        'prioritas' => 'required|in:Rendah,Sedang,Tinggi,Urgent',
        'status' => 'required|in:Open,Progress,Pending,Done,Cancel',
        'kode_pengaduan' => 'nullable|string|max:255',
        'departemen_id' => 'required|exists:m_departemen,id',
        'jenis_workorder_id' => 'required|exists:m_jenis_workorder,id',
        'pic_id' => 'required|exists:users,id',
        'user_id' => 'required|exists:users,id',
    ]);

    try {
        // ==================================================
        // VALIDASI JENIS WORKORDER HARUS AKTIF
        // ==================================================
        $jenisWorkorder = JenisWorkorder::findOrFail(
            $validatedData['jenis_workorder_id']
        );
        if (!$jenisWorkorder->is_active) {
            return response()->json([
                'message' => 'Jenis workorder nonaktif dan tidak dapat digunakan.'
            ], 422);
        }

        // ==================================================
        // CREATE WORKORDER
        // ==================================================
        $workorder = (new WorkorderService())
            ->createWorkorders($validatedData);
        return response()->json([
            'message' => 'Workorder berhasil disimpan',
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
            $workorder = Workorder::with([
                'departemen',
                'jenisWorkorder',
                'pic',
                'user',
            ])->findOrFail($id);
            return response()->json([
                'data' => $workorder
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
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
        'nama_workorder' => 'sometimes|string|max:255',
        'deskripsi' => 'nullable|string',
        'lokasi' => 'nullable|string|max:255',
        'prioritas' => 'sometimes|in:Rendah,Sedang,Tinggi,Urgent',
        'status' => 'sometimes|in:Open,Progress,Pending,Done,Cancel',
        'kode_pengaduan' => 'nullable|string|max:255',
        'departemen_id' => 'sometimes|exists:m_departemen,id',
        'jenis_workorder_id' => 'sometimes|exists:m_jenis_workorder,id',
        'pic_id' => 'sometimes|exists:users,id',
        'user_id' => 'sometimes|exists:users,id',
        ]);
        try {
            $workorder = Workorder::findOrFail($id);
            $workorder->update($validatedData);
            return response()->json([
                'message' => 'Workorder berhasil diperbarui',
                'data' => $workorder,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
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
        $workorder = Workorder::findOrFail($id);
        $workorder->delete();
        return response()->json([
            'message' => 'Workorder berhasil dihapus',
        ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
