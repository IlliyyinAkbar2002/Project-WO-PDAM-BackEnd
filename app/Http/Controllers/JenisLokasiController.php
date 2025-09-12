<?php

namespace App\Http\Controllers;

use App\Models\JenisLokasi;
use Illuminate\Http\Request;

class JenisLokasiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $jenisLokasi = JenisLokasi::all();
            return response()->json($jenisLokasi, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data jenis lokasi',
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
        $validatedData = $request->validate([
            'nama' => 'required|string',
        ]);

        try {
            $jenisLokasi = JenisLokasi::create($validatedData);
            return response()->json([
                'message' => 'Data jenis lokasi berhasil ditambahkan',
                'data' => $jenisLokasi
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat menambahkan data jenis lokasi',
                'message' => $e->getMessage()
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
            $jenisLokasi = JenisLokasi::findOrfail($id);
            return response()->json($jenisLokasi, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data jenis lokasi',
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
            'nama' => 'required|string',
        ]);

        try {
            $jenisLokasi = JenisLokasi::findOrFail($id);
            $jenisLokasi->update($validatedData);

            return response()->json([
                'message' => 'Data jenis lokasi berhasil diupdate',
                'data' => $jenisLokasi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengupdate data jenis lokasi',
                'message' => $e->getMessage()
            ], 500);
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
            $jenisLokasi = JenisLokasi::findOrFail($id);
            $jenisLokasi->delete();

            return response()->json([
                'message' => 'Data jenis lokasi berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat menghapus data jenis lokasi',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
