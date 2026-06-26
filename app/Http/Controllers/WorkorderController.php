<?php

namespace App\Http\Controllers;

use App\Models\LemburSpl;
use App\Models\JenisWorkorder;
use App\Models\Pengaduan;
use App\Models\User;
use App\Models\Workorder;
use App\Models\WorkorderAction;
use App\Notifications\WorkOrderNotification;
use App\Services\ProgressWorkorderService;
use App\Services\WorkorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                'assignedTo',
                'createdBy',
            ]);
            // Filter pencarian
            if ($search) {
                $query->where('nama_workorder', 'ILIKE', "%{$search}%")
                    ->orWhere('kode_pengaduan', 'ILIKE', "%{$search}%");
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

    public function history(Request $request)
    {
        try {
            $search = $request->query('search');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);
            $query = Workorder::with([
                'pengaduan',
                'departemen',
                'jenisWorkorder',
                'workorderAssignment.location',
                'workorderAssignment.spv',
                'workorderAssignment.picMember.pegawai',
                'assignmentMembers.pegawai',
                'laporanWorkorder',
                'progressWorkorder' => function ($q) {
                    $q->latest();
                }
            ])->where('status', 'Selesai');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_workorder', 'ILIKE', "%{$search}%")
                        ->orWhere('kode_pengaduan', 'ILIKE', "%{$search}%");
                });
            }

            $history = $query
                ->orderBy('updated_at', 'desc')
                ->paginate(
                    $limit,
                    ['*'],
                    'page',
                    $page
                );
            return response()->json([
                'success' => true,
                'data' => $history->items(),
                'totalPages' => $history->lastPage(),
                'currentPage' => $history->currentPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil history workorder',
                'error' => $e->getMessage(),
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
            'lokasi' => 'required|string|max:255',
            'prioritas' => 'required|in:Rendah,Sedang,Tinggi,Urgent',
            'status' => 'required|in:Pending,Proses,Selesai,Tutup',
            'kode_pengaduan' => 'required|exists:pengaduan,kode_pengaduan',
            'departemen_id' => 'required|exists:m_departemen,id',
            'jenis_workorder_id' => 'required|exists:m_jenis_workorder,id',
            'assigned_to' => 'required|exists:m_pegawai,id',
            'created_by' => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
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
            // AMBIL DATA PENGADUAN
            // ==================================================
            $pengaduan = Pengaduan::where('kode_pengaduan', $validatedData['kode_pengaduan'])->firstOrFail();

            // ==================================================
            // AUTO AMBIL LOKASI DARI PENGADUAN
            // ==================================================
            $validatedData['lokasi'] = $pengaduan->lokasi;

            // FORCE STATUS INITIAL
            $validatedData['status'] = 'Pending';

            // ==================================================
            // CREATE WORKORDER
            // ==================================================
            $workorder = (new WorkorderService())
                ->createWorkorders($validatedData);

            // ==================================================
            // UPDATE STATUS PENGADUAN -> PROSES
            // ==================================================
            $pengaduan->update([
                'status' => Pengaduan::STATUS_PROSES
            ]);
            DB::commit();
            $workorder->load([
                'departemen',
                'jenisWorkorder',
                'assignedTo',
                'createdBy',
            ]);

            // ==================================================
            // NOTIFIKASI: WO baru → SPV (assigned_to = m_pegawai).
            // Best-effort: kegagalan notif TIDAK boleh menggagalkan WO.
            // ==================================================
            try {
                $spvUser = User::where('pegawai_id', $workorder->assigned_to)->first();
                if ($spvUser) {
                    $creator    = User::with('pegawai')->find($workorder->created_by);
                    $senderName = optional(optional($creator)->pegawai)->nama
                        ?? optional($creator)->name ?? 'Admin';
                    $spvUser->notify(new WorkOrderNotification(
                        'Work Order Baru',
                        "{$senderName} membuat work order #{$workorder->nama_workorder} untuk Anda.",
                        (int) $workorder->id,
                        'wo_created',
                        $senderName
                    ));
                }
            } catch (\Throwable $e) {
                Log::warning('notify wo_created failed', [
                    'workorder_id' => $workorder->id,
                    'error'        => $e->getMessage(),
                ]);
            }
            return response()->json([
                'message' => 'Workorder berhasil disimpan',
                'data' => $workorder,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
                'assignedTo',
                'createdBy',
                // Ini supaya bisa menampilkan data anggota tim workorder, termasuk SPV dan anggota tim. Di Flutter
                'workorderAssignment.spv',
                'workorderAssignment.members.user',
                'workorderAssignment.location',
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

    public function historyDetail($id)
    {
        try {
            $workorder = Workorder::with([
                'pengaduan',
                'departemen',
                'jenisWorkorder',
                'assignedTo',
                'createdBy',
                'workorderAssignment.location',
                'workorderAssignment.spv',
                'workorderAssignment.picMember.pegawai',
                'workorderAssignment.members.pegawai',
                'progressWorkorder' => function ($q) {
                    $q->with([
                        'dokumentasiProgress',
                        'latestDetail'
                    ])->orderBy('created_at', 'desc');
                },
                'laporanWorkorder',
            ])
            ->where('id', $id)
            ->where('status', 'Selesai')
            ->firstOrFail();
            return response()->json([
                'success' => true,
                'workorder' => $workorder,
                'assignment' => $workorder->workorderAssignment,
                'progress' => $workorder->progressWorkorder,
                'laporan' => $workorder->laporanWorkorder,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail history workorder',
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
        'status' => 'sometimes|in:Pending,Proses,Selesai,Tutup',
        'kode_pengaduan' => 'nullable|string|max:255',
        'departemen_id' => 'sometimes|exists:m_departemen,id',
        'jenis_workorder_id' => 'sometimes|exists:m_jenis_workorder,id',
        'assigned_to' => 'sometimes|exists:m_pegawai,id',
        'created_by' => 'sometimes|exists:users,id',
        ]);
        try {
            $workorder = Workorder::findOrFail($id);
            $workorder->update($validatedData);
            $workorder->load([
                'departemen',
                'jenisWorkorder',
                'assignedTo',
                'createdBy',
            ]);
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

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,Proses,Selesai,Tutup'
        ]);

        DB::beginTransaction();

        try {
            $workorder = Workorder::with('pengaduan')->findOrFail($id);
            $workorder->update([
                'status' => $request->status
            ]);

            // SYNC KE PENGADUAN
            if ($workorder->pengaduan) {
                $workorder->pengaduan->update([
                    'status' => $request->status
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Status berhasil diupdate',
                'data' => $workorder
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $workorder = Workorder::findOrFail($id);
            $workorder->is_active = !$workorder->is_active;
            $workorder->save();
            $workorder->load([
                'departemen',
                'jenisWorkorder',
                'assignedTo',
                'createdBy',
            ]);
            return response()->json([
                'message' => $workorder->is_active
                    ? 'Workorder berhasil diaktifkan'
                    : 'Workorder berhasil dinonaktifkan',

                'is_active' => $workorder->is_active,
                'workorder_id' => $workorder->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
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
        // 
    }
}
