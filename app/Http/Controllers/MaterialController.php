<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $material = Material::with('pegawai:id,nama')->get();
            return response()->json($material, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data material',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
            'kode_material' => 'required|integer|unique:m_material,kode_material',
            'nama' => 'required|string',
            'jumlah_stok' => 'required|integer',
        ]);

        $user = Auth::user();

        $validatedData['pegawai_id'] = $user->pegawai_id;

        $validatedData['terpakai'] = 0;

        $material = Material::create($validatedData);
        return response()->json([
            'message' => 'Data material berhasil ditambahkan',
            'material' => $material->append('tersedia')
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Material  $material
     * @return \Illuminate\Http\Response
     */
    public function show($kode_material)
    {
        try {
        $material = Material::with('pegawai:id,nama')
            ->findOrFail($kode_material);

        return response()->json([
            'data' => $material->append('tersedia')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Data tidak ditemukan',
        ], 404);
    }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Material  $material
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $kode_material)
    {
        $material = Material::findOrFail($kode_material);

        $validated = $request->validate([
            'nama' => 'required|string',
            'jumlah_stok' => 'required|integer|min:' . $material->terpakai,
        ]);

        $material->update($validated);

        return response()->json([
            'message' => 'Material berhasil diperbarui',
            'data' => $material->append('tersedia')
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Material  $material
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $kode_material)
    {
        $request->validate([
        'jumlah_pakai' => 'required|integer|min:1'
        ]);

        $material = Material::findOrFail($kode_material);

        if ($material->terpakai + $request->jumlah_pakai > $material->jumlah_stok) {
            return response()->json([
                'message' => 'Stok tidak mencukupi'
            ], 400);
        }

        $material->terpakai += $request->jumlah_pakai;
        $material->save();

        return response()->json([
            'message' => 'Pemakaian berhasil diperbarui',
            'data' => $material->append('tersedia')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Material  $material
     * @return \Illuminate\Http\Response
     */
    public function destroy($kode_material)
    {
        $material = Material::findOrFail($kode_material);
        $material->delete();

        return response()->json([
            'message' => 'Material berhasil dihapus'
        ]);
    }
}
