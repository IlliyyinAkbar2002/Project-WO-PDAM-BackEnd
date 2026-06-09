<?php

namespace App\Http\Controllers;

use App\Models\LemburSpl;
use App\Models\LemburSplMember;
use App\Models\Status;
use App\Models\Workorder;

use App\Services\LemburApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LemburSplController extends Controller
{
    /**
     * Eager-load default untuk endpoint listing & detail.
     * Dipusatkan agar response konsisten antara index & show.
     */
    private array $defaultWith = [
        'workorder',
        'status',
        'pemohon.pegawai:id,nama,nip',
        'verifikator.pegawai:id,nama,nip',
        'members.user.pegawai',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $lemburSpl = LemburSpl::with($this->defaultWith)->get();
            return response()->json($lemburSpl, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data lembur spl',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * Endpoint ini dipakai mobile (Flutter) untuk submit form
     * "Pengajuan Lembur" oleh SPV terhadap WO yang sudah ditugaskan
     * kepadanya (status DITUGASKAN_KE_SPV). Ini adalah alur "assign staff
     * via lembur": staff baru benar-benar ditugaskan setelah pengajuan ini
     * di-approve atasan (lihat update()).
     *
     *   - workorder_id    (WO yang sedang dipegang SPV — di-link ke pengajuan)
     *   - judul_pekerjaan  (FE boleh prefill dari WO — lihat red box di form)
     *   - jenis_pekerjaan  (free text, contoh: "Emergency Maintainance")
     *   - tanggal_lembur
     *   - jam_mulai (opsional, jam mulai lembur)
     *   - estimasi_jam (Estimasi Waktu Lembur, jam)
     *   - members (Anggota Tim — array of user_id, lock-to-request)
     *   - alasan_lembur
     *
     * Link ke WO disimpan dua arah: `lembur_spl.workorder_id` (FK forward,
     * unik + cascade — selaras konvensi wo_meter/laporan_workorder) dan
     * `workorder.lembur_spl_id` (penanda "WO ini lembur"). Keduanya di-set
     * bersamaan di sini agar selalu sinkron.
     *
     * Sisi web (FE Next.js) cukup melakukan PUT/PATCH ke endpoint update
     * untuk verifikasi (approve/reject).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'workorder_id'       => ['required', Rule::exists('workorder', 'id')],
            'judul_pekerjaan'    => 'required|string|max:255',
            'jenis_pekerjaan'    => 'required|string|max:255',
            'tanggal_lembur'     => 'required|date',
            'jam_mulai'          => 'nullable|date_format:H:i',
            'estimasi_jam'       => 'required|integer|min:1|max:24',
            'alasan_lembur'      => 'required|string',

            // Anggota tim — minimal 1, harus user yang valid, tidak boleh duplikat.
            'members'            => 'required|array|min:1',
            'members.*'          => ['integer', 'distinct', Rule::exists('users', 'id')],
        ]);

        $workorder = Workorder::with('status')->findOrFail($validated['workorder_id']);

        // Guard state WO — mirror AssignmentService::guardAssignability,
        // tapi untuk alur lembur (sebelum assignment dibuat).
        if ((int) $workorder->assigned_to !== (int) $request->user()->id) {
            return response()->json([
                'error' => 'Hanya SPV yang ditugaskan pada WO ini yang bisa mengajukan lembur.',
            ], 403);
        }
        if (optional($workorder->status)->kode !== 'DITUGASKAN_KE_SPV') {
            return response()->json([
                'error' => 'WO tidak pada status DITUGASKAN_KE_SPV (belum bisa diajukan lembur atau sudah ditugaskan).',
            ], 422);
        }
        if ($workorder->lembur_spl_id) {
            return response()->json([
                'error' => 'WO ini sudah memiliki pengajuan lembur.',
            ], 422);
        }

        $statusAwalId = Status::where('kode', 'BELUM_DISETUJUI')->value('id')
            ?? Status::query()->min('id');

        DB::beginTransaction();
        try {
            $lemburSpl = LemburSpl::create([
                'workorder_id'       => $workorder->id,
                'pemohon_id'         => $request->user()->id,
                'jenis_pekerjaan'    => $validated['jenis_pekerjaan'],
                'judul_pekerjaan'    => $validated['judul_pekerjaan'],
                'tanggal_lembur'     => $validated['tanggal_lembur'],
                'jam_mulai'          => $validated['jam_mulai'] ?? null,
                'estimasi_jam'       => $validated['estimasi_jam'],
                'alasan_lembur'      => $validated['alasan_lembur'],
                'status_id'          => $statusAwalId,
                'waktu_pengajuan'    => now(),
            ]);

            $rows = collect($validated['members'])
                ->unique()
                ->map(fn ($userId) => [
                    'lembur_spl_id' => $lemburSpl->id,
                    'user_id'       => (int) $userId,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ])
                ->all();

            if (! empty($rows)) {
                LemburSplMember::insert($rows);
            }

            // Link WO → pengajuan lembur. Inilah penanda "WO ini lembur".
            $workorder->update(['lembur_spl_id' => $lemburSpl->id]);

            DB::commit();

            $lemburSpl->load($this->defaultWith);

            return response()->json([
                'message' => 'Pengajuan lembur SPL berhasil dibuat',
                'data'    => $lemburSpl,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error'   => 'Gagal menyimpan pengajuan lembur SPL',
                'message' => $e->getMessage(),
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
            $lemburSpl = LemburSpl::with($this->defaultWith)->findOrFail($id);
            return response()->json($lemburSpl, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data lembur spl',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifikasi pengajuan lembur oleh atasan (superadmin/manager) dari web.
     *
     * - APPROVE (status DISETUJUI): pengajuan diterima, lalu staff benar-benar
     *   ditugaskan ke WO via LemburApprovalService — membangun
     *   workorder_assignment + wo_assignment_member, transisi WO →
     *   DITUGASKAN_KE_STAFF, seed progress awal, dan catat audit trail.
     *   Identik dengan hasil AssignmentService::assignStaff (alur normal).
     * - REJECT (status DITOLAK): pengajuan ditolak. WO tetap di
     *   DITUGASKAN_KE_SPV sehingga SPV bisa mengajukan ulang; tidak ada
     *   assignment yang dibuat.
     *
     * Status WO TIDAK lagi disalin mentah dari status_id pengajuan — itu dua
     * domain status berbeda (lembur vs workorder).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'verifikator_id' => 'nullable|exists:users,id',
            'status_id' => 'required|exists:m_status,id',
            'alasan_ditolak' => 'nullable|string'
        ]);

        $disetujuiId = Status::where('kode', 'DISETUJUI')->value('id');

        DB::beginTransaction();
        try {
            $lemburSpl = LemburSpl::with(['workorder.workorderAssignment', 'members'])->findOrFail($id);
            $previousStatusId = $lemburSpl->status_id;

            $lemburSpl->update([
                'verifikator_id' => $validatedData['verifikator_id'],
                'status_id' => $validatedData['status_id'],
                'waktu_verifikasi' => now(),
                'alasan_ditolak' => $validatedData['alasan_ditolak'] ?? null,
            ]);

            // APPROVE (transisi baru ke DISETUJUI) → tugaskan staff ke WO.
            $isNewApproval = $disetujuiId !== null
                && (int) $validatedData['status_id'] === (int) $disetujuiId
                && (int) $previousStatusId !== (int) $disetujuiId;

            if ($isNewApproval) {
                (new LemburApprovalService())->approveAndAssign($lemburSpl);
            }

            DB::commit();

            $lemburSpl->load($this->defaultWith);

            return response()->json([
                'message' => 'Data lembur SPL berhasil diupdate',
                'data' => $lemburSpl
            ], 200);
        } catch (\LogicException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
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
            $lemburSpl = LemburSpl::findOrFail($id);
            $lemburSpl->delete();
            return response()->json(['message' => 'Data lembur SPL berhasil dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
