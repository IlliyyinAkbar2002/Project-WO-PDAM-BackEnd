<?php

namespace App\Http\Controllers;

use App\Models\LaporanWorkorder;
use App\Models\MasterAction;
use App\Models\ProgressDetail;
use App\Models\ProgressWorkorder;
use App\Models\Status;
use App\Models\TipeProgress;
use App\Models\User;
use App\Models\Workorder;
use App\Models\WorkorderAction;
use App\Models\WoInfrastruktur;
use App\Models\WoJaringan;
use App\Models\WoMeter;
use App\Notifications\WorkOrderNotification;
use App\Services\ProgressWorkorderService;
use App\Constants\TahapanWorkorder;
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
     * Estimasi jumlah hari kerja WO (selisih tanggal_mulai → estimasi_selesai, minimal 1).
     * Sekadar metadata tampilan (estimasi_hari) — bukan kuota.
     */
    private function estimasiHari(?string $tanggalMulai, ?string $estimasiSelesai): int
    {
        if (!$tanggalMulai || !$estimasiSelesai) {
            return 1;
        }
        $start = \Illuminate\Support\Carbon::parse($tanggalMulai)->startOfDay();
        $end   = \Illuminate\Support\Carbon::parse($estimasiSelesai)->startOfDay();
        return max(1, (int) $start->diffInDays($end, false));
    }

    private function rejectIfWorkorderFinal(Workorder $workorder): ?\Illuminate\Http\JsonResponse
    {
        if ((int) $workorder->status_id === $this->statusId('DITOLAK_SPV')) {
            return response()->json([
                'error' => 'WO sudah ditolak final (DITOLAK_SPV) dan tidak dapat dilanjutkan.'
            ], 422);
        }

        return null;
    }

    protected function hydrateInputFromBody(Request $request): void
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


    public function start(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $rules = [
            'workorder_id' => 'required|exists:workorder,id',
            'hasil_pengerjaan' => 'nullable|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy'  => 'nullable|numeric',
            'foto' => 'nullable|array',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:4048',
        ];

        $kategoriForm = optional(
            optional(Workorder::find($request->input('workorder_id')))->jenisWorkorder
        )->kategori_form;

        if ($kategoriForm === 'meter') {
            $rules['nomor_meter']        = 'required|string|max:64';
            $rules['kondisi_meter_awal'] = 'nullable|string';
        } elseif ($kategoriForm === 'jaringan') {
            $rules['jenis_pipa']        = 'required|string|max:64';
            $rules['diameter_pipa']     = 'nullable|numeric';
            $rules['panjang_pipa']      = 'nullable|numeric';
            $rules['tingkat_kerusakan'] = 'nullable|string|max:32';
        } elseif ($kategoriForm === 'infrastruktur') {
            $rules['nama_aset']    = 'required|string|max:255';
            $rules['jenis_aset']   = 'required|string|max:64';
            $rules['kapasitas']    = 'nullable|string|max:64';
            $rules['kondisi_awal'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        $userId = optional($request->user())->id;
        $workorder = Workorder::with('assignmentMembers')->findOrFail($validated['workorder_id']);
        $allowedStatuses = array_filter([
            $this->statusId('DITUGASKAN_KE_STAFF'),
            $this->statusId('IN_PROGRESS'),
        ]);

        if (! $workorder->assignmentMembers->pluck('user_id')->contains($userId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($workorder)) {
            return $finalError;
        }

        if (! in_array($workorder->status_id, $allowedStatuses, true)) {
            return response()->json(['error' => 'Status WO tidak valid untuk mulai kerja'], 422);
        }

        if ((int) $workorder->status_id === $this->statusId('DITUGASKAN_KE_STAFF')) {
            $dibatalkanId = $this->statusId('DIBATALKAN');
            $hasInspeksi = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->where('tipe_progress_id', $this->tipeId('INSPEKSI'))
                ->whereNotNull('waktu_submit')
                ->when($dibatalkanId !== null, fn ($q) => $q->where('status_id', '!=', $dibatalkanId))
                ->exists();

            if (! $hasInspeksi) {
                return response()->json([
                    'error' => 'Lakukan dan submit inspeksi terlebih dahulu sebelum mulai kerja.'
                ], 422);
            }
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
                'tahapan'   => TahapanWorkorder::PERSIAPAN,
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

        
            $this->persistKategoriAwal($workorder, $kategoriForm, $validated);

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

            $tahapanTertinggi = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->whereNotNull('waktu_submit')
                ->when($this->statusId('DIBATALKAN') !== null, fn ($q) => $q->where('status_id', '!=', $this->statusId('DIBATALKAN')))
                ->max('tahapan');

            return response()->json([
                'progress' => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id' => $workorder->id,
                    'progres_persen' => $workorder->progres_persen,
                    'status' => $workorder->status,
                    'tahapan_tertinggi' => $tahapanTertinggi,
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

        if ($request->filled('tipe_progress_id')
            && ! $request->filled('tipe_progress_kode')
            && ! $request->filled('tipe_progress')) {
            $kodeFromId = TipeProgress::where('id', $request->input('tipe_progress_id'))->value('kode');
            if ($kodeFromId !== null) {
                $request->merge(['tipe_progress_kode' => $kodeFromId]);
            }
        }

        $rules = [
            'workorder_id' => 'required|exists:workorder,id',
            'tipe_progress_kode' => 'required_without:tipe_progress|nullable|in:PROGRESS,SELESAI,INSPEKSI',
            'tipe_progress' => 'required_without:tipe_progress_kode|nullable|in:PROGRESS,SELESAI,INSPEKSI',
            'hasil_pengerjaan' => 'required|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy'  => 'nullable|numeric',
            'foto' => 'nullable|array',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:4048',
            'tahapan' => 'nullable|integer|between:1,4',
        ];

        
        $tipeProgressKode = $request->input('tipe_progress_kode') ?? $request->input('tipe_progress');
        $kategoriForm = null;
        if ($tipeProgressKode === 'SELESAI') {
            $kategoriForm = optional(
                optional(Workorder::find($request->input('workorder_id')))->jenisWorkorder
            )->kategori_form;

            if ($kategoriForm === 'meter') {
                $rules['kondisi_meter_akhir'] = 'required|string';
                $rules['hasil_kalibrasi'] = 'required|string';
            } elseif ($kategoriForm === 'jaringan') {
                $rules['tindakan_perbaikan'] = 'required|string';
                $rules['hasil_inspeksi'] = 'required|string';
            } elseif ($kategoriForm === 'infrastruktur') {
                $rules['kondisi_akhir'] = 'required|string';
                $rules['jadwal_pemeliharaan'] = 'required|date';
                $rules['tindakan'] = 'required|string';
            }
        }

        
        if ($tipeProgressKode === 'INSPEKSI') {
            $rules['foto'] = 'required|array|min:1';
        }

        $validated = $request->validate($rules);

        $tipeProgressKode = $validated['tipe_progress_kode'] ?? $validated['tipe_progress'] ?? null;

        if ($tipeProgressKode === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tipe_progress_kode' => ['Harus mengisi tipe_progress_kode atau tipe_progress (PROGRESS / SELESAI / INSPEKSI).'],
            ]);
        }

        $userId = optional($request->user())->id;
        $workorder = Workorder::with('assignmentMembers')->findOrFail($validated['workorder_id']);

        if (! $workorder->assignmentMembers->pluck('user_id')->contains($userId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($workorder)) {
            return $finalError;
        }

        if ($tipeProgressKode === 'INSPEKSI') {

            if ($this->tipeId('INSPEKSI') === null) {
                return response()->json([
                    'error' => 'Tipe progress INSPEKSI belum tersedia di database. Jalankan: php artisan db:seed'
                ], 422);
            }

            
            $allowedForInspeksi = array_filter([
                $this->statusId('DITUGASKAN_KE_STAFF'),
                $this->statusId('IN_PROGRESS'),
            ]);
            if (! in_array((int) $workorder->status_id, $allowedForInspeksi, true)) {
                return response()->json(['error' => 'Status WO tidak valid untuk inspeksi'], 422);
            }
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


        DB::beginTransaction();
        try {
            $order = ((int) ProgressWorkorder::where('workorder_id', $workorder->id)->max('order')) + 1;

            $tahapan = $validated['tahapan'] ?? null;
            if ($tipeProgressKode === 'SELESAI') {
                $tahapan = TahapanWorkorder::DOKUMENTASI;
            } elseif ($tipeProgressKode === 'INSPEKSI') {
                $tahapan = TahapanWorkorder::PERSIAPAN;
            }

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
                'tahapan'   => $tahapan,
            ]);

            if ($request->hasFile('foto')) {
                foreach ($request->file('foto') as $file) {
                    $path = $file->store('dokumentasi_progress', 'public');
                    $progress->dokumentasiProgress()->create([
                        'url' => $path,
                        // Foto inspeksi diberi jenis tersendiri agar FE/dashboard
                        // bisa membedakannya dari foto hasil kerja.
                        'jenis' => $tipeProgressKode === 'INSPEKSI' ? 'INSPEKSI' : 'HASIL_KERJA',
                    ]);
                }
            }

            if ($tipeProgressKode === 'SELESAI') {
                $workorder->update([
                    'status_id' => $this->statusId('PENGECEKAN'),
                    'tanggal_selesai' => now(),
                ]);


                $this->persistKategoriHasilAkhir($workorder, $kategoriForm, $validated);
            } elseif ($tipeProgressKode === 'PROGRESS') {
                $workorder->update(['status_id' => $this->statusId('IN_PROGRESS')]);
            }


            $actionKode = match (true) {
                $tipeProgressKode === 'SELESAI'  => 'SELESAI_KERJA',
                $tipeProgressKode === 'INSPEKSI' => 'INSPEKSI',
                default                          => 'SUBMIT_PROGRESS',
            };
            $submitActionId = MasterAction::where('kode', $actionKode)->value('id');
            if ($submitActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id'    => $submitActionId,
                    'actor_id'     => $userId,
                    'keterangan'   => match (true) {
                        $tipeProgressKode === 'SELESAI'  => 'Petugas menandai pekerjaan selesai',
                        $tipeProgressKode === 'INSPEKSI' => 'Petugas melakukan inspeksi awal',
                        default                          => 'Petugas melaporkan progres',
                    },
                    'waktu_mulai'  => now(),
                ]);
            }

            DB::commit();

            if ($tipeProgressKode === 'SELESAI') {
                $this->notifyWorkOrderReadyForReview($workorder, $userId);
            }

            // Refresh workorder agar progres_persen terhitung ulang (termasuk cek kuota)
            $workorder->refresh();
            $workorder->load('status');

            $tahapanTertinggi = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->whereNotNull('waktu_submit')
                ->when($this->statusId('DIBATALKAN') !== null, fn ($q) => $q->where('status_id', '!=', $this->statusId('DIBATALKAN')))
                ->max('tahapan');

            return response()->json([
                'progress' => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id' => $workorder->id,
                    'progres_persen' => $workorder->progres_persen,
                    'status' => $workorder->status,
                    'tahapan_tertinggi' => $tahapanTertinggi,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

   
    public function resubmit(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $progressId = $request->input('progress_id') ?? $request->input('progress_workorder_id');
        $request->merge(['progress_id' => $progressId]);

        $request->validate([
            'progress_id' => 'required|exists:progress_workorder,id',
        ]);

        $progress = ProgressWorkorder::with([
            'workorder.assignmentMembers',
            'workorder.jenisWorkorder',
            'tipeProgress',
        ])->findOrFail($progressId);
        $workorder = $progress->workorder;
        $tipeKode = optional($progress->tipeProgress)->kode;

        $rules = [
            'hasil_pengerjaan' => 'required|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy'  => 'nullable|numeric',
            'foto' => 'nullable|array',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:4048',
            'tahapan' => 'nullable|integer|between:1,4',
        ];

        $kategoriForm = null;
        if ($tipeKode === 'SELESAI') {
            $kategoriForm = optional($workorder->jenisWorkorder)->kategori_form;

            if ($kategoriForm === 'meter') {
                $rules['kondisi_meter_akhir'] = 'required|string';
                $rules['hasil_kalibrasi'] = 'required|string';
            } elseif ($kategoriForm === 'jaringan') {
                $rules['tindakan_perbaikan'] = 'required|string';
                $rules['hasil_inspeksi'] = 'required|string';
            } elseif ($kategoriForm === 'infrastruktur') {
                $rules['kondisi_akhir'] = 'required|string';
                $rules['jadwal_pemeliharaan'] = 'required|date';
                $rules['tindakan'] = 'required|string';
            }
        }

        $validated = $request->validate($rules);

        $userId = optional($request->user())->id;

        if (! $workorder->assignmentMembers->pluck('user_id')->contains($userId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($workorder)) {
            return $finalError;
        }

        if (in_array($tipeKode, ['REVISI', 'DITOLAK'], true)) {
            return response()->json(['error' => 'Baris ini bukan laporan petugas dan tidak dapat diresubmit'], 422);
        }

        if ((int) $progress->status_id !== $this->statusId('REVISI_REQUESTED')) {
            return response()->json(['error' => 'Progress tidak dalam status revisi'], 422);
        }

        if ($tipeKode === 'SELESAI') {
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

        DB::beginTransaction();
        try {
            $tahapan = $validated['tahapan'] ?? null;
            if ($tipeKode === 'SELESAI') {
                $tahapan = TahapanWorkorder::DOKUMENTASI;
            } elseif ($tipeKode === 'INSPEKSI') {
                $tahapan = TahapanWorkorder::PERSIAPAN;
            }

            // Update baris yang direvisi in-place. submitted_by_user_id sengaja
            // tidak diubah agar baris tetap milik petugas pelapor aslinya.
            $progress->update([
                'status_id'        => $this->statusId('SUBMITTED'),
                'hasil_pengerjaan' => $validated['hasil_pengerjaan'],
                'waktu_submit'     => now(),
                'latitude'         => $validated['latitude'],
                'longitude'        => $validated['longitude'],
                'accuracy'         => $validated['accuracy'] ?? null,
                'tahapan'          => $tahapan,
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

            // Siklus review baru untuk hasil perbaikan ini.
            ProgressDetail::create([
                'progress_workorder_id' => $progress->id,
                'status' => 'pending',
            ]);

            if ($tipeKode === 'SELESAI') {
                $workorder->update([
                    'status_id' => $this->statusId('PENGECEKAN'),
                    'tanggal_selesai' => now(),
                ]);

                $this->persistKategoriHasilAkhir($workorder, $kategoriForm, $validated);
            } elseif ($tipeKode !== 'INSPEKSI') {
                $workorder->update(['status_id' => $this->statusId('IN_PROGRESS')]);
            }

            $actionKode = match (true) {
                $tipeKode === 'SELESAI'  => 'SELESAI_KERJA',
                $tipeKode === 'INSPEKSI' => 'INSPEKSI',
                default                  => 'SUBMIT_PROGRESS',
            };
            $submitActionId = MasterAction::where('kode', $actionKode)->value('id');
            if ($submitActionId) {
                WorkorderAction::create([
                    'workorder_id' => $workorder->id,
                    'action_id'    => $submitActionId,
                    'actor_id'     => $userId,
                    'keterangan'   => match (true) {
                        $tipeKode === 'SELESAI'  => 'Petugas mengirim ulang hasil pekerjaan (revisi)',
                        $tipeKode === 'INSPEKSI' => 'Petugas mengirim ulang inspeksi (revisi)',
                        default                  => 'Petugas mengirim ulang progres (revisi)',
                    },
                    'waktu_mulai'  => now(),
                ]);
            }

            DB::commit();

            if ($tipeKode === 'SELESAI') {
                $this->notifyWorkOrderReadyForReview($workorder, $userId);
            }

            $workorder->refresh();
            $workorder->load('status');

            $tahapanTertinggi = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->whereNotNull('waktu_submit')
                ->when($this->statusId('DIBATALKAN') !== null, fn ($q) => $q->where('status_id', '!=', $this->statusId('DIBATALKAN')))
                ->max('tahapan');

            return response()->json([
                'message' => 'Resubmit progress berhasil',
                'progress' => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id' => $workorder->id,
                    'progres_persen' => $workorder->progres_persen,
                    'status' => $workorder->status,
                    'tahapan_tertinggi' => $tahapanTertinggi,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    private function persistKategoriAwal(Workorder $workorder, ?string $kategoriForm, array $validated): void
    {
        if ($kategoriForm === 'meter') {
            WoMeter::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'nomor_meter'        => $validated['nomor_meter'],
                    'kondisi_meter_awal' => $validated['kondisi_meter_awal'] ?? null,
                ]
            );
        } elseif ($kategoriForm === 'jaringan') {
            WoJaringan::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'jenis_pipa'        => $validated['jenis_pipa'],
                    'diameter_pipa'     => $validated['diameter_pipa'] ?? null,
                    'panjang_pipa'      => $validated['panjang_pipa'] ?? null,
                    'tingkat_kerusakan' => $validated['tingkat_kerusakan'] ?? null,
                ]
            );
        } elseif ($kategoriForm === 'infrastruktur') {
            WoInfrastruktur::updateOrCreate(
                ['workorder_id' => $workorder->id],
                [
                    'nama_aset'    => $validated['nama_aset'],
                    'jenis_aset'   => $validated['jenis_aset'],
                    'kapasitas'    => $validated['kapasitas'] ?? null,
                    'kondisi_awal' => $validated['kondisi_awal'] ?? null,
                ]
            );
        }
    }

 
    private function persistKategoriHasilAkhir(Workorder $workorder, ?string $kategoriForm, array $validated): void
    {
        if ($kategoriForm === 'meter') {
            WoMeter::where('workorder_id', $workorder->id)->update([
                'kondisi_meter_akhir' => $validated['kondisi_meter_akhir'],
                'hasil_kalibrasi'     => $validated['hasil_kalibrasi'],
            ]);
        } elseif ($kategoriForm === 'jaringan') {
            WoJaringan::where('workorder_id', $workorder->id)->update([
                'tindakan_perbaikan' => $validated['tindakan_perbaikan'],
                'hasil_inspeksi'     => $validated['hasil_inspeksi'],
            ]);
        } elseif ($kategoriForm === 'infrastruktur') {
            WoInfrastruktur::where('workorder_id', $workorder->id)->update([
                'kondisi_akhir'       => $validated['kondisi_akhir'],
                'jadwal_pemeliharaan' => $validated['jadwal_pemeliharaan'],
                'tindakan'            => $validated['tindakan'],
            ]);
        }
    }


    private function notifyWorkOrderReadyForReview(Workorder $workorder, ?int $picUserId): void
    {
        try {
            $spvId = $workorder->assigned_to;
            if (! $spvId) {
                return;
            }

            $spv = User::find($spvId);
            if (! $spv) {
                return;
            }

            $pic = $picUserId ? User::with('pegawai:id,nama')->find($picUserId) : null;
            $senderName = optional(optional($pic)->pegawai)->nama ?? optional($pic)->name ?? 'Petugas';

            $spv->notify(new WorkOrderNotification(
                'Work Order Menunggu Review',
                "{$senderName} telah menyelesaikan WO #{$workorder->nama_workorder} dan menunggu review Anda.",
                (int) $workorder->id,
                'wo_ready_for_review',
                $senderName
            ));
        } catch (\Throwable $e) {
            Log::warning('notifyWorkOrderReadyForReview failed', [
                'workorder_id' => $workorder->id,
                'pic_user_id'  => $picUserId,
                'error'        => $e->getMessage(),
            ]);
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

        if ($finalError = $this->rejectIfWorkorderFinal($workorder)) {
            return $finalError;
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
                    'status_id' => $this->statusId('REVISI_REQUESTED'),
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
                    'status_id' => $this->statusId('DITOLAK_SPV'),
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
            $progress = ProgressWorkorder::with([
                'dokumentasiProgress',
                'workorder.woMeter',
                'workorder.woJaringan',
                'workorder.woInfrastruktur',
            ])->findOrFail($id);

            $wo = $progress->workorder;

            $kategoriData = null;
            if ($wo) {
                if ($wo->woMeter) {
                    $kategoriData = [
                        'nomor_meter'         => $wo->woMeter->nomor_meter,
                        'kondisi_meter_awal'  => $wo->woMeter->kondisi_meter_awal,
                        'kondisi_meter_akhir' => $wo->woMeter->kondisi_meter_akhir,
                        'hasil_kalibrasi'     => $wo->woMeter->hasil_kalibrasi,     
                    ];
                } elseif ($wo->woJaringan) {
                    $kategoriData = [
                        'jenis_pipa'         => $wo->woJaringan->jenis_pipa,
                        'diameter_pipa'      => $wo->woJaringan->diameter_pipa,
                        'panjang_pipa'       => $wo->woJaringan->panjang_pipa,
                        'tingkat_kerusakan'  => $wo->woJaringan->tingkat_kerusakan,
                        'tindakan_perbaikan' => $wo->woJaringan->tindakan_perbaikan,
                        'hasil_inspeksi'     => $wo->woJaringan->hasil_inspeksi,    
                    ];
                } elseif ($wo->woInfrastruktur) {
                    $kategoriData = [
                        'nama_aset'           => $wo->woInfrastruktur->nama_aset,
                        'jenis_aset'          => $wo->woInfrastruktur->jenis_aset,
                        'kapasitas'           => $wo->woInfrastruktur->kapasitas,
                        'kondisi_awal'        => $wo->woInfrastruktur->kondisi_awal,
                        'kondisi_akhir'       => $wo->woInfrastruktur->kondisi_akhir,      
                        'jadwal_pemeliharaan' => $wo->woInfrastruktur->jadwal_pemeliharaan,
                        'tindakan'            => $wo->woInfrastruktur->tindakan,           
                    ];
                }
            }

            $payload = $progress->toArray();
            unset($payload['workorder']);
            $payload['kategori_data'] = $kategoriData;

            return response()->json($payload);
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
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:4048',
        ]);

        $progressWorkorder = ProgressWorkorder::with('workorder')->findOrFail($id);
        $userId = optional($request->user())->id;

        if ((int) $progressWorkorder->workorder->assigned_to !== (int) $userId) {
            return response()->json(['error' => 'Hanya petugas assigned yang bisa update progress'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($progressWorkorder->workorder)) {
            return $finalError;
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
     * Get progress grouped by team member for a specific workorder.
     *
     * Endpoint untuk SPV melihat progress individual setiap anggota tim di mobile app.
     * Tiap anggota mengembalikan progress_list (semua entri progres + dokumentasi)
     * beserta milestone tertinggi: tahapan_tertinggi (1..4) dan progress_tahapan (0..100),
     * selaras dengan memberSummary(). Tidak lagi mengembalikan field kuota.
     *
     * @param  int  $workorderId
     * @return \Illuminate\Http\Response
     */
    public function progressByMember($workorderId)
    {
        try {
            $workorder = Workorder::with([
                'assignmentMembers.user.pegawai.jabatan',
                'workorderAssignment'
            ])->findOrFail($workorderId);

            $assignment = $workorder->workorderAssignment;
            $tanggalMulai = optional($assignment)->tanggal_mulai ?? $workorder->tanggal_mulai;
            $estimasiSelesai = optional($assignment)->estimasi_selesai;

            $totalDays = $this->estimasiHari($tanggalMulai, $estimasiSelesai);

            $members = $workorder->assignmentMembers->map(function ($member) use ($workorderId) {
                $userId = $member->user_id;

            
                $progressList = ProgressWorkorder::where('workorder_id', $workorderId)
                    ->where('submitted_by_user_id', $userId)
                    ->with(['tipeProgress', 'status', 'dokumentasiProgress'])
                    ->orderBy('order', 'asc')
                    ->get();

                
                $dibatalkanId = \App\Models\Status::where('kode', 'DIBATALKAN')->value('id');
                $memberMaxTahapan = ProgressWorkorder::where('workorder_id', $workorderId)
                    ->where('submitted_by_user_id', $userId)
                    ->whereNotNull('waktu_submit')
                    ->when($dibatalkanId !== null, fn ($q) => $q->where('status_id', '!=', $dibatalkanId))
                    ->max('tahapan');

                $memberProgressTahapan = $memberMaxTahapan !== null
                    ? (int) round(($memberMaxTahapan / 4) * 100)
                    : null;

                return [
                    'user_id' => $userId,
                    'nama' => optional($member->user->pegawai)->nama ?? optional($member->user)->name,
                    'nip' => optional($member->user->pegawai)->nip,
                    'jabatan' => optional(optional($member->user->pegawai)->jabatan)->nama,
                    'is_pic' => $member->is_pic,
                    'tahapan_tertinggi' => $memberMaxTahapan,
                    'progress_tahapan' => $memberProgressTahapan,
                    'progress_list' => $progressList,
                ];
            });

            return response()->json([
                'workorder_id' => $workorder->id,
                'workorder_name' => $workorder->nama_workorder,
                'estimasi_hari' => $totalDays,
                'members' => $members,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Workorder not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get member progress summary for web dashboard.
     *
     * Ringkasan progress per anggota berbasis milestone (tahapan 1..4 → 25/50/75/100).
     * Tidak lagi memakai konsep kuota — tanpa quota_total/quota_remaining/daily_breakdown.
     *
     * @param  int  $workorderId
     * @return \Illuminate\Http\Response
     */
    public function memberSummary($workorderId)
    {
        try {
            $workorder = Workorder::with([
                'assignmentMembers.user.pegawai.jabatan',
                'workorderAssignment',
                'status'
            ])->findOrFail($workorderId);

            $assignment = $workorder->workorderAssignment;
            $tanggalMulai = optional($assignment)->tanggal_mulai ?? $workorder->tanggal_mulai;
            $estimasiSelesai = optional($assignment)->estimasi_selesai;
            $totalDays = $this->estimasiHari($tanggalMulai, $estimasiSelesai);

            $dibatalkanId = \App\Models\Status::where('kode', 'DIBATALKAN')->value('id');

            $membersSummary = $workorder->assignmentMembers->map(function ($member) use ($workorderId, $dibatalkanId) {
                $userId = $member->user_id;

                // Semua laporan milik anggota ini yang sudah disubmit & tidak dibatalkan.
                $base = ProgressWorkorder::where('workorder_id', $workorderId)
                    ->where('submitted_by_user_id', $userId)
                    ->whereNotNull('waktu_submit')
                    ->when($dibatalkanId !== null, fn ($q) => $q->where('status_id', '!=', $dibatalkanId));

                $memberMaxTahapan = (clone $base)->max('tahapan');
                $firstSubmission  = (clone $base)->min('waktu_submit');
                $lastSubmission   = (clone $base)->max('waktu_submit');
                
                $memberProgressTahapan = $memberMaxTahapan !== null ? (int) round(($memberMaxTahapan / 4) * 100) : null;

                return [
                    'user_id' => $userId,
                    'nama' => optional($member->user->pegawai)->nama ?? optional($member->user)->name,
                    'nip' => optional($member->user->pegawai)->nip,
                    'jabatan' => optional(optional($member->user->pegawai)->jabatan)->nama,
                    'jabatan_kode' => optional(optional($member->user->pegawai)->jabatan)->kode,
                    'is_pic' => $member->is_pic,
                    'statistics' => [
                        'tahapan_tertinggi'   => $memberMaxTahapan,
                        'progress_tahapan'    => $memberProgressTahapan,
                        // Key lama dipertahankan; kini bernilai milestone, bukan kuota.
                        'progress_percentage' => $memberProgressTahapan,
                        'first_submission'    => $firstSubmission,
                        'last_submission'     => $lastSubmission,
                    ],
                ];
            });

            // Statistik keseluruhan tim (berbasis milestone)
            $teamStats = [
                'total_members' => $workorder->assignmentMembers->count(),
                'avg_progress_percentage' => $membersSummary->avg('statistics.progress_percentage'),
            ];

            return response()->json([
                'workorder' => [
                    'id' => $workorder->id,
                    'nama_workorder' => $workorder->nama_workorder,
                    'status' => $workorder->status,
                    'tanggal_mulai' => $tanggalMulai,
                    'estimasi_selesai' => $estimasiSelesai,
                    'estimasi_hari' => $totalDays,
                ],
                'team_statistics' => $teamStats,
                'members' => $membersSummary,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Workorder not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
