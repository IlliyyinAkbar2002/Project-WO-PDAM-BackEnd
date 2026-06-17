<?php

namespace App\Http\Controllers;
use App\Models\LaporanWorkorder;

use Illuminate\Http\Request;

class LaporanWorkorderController extends Controller
{
    //
    public function index()
    {
        try {
            $laporanWorkorders = LaporanWorkorder::with(['workorder', 'issuer', 'approver'])->get();
            return response()->json($laporanWorkorders, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data laporan workorder',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $laporanWorkorder = LaporanWorkorder::with(['workorder', 'issuer', 'approver'])->findOrFail($id);
            return response()->json($laporanWorkorder, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data laporan workorder',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'workorder_id' => 'required|exists:workorder,id',
            'nomor_laporan' => 'required|string|unique:laporan_workorder,nomor_laporan',
            'tanggal_terbit' => 'required|date',
            'ringkasan_pekerjaan' => 'required|string',
            'hasil_akhir_snapshot' => 'required|array',
            'petugas_snapshot' => 'required|array',
            'catatan_spv' => 'nullable|string',
            'issued_by_user_id' => 'required|exists:users,id',
            'approved_by_user_id' => 'nullable|exists:users,id',
        ]);

        try {
            $laporanWorkorder = LaporanWorkorder::create($validatedData);
            return response()->json([
                'message' => 'Laporan workorder berhasil dibuat',
                'laporan_workorder' => $laporanWorkorder
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat membuat laporan workorder',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
