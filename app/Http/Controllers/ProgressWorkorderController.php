<?php

namespace App\Http\Controllers;

use App\Models\LaporanWorkorder;
use App\Models\Pengaduan;
use App\Models\ProgressDetail;
use App\Models\ProgressWorkorder;
use App\Models\User;
use App\Models\Workorder;
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

/**
 * Alur progress Work Order (Mobile) — versi enum + pegawai (branch MergerManual).
 *
 * Catatan skema (berbeda dari source user/lookup-table):
 *  - progress_workorder.tipe_progress = ENUM ('inpeksi','mulai','progress','selesai'); TIDAK ada status_id.
 *  - Identitas pelapor = m_pegawai (submitted_by_pegawai_id), bukan users.
 *  - workorder.status = ENUM ('Pending','Proses','Selesai','Tutup'); workorder.assigned_to = m_pegawai (SPV).
 *  - State siklus review dilacak via tabel progress_detail (pending/approved/rejected) + waktu_submit.
 */
class ProgressWorkorderController extends Controller
{
    /** Map kode logis → nilai enum tipe_progress di DB ('inpeksi' mengikuti ejaan kolom). */
    private function tipeEnum(string $kode): string
    {
        return [
            'INSPEKSI' => 'inpeksi',
            'MULAI'    => 'mulai',
            'PROGRESS' => 'progress',
            'SELESAI'  => 'selesai',
        ][$kode] ?? strtolower($kode);
    }

    /** Kategori form WO diturunkan dari baris wo_* yang dibuat saat assignment. */
    private function resolveKategori(Workorder $workorder): ?string
    {
        if ($workorder->meter()->exists()) {
            return 'meter';
        }
        if ($workorder->jaringan()->exists()) {
            return 'jaringan';
        }
        if ($workorder->infrastruktur()->exists()) {
            return 'infrastruktur';
        }
        return null;
    }

    private function isFinal(Workorder $workorder): bool
    {
        return in_array($workorder->status, ['Selesai', 'Tutup'], true);
    }

    private function rejectIfWorkorderFinal(Workorder $workorder): ?\Illuminate\Http\JsonResponse
    {
        if ($this->isFinal($workorder)) {
            return response()->json([
                'error' => 'WO sudah final (Selesai/Tutup) dan tidak dapat dilanjutkan.'
            ], 422);
        }
        return null;
    }

    /**
     * Validasi urutan tahapan (defense-in-depth, selaras hard-guard FE).
     * PROGRESS: tahapan ∈ [maxTahapan, min(maxTahapan+1, PENGUJIAN)], wajib ≥1.
     * SELESAI : butuh maxTahapan ≥ PENGUJIAN. INSPEKSI/MULAI dilewati (tahapan dipaksa server).
     * Return JsonResponse 422 bila melanggar, null bila lolos.
     */
    private function rejectIfTahapanInvalid(Workorder $workorder, string $tipeKode, ?int $tahapan): ?\Illuminate\Http\JsonResponse
    {
        $maxTahapan = (int) $this->tahapanTertinggi($workorder->id); // 0 bila belum ada

        if ($tipeKode === 'PROGRESS') {
            $tahapan = (int) $tahapan; // null → 0
            if ($tahapan < TahapanWorkorder::PERSIAPAN) {
                return response()->json(['error' => 'Tahapan progress wajib diisi (1-3).'], 422);
            }
            if ($tahapan < $maxTahapan) {
                return response()->json(['error' => "Tahap {$tahapan} sudah dilewati. Tidak bisa mundur."], 422);
            }
            if ($tahapan > $maxTahapan + 1 || $tahapan > TahapanWorkorder::PENGUJIAN) {
                return response()->json(['error' => 'Selesaikan tahap sebelumnya dulu sebelum lanjut.'], 422);
            }
        }

        if ($tipeKode === 'SELESAI' && $maxTahapan < TahapanWorkorder::PENGUJIAN) {
            return response()->json(['error' => 'Selesaikan tahap Pengujian terlebih dahulu sebelum menyelesaikan work order.'], 422);
        }

        return null;
    }

    /** Apakah pegawai ini anggota tim WO. */
    private function isMember(Workorder $workorder, ?int $pegawaiId): bool
    {
        return $pegawaiId !== null
            && $workorder->assignmentMembers->pluck('pegawai_id')->map(fn ($v) => (int) $v)->contains((int) $pegawaiId);
    }

