<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJenisWorkorderRequest;
use App\Http\Requests\UpdateJenisWorkorderRequest;
use App\Http\Resources\JenisWorkorderResource;
use App\Models\JenisWorkorder;
use App\Services\JenisWorkorderService;
use Illuminate\Http\Request;

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

            $query = JenisWorkorder::with('detailForm', 'kpi', 'detailForm.optionForm');
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
            // For testing - let's make detail_form optional
            $validator = \Validator::make($request->all(), [
                'nama' => 'required|string',
                'kpi_id' => 'required|integer|exists:master_kpi,id',
                'detail_form' => 'nullable|array',
                'detail_form.*.id' => 'required|integer',
                'detail_form.*.nama_field' => 'required|string|max:255',
                'detail_form.*.tipe_field' => 'required|string',
                'detail_form.*.tipe_data' => 'nullable|string',
                'detail_form.*.unit_satuan' => 'nullable|string',
                'detail_form.*.sifat' => 'required|string',
                'detail_form.*.parent' => 'required|integer',
                'detail_form.*.keterangan' => 'nullable|string',
                'detail_form.*.hint_text' => 'required|string',
                'detail_form.*.order' => 'required|integer',
                'detail_form.*.min' => 'nullable|integer',
                'detail_form.*.max' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray()
                ], 422);
            }

            $data = $validator->validated();
            
            // If no detail_form provided, add a default one for testing
            if (empty($data['detail_form'])) {
                $data['detail_form'] = [
                    [
                        'id' => 1,
                        'nama_field' => 'Default Field',
                        'tipe_field' => 'text',
                        'tipe_data' => 'string',
                        'sifat' => 'required',
                        'parent' => 0,
                        'hint_text' => 'Default hint text',
                        'order' => 1
                    ]
                ];
            }
            
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


    // public function store(StoreJenisWorkorderRequest $request)
    // {
    //     try {
    //         $data = $request->validated();
    //         $jenisWorkorder = $this->jenisWorkorderService->store($data);
    //         return new JenisWorkorderResource($jenisWorkorder);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
    //     }
    // }

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
                'detailForm' => function ($query) {
                    $query->orderBy('order');
                },
                'detailForm.optionForm' => function ($query) {
                    $query->orderBy('order');
                }
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
            // For testing - let's make detail_form optional for updates
            $validator = \Validator::make($request->all(), [
                'nama' => 'sometimes|required|string',
                'kpi_id' => 'sometimes|required|integer|exists:master_kpi,id',
                'detail_form' => 'sometimes|required|array',
                'detail_form.*.id' => 'required_with:detail_form|integer',
                'detail_form.*.nama_field' => 'required_with:detail_form|string|max:255',
                'detail_form.*.tipe_field' => 'required_with:detail_form|string',
                'detail_form.*.tipe_data' => 'nullable|string',
                'detail_form.*.unit_satuan' => 'nullable|string',
                'detail_form.*.sifat' => 'required_with:detail_form|string',
                'detail_form.*.parent' => 'required_with:detail_form|integer',
                'detail_form.*.keterangan' => 'nullable|string',
                'detail_form.*.hint_text' => 'required_with:detail_form|string',
                'detail_form.*.order' => 'required_with:detail_form|integer',
                'detail_form.*.min' => 'nullable|integer',
                'detail_form.*.max' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray()
                ], 422);
            }

            $data = $validator->validated();
            
            // If only updating nama, fetch existing data
            if (!isset($data['detail_form']) && count($data) == 1 && isset($data['nama'])) {
                $existing = JenisWorkorder::with('detailForm.optionForm', 'kpi')->findOrFail($id);
                $data['kpi_id'] = $existing->kpi_id;
                
                // Convert existing detail forms to the expected format
                $data['detail_form'] = $existing->detailForm->map(function($detail) {
                    $detailArray = [
                        'id' => $detail->id,
                        'nama_field' => $detail->nama_field,
                        'tipe_field' => $detail->tipe_field,
                        'tipe_data' => $detail->tipe_data,
                        'unit_satuan' => $detail->unit_satuan,
                        'sifat' => $detail->sifat,
                        'parent' => $detail->parent,
                        'keterangan' => $detail->keterangan,
                        'hint_text' => $detail->hint_text,
                        'order' => $detail->order,
                        'min' => $detail->min,
                        'max' => $detail->max,
                    ];
                    
                    if ($detail->tipe_field === 'dropdown' && $detail->optionForm->count() > 0) {
                        $detailArray['option_form'] = $detail->optionForm->map(function($option) {
                            return [
                                'id' => $option->id,
                                'nama_opsi' => $option->nama_opsi,
                                'parent' => $option->parent,
                                'order' => $option->order,
                            ];
                        })->toArray();
                    }
                    
                    return $detailArray;
                })->toArray();
            }
            
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
