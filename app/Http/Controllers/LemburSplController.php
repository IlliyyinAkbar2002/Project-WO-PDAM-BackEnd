<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use App\Models\LemburSpl;
use App\Models\LemburSplMember;
use App\Models\MasterLocation;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use App\Models\Workorder;
use App\Notifications\WorkOrderNotification;
use App\Services\LemburApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class LemburSplController extends Controller
{
    private array $defaultWith = [
        'workorder',
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

    public function store(Request $request)
    {
        // Referensi "petugas lapangan": role employee + jabatan Staff Senior/Staff,
        // dan harus satu departemen dengan pemohon (SPV). Diresolusi by nama agar tidak
        // bergantung pada magic id yang rapuh terhadap urutan seeding.
        $employeeRoleId    = Role::where('nama', 'employee')->value('id');
        $allowedJabatanIds = Jabatan::whereIn('nama', ['Staff Senior', 'Staff'])->pluck('id')->all();
        $pemohonDeptId     = Pegawai::whereKey($request->user()->pegawai_id)->value('departemen_id');

        $validated = $request->validate([
            'workorder_id'       => ['required', Rule::exists('workorder', 'id')],
            'judul_pekerjaan'    => 'required|string|max:255',
            'jenis_pekerjaan'    => 'required|string|max:255',
            'tanggal_lembur'     => 'required|date',
            'jam_mulai'          => 'nullable|date_format:H:i',
            'estimasi_jam'       => 'required|integer|min:1|max:24',
            'alasan_lembur'      => 'required|string',
            'latitude'           => 'nullable|numeric',
            'longitude'          => 'nullable|numeric',
            'nama_lokasi'        => 'nullable|string',
            'location_id'        => 'nullable|integer',

            // Anggota minimal 1, dikirim FE sebagai pegawai_id (m_pegawai.id), tidak duplikat.
            // Syarat petugas lapangan (role/departemen/jabatan) dicek setelah validate.
            'members'            => 'required|array|min:1',
            'members.*'          => [
                'integer',
                'distinct',
                Rule::exists('m_pegawai', 'id'),
            ],
        ]);

        
        // members dikirim sebagai pegawai_id. Petugas lapangan yang sah = pegawai pada
        // departemen yang sama dengan pemohon (SPV), berjabatan Staff Senior/Staff, dan
        // punya akun user dengan role employee.
        $validMembers = Pegawai::whereIn('id', $validated['members'])
            ->where('departemen_id', $pemohonDeptId)
            ->whereIn('jabatan_id', $allowedJabatanIds)
            ->whereHas('user', fn ($q) => $q->where('role_id', $employeeRoleId))
            ->pluck('id')
            ->all();

        $invalidMembers = array_values(array_diff($validated['members'], $validMembers));

        if (!empty($invalidMembers)) {
            return response()->json([
                'error'   => 'Sebagian anggota tim tidak memenuhi syarat: harus satu departemen dengan pemohon (SPV) dan berjabatan Staff Senior/Staff.',
                'members' => array_values($invalidMembers),
            ], 422);
        }

        $workorder = Workorder::findOrFail($validated['workorder_id']);

        if ((int) $workorder->assigned_to !== (int) $request->user()->pegawai_id) {
            return response()->json([
                'error' => 'Hanya SPV yang ditugaskan pada WO ini yang bisa mengajukan lembur.',
            ], 403);
        }

        if ($workorder->status !== 'Pending') {
            return response()->json([
                'error' => "WO tidak pada status 'Pending', tidak bisa diajukan lembur (status saat ini: {$workorder->status}).",
            ], 422);
        }
        if ($workorder->lembur_spl_id) {
            return response()->json([
                'error' => 'WO ini sudah memiliki pengajuan lembur.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $locationId = $validated['location_id'] ?? null;
            if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
                $namaLokasi = $validated['nama_lokasi'] 
                    ?? $workorder->lokasi 
                    ?? $workorder->nama_workorder
                    ?? 'Lokasi Lembur SPL';

                $location = MasterLocation::create([
                    'nama'         => $namaLokasi,
                    'latitude'     => $validated['latitude'],
                    'longitude'    => $validated['longitude'],
                    'radius_meter' => 100,
                ]);
                $locationId = $location->id;
            }

            $lemburSpl = LemburSpl::create([
                'workorder_id'       => $workorder->id,
                'pemohon_id'         => $request->user()->id,
                'jenis_pekerjaan'    => $validated['jenis_pekerjaan'],
                'judul_pekerjaan'    => $validated['judul_pekerjaan'],
                'tanggal_lembur'     => $validated['tanggal_lembur'],
                'jam_mulai'          => $validated['jam_mulai'] ?? null,
                'estimasi_jam'       => $validated['estimasi_jam'],
                'alasan_lembur'      => $validated['alasan_lembur'],
                'latitude'           => $validated['latitude'] ?? null,
                'longitude'          => $validated['longitude'] ?? null,
                'location_id'        => $locationId,
                'status'             => 'pending', // status awal pengajuan lembur
                'waktu_pengajuan'    => now(),
            ]);

            // FK lembur_spl_member.user_id menunjuk ke users.id, sedangkan members masuk
            // sebagai pegawai_id. Petakan pegawai_id -> users.id sebelum insert.
            $pegToUser = User::whereIn('pegawai_id', $validated['members'])->pluck('id', 'pegawai_id');

            $rows = collect($validated['members'])
                ->unique()
                ->map(fn ($pegId) => [
                    'lembur_spl_id' => $lemburSpl->id,
                    'user_id'       => (int) $pegToUser[$pegId],
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


    public function update(Request $request, $id)
    {
        $allowedRoles = [1, 2, 3];
        
        if (!in_array((int) $request->user()->role_id, $allowedRoles)) {
            return response()->json([
                'error' => 'Anda tidak memiliki hak akses untuk memverifikasi pengajuan lembur.',
            ], 403);
        }

        $validatedData = $request->validate([
            'verifikator_id' => 'nullable|exists:users,id',
            'status'         => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'alasan_ditolak' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $lemburSpl = LemburSpl::with(['workorder.workorderAssignment', 'members'])->findOrFail($id);
            $previousStatus = $lemburSpl->status;

            $lemburSpl->update([
                'verifikator_id'   => $validatedData['verifikator_id'],
                'status'           => $validatedData['status'],
                'waktu_verifikasi' => now(),
                'alasan_ditolak'   => $validatedData['alasan_ditolak'] ?? null,
            ]);

            // APPROVE = status baru berpindah ke 'approved'.
            $isNewApproval = $validatedData['status'] === 'approved'
                && $previousStatus !== 'approved';

            if ($isNewApproval) {
                $workorder = $lemburSpl->workorder;
                if ($workorder && $workorder->status === 'Pending') {
                    $workorder->update(['status' => 'Proses']);
                }

                // Auto-assign: replikasi anggota lembur → staff WO (wo_assignment_member).
                (new LemburApprovalService())->approveAndAssign($lemburSpl);
            }

            DB::commit();

            // ==================================================
            // NOTIFIKASI: pengajuan lembur disetujui → anggota lembur (staff lapangan).
            // Anggota tersimpan sebagai users.id di lembur_spl_member.user_id, jadi
            // resolve langsung lewat users.id. Best-effort (di luar transaksi bisnis).
            // ==================================================
            if ($isNewApproval) {
                try {
                    $lemburSpl->loadMissing('members', 'workorder');

                    $workorderId = (int) $lemburSpl->workorder_id;
                    $namaWo      = optional($lemburSpl->workorder)->nama_workorder ?? "#$workorderId";

                    $verifUser  = $lemburSpl->verifikator_id
                        ? User::with('pegawai')->find($lemburSpl->verifikator_id)
                        : null;
                    $senderName = optional(optional($verifUser)->pegawai)->nama
                        ?? optional($verifUser)->name ?? 'Manajer';

                    $memberUserIds = $lemburSpl->members->pluck('user_id')->filter()->unique();
                    $staffUsers = User::whereIn('id', $memberUserIds)->get();
                    foreach ($staffUsers as $staff) {
                        $staff->notify(new WorkOrderNotification(
                            'Pengajuan Lembur Disetujui',
                            "Pengajuan lembur pada WO #{$namaWo} telah disetujui. Anda ditugaskan.",
                            $workorderId,
                            'wo_lembur_approved',
                            $senderName
                        ));
                    }
                } catch (\Throwable $e) {
                    Log::warning('notify wo_lembur_approved failed', [
                        'lembur_spl_id' => $lemburSpl->id,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }

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
