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

            $query = JenisWorkorder::with([
                'formWorkorder' => function ($q) {
                    $q->orderByRaw('"order" ASC');
                },
                'formWorkorder.detailForm' => function ($q) {
                    $q->orderByRaw('"order" ASC');
                },
                'formWorkorder.kpi'
            ]);

            if ($search) {
                $query->where('nama', 'ILIKE', "%{$search}%");
            }

            $query->orderBy('created_at', $sort)->orderBy('id', $sort);

            if ($all) {
                return response()->json([
                    'data' => $query->get(),
                ]);
            }

            $jenisworkorders = $query->paginate($limit, ['*'], 'page', $page);
            return response()->json([
                'data' => $jenisworkorders->items(),
                'totalPages' => $jenisworkorders->lastPage(),
                'currentPage' => $jenisworkorders->currentPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data jenis workorder',
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
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray()
                ], 422);
            }

            $data = $request->all();
            $jenisWorkorder = $this->jenisWorkorderService->store($data);

            return response()->json([
                'message' => 'Data jenis workorder berhasil dibuat',
                'data' => $jenisWorkorder
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat membuat data jenis workorder',
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
        try {
            $jenisWorkorder = JenisWorkorder::with([
                'formWorkorder' => function ($query) {
                    $query->orderByRaw('"order" ASC');
                },
                'formWorkorder.detailForm' => function ($query) {
                    $query->orderByRaw('"order" ASC');
                },
                'formWorkorder.kpi'
            ])->findOrFail($id);
            return response()->json($jenisWorkorder, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data jenis workorder',
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
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'sometimes|required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            $jenisWorkorder = $this->jenisWorkorderService->update($id, $data);

            return response()->json([
                'message' => 'Data jenis workorder berhasil diupdate',
                'data' => new JenisWorkorderResource($jenisWorkorder)
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal menyimpan data',
                'message' => $e->getMessage()
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
            $jenisWorkorder = JenisWorkorder::findOrFail($id);
            $jenisWorkorder->delete();
            return response()->json([
                'message' => 'Data jenis workorder berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat menghapus data jenis workorder',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
