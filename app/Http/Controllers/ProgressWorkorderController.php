<?php

namespace App\Http\Controllers;

use App\Models\ProgressWorkorder;
use App\Models\Status;
use App\Models\TipeProgress;
use App\Models\Workorder;
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

    public function start(Request $request)
    {
        $validated = $request->validate([
            'workorder_id' => 'required|exists:workorder,id',
            'hasil_pengerjaan' => 'nullable|string|max:255',
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

            DB::commit();
            return response()->json($progress->load('dokumentasiProgress'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'workorder_id' => 'required|exists:workorder,id',
            'tipe_progress_kode' => 'nullable|in:PROGRESS,SELESAI',
            'tipe_progress' => 'nullable|in:PROGRESS,SELESAI',
            'hasil_pengerjaan' => 'required|string|max:255',
            'foto' => 'nullable|array',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Backward compatibility: terima key lama `tipe_progress`
        // namun prioritaskan key resmi `tipe_progress_kode`.
        $tipeProgressKode = $validated['tipe_progress_kode'] ?? $validated['tipe_progress'] ?? null;
        if ($tipeProgressKode === null) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'tipe_progress_kode' => ['The tipe progress kode field is required.'],
                ],
            ], 422);
        }

        $userId = optional($request->user())->id;
        $workorder = Workorder::with('assignmentMembers')->findOrFail($validated['workorder_id']);

        if (! $workorder->assignmentMembers->pluck('user_id')->contains($userId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
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

            DB::commit();
            return response()->json($progress->load('dokumentasiProgress'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function review(Request $request)
    {
        $validated = $request->validate([
            'progress_id' => 'required|exists:progress_workorder,id',
            'decision' => 'required|in:accept,revisi,tolak',
            'alasan_penolakan' => 'nullable|string',
            'field_to_revise' => 'nullable|array',
        ]);

        $progress = ProgressWorkorder::with('workorder')->findOrFail($validated['progress_id']);
        $workorder = $progress->workorder;
        $userId = optional($request->user())->id;

        if ((int) $workorder->assigned_to !== (int) $userId) {
            return response()->json(['error' => 'Hanya SPV assigned yang bisa review'], 403);
        }

        DB::beginTransaction();
        try {
            if ($validated['decision'] === 'accept') {
                $progress->update([
                    'status_id' => $this->statusId('VERIFIED'),
                    'reviewed_by_user_id' => $userId,
                    'reviewed_at' => now(),
                ]);

                $workorder->update(['status_id' => $this->statusId('MENUNGGU_APPROVAL_MANAGER')]);
            } elseif ($validated['decision'] === 'revisi') {
                $progress->update([
                    'status_id' => $this->statusId('REVISI_REQUESTED'),
                    'reviewed_by_user_id' => $userId,
                    'reviewed_at' => now(),
                    'alasan_penolakan' => $validated['alasan_penolakan'] ?? null,
                    'field_to_revise' => $validated['field_to_revise'] ?? null,
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
                $progress->update([
                    'status_id' => $this->statusId('DITOLAK_SPV'),
                    'reviewed_by_user_id' => $userId,
                    'reviewed_at' => now(),
                    'alasan_penolakan' => $validated['alasan_penolakan'] ?? null,
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
            }

            DB::commit();
            return response()->json(['message' => 'Review progress berhasil diproses'], 200);
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
            $query = ProgressWorkorder::with('dokumentasiProgress', 'detailProgress');

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
            $progressWorkorder = ProgressWorkorder::with('dokumentasiProgress', 'detailProgress')->findOrFail($id);
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
        Log::info('Incoming request headers:', $request->header());
        Log::info('Incoming request data:', $request->all());
        Log::info('Incoming files:', $request->file());
        Log::info('Raw request body:', [$request->getContent()]);
        Log::info('POST data:', $_POST);
        Log::info('FILES data:', $_FILES);
        Log::info('Method:', [$request->method()]);

        
        $progressWorkorder = ProgressWorkorder::with('tipeProgress')->findOrFail($id);
        $kodeTipe = optional($progressWorkorder->tipeProgress)->kode;

        // Revisi Mei 2026: Blok `detail_progress` (EAV lama) sudah DIHAPUS.
        // Tabel detail_progress + detail_form di-drop. Field hasil akhir
        // pengerjaan untuk tipe SELESAI sekarang di-UPDATE langsung ke
        // wo_meter / wo_jaringan / wo_infrastruktur — lihat Flow_WO Tahap 8.
        // Endpoint terpisah untuk update field tabel kategori akan disediakan
        // di ticket yang sama (belum diimplementasi di revisi ini).
        $rules = [
            'hasil_pengerjaan' => 'required|string|max:255',
            'waktu_submit'     => 'required|date',
            'foto'             => 'required|array|min:1',
            'foto.*'           => 'image|mimes:jpeg,png,jpg|max:2048',
        ];

        $validatedData = $request->validate($rules);

        DB::beginTransaction();
        try {
            $progressWorkorder->update([
                'waktu_submit'         => now(),
                'hasil_pengerjaan'     => $validatedData['hasil_pengerjaan'],
                'submitted_by_user_id' => optional($request->user())->id,
            ]);

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
            $workorders = \App\Models\Workorder::where('status_id', $inProgressId)->get();

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
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
