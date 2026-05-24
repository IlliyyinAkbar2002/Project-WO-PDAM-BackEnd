<?php

namespace App\Http\Controllers;

use App\Models\LaporanWorkorder;
use App\Models\MasterAction;
use App\Models\ProgressDetail;
use App\Models\ProgressWorkorder;
use App\Models\Status;
use App\Models\TipeProgress;
use App\Models\Workorder;
use App\Models\WorkorderAction;
use App\Services\ProgressWorkorderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgressWorkorderController extends Controller
{
    private function statusId(string $kode): ?int
    {
        return Status::where('kode', $kode)->value('id');
    }

    private function tipeId(string $kode): ?int
    {
        return TipeProgress::where('kode', $kode)->value('id');
    }

    /**
     * Kompatibilitas FE: beberapa client lama kirim multipart/json dengan method
     * yang tidak konsisten. Pastikan field non-file tetap terbaca sebelum validasi.
     */
    private function hydrateInputFromBody(Request $request): void
    {
        if ($request->request->count() > 0) {
            return;
        }

        $content = (string) $request->getContent();
        if ($content === '') {
            return;
        }

        $contentTypeHeader = (string) $request->header('Content-Type', '');
        $contentType = strtolower($contentTypeHeader);

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge($decoded);
            }
            return;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($content, $decoded);
            if (is_array($decoded) && $decoded !== []) {
                $request->merge($decoded);
            }
            return;
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            $decoded = $this->extractMultipartTextFields($content, $contentTypeHeader);
            if ($decoded !== []) {
                $request->merge($decoded);
            }
        }
    }

    private function extractMultipartTextFields(string $content, string $contentType): array
    {
        if (!preg_match('/boundary="?([^";]+)"?/i', $contentType, $matches)) {
            return [];
        }

        $boundary = '--' . $matches[1];
        $parts = explode($boundary, $content);
        $pairs = [];

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || $part === '--' || $part === "--\r\n") {
                continue;
            }

            if (!str_contains($part, "\r\n\r\n")) {
                continue;
            }

            [$rawHeaders, $rawBody] = explode("\r\n\r\n", $part, 2);

            if (!preg_match('/Content-Disposition:\s*form-data;\s*name="([^"]+)"/i', $rawHeaders, $nameMatches)) {
                continue;
            }

            if (preg_match('/filename="[^"]*"/i', $rawHeaders)) {
                continue;
            }

            $fieldName = $nameMatches[1];
            $fieldValue = rtrim($rawBody, "\r\n");
            $pairs[] = rawurlencode($fieldName) . '=' . rawurlencode($fieldValue);
        }

        if ($pairs === []) {
            return [];
        }

        parse_str(implode('&', $pairs), $decoded);
        return is_array($decoded) ? $decoded : [];
    }

    private function validateProgressLimit(Workorder $workorder): ?\Illuminate\Http\JsonResponse
    {
        $mulaiTipeId = TipeProgress::where('kode', 'MULAI')->value('id');

        $dailyQuotaEverExhausted = DB::table('progress_workorder')
            ->selectRaw('1')
            ->where('workorder_id', $workorder->id)
            ->whereNotNull('waktu_submit')
            ->where('tipe_progress_id', '!=', $mulaiTipeId)
            ->groupByRaw('waktu_submit::date')
            ->havingRaw('COUNT(*) >= 8')
            ->exists();

        if ($dailyQuotaEverExhausted) {
            return response()->json(['error' => 'Limit pelaporan harian (maksimal 8x per hari) telah tercapai.'], 422);
        }

        // 2. Cek sisa kuota pelaporan total berdasarkan estimasi hari
        $assignment = $workorder->workorderAssignment;
        $tanggalMulai = optional($assignment)->tanggal_mulai ?? $workorder->tanggal_mulai;
        $estimasiSelesai = optional($assignment)->estimasi_selesai;

        $totalDays = 1;
        if ($tanggalMulai && $estimasiSelesai) {
            $start = \Illuminate\Support\Carbon::parse($tanggalMulai)->startOfDay();
            $end = \Illuminate\Support\Carbon::parse($estimasiSelesai)->startOfDay();
            $diff = max(0, (int) $start->diffInDays($end, false));
            $totalDays = $diff + 1;
        }

        $maxPelaporanTotal = $totalDays * 8;
        $totalPelaporan = ProgressWorkorder::where('workorder_id', $workorder->id)
            ->whereNotNull('waktu_submit')
            ->where('tipe_progress_id', '!=', $mulaiTipeId)
            ->count();

        if ($totalPelaporan >= $maxPelaporanTotal) {
            return response()->json([
                'error' => "Sisa kuota pelaporan habis. Limit total (maksimal {$maxPelaporanTotal} kali untuk {$totalDays} hari pengerjaan) telah tercapai."
            ], 422);
        }

        return null;
    }

    public function start(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $validated = $request->validate([
            'workorder_id' => 'required|exists:workorder,id',
            'hasil_pengerjaan' => 'nullable|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy'  => 'nullable|numeric',
            'foto' => 'nullable|array',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $userId = optional($request->user())->id;
        $workorder = Workorder::with('assignmentMembers')->findOrFail($validated['workorder_id']);
        $allowedStatuses = array_filter([
            $this->statusId('DITUGASKAN_KE_STAFF'),
            $this->statusId('IN_PROGRESS'),
        ]);

        if (! $workorder->assignmentMembers->pluck('user_id')->contains($userId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if (! in_array($workorder->status_id, $allowedStatuses, true)) {
            return response()->json(['error' => 'Status WO tidak valid untuk mulai kerja'], 422);
        }

        DB::beginTransaction();
        try {
            $order = ((int) ProgressWorkorder::where('workorder_id', $workorder->id)->max('order')) + 1;

            $progress = ProgressWorkorder::create([
                'workorder_id' => $workorder->id,
                'tipe_progress_id' => $this->tipeId('MULAI'),
                'status_id' => $this->statusId('SUBMITTED'),
                'submitted_by_user_id' => $userId,
                'hasil_pengerjaan' => $validated['hasil_pengerjaan'] ?? 'Mulai pekerjaan',
                'waktu_submit' => now(),
                'order' => $order,
                'latitude'  => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy'  => $validated['accuracy'] ?? null,
            ]);

            if ($request->hasFile('foto')) {
                foreach ($request->file('foto') as $file) {
                    $path = $file->store('dokumentasi_progress', 'public');
                    $progress->dokumentasiProgress()->create([
                        'url' => $path,
                        'jenis' => 'HASIL_KERJA',
                    ]);
                }
            }

            $workorder->update(['status_id' => $this->statusId('IN_PROGRESS')]);

            $mulaiActionId = MasterAction::where('kode', 'MULAI_KERJA')->value('id');
            if ($mulaiActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id'    => $mulaiActionId,
                    'actor_id'     => $userId,
                    'keterangan'   => 'Petugas memulai pekerjaan',
                    'waktu_mulai'  => now(),
                ]);
            }

            DB::commit();

            // Refresh workorder agar progres_persen terhitung ulang (termasuk cek kuota)
            $workorder->refresh();
            $workorder->load('status');

            return response()->json([
                'progress' => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id' => $workorder->id,
                    'progres_persen' => $workorder->progres_persen,
                    'status' => $workorder->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function submit(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $validated = $request->validate([
            'workorder_id' => 'required|exists:workorder,id',
            'tipe_progress_kode' => 'required_without:tipe_progress|nullable|in:PROGRESS,SELESAI',
            'tipe_progress' => 'required_without:tipe_progress_kode|nullable|in:PROGRESS,SELESAI',
            'hasil_pengerjaan' => 'required|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy'  => 'nullable|numeric',
            'foto' => 'nullable|array',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $tipeProgressKode = $validated['tipe_progress_kode'] ?? $validated['tipe_progress'] ?? null;

        if ($tipeProgressKode === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tipe_progress_kode' => ['Harus mengisi tipe_progress_kode atau tipe_progress (PROGRESS / SELESAI).'],
            ]);
        }

        $userId = optional($request->user())->id;
        $workorder = Workorder::with('assignmentMembers')->findOrFail($validated['workorder_id']);

        if (! $workorder->assignmentMembers->pluck('user_id')->contains($userId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if ($tipeProgressKode === 'SELESAI') {
            $isPicForThisWO = $workorder->assignmentMembers
                ->where('user_id', $userId)
                ->where('is_pic', true)
                ->isNotEmpty();

            $request->user()->loadMissing('pegawai.jabatan');
            $jabatanKode = optional(optional($request->user()->pegawai)->jabatan)->kode;

            if (! $isPicForThisWO || $jabatanKode !== 'SENIOR_STAFF') {
                return response()->json([
                    'error' => 'Hanya PIC dengan jabatan Senior Staff yang dapat submit SELESAI'
                ], 403);
            }
        }

        if ($tipeProgressKode !== 'SELESAI') {
            if ($limitError = $this->validateProgressLimit($workorder)) {
                return $limitError;
            }
        }

        DB::beginTransaction();
        try {
            $order = ((int) ProgressWorkorder::where('workorder_id', $workorder->id)->max('order')) + 1;

            $progress = ProgressWorkorder::create([
                'workorder_id' => $workorder->id,
                'tipe_progress_id' => $this->tipeId($tipeProgressKode),
                'status_id' => $this->statusId('SUBMITTED'),
                'submitted_by_user_id' => $userId,
                'hasil_pengerjaan' => $validated['hasil_pengerjaan'],
                'waktu_submit' => now(),
                'order' => $order,
                'latitude'  => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy'  => $validated['accuracy'] ?? null,
            ]);

            if ($request->hasFile('foto')) {
                foreach ($request->file('foto') as $file) {
                    $path = $file->store('dokumentasi_progress', 'public');
                    $progress->dokumentasiProgress()->create([
                        'url' => $path,
                        'jenis' => 'HASIL_KERJA',
                    ]);
                }
            }

            if ($tipeProgressKode === 'SELESAI') {
                $workorder->update([
                    'status_id' => $this->statusId('PENGECEKAN'),
                    'tanggal_selesai' => now(),
                ]);
            } else {
                $workorder->update(['status_id' => $this->statusId('IN_PROGRESS')]);
            }

            $actionKode = $tipeProgressKode === 'SELESAI' ? 'SELESAI_KERJA' : 'SUBMIT_PROGRESS';
            $submitActionId = MasterAction::where('kode', $actionKode)->value('id');
            if ($submitActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id'    => $submitActionId,
                    'actor_id'     => $userId,
                    'keterangan'   => $tipeProgressKode === 'SELESAI'
                        ? 'Petugas menandai pekerjaan selesai'
                        : 'Petugas melaporkan progres',
                    'waktu_mulai'  => now(),
                ]);
            }

            DB::commit();

            // Refresh workorder agar progres_persen terhitung ulang (termasuk cek kuota)
            $workorder->refresh();
            $workorder->load('status');

            return response()->json([
                'progress' => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id' => $workorder->id,
                    'progres_persen' => $workorder->progres_persen,
                    'status' => $workorder->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function review(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $validated = $request->validate([
            'progress_id'      => 'required|exists:progress_workorder,id',
            'decision'         => 'required|in:accept,revisi,tolak',
            'approval_notes'   => 'nullable|string',
            'alasan_penolakan' => 'nullable|string',
            'field_to_revise'  => 'nullable|array',
        ]);

        $progress = ProgressWorkorder::with('workorder')->findOrFail($validated['progress_id']);
        $workorder = $progress->workorder;
        $userId = optional($request->user())->id;

        if ((int) $workorder->assigned_to !== (int) $userId) {
            return response()->json(['error' => 'Hanya SPV yang membuat WO ini yang bisa review'], 403);
        }

        $allowedStatuses = array_filter([
            $this->statusId('PENGECEKAN'),
            $this->statusId('IN_PROGRESS'),
        ]);
        if (!in_array((int) $workorder->status_id, $allowedStatuses, true)) {
            return response()->json(['error' => 'Status WO tidak valid untuk review'], 422);
        }

        DB::beginTransaction();
        try {
            // Catat hasil review ke progress_detail (1 progres bisa punya
            // banyak siklus review). Kolom alasan_penolakan/field_to_revise/
            // reviewed_at sudah tidak lagi disimpan di progress_workorder.
            $fieldToReviseArr = $validated['field_to_revise'] ?? null;
            $fieldToReviseStr = is_array($fieldToReviseArr)
                ? implode(',', array_map('strval', $fieldToReviseArr))
                : $fieldToReviseArr;

            if ($validated['decision'] === 'accept') {
                $approvalNotes = $validated['approval_notes'] ?? null;

                ProgressDetail::create([
                    'progress_workorder_id' => $progress->id,
                    'status'                => 'approved',
                    'reviewed_by_user_id'   => $userId,
                    'reviewed_at'           => now(),
                ]);

                $progress->update([
                    'status_id' => $this->statusId('VERIFIED'),
                ]);

                $workorder->update([
                    'status_id'           => $this->statusId('SELESAI'),
                    'approved_by_user_id' => $userId,
                    'approved_at'         => now(),
                    'approval_notes'      => $approvalNotes,
                    'tanggal_selesai'     => now(),
                ]);

                $workorder->loadMissing([
                    'jenisWorkorder',
                    'woMeter',
                    'woJaringan',
                    'woInfrastruktur',
                    'assignmentMembers.user.pegawai',
                ]);

                LaporanWorkorder::updateOrCreate(
                    ['workorder_id' => $workorder->id],
                    [
                        'nomor_laporan'         => sprintf('LAP-WO-%s-%04d', now()->format('Y'), $workorder->id),
                        'tanggal_terbit'        => now(),
                        'ringkasan_pekerjaan'   => $workorder->deskripsi ?? $workorder->nama_workorder,
                        'hasil_akhir_snapshot'  => $this->resolveKategoriSnapshot($workorder),
                        'petugas_snapshot'      => $workorder->assignmentMembers->map(fn ($m) => [
                            'user_id' => optional($m->user)->id,
                            'nama'    => optional(optional($m->user)->pegawai)->nama,
                            'nip'     => optional(optional($m->user)->pegawai)->nip,
                        ])->values()->all(),
                        'catatan_spv'           => $approvalNotes,
                        'issued_by_user_id'     => $workorder->assigned_to,
                        'approved_by_user_id'   => $userId,
                        'approved_at'           => now(),
                    ]
                );

                $approveActionId = MasterAction::where('kode', 'APPROVE')->value('id');
                if ($approveActionId) {
                    WorkorderAction::create([
                        'workorder_id' => $workorder->id,
                        'action_id'    => $approveActionId,
                        'actor_id'     => $userId,
                        'keterangan'   => $approvalNotes,
                        'waktu_mulai'  => now(),
                    ]);
                }
            } elseif ($validated['decision'] === 'revisi') {
                ProgressDetail::create([
                    'progress_workorder_id' => $progress->id,
                    'status'                => 'rejected',
                    'reviewed_by_user_id'   => $userId,
                    'reviewed_at'           => now(),
                    'alasan_penolakan'      => $validated['alasan_penolakan'] ?? null,
                    'field_to_revise'       => $fieldToReviseStr,
                ]);

                $progress->update([
                    'status_id' => $this->statusId('REVISI_REQUESTED'),
                ]);

                $nextOrder = ((int) ProgressWorkorder::where('workorder_id', $workorder->id)->max('order')) + 1;
                ProgressWorkorder::create([
                    'workorder_id' => $workorder->id,
                    'tipe_progress_id' => $this->tipeId('REVISI'),
                    'status_id' => $this->statusId('SUBMITTED'),
                    'submitted_by_user_id' => $userId,
                    'hasil_pengerjaan' => 'SPV meminta revisi',
                    'order' => $nextOrder,
                ]);

                $workorder->update(['status_id' => $this->statusId('IN_PROGRESS')]);
            } else {
                ProgressDetail::create([
                    'progress_workorder_id' => $progress->id,
                    'status'                => 'rejected',
                    'reviewed_by_user_id'   => $userId,
                    'reviewed_at'           => now(),
                    'alasan_penolakan'      => $validated['alasan_penolakan'] ?? null,
                ]);

                $progress->update([
                    'status_id' => $this->statusId('DITOLAK_SPV'),
                ]);

                $nextOrder = ((int) ProgressWorkorder::where('workorder_id', $workorder->id)->max('order')) + 1;
                ProgressWorkorder::create([
                    'workorder_id' => $workorder->id,
                    'tipe_progress_id' => $this->tipeId('DITOLAK'),
                    'status_id' => $this->statusId('SUBMITTED'),
                    'submitted_by_user_id' => $userId,
                    'hasil_pengerjaan' => 'SPV menolak hasil pekerjaan',
                    'order' => $nextOrder,
                ]);

                $workorder->update(['status_id' => $this->statusId('DITOLAK_SPV')]);

                $rejectActionId = MasterAction::where('kode', 'REJECT')->value('id');
                if ($rejectActionId) {
                    WorkorderAction::create([
                        'workorder_id' => $workorder->id,
                        'action_id'    => $rejectActionId,
                        'actor_id'     => $userId,
                        'keterangan'   => $validated['alasan_penolakan'] ?? null,
                        'waktu_mulai'  => now(),
                    ]);
                }
            }

            DB::commit();

            $workorder->refresh();
            $workorder->load('status');

            return response()->json([
                'message' => 'Review progress berhasil diproses',
                'workorder' => [
                    'id' => $workorder->id,
                    'status' => $workorder->status,
                    'approved_by_user_id' => $workorder->approved_by_user_id,
                    'approved_at' => $workorder->approved_at,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cancel(Request $request, $id)
    {
        $progress = ProgressWorkorder::with('workorder.assignmentMembers')->findOrFail($id);
        $userId = optional($request->user())->id;

        if ((int) $progress->submitted_by_user_id !== (int) $userId) {
            return response()->json(['error' => 'Hanya petugas yang submit yang bisa membatalkan'], 403);
        }

        if ((int) $progress->status_id !== $this->statusId('SUBMITTED')) {
            return response()->json(['error' => 'Progress sudah direview, tidak bisa dibatalkan'], 422);
        }

        if ($progress->waktu_submit === null) {
            return response()->json(['error' => 'Progress belum disubmit'], 422);
        }

        $submitTime = \Illuminate\Support\Carbon::parse($progress->waktu_submit);
        if ($submitTime->diffInSeconds(now(), false) > 300) {
            return response()->json(['error' => 'Batas waktu pembatalan (5 menit) telah lewat'], 422);
        }

        DB::beginTransaction();
        try {
            $progress->update([
                'status_id' => $this->statusId('DIBATALKAN'),
                'waktu_submit' => null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Progress berhasil dibatalkan',
                'progress_id' => $progress->id,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = ProgressWorkorder::with('dokumentasiProgress');

            if ($request->has('workorder_id')) {
                $query->where('workorder_id', $request->query('workorder_id'))
                    ->orderBy('order', 'asc');
                $item = $query->get();
                return response()->json($item, 200);
            }
            $list = $query->get();
            return response()->json($list, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data progress workorder',
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
        try {
            $progressWorkorder = ProgressWorkorder::with('dokumentasiProgress')->findOrFail($id);
            return response()->json($progressWorkorder);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Detail progress not found'], 404);
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
        $this->hydrateInputFromBody($request);

        $validatedData = $request->validate([
            'hasil_pengerjaan' => 'required|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy'  => 'nullable|numeric',
            'foto' => 'nullable|array',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $progressWorkorder = ProgressWorkorder::with('workorder')->findOrFail($id);
        $userId = optional($request->user())->id;

        if ((int) $progressWorkorder->workorder->assigned_to !== (int) $userId) {
            return response()->json(['error' => 'Hanya petugas assigned yang bisa update progress'], 403);
        }

    DB::beginTransaction();
    try {
        $progressWorkorder->update([
            'waktu_submit'         => now(),
            'hasil_pengerjaan'     => $validatedData['hasil_pengerjaan'],
            'submitted_by_user_id' => optional($request->user())->id,
        ]);

        // Foto hasil kerja
        if ($request->hasFile('foto')) {
            foreach ($request->file('foto') as $file) {
                $path = $file->store('dokumentasi_progress', 'public');
                $progressWorkorder->dokumentasiProgress()->create(['url' => $path]);
            }
        }

        (new ProgressWorkorderService())->updateStatusOnSubmit($progressWorkorder->id);

        DB::commit();
        return response()->json($progressWorkorder->load('dokumentasiProgress'), 200);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Update error:', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Terjadi kesalahan saat memperbarui data'], 500);
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

    /**
     * Manual run to add progress for active workorders
     *
     * @return \Illuminate\Http\Response
     */
    public function manualRun()
    {
        try {
            $service = new ProgressWorkorderService();
            $inProgressId = $this->statusId('IN_PROGRESS');
            $workorders = Workorder::where('status_id', $inProgressId)->get();

            foreach ($workorders as $workorder) {
                $service->addWorkorderProgress($workorder->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Progress ditambahkan untuk semua workorder aktif'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menjalankan manual run',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the remaining quota for progress submission
     *
     * @param  int  $workorderId
     * @return \Illuminate\Http\Response
     */
    public function quota($workorderId)
    {
        try {
            $workorder = Workorder::with('workorderAssignment')->findOrFail($workorderId);
            $mulaiTipeId = TipeProgress::where('kode', 'MULAI')->value('id');

            $countHariIni = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->whereNotNull('waktu_submit')
                ->where('tipe_progress_id', '!=', $mulaiTipeId)
                ->whereDate('waktu_submit', now()->toDateString())
                ->count();

            $dailyQuotaEverExhausted = DB::table('progress_workorder')
                ->selectRaw('1')
                ->where('workorder_id', $workorder->id)
                ->whereNotNull('waktu_submit')
                ->where('tipe_progress_id', '!=', $mulaiTipeId)
                ->groupByRaw('waktu_submit::date')
                ->havingRaw('COUNT(*) >= 8')
                ->exists();

            $sisaHariIni = $dailyQuotaEverExhausted ? 0 : max(0, 8 - $countHariIni);

            // Sisa kuota pelaporan total berdasarkan estimasi hari
            $assignment = $workorder->workorderAssignment;
            $tanggalMulai = optional($assignment)->tanggal_mulai ?? $workorder->tanggal_mulai;
            $estimasiSelesai = optional($assignment)->estimasi_selesai;

            $totalDays = 1;
            if ($tanggalMulai && $estimasiSelesai) {
                $start = \Illuminate\Support\Carbon::parse($tanggalMulai)->startOfDay();
                $end = \Illuminate\Support\Carbon::parse($estimasiSelesai)->startOfDay();
                $diff = max(0, (int) $start->diffInDays($end, false));
                $totalDays = $diff + 1;
            }

            $maxPelaporanTotal = $totalDays * 8;
            $totalPelaporan = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->whereNotNull('waktu_submit')
                ->where('tipe_progress_id', '!=', $mulaiTipeId)
                ->count();

            $sisaKuotaTotal = max(0, $maxPelaporanTotal - $totalPelaporan);

            $cancelableIds = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->where('status_id', $this->statusId('SUBMITTED'))
                ->whereNotNull('waktu_submit')
                ->where('waktu_submit', '>=', now()->subMinutes(5))
                ->pluck('id');

            return response()->json([
                'workorder_id' => $workorder->id,
                'sisa_kuota_hari_ini' => $sisaHariIni,
                'sisa_kuota_total' => $sisaKuotaTotal,
                'total_kuota_hari_ini' => 8,
                'total_kuota_keseluruhan' => $maxPelaporanTotal,
                'sudah_submit_hari_ini' => $countHariIni,
                'sudah_submit_total' => $totalPelaporan,
                'estimasi_hari' => $totalDays,
                'bisa_cancel' => $cancelableIds,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Workorder not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
