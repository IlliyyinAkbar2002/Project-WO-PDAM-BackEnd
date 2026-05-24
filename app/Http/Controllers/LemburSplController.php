<?php

namespace App\Http\Controllers;

use App\Models\LemburSpl;
use App\Models\LemburSplMember;
use App\Models\MasterAction;
use App\Models\Status;

use App\Services\ProgressWorkorderService;
use App\Services\WorkorderActionService;
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
     * "Pengajuan Lembur":
     *   - judul_pekerjaan
     *   - jenis_pekerjaan (free text, contoh: "Emergency Maintainance")
     *   - tanggal_lembur
     *   - jam_mulai (opsional, jam mulai lembur)
     *   - estimasi_jam (Estimasi Waktu Lembur, jam)
     *   - members (Anggota Tim — array of user_id)
     *   - alasan_lembur
     *
     * Sisi web (FE Next.js) cukup melakukan PUT/PATCH ke endpoint update
     * untuk verifikasi (approve/reject). Field verifikasi (`verifikator_id`,
     * `status_id`, `waktu_verifikasi`, `alasan_ditolak`) tetap diisi di
     * sana — TIDAK diubah oleh kontrak ini.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
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

        $statusAwalId = Status::where('kode', 'BELUM_DISETUJUI')->value('id')
            ?? Status::query()->min('id');

        DB::beginTransaction();
        try {
            $lemburSpl = LemburSpl::create([
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
     * Update the specified resource in storage.
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
        DB::beginTransaction();
        try {
            $lemburSpl = LemburSpl::with('workorder')->findOrFail($id);
            $previousStatusId = $lemburSpl->status_id;
            $lemburSpl->update([
                'verifikator_id' => $validatedData['verifikator_id'],
                'status_id' => $validatedData['status_id'],
                'waktu_verifikasi' => now(),
                'alasan_ditolak' => $validatedData['alasan_ditolak'] ?? null,
            ]);
            if ($lemburSpl->workorder) {
                $lemburSpl->workorder->update([
                    'status_id' => $validatedData['status_id']
                ]);

                if ((int) $validatedData['status_id'] === 2 && (int) $previousStatusId !== 2) {
                    (new ProgressWorkorderService())->createInitialProgress($lemburSpl->workorder->id);
                    $penugasanActionId = MasterAction::where('kode', 'PENUGASAN')->value('id');
                    (new WorkorderActionService())->createAction([
                        'workorder_id' => $lemburSpl->workorder->id,
                        'action_id' => $penugasanActionId,
                        'actor_id' => $lemburSpl->workorder->assigned_to,
                        'keterangan' => 'Penugasan awal',
                        'waktu_mulai' => $lemburSpl->workorder->tanggal_mulai,
                        'estimasi_selesai' => optional($lemburSpl->workorder->workorderAssignment)->estimasi_selesai,
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Data lembur SPL berhasil diupdate',
                'data' => $lemburSpl
            ], 200);
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
