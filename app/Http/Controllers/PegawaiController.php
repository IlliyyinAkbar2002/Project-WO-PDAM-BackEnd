<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    /**
     * Display a listing of the resource.
     * Returns pegawai data in User format with nested employee data
     * This matches the expected structure for the Flutter app
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = Pegawai::with([
                'user:id,pegawai_id,email,role_id',
                'departemen:id,nama',
                'jabatan:id,nama'
            ]);

            // Filter berdasarkan departemen
            if ($request->has('departemen_id')) {
                $query->where('departemen_id', $request->departemen_id);
            }

            // Filter berdasarkan jabatan
            if ($request->has('jabatan_id')) {
                $query->where('jabatan_id', $request->jabatan_id);
            }

            // Eager load user relationship to avoid N+1 queries
            $pegawaiList = $query->get();
            
            // Transform pegawai data to User format with nested employee data
            $transformedData = $pegawaiList->map(function ($pegawai) {
                return [
                    'id' => $pegawai->id,
                    'pegawai_id' => $pegawai->id,
                    'name' => $pegawai->nama,
                    'email' => $pegawai->user->email ?? null,
                    'role_id' => $pegawai->user->role_id ?? null,
                    'created_at' => $pegawai->created_at,
                    'updated_at' => $pegawai->updated_at,
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nama' => $pegawai->nama,
                        'nip' => $pegawai->nip,
                        'departemen' => $pegawai->departemen->nama ?? null,
                        'jabatan' => $pegawai->jabatan->nama ?? null,
                    ]
                ];
            });
            
            return response()->json([
                'data' => $transformedData]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data pegawai',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pegawai filtered by departemen and multiple jabatan
     * Example: /api/v1/pegawai/filter?departemen_id=2&jabatan_id[]=5&jabatan_id[]=6
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getPegawaiByFilter(Request $request)
    {
        try {
            $query = Pegawai::with([
                'user:id,pegawai_id,email,role_id',
                'departemen:id,nama',
                'jabatan:id,nama'
            ]);

            // Filter by departemen
            if ($request->has('departemen_id')) {
                $query->where('departemen_id', $request->departemen_id);
            }

            // Filter by multiple jabatan (supports array)
            if ($request->has('jabatan_id')) {
                $jabatanIds = $request->jabatan_id;
                if (is_array($jabatanIds)) {
                    $query->whereIn('jabatan_id', $jabatanIds);
                } else {
                    $query->where('jabatan_id', $jabatanIds);
                }
            }

            $pegawaiList = $query->get();

            // Transform to consistent format
            $transformedData = $pegawaiList->map(function ($pegawai) {
                return [
                    'id' => $pegawai->id,
                    'pegawai_id' => $pegawai->id,
                    'name' => $pegawai->nama,
                    'email' => $pegawai->user->email ?? null,
                    'role_id' => $pegawai->user->role_id ?? null,
                    'created_at' => $pegawai->created_at,
                    'updated_at' => $pegawai->updated_at,
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nama' => $pegawai->nama,
                        'nip' => $pegawai->nip,
                        'departemen' => $pegawai->departemen->nama ?? null,
                        'jabatan' => $pegawai->jabatan->nama ?? null,
                    ]
                ];
            });

            return response()->json([
                'data' => $transformedData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data pegawai',
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
        //
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
        //
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
}
