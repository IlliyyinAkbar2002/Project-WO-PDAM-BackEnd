<?php

namespace App\Http\Controllers;

use App\Models\Workorder;
use App\Services\AssignmentService;
use Illuminate\Http\Request;

class AssignmentWorkorder extends Controller
{
    private AssignmentService $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * GET /api/v1/workorder/{id}/assignment
     * Menampilkan data assignment beserta members untuk workorder tertentu.
     */
    public function show($id)
    {
        $workorder = Workorder::findOrFail($id);

        $assignment = $workorder->workorderAssignment()
            ->with(['spv', 'members.user.pegawai', 'location'])
            ->first();

        if (!$assignment) {
            return response()->json([
                'message' => 'Workorder ini belum memiliki assignment.',
            ], 404);
        }

        return response()->json([
            'message' => 'Data assignment ditemukan.',
            'data'    => $assignment,
        ]);
    }

    public function assignStaff(Request $request, $id)
    {
        $workorder = Workorder::with('jenisWorkorder', 'workorderAssignment.members')
            ->findOrFail($id);

        $input = $request->all();
        if (!isset($input['form_kategori']) || !is_array($input['form_kategori'])) {
            // Prioritas: kategori_form dari payload > dari DB
            $kategori = $input['kategori_form'] ?? optional($workorder->jenisWorkorder)->kategori_form;
            $kategoriFields = $this->extractKategoriFields($kategori, $input);
            if (!empty($kategoriFields)) {
                $request->merge(['form_kategori' => $kategoriFields]);
            }
        }

        $validated = $request->validate([
            'kategori_form'          => 'nullable|string|in:meter,jaringan,infrastruktur',
            // Field kategori "awal" kini diisi staff saat MULAI (lihat
            // BE_pindah_form_awal_ke_mulai.md), jadi form_kategori boleh kosong/{}
            // di tahap assign. 'required|array' akan menolak {} (Laravel
            // menganggap array kosong = tidak terisi), karena itu pakai nullable.
            'form_kategori'          => 'nullable|array',
            'petugas'                => 'required|array|min:1',
            'petugas.*.user_id'      => 'required|exists:users,id',
            'petugas.*.peran'        => 'nullable|in:koordinator,anggota',
            'latitude'               => 'nullable|numeric|between:-90,90',
            'longitude'              => 'nullable|numeric|between:-180,180',
            'accuracy'               => 'nullable|numeric|min:0',
            'nama_lokasi'            => 'nullable|string|max:255',
            'deskripsi'              => 'nullable|string',
            'tanggal_mulai'          => 'nullable|date',
            'tanggal_selesai'        => 'nullable|date',
            'estimasi_selesai'       => 'nullable|date',
        ]);

        try {
            $this->assignmentService->assignStaff(
                $workorder,
                $validated,
                (int) $request->user()->id
            );

            // Reload WO dengan relasi lengkap supaya FE tidak perlu
            // memanggil endpoint detail lagi setelah assign sukses.
            // Shape mengikuti WorkorderController@show + tambahan
            // workorderAssignment dan members (user.pegawai).
            $fresh = Workorder::with([
                'assignmentMembers.user.pegawai',
                'assignedTo',
                'jenisWorkorder',
                'status',
                'lemburSpl',
                'latestFreeze',
                'woMeter',
                'woJaringan',
                'woInfrastruktur',
                'workorderAssignment.spv.pegawai',
                'workorderAssignment.members.user.pegawai',
                'workorderAssignment.location',
            ])->findOrFail($workorder->id);

            return response()->json([
                'message' => 'Assign staff berhasil',
                'data'    => $fresh,
            ], 200);
        } catch (\LogicException $e) {
            $code = str_contains($e->getMessage(), 'Hanya SPV') ? 403 : 422;
            return response()->json(['error' => $e->getMessage()], $code);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ekstrak field-field kategori dari payload flat berdasarkan tipe kategori.
     * Ini memungkinkan FE mengirim field langsung tanpa wrapper "form_kategori".
     */
    private function extractKategoriFields(?string $kategori, array $input): array
    {
        $fieldMap = [
            'meter' => [
                'nomor_meter', 'kondisi_meter_awal', 'kondisi_meter_akhir', 'hasil_kalibrasi',
            ],
            'jaringan' => [
                'jenis_pipa', 'diameter_pipa', 'panjang_pipa',
                'tingkat_kerusakan', 'tindakan_perbaikan', 'hasil_inspeksi',
            ],
            'infrastruktur' => [
                'nama_aset', 'jenis_aset', 'kapasitas',
                'kondisi_awal', 'kondisi_akhir', 'jadwal_pemeliharaan', 'tindakan',
            ],
        ];

        $fields = $fieldMap[$kategori] ?? [];
        $result = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $result[$field] = $input[$field];
            }
        }

        return $result;
    }
}