    /** Milestone tertinggi dari progress yang sudah disubmit (cancel = waktu_submit null → otomatis terexclude). */
    private function tahapanTertinggi(int $workorderId, ?int $pegawaiId = null): ?int
    {
        return ProgressWorkorder::where('workorder_id', $workorderId)
            ->whereNotNull('waktu_submit')
            ->when($pegawaiId !== null, fn ($q) => $q->where('submitted_by_pegawai_id', $pegawaiId))
            ->max('tahapan');
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

    /**
     * Staff memulai pekerjaan (MULAI). Wajib sudah submit INSPEKSI lebih dulu.
     *
     * Data kategori AWAL idealnya dibuat saat assignment, namun alur mobile dapat
     * mengirim field AWAL datar (sesuai kategori WO) di tombol "Mulai". Field tsb
     * divalidasi opsional di sini lalu ditambal ke tabel wo_* via persistKategoriAwal().
     */
    public function start(Request $request)
    {
        $this->hydrateInputFromBody($request);

        // Resolusi kategori dulu agar aturan validasi field AWAL bisa ditambahkan.
        // Pakai TIPE WO (m_jenis_workorder.kategori), BUKAN keberadaan baris wo_*:
        // baris wo_* belum tentu ada saat MULAI (dibuat di assignment hanya bila
        // form_kategori dikirim). Fallback ke resolveKategori bila tipe tak ter-set.
        $workorder    = Workorder::with(['assignmentMembers', 'jenisWorkorder'])->findOrFail($request->input('workorder_id'));
        $kategoriForm = optional($workorder->jenisWorkorder)->kategori
            ?? $this->resolveKategori($workorder);

        $rules = [
            'workorder_id'     => 'required|exists:workorder,id',
            'hasil_pengerjaan' => 'nullable|string|max:255',
            'latitude'         => 'required|numeric',
            'longitude'        => 'required|numeric',
            'accuracy'         => 'nullable|numeric',
            'foto'             => 'nullable|array',
            'foto.*'           => 'image|mimes:jpeg,png,jpg|max:4048',
        ];

        // Field kategori AWAL — opsional (nullable) supaya tidak memutus alur lama.
        if ($kategoriForm === 'meter') {
            $rules['nomor_meter']        = 'nullable|string';
            $rules['kondisi_meter_awal'] = 'nullable|string';
        } elseif ($kategoriForm === 'jaringan') {
            $rules['jenis_pipa']        = 'nullable|string';
            $rules['diameter_pipa']     = 'nullable|numeric'; // varchar(255) di DB; terima angka dari multipart.
            $rules['panjang_pipa']      = 'nullable|numeric'; // double precision di DB.
            $rules['tingkat_kerusakan'] = 'nullable|string';
        } elseif ($kategoriForm === 'infrastruktur') {
            $rules['nama_aset']    = 'nullable|string';
            $rules['jenis_aset']   = 'nullable|string';
            $rules['kapasitas']    = 'nullable|string';
            $rules['kondisi_awal'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        $pegawaiId = (int) optional($request->user())->pegawai_id;

        if (! $this->isMember($workorder, $pegawaiId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($workorder)) {
            return $finalError;
        }

        // Wajib inspeksi sebelum mulai kerja (hanya saat belum pernah MULAI).
        $sudahMulai = ProgressWorkorder::where('workorder_id', $workorder->id)
            ->where('tipe_progress', 'mulai')->exists();
        if (! $sudahMulai) {
            $hasInspeksi = ProgressWorkorder::where('workorder_id', $workorder->id)
                ->where('tipe_progress', 'inpeksi')
                ->whereNotNull('waktu_submit')
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
                'workorder_id'           => $workorder->id,
                'tipe_progress'          => $this->tipeEnum('MULAI'),
                'submitted_by_pegawai_id' => $pegawaiId,
                'hasil_pengerjaan'       => $validated['hasil_pengerjaan'] ?? 'Mulai pekerjaan',
                'waktu_submit'           => now(),
                'order'                  => $order,
                'latitude'               => $validated['latitude'],
                'longitude'              => $validated['longitude'],
                'accuracy'               => $validated['accuracy'] ?? null,
                'tahapan'                => TahapanWorkorder::PERSIAPAN,
            ]);

            if ($request->hasFile('foto')) {
                foreach ($request->file('foto') as $file) {
                    $path = $file->store('dokumentasi_progress', 'public');
                    $progress->dokumentasiProgress()->create([
                        'url'   => $path,
                        'jenis' => 'HASIL_KERJA',
                    ]);
                }
            }

            if ($workorder->status !== 'Proses') {
                $workorder->update(['status' => 'Proses']);
            }

            // Tambal data kategori AWAL bila dikirim mobile (tidak menimpa kolom AKHIR).
            $this->persistKategoriAwal($workorder, $kategoriForm, $validated);

            DB::commit();

            $workorder->refresh();

            return response()->json([
                'progress'  => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id'                => $workorder->id,
                    'progres_persen'    => $workorder->progres_persen,
                    'status'            => $workorder->status,
                    'tahapan_tertinggi' => $this->tahapanTertinggi($workorder->id),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Staff submit progres: INSPEKSI / PROGRESS / SELESAI.
     */
    public function submit(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $rules = [
            'workorder_id'       => 'required|exists:workorder,id',
            'tipe_progress_kode' => 'required_without:tipe_progress|nullable|in:PROGRESS,SELESAI,INSPEKSI',
            'tipe_progress'      => 'required_without:tipe_progress_kode|nullable|in:PROGRESS,SELESAI,INSPEKSI',
            'hasil_pengerjaan'   => 'required|string|max:255',
            'latitude'           => 'required|numeric',
            'longitude'          => 'required|numeric',
            'accuracy'           => 'nullable|numeric',
            'foto'               => 'nullable|array',
            'foto.*'             => 'image|mimes:jpeg,png,jpg|max:4048',
            'tahapan'            => 'nullable|integer|between:1,4',
        ];

        $tipeProgressKode = $request->input('tipe_progress_kode') ?? $request->input('tipe_progress');
        $workorder = Workorder::with('assignmentMembers')->findOrFail($request->input('workorder_id'));
        $kategoriForm = null;

        if ($tipeProgressKode === 'SELESAI') {
            $kategoriForm = $this->resolveKategori($workorder);
            if ($kategoriForm === 'meter') {
                $rules['kondisi_meter_akhir'] = 'required|string';
                $rules['hasil_kalibrasi']     = 'required|string';
            } elseif ($kategoriForm === 'jaringan') {
                $rules['tindakan_perbaikan'] = 'required|string';
                $rules['hasil_inspeksi']     = 'required|string';
            } elseif ($kategoriForm === 'infrastruktur') {
                $rules['kondisi_akhir']       = 'required|string';
                $rules['jadwal_pemeliharaan'] = 'required|date';
                $rules['tindakan']            = 'required|string';
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

        $pegawaiId = (int) optional($request->user())->pegawai_id;

        if (! $this->isMember($workorder, $pegawaiId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($workorder)) {
            return $finalError;
        }

        if ($tipeProgressKode === 'INSPEKSI' && $workorder->status !== 'Proses') {
            return response()->json(['error' => 'Status WO tidak valid untuk inspeksi'], 422);
        }

        if ($tipeProgressKode === 'SELESAI') {
            // Hanya PIC (koordinator) yang boleh menandai selesai.
            $isPic = $workorder->assignmentMembers
                ->where('pegawai_id', $pegawaiId)
                ->where('is_pic', true)
                ->isNotEmpty();

            if (! $isPic) {
                return response()->json([
                    'error' => 'Hanya PIC (koordinator) yang dapat submit SELESAI'
                ], 403);
            }
        }

        if ($guardError = $this->rejectIfTahapanInvalid($workorder, $tipeProgressKode, $validated['tahapan'] ?? null)) {
            return $guardError;
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
                'workorder_id'            => $workorder->id,
                'tipe_progress'           => $this->tipeEnum($tipeProgressKode),
                'submitted_by_pegawai_id' => $pegawaiId,
                'hasil_pengerjaan'        => $validated['hasil_pengerjaan'],
                'waktu_submit'            => now(),
                'order'                   => $order,
                'latitude'                => $validated['latitude'],
                'longitude'               => $validated['longitude'],
                'accuracy'                => $validated['accuracy'] ?? null,
                'tahapan'                 => $tahapan,
            ]);

            if ($request->hasFile('foto')) {
                foreach ($request->file('foto') as $file) {
                    $path = $file->store('dokumentasi_progress', 'public');
                    $progress->dokumentasiProgress()->create([
                        'url'   => $path,
                        'jenis' => $tipeProgressKode === 'INSPEKSI' ? 'INSPEKSI' : 'HASIL_KERJA',
                    ]);
                }
            }

            if ($tipeProgressKode === 'SELESAI') {
                $this->persistKategoriHasilAkhir($workorder, $kategoriForm, $validated);

                // Buka siklus review (menunggu SPV). WO tetap 'Proses' sampai SPV approve.
                ProgressDetail::create([
                    'progress_workorder_id' => $progress->id,
                    'status'                => 'pending',
                ]);
            }

            DB::commit();

            if ($tipeProgressKode === 'SELESAI') {
                $this->notifyWorkOrderReadyForReview($workorder, $pegawaiId);
            }

            $workorder->refresh();

            return response()->json([
                'progress'  => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id'                => $workorder->id,
                    'progres_persen'    => $workorder->progres_persen,
                    'status'            => $workorder->status,
                    'tahapan_tertinggi' => $this->tahapanTertinggi($workorder->id),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Staff submit ulang setelah SPV minta revisi.
     */
    public function resubmit(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $progressId = $request->input('progress_id') ?? $request->input('progress_workorder_id');
        $request->merge(['progress_id' => $progressId]);
        $request->validate(['progress_id' => 'required|exists:progress_workorder,id']);

        $progress = ProgressWorkorder::with(['workorder.assignmentMembers', 'latestDetail'])
            ->findOrFail($progressId);
        $workorder = $progress->workorder;
        $tipeKode  = strtoupper($progress->tipe_progress); // 'selesai' → 'SELESAI', dst.

        $rules = [
            'hasil_pengerjaan' => 'required|string|max:255',
            'latitude'         => 'required|numeric',
            'longitude'        => 'required|numeric',
            'accuracy'         => 'nullable|numeric',
            'foto'             => 'nullable|array',
            'foto.*'           => 'image|mimes:jpeg,png,jpg|max:4048',
            'tahapan'          => 'nullable|integer|between:1,4',
        ];

        $kategoriForm = null;
        if ($progress->tipe_progress === 'selesai') {
            $kategoriForm = $this->resolveKategori($workorder);
            if ($kategoriForm === 'meter') {
                $rules['kondisi_meter_akhir'] = 'required|string';
                $rules['hasil_kalibrasi']     = 'required|string';
            } elseif ($kategoriForm === 'jaringan') {
                $rules['tindakan_perbaikan'] = 'required|string';
                $rules['hasil_inspeksi']     = 'required|string';
            } elseif ($kategoriForm === 'infrastruktur') {
                $rules['kondisi_akhir']       = 'required|string';
                $rules['jadwal_pemeliharaan'] = 'required|date';
                $rules['tindakan']            = 'required|string';
            }
        }

        $validated = $request->validate($rules);
        $pegawaiId = (int) optional($request->user())->pegawai_id;

        if (! $this->isMember($workorder, $pegawaiId)) {
            return response()->json(['error' => 'User bukan petugas WO ini'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($workorder)) {
            return $finalError;
        }

        // Hanya progress yang ditolak (revisi) yang bisa diresubmit.
        if (optional($progress->latestDetail)->status !== 'rejected') {
            return response()->json(['error' => 'Progress tidak dalam status revisi'], 422);
        }

        if ($progress->tipe_progress === 'selesai') {
            $isPic = $workorder->assignmentMembers
                ->where('pegawai_id', $pegawaiId)
                ->where('is_pic', true)
                ->isNotEmpty();
            if (! $isPic) {
                return response()->json([
                    'error' => 'Hanya PIC (koordinator) yang dapat submit SELESAI'
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            // Resubmit memperbaiki ISI milestone yang ditolak, bukan menggeser tahap.
            $tahapan = $progress->tahapan; // pertahankan tahapan asli; abaikan override klien
            if ($progress->tipe_progress === 'selesai') {
                $tahapan = TahapanWorkorder::DOKUMENTASI;
            } elseif ($progress->tipe_progress === 'inpeksi') {
                $tahapan = TahapanWorkorder::PERSIAPAN;
            }

            // Update baris in-place; submitted_by_pegawai_id tidak diubah (tetap milik pelapor asli).
            $progress->update([
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
                        'url'   => $path,
                        'jenis' => 'HASIL_KERJA',
                    ]);
                }
            }

            // Siklus review baru untuk hasil perbaikan ini.
            ProgressDetail::create([
                'progress_workorder_id' => $progress->id,
                'status'                => 'pending',
            ]);

            if ($progress->tipe_progress === 'selesai') {
                $this->persistKategoriHasilAkhir($workorder, $kategoriForm, $validated);
            }

            DB::commit();

            if ($progress->tipe_progress === 'selesai') {
                $this->notifyWorkOrderReadyForReview($workorder, $pegawaiId);
            }

            $workorder->refresh();

            return response()->json([
                'message'   => 'Resubmit progress berhasil',
                'progress'  => $progress->load('dokumentasiProgress'),
                'workorder' => [
                    'id'                => $workorder->id,
                    'progres_persen'    => $workorder->progres_persen,
                    'status'            => $workorder->status,
                    'tahapan_tertinggi' => $this->tahapanTertinggi($workorder->id),
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Tambal kolom kategori AWAL (dari payload mobile saat MULAI) ke tabel wo_*.
     * Hanya field yang BENAR-BENAR dikirim yang ditulis — kolom AKHIR & nilai existing
     * tidak ditimpa dengan null. updateOrCreate keyed pada workorder_id (simetris dgn
     * persistKategoriHasilAkhir, tapi pakai updateOrCreate karena baris bisa belum ada).
     */
    private function persistKategoriAwal(Workorder $workorder, ?string $kategoriForm, array $validated): void
    {
        $only = static function (array $keys) use ($validated): array {
            $data = [];
            foreach ($keys as $key) {
                if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                    $data[$key] = $validated[$key];
                }
            }
            return $data;
        };

        if ($kategoriForm === 'meter') {
            $data = $only(['nomor_meter', 'kondisi_meter_awal']);
            if ($data !== []) {
                WoMeter::updateOrCreate(['workorder_id' => $workorder->id], $data);
            }
        } elseif ($kategoriForm === 'jaringan') {
            $data = $only(['jenis_pipa', 'diameter_pipa', 'panjang_pipa', 'tingkat_kerusakan']);
            if ($data !== []) {
                WoJaringan::updateOrCreate(['workorder_id' => $workorder->id], $data);
            }
        } elseif ($kategoriForm === 'infrastruktur') {
            $data = $only(['nama_aset', 'jenis_aset', 'kapasitas', 'kondisi_awal']);
            if ($data !== []) {
                WoInfrastruktur::updateOrCreate(['workorder_id' => $workorder->id], $data);
            }
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

    private function notifyWorkOrderReadyForReview(Workorder $workorder, ?int $picPegawaiId): void
    {
        try {
            $spvPegawaiId = $workorder->assigned_to;
            if (! $spvPegawaiId) {
                return;
            }

            $spv = User::where('pegawai_id', $spvPegawaiId)->first();
            if (! $spv) {
                return;
            }

            $pic = $picPegawaiId
                ? \App\Models\Pegawai::select('id', 'nama')->find($picPegawaiId)
                : null;
            $senderName = optional($pic)->nama ?? 'Petugas';

            $spv->notify(new WorkOrderNotification(
                'Work Order Menunggu Review',
                "{$senderName} telah menyelesaikan WO #{$workorder->nama_workorder} dan menunggu review Anda.",
                (int) $workorder->id,
                'wo_ready_for_review',
                $senderName
            ));
        } catch (\Throwable $e) {
            Log::warning('notifyWorkOrderReadyForReview failed', [
                'workorder_id'   => $workorder->id,
                'pic_pegawai_id' => $picPegawaiId,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * SPV mereview progress: accept (→ generate Laporan), revisi (balik kerja), tolak (final).
     */
    public function review(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $validated = $request->validate([
            'progress_id'      => 'required|exists:progress_workorder,id',
            'decision'         => 'required|in:accept,revisi,tolak',
            'approval_notes'   => 'nullable|string',
            'field_to_revise'  => 'nullable|array',
        ]);

        $progress  = ProgressWorkorder::with('workorder')->findOrFail($validated['progress_id']);
        $workorder = $progress->workorder;
        $userId    = (int) optional($request->user())->id;
        $pegawaiId = (int) optional($request->user())->pegawai_id;

        // SPV = pegawai yang ditugaskan pada WO (workorder.assigned_to).
        if ((int) $workorder->assigned_to !== $pegawaiId) {
            return response()->json(['error' => 'Hanya SPV yang ditugaskan pada WO ini yang bisa review'], 403);
        }

        if ($this->isFinal($workorder)) {
            return response()->json(['error' => 'WO sudah final (Selesai/Tutup).'], 422);
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

                $workorder->update(['status' => 'Selesai']);

                if ($workorder->kode_pengaduan) {
                    Pengaduan::where('kode_pengaduan', $workorder->kode_pengaduan)
                        ->update(['status' => Pengaduan::STATUS_SELESAI]);
                }

                $workorder->loadMissing(['meter', 'jaringan', 'infrastruktur', 'assignmentMembers.pegawai']);

                $laporan = LaporanWorkorder::updateOrCreate(
                    ['workorder_id' => $workorder->id],
                    [
                        'nomor_laporan'        => sprintf('LAP-WO-%s-%04d', now()->format('Y'), $workorder->id),
                        'tanggal_terbit'       => now(),
                        'ringkasan_pekerjaan'  => $workorder->deskripsi ?? $workorder->nama_workorder,
                        'hasil_akhir_snapshot' => $this->resolveKategoriSnapshot($workorder),
                        'petugas_snapshot'     => $workorder->assignmentMembers->map(fn ($m) => [
                            'pegawai_id' => (int) $m->pegawai_id,
                            'nama'       => optional($m->pegawai)->nama,
                            'nip'        => optional($m->pegawai)->nip,
                            'is_pic'     => (bool) $m->is_pic,
                        ])->values()->all(),
                        'catatan_spv'          => $approvalNotes,
                        'issued_by_user_id'    => $userId,
                        'approved_by_user_id'  => $userId,
                        'approved_at'          => now(),
                    ]
                );

                DB::commit();
                $workorder->refresh();

                return response()->json([
                    'message'   => 'Review disetujui, laporan dibuat',
                    'workorder' => ['id' => $workorder->id, 'status' => $workorder->status],
                    'laporan'   => $laporan,
                ], 200);
            }

            if ($validated['decision'] === 'revisi') {
                ProgressDetail::create([
                    'progress_workorder_id' => $progress->id,
                    'status'                => 'rejected',
                    'reviewed_by_user_id'   => $userId,
                    'reviewed_at'           => now(),
                    'alasan_revisi'      => $validated['alasan_revisi'] ?? null
                ]);

                // WO tetap berjalan; staff melakukan perbaikan lalu resubmit.
                if ($workorder->status !== 'Proses') {
                    $workorder->update(['status' => 'Proses']);
                }
            } else { // tolak (final)
                ProgressDetail::create([
                    'progress_workorder_id' => $progress->id,
                    'status'                => 'rejected',
                    'reviewed_by_user_id'   => $userId,
                    'reviewed_at'           => now(),
                    'alasan_revisi'      => $validated['alasan_revisi'] ?? null,
                ]);

                $workorder->update(['status' => 'Tutup']);
            }

            DB::commit();
            $workorder->refresh();

            return response()->json([
                'message'   => 'Review progress berhasil diproses',
                'workorder' => ['id' => $workorder->id, 'status' => $workorder->status],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Staff membatalkan progress yang baru saja disubmit (window 5 menit, sebelum direview).
     */
    public function cancel(Request $request, $id)
    {
        $progress  = ProgressWorkorder::with('progressDetails')->findOrFail($id);
        $pegawaiId = (int) optional($request->user())->pegawai_id;

        if ((int) $progress->submitted_by_pegawai_id !== $pegawaiId) {
            return response()->json(['error' => 'Hanya petugas yang submit yang bisa membatalkan'], 403);
        }

        // Sudah pernah direview (approved/rejected) → tidak bisa dibatalkan.
        $sudahReview = $progress->progressDetails
            ->whereIn('status', ['approved', 'rejected'])
            ->isNotEmpty();
        if ($sudahReview) {
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
            // Tutup siklus review pending (jika ada) lalu tandai progress sebagai batal.
            $progress->progressDetails()->where('status', 'pending')->delete();
            $progress->update(['waktu_submit' => null]);

            DB::commit();

            return response()->json([
                'message'     => 'Progress berhasil dibatalkan',
                'progress_id' => $progress->id,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            // Eager-load detail review agar FE bisa menurunkan status siklus
            // (pending/approved/rejected) langsung dari list, sama seperti show().
            $query = ProgressWorkorder::with([
                'dokumentasiProgress',
                'progressDetails',
                'latestDetail',
            ]);

            if ($request->has('workorder_id')) {
                $query->where('workorder_id', $request->query('workorder_id'))
                    ->orderBy('order', 'asc');
                return response()->json($query->get(), 200);
            }

            return response()->json($query->get(), 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Terjadi kesalahan saat mengambil data progress workorder',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        //
    }

    public function show($id)
    {
        try {
            $progress = ProgressWorkorder::with([
                'dokumentasiProgress',
                'progressDetails',
                'workorder.meter',
                'workorder.jaringan',
                'workorder.infrastruktur',
            ])->findOrFail($id);

            $wo = $progress->workorder;
            $kategoriData = null;
            if ($wo) {
                if ($wo->meter) {
                    $kategoriData = [
                        'nomor_meter'         => $wo->meter->nomor_meter,
                        'kondisi_meter_awal'  => $wo->meter->kondisi_meter_awal,
                        'kondisi_meter_akhir' => $wo->meter->kondisi_meter_akhir,
                        'hasil_kalibrasi'     => $wo->meter->hasil_kalibrasi,
                    ];
                } elseif ($wo->jaringan) {
                    $kategoriData = [
                        'jenis_pipa'         => $wo->jaringan->jenis_pipa,
                        'diameter_pipa'      => $wo->jaringan->diameter_pipa,
                        'panjang_pipa'       => $wo->jaringan->panjang_pipa,
                        'tingkat_kerusakan'  => $wo->jaringan->tingkat_kerusakan,
                        'tindakan_perbaikan' => $wo->jaringan->tindakan_perbaikan,
                        'hasil_inspeksi'     => $wo->jaringan->hasil_inspeksi,
                    ];
                } elseif ($wo->infrastruktur) {
                    $kategoriData = [
                        'nama_aset'           => $wo->infrastruktur->nama_aset,
                        'jenis_aset'          => $wo->infrastruktur->jenis_aset,
                        'kapasitas'           => $wo->infrastruktur->kapasitas,
                        'kondisi_awal'        => $wo->infrastruktur->kondisi_awal,
                        'kondisi_akhir'       => $wo->infrastruktur->kondisi_akhir,
                        'jadwal_pemeliharaan' => $wo->infrastruktur->jadwal_pemeliharaan,
                        'tindakan'            => $wo->infrastruktur->tindakan,
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

    public function update(Request $request, $id)
    {
        $this->hydrateInputFromBody($request);

        $validatedData = $request->validate([
            'hasil_pengerjaan' => 'required|string|max:255',
            'latitude'         => 'required|numeric',
            'longitude'        => 'required|numeric',
            'accuracy'         => 'nullable|numeric',
            'foto'             => 'nullable|array',
            'foto.*'           => 'image|mimes:jpeg,png,jpg|max:4048',
        ]);

        $progressWorkorder = ProgressWorkorder::with('workorder.assignmentMembers')->findOrFail($id);
        $pegawaiId = (int) optional($request->user())->pegawai_id;

        if (! $this->isMember($progressWorkorder->workorder, $pegawaiId)) {
            return response()->json(['error' => 'Hanya petugas WO ini yang bisa update progress'], 403);
        }

        if ($finalError = $this->rejectIfWorkorderFinal($progressWorkorder->workorder)) {
            return $finalError;
        }

        DB::beginTransaction();
        try {
            $progressWorkorder->update([
                'waktu_submit'            => now(),
                'hasil_pengerjaan'        => $validatedData['hasil_pengerjaan'],
                'latitude'                => $validatedData['latitude'],
                'longitude'               => $validatedData['longitude'],
                'accuracy'                => $validatedData['accuracy'] ?? null,
                'submitted_by_pegawai_id' => $pegawaiId,
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

    public function destroy($id)
    {
        //
    }

    private function resolveKategoriSnapshot(Workorder $workorder): array
    {
        if ($workorder->meter) {
            return $workorder->meter->toArray();
        }
        if ($workorder->jaringan) {
            return $workorder->jaringan->toArray();
        }
        if ($workorder->infrastruktur) {
            return $workorder->infrastruktur->toArray();
        }
        return [];
    }

    /**
     * Batch tambah progress untuk WO aktif ('Proses').
     */
    public function manualRun()
    {
        try {
            $service = new ProgressWorkorderService();
            $workorders = Workorder::where('status', 'Proses')->get();

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
     * Progress per anggota tim untuk satu WO (SPV melihat progres individual di mobile).
     */
    public function progressByMember($workorderId)
    {
        try {
            $workorder = Workorder::with([
                'assignmentMembers.pegawai.jabatan',
                'workorderAssignment',
            ])->findOrFail($workorderId);

            $assignment      = $workorder->workorderAssignment;
            $tanggalMulai    = optional($assignment)->tanggal_mulai;
            $estimasiSelesai = optional($assignment)->estimasi_selesai;
            $totalDays       = $this->estimasiHari($tanggalMulai, $estimasiSelesai);

            $members = $workorder->assignmentMembers->map(function ($member) use ($workorderId) {
                $pegawaiId = (int) $member->pegawai_id;

                $progressList = ProgressWorkorder::where('workorder_id', $workorderId)
                    ->where('submitted_by_pegawai_id', $pegawaiId)
                    ->with('dokumentasiProgress')
                    ->orderBy('order', 'asc')
                    ->get();

                $memberMaxTahapan = $this->tahapanTertinggi($workorderId, $pegawaiId);
                $memberProgressTahapan = $memberMaxTahapan !== null
                    ? (int) round(($memberMaxTahapan / 4) * 100)
                    : null;

                return [
                    'pegawai_id'        => $pegawaiId,
                    'nama'              => optional($member->pegawai)->nama,
                    'nip'               => optional($member->pegawai)->nip,
                    'jabatan'           => optional(optional($member->pegawai)->jabatan)->nama,
                    'is_pic'            => (bool) $member->is_pic,
                    'tahapan_tertinggi' => $memberMaxTahapan,
                    'progress_tahapan'  => $memberProgressTahapan,
                    'progress_list'     => $progressList,
                ];
            });

            $totalLaporan = $members->sum(function ($m) {
                return count($m['progress_list']);
            });

            return response()->json([
                'workorder_id'   => $workorder->id,
                'workorder_name' => $workorder->nama_workorder,
                'estimasi_hari'  => $totalDays,
                'total_laporan'  => $totalLaporan,
                'members'        => $members,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Workorder not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ringkasan progress per anggota (dashboard) berbasis milestone tahapan.
     */
    public function memberSummary($workorderId)
    {
        try {
            $workorder = Workorder::with([
                'assignmentMembers.pegawai.jabatan',
                'workorderAssignment',
            ])->findOrFail($workorderId);

            // $assignment      = $workorder->workorderAssignment;
            // $tanggalMulai    = optional($assignment)->tanggal_mulai;
            // $estimasiSelesai = optional($assignment)->estimasi_selesai;
            // $totalDays       = $this->estimasiHari($tanggalMulai, $estimasiSelesai);

            $membersSummary = $workorder->assignmentMembers->map(function ($member) use ($workorderId) {
                $pegawaiId = (int) $member->pegawai_id;

                $base = ProgressWorkorder::where('workorder_id', $workorderId)
                    ->where('submitted_by_pegawai_id', $pegawaiId)
                    ->whereNotNull('waktu_submit');

                $memberMaxTahapan = (clone $base)->max('tahapan');
                $firstSubmission  = (clone $base)->min('waktu_submit');
                $lastSubmission   = (clone $base)->max('waktu_submit');
                $memberProgressTahapan = $memberMaxTahapan !== null ? (int) round(($memberMaxTahapan / 4) * 100) : null;

                return [
                    'pegawai_id'   => $pegawaiId,
                    'nama'         => optional($member->pegawai)->nama,
                    'nip'          => optional($member->pegawai)->nip,
                    'jabatan'      => optional(optional($member->pegawai)->jabatan)->nama,
                    'is_pic'       => (bool) $member->is_pic,
                    'statistics'   => [
                        'tahapan_tertinggi'   => $memberMaxTahapan,
                        'progress_tahapan'    => $memberProgressTahapan,
                        'progress_percentage' => $memberProgressTahapan,
                        'first_submission'    => $firstSubmission,
                        'last_submission'     => $lastSubmission,
                    ],
                ];
            });

            // $teamStats = [
            //     'total_members'           => $workorder->assignmentMembers->count(),
            //     'avg_progress_percentage' => $membersSummary->avg('statistics.progress_percentage'),
            // ];

            return response()->json([
                'workorder' => [
                    'id'               => $workorder->id,
                    'nama_workorder'   => $workorder->nama_workorder,
                    'status'           => $workorder->status,
                    // 'tanggal_mulai'    => $tanggalMulai,
                    // 'estimasi_selesai' => $estimasiSelesai,
                    // 'estimasi_hari'    => $totalDays,
                ],
                // 'team_statistics' => $teamStats,
                'members'         => $membersSummary,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Workorder not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function monitoring()
    {
        try {
            $workorders = Workorder::select('id', 'nama_workorder', 'status')->get();
            $data = $workorders->map(function ($wo) {

                $maxTahapan = ProgressWorkorder::where('workorder_id', $wo->id)
                    ->whereNotNull('waktu_submit')
                    ->max('tahapan');
                return [
                    'id' => $wo->id,
                    'nama_workorder' => $wo->nama_workorder,
                    'status' => $wo->status,
                    'progress_percentage' => $maxTahapan
                        ? round(($maxTahapan / 4) * 100)
                        : 0,
                ];
            });
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Monitoring failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
