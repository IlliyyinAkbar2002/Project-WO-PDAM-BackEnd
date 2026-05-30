<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJenisWorkorderRequest;
use App\Http\Requests\UpdateJenisWorkorderRequest;
use App\Http\Resources\JenisWorkorderResource;
use App\Models\JenisWorkorder;
use App\Services\JenisWorkorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JenisWorkorderController extends Controller
{
    protected $jenisWorkorderService;
    public function __construct(JenisWorkorderService $jenisWorkorderService)
    {
        $this->jenisWorkorderService = $jenisWorkorderService;
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
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);
            $sort = $request->query('sort', 'desc');
            $all = $request->query('all', false);

            $query = JenisWorkorder::query();

            // Search
            if ($search) {
                $query->where('nama', 'ILIKE', "%{$search}%");
            }
            // Sorting
            $query->orderBy('created_at', $sort)
                ->orderBy('id', $sort);
            // Jika ambil semua
            if ($all) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data jenis workorder berhasil diambil',
                    'data' => $query->get(),
                ]);
            }
            // Pagination
            $jenisworkorders = $query->paginate(
                $limit,
                ['*'],
                'page',
                $page
            );
            return response()->json([
                'success' => true,
                'message' => 'Data jenis workorder berhasil diambil',
                'data' => $jenisworkorders->items(),
                'totalPages' => $jenisworkorders->lastPage(),
                'currentPage' => $jenisworkorders->currentPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jenis workorder',
                'error' => $e->getMessage()
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
            $jenisWorkorder = JenisWorkorder::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail jenis workorder berhasil diambil',
                'data' => new JenisWorkorderResource($jenisWorkorder)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail jenis workorder',
                'error' => $e->getMessage()
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
        
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $jenisWorkorder = JenisWorkorder::findOrFail($id);
        $jenisWorkorder->update([
            'is_active' => $validated['is_active'],
        ]);
        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'data' => $jenisWorkorder,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        
    }
}
