<?php

namespace App\Http\Controllers;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

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
        logger($request->all());
        try {
            $query = Pegawai::with([
                'user:id,pegawai_id,email,role_id,is_active',
                'departemen:id,nama',
                'jabatan:id,nama'
            ]);

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where(DB::raw('LOWER(nama)'), 'LIKE', '%' . strtolower($search) . '%')
                    ->orWhere(DB::raw('LOWER(nip)'), 'LIKE', '%' . strtolower($search) . '%');
                });
            }

            // Filter berdasarkan departemen
            if ($request->filled('departemen_id')) {
                $query->where('m_pegawai.departemen_id', (int) $request->departemen_id);
            }

            // Filter berdasarkan jabatan
            if ($request->filled('jabatan_id')) {
                $query->where('m_pegawai.jabatan_id', (int) $request->jabatan_id);
            }

            // Eager load user relationship to avoid N+1 queries
            $pegawaiList = $query->paginate($request->per_page ?? 10);
            
            // Transform pegawai data to User format with nested employee data
            $transformedData = collect($pegawaiList->items())->map(function ($pegawai) {
                return [
                    'id' => $pegawai->id,
                    'pegawai_id' => $pegawai->id,
                    'user_id' => $pegawai->user?->id,
                    'name' => $pegawai->nama,
                    'email' => $pegawai->user->email ?? null,
                    'role_id' => $pegawai->user->role_id ?? null,
                    'role' => $pegawai->user?->role?->nama,
                    'is_active' => $pegawai->user?->is_active ?? false,
                    'created_at' => $pegawai->created_at,
                    'updated_at' => $pegawai->updated_at,
                    'departemen_id' => $pegawai->departemen_id,
                    'jabatan_id' => $pegawai->jabatan_id,
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nama' => $pegawai->nama,
                        'nip' => $pegawai->nip,
                        'jenis_kelamin' => $pegawai->jenis_kelamin,
                        'tanggal_lahir' => $pegawai->tanggal_lahir,
                        'alamat' => $pegawai->alamat,
                        'telepon' => $pegawai->telepon,
                        'departemen' => $pegawai->departemen?->nama,
                        'jabatan' => $pegawai->jabatan?->nama,
                    ]
                ];
            });
            return response()->json([
                'data' => $transformedData,
                'current_page' => $pegawaiList->currentPage(),
                'last_page' => $pegawaiList->lastPage(),
                'per_page' => $pegawaiList->perPage(),
                'total' => $pegawaiList->total(),
                ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data pegawai',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function meta()
    {
        logger('META CALLED');
        return response()->json([
            'departemen' => Departemen::select('id', 'nama')->get(),
            'jabatan' => Jabatan::select('id', 'nama')->get(),
            'role' => Role::select('id', 'nama')->get(),
        ]);
    }

    public function filterOptions()
    {
        return response()->json([
            'departemen' => Departemen::select('id', 'nama')->get(),
            'jabatan' => Jabatan::select('id', 'nama')->get(),
        ]);
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
                    'departemen_id' => $pegawai->departemen_id,
                    'jabatan_id' => $pegawai->jabatan_id,
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nama' => $pegawai->nama,
                        'nip' => $pegawai->nip,
                        'departemen' => $pegawai->departemen->nama,
                        'jabatan' => $pegawai->jabatan->nama,
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
        logger()->info('PAYLOAD PEGAWAI STORE', $request->all());
        $validated = $request->validate([
            // USER
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role_id' => 'required|integer|exists:m_role,id',
            // PEGAWAI (core)
            'nama' => 'required|string',
            'nip' => 'required|string|max:50|unique:m_pegawai,nip',
            'departemen_id' => 'required|integer|exists:m_departemen,id',
            'jabatan_id' => 'required|integer|exists:m_jabatan,id',
            // OPTIONAL
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $pegawai = Pegawai::create([
                'nama' => $validated['nama'],
                'nip' => $validated['nip'],
                'departemen_id' => $validated['departemen_id'],
                'jabatan_id' => $validated['jabatan_id'],
            ]);
            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => $validated['role_id'],
                'pegawai_id' => $pegawai->id,
                'is_active' => true,
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Akun pegawai berhasil dibuat',
                'data' => [
                    'pegawai_id' => $pegawai->id,
                    'email' => $validated['email'],
                ]
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data pegawai',
                'error' => $e->getMessage()
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
            $pegawai = Pegawai::with([
                'departemen:id,nama',
                'jabatan:id,nama',
                'user.role:id,nama'
            ])->findOrFail($id);
            return response()->json([
                'id' => $pegawai->id,
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'tanggal_lahir' => $pegawai->tanggal_lahir,
                'jenis_kelamin' => $pegawai->jenis_kelamin,
                'alamat' => $pegawai->alamat,
                'telepon' => $pegawai->telepon,
                'departemen_id' => $pegawai->departemen_id,
                'departemen' => $pegawai->departemen?->nama,
                'jabatan_id' => $pegawai->jabatan_id,
                'jabatan' => $pegawai->jabatan?->nama,
                'user' => [
                    'id' => $pegawai->user?->id,
                    'email' => $pegawai->user?->email,
                    'role_id' => $pegawai->user?->role_id,
                    'role' => $pegawai->user?->role?->nama,
                    'is_active' => $pegawai->user?->is_active,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan',
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
        DB::beginTransaction();
        try {
            $pegawai = Pegawai::with('user')->findOrFail($id);
            $validated = $request->validate([
                'nama' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $pegawai->user->id,
                'role_id' => 'required|exists:m_role,id',
                'departemen_id' => 'required|exists:m_departemen,id',
                'jabatan_id' => 'required|exists:m_jabatan,id',
                'nip' => 'nullable|string|max:50',
                'tanggal_lahir' => 'nullable|date',
                'jenis_kelamin' => 'nullable|string',
                'alamat' => 'nullable|string',
                'telepon' => 'nullable|string|max:20',
            ]);
            $pegawai->update([
                'nama' => $validated['nama'],
                'nip' => $validated['nip'] ?? null,
                'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
                'jenis_kelamin' => $validated['jenis_kelamin'] ?? null,
                'alamat' => $validated['alamat'] ?? null,
                'telepon' => $validated['telepon'] ?? null,
                'departemen_id' => $validated['departemen_id'],
                'jabatan_id' => $validated['jabatan_id'],
            ]);

            $pegawai->user->update([
                'email' => $validated['email'],
                'role_id' => $validated['role_id'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Data pegawai berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Gagal memperbarui data pegawai',
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
        // 
    }
}
