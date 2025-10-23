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
    public function index()
    {
        try {
            // Eager load user relationship to avoid N+1 queries
            $pegawaiList = Pegawai::with('user:id,pegawai_id,email,role_id')->get();
            
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
                    ]
                ];
            });
            
            return response()->json($transformedData);
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
