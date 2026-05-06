<?php

namespace App\Http\Controllers;

use App\Models\LaporanWorkorder;
use App\Models\LemburSpl;
use App\Models\MasterAction;
use App\Models\Status;
use App\Models\WoInfrastruktur;
use App\Models\WoJaringan;
use App\Models\WoMeter;
use App\Models\Workorder;
use App\Models\WorkorderAction;

use App\Services\ProgressWorkorderService;
use App\Services\WorkorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkorderController extends Controller
{
    private function statusId(string $kode): ?int
    {
        return Status::where('kode', $kode)->value('id');
    }

    private function actionId(string $kode): ?int
    {
        return MasterAction::where('kode', $kode)->value('id');
    }

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

            $query = Workorder::with('assignmentMembers', 'pic', 'jenisWorkorder', 'jenisLokasi', 'tipeWorkorder', 'status', 'lemburSpl', 'location');

            // Filter berdasarkan role authenticated user.
            $user = $request->user();

            if ($user->role_id != 1) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereHas('assignmentMembers', fn ($qq) => $qq->where('user_id', $user->id));
                });
            }
            
            if ($type) {
                $query->where('tipe_workorder_id', $type);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_workorder', 'ILIKE', "%{$search}%")
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
                        ->orWhere('tanggal_mulai', 'ILIKE', "%{$search}%")
                        ->orWhere('estimasi_selesai', 'ILIKE', "%{$search}%")
                        ->orWhere('estimasi_durasi', 'ILIKE', "%{$search}%")
                        ->orWhere('unit_waktu', 'ILIKE', "%{$search}%")
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
        $validatedData = $request->validate([
            'nama_workorder' => 'required|string',
            'deskripsi' => 'nullable|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_laporan' => 'nullable|date',
            'estimasi_durasi' => 'required|integer',
            'unit_waktu' => 'required|in:menit,jam,hari,Jam,Hari,Bulan',
            'estimasi_selesai' => 'required|date',
            'longitude' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'location_id' => 'nullable|exists:m_location,id',
            'lokasi' => 'nullable|string|max:255',
            'jenis_workorder_id' => 'required|exists:m_jenis_workorder,id',
            'jenis_lokasi_id' => 'required|exists:m_jenis_lokasi,id',
            'tipe_workorder_id' => 'required|exists:m_tipe_workorder,id',
            'assigned_to' => 'required|exists:users,id',
            'departemen_id' => 'nullable|exists:m_departemen,id',
            'pengaduan_id' => 'nullable|exists:pengaduan,id',
            'kpi_id' => 'nullable|exists:m_kpi,id',
            'prioritas' => 'nullable|in:rendah,sedang,tinggi,darurat',
            'petugas_id' => 'nullable|array|min:1',
            'petugas_id.*' => 'exists:users,id',
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
            $workorder = Workorder::with('assignmentMembers', 'assignedTo', 'jenisWorkorder', 'jenisLokasi', 'tipeWorkorder', 'status', 'lemburSpl', 'latestFreeze', 'location')->findOrFail($id);
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

    public function assignStaff(Request $request, $id)
    {
        $validated = $request->validate([
            'form_kategori' => 'required|array',
            'petugas' => 'required|array|min:1',
            'petugas.*.user_id' => 'required|exists:users,id',
            'petugas.*.peran' => 'nullable|in:koordinator,anggota',
        ]);

        DB::beginTransaction();
        try {
            $workorder = Workorder::with('jenisWorkorder', 'assignedTo.pegawai', 'workorderAssignment.members')
                ->findOrFail($id);

            $statusSpv = $this->statusId('DITUGASKAN_KE_SPV');
            $statusStaff = $this->statusId('DITUGASKAN_KE_STAFF');

            if ((int) $workorder->assigned_to !== (int) optional($request->user())->id) {
                return response()->json(['error' => 'Hanya SPV assigned yang bisa assign staff'], 403);
            }

            if ((int) $workorder->status_id !== (int) $statusSpv) {
                return response()->json(['error' => 'WO bukan pada status DITUGASKAN_KE_SPV'], 422);
            }

            if ($workorder->workorderAssignment && $workorder->workorderAssignment->members()->exists()) {
                return response()->json(['error' => 'WO sudah pernah di-assign ke staff'], 422);
            }

            $kategori = optional($workorder->jenisWorkorder)->kategori_form;
            if (! in_array($kategori, ['meter', 'jaringan', 'infrastruktur'], true)) {
                return response()->json(['error' => 'kategori_form belum valid pada jenis workorder'], 422);
            }

            $this->createKategoriForm($kategori, $workorder->id, $validated['form_kategori']);

            // Buat atau update workorder_assignment
            $assignment = \App\Models\WorkorderAssignment::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'spv_user_id' => $request->user()->id,
                    'assigned_at' => now(),
                ]
            );

            // Insert anggota tim ke wo_assignment_member
            foreach ($validated['petugas'] as $staff) {
                \App\Models\WoAssignmentMember::create([
                    'assignment_id' => $assignment->id,
                    'user_id'       => (int) $staff['user_id'],
                    'is_pic'        => ($staff['peran'] ?? null) === 'koordinator',
                ]);
            }

            $workorder->update(['status_id' => $statusStaff]);

            $penugasanActionId = $this->actionId('PENUGASAN');
            if ($penugasanActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id' => $penugasanActionId,
                    'actor_id' => optional($request->user())->id,
                    'keterangan' => 'SPV mengisi form kategori dan assign staff',
                    'waktu_mulai' => now(),
                    'estimasi_selesai' => $workorder->estimasi_selesai,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Assign staff berhasil'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string',
        ]);

        if ((int) $request->user()->role_id !== 2) {
            return response()->json(['error' => 'Hanya manager yang bisa approve'], 403);
        }

        DB::beginTransaction();
        try {
            $workorder = Workorder::with([
                'jenisWorkorder',
                'woMeter',
                'woJaringan',
                'woInfrastruktur',
                'assignmentMembers.user.pegawai',
            ])->findOrFail($id);

            $menungguManager = $this->statusId('MENUNGGU_APPROVAL_MANAGER');
            if ((int) $workorder->status_id !== (int) $menungguManager) {
                return response()->json(['error' => 'Status WO belum siap untuk approval manager'], 422);
            }

            $workorder->update([
                'status_id' => $this->statusId('SELESAI'),
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $validated['approval_notes'] ?? null,
            ]);

            $approveActionId = $this->actionId('APPROVE');
            if ($approveActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id' => $approveActionId,
                    'actor_id' => $request->user()->id,
                    'keterangan' => 'Manager approve final workorder',
                    'waktu_mulai' => now(),
                    'estimasi_selesai' => $workorder->estimasi_selesai,
                ]);
            }

            $reportNo = sprintf('LAP-WO-%s-%04d', now()->format('Y'), $workorder->id);
            $snapshot = $this->resolveKategoriSnapshot($workorder);
            $petugasSnapshot = $workorder->assignmentMembers->map(function ($member) {
                $u = $member->user;
                return [
                    'user_id' => optional($u)->id,
                    'nama' => optional(optional($u)->pegawai)->nama,
                    'nip' => optional(optional($u)->pegawai)->nip,
                ];
            })->values()->all();

            LaporanWorkorder::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'nomor_laporan' => $reportNo,
                    'tanggal_terbit' => now(),
                    'ringkasan_pekerjaan' => $workorder->deskripsi ?? $workorder->nama_workorder,
                    'hasil_akhir_snapshot' => $snapshot,
                    'petugas_snapshot' => $petugasSnapshot,
                    'catatan_manager' => $validated['approval_notes'] ?? null,
                    'issued_by_user_id' => $workorder->assigned_to,
                    'approved_by_user_id' => $request->user()->id,
                    'approved_at' => now(),
                ]
            );

            DB::commit();
            return response()->json(['message' => 'Workorder berhasil di-approve manager'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'required|string',
        ]);

        if ((int) $request->user()->role_id !== 2) {
            return response()->json(['error' => 'Hanya manager yang bisa reject'], 403);
        }

        DB::beginTransaction();
        try {
            $workorder = Workorder::findOrFail($id);
            $menungguManager = $this->statusId('MENUNGGU_APPROVAL_MANAGER');
            if ((int) $workorder->status_id !== (int) $menungguManager) {
                return response()->json(['error' => 'Status WO belum siap untuk reject manager'], 422);
            }

            $workorder->update([
                'status_id' => $this->statusId('DITOLAK_MANAGER'),
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $validated['approval_notes'],
            ]);

            $rejectActionId = $this->actionId('REJECT');
            if ($rejectActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id' => $rejectActionId,
                    'actor_id' => $request->user()->id,
                    'keterangan' => 'Manager reject final workorder',
                    'waktu_mulai' => now(),
                    'estimasi_selesai' => $workorder->estimasi_selesai,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Workorder berhasil di-reject manager'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function resolveKategoriSnapshot(Workorder $workorder): array
    {
        $kategori = optional($workorder->jenisWorkorder)->kategori_form;
        if ($kategori === 'meter') {
            return optional($workorder->woMeter)->toArray() ?? [];
        }
        if ($kategori === 'jaringan') {
            return optional($workorder->woJaringan)->toArray() ?? [];
        }
        if ($kategori === 'infrastruktur') {
            return optional($workorder->woInfrastruktur)->toArray() ?? [];
        }

        return [];
    }

    private function createKategoriForm(string $kategori, int $workorderId, array $payload): void
    {
        if ($kategori === 'meter') {
            $required = ['nomor_meter'];
            $this->ensureRequiredFields($payload, $required);
            WoMeter::create([
                'workorder_id' => $workorderId,
                'nomor_meter' => $payload['nomor_meter'],
                'kondisi_meter_awal' => $payload['kondisi_meter_awal'] ?? null,
                'kondisi_meter_akhir' => $payload['kondisi_meter_akhir'] ?? null,
                'hasil_kalibrasi' => $payload['hasil_kalibrasi'] ?? null,
            ]);
            return;
        }

        if ($kategori === 'jaringan') {
            $required = ['jenis_pipa'];
            $this->ensureRequiredFields($payload, $required);
            WoJaringan::create([
                'workorder_id' => $workorderId,
                'jenis_pipa' => $payload['jenis_pipa'],
                'diameter_pipa' => $payload['diameter_pipa'] ?? null,
                'panjang_pipa' => $payload['panjang_pipa'] ?? null,
                'tingkat_kerusakan' => $payload['tingkat_kerusakan'] ?? null,
                'tindakan_perbaikan' => $payload['tindakan_perbaikan'] ?? null,
                'hasil_inspeksi' => $payload['hasil_inspeksi'] ?? null,
            ]);
            return;
        }

        $required = ['nama_aset', 'jenis_aset'];
        $this->ensureRequiredFields($payload, $required);
        WoInfrastruktur::create([
            'workorder_id' => $workorderId,
            'nama_aset' => $payload['nama_aset'],
            'jenis_aset' => $payload['jenis_aset'],
            'kapasitas' => $payload['kapasitas'] ?? null,
            'kondisi_awal' => $payload['kondisi_awal'] ?? null,
            'kondisi_akhir' => $payload['kondisi_akhir'] ?? null,
            'jadwal_pemeliharaan' => $payload['jadwal_pemeliharaan'] ?? null,
            'tindakan' => $payload['tindakan'] ?? null,
        ]);
    }

    private function ensureRequiredFields(array $payload, array $required): void
    {
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                throw new \InvalidArgumentException("Field {$field} wajib diisi");
            }
        }
    }
}
