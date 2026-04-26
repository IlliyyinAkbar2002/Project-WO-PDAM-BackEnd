<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use App\Models\Pegawai;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    /**
     * Display a listing of the resource.
     * Returns pegawai data in User format with nested employee data
     * This matches the expected structure for the Flutter app.
     *
     * Catatan kontrak:
     *   - `id`          = users.id (dipakai FE sebagai petugas_id → divalidasi
     *                     `exists:users,id` di WorkorderController@store dan
     *                     referensi FK ke `workorder_petugas.user_id`).
     *   - `pegawai_id`  = m_pegawai.id (identitas profil kepegawaian).
     * Pegawai yang belum memiliki akun User dikecualikan dari list agar tidak
     * bisa dipilih sebagai assignee (kalau lolos, pasti 422 di server).
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
            ])->whereHas('user');

            if ($request->has('departemen_id')) {
                $query->where('departemen_id', $request->departemen_id);
            }

            if ($request->has('jabatan_id')) {
                $query->where('jabatan_id', $request->jabatan_id);
            }

            $pegawaiList = $query->get();

            $transformedData = $pegawaiList->map(function ($pegawai) {
                return [
                    'id'         => $pegawai->user->id,
                    'pegawai_id' => $pegawai->id,
                    'name'       => $pegawai->nama,
                    'email'      => $pegawai->user->email ?? null,
                    'role_id'    => $pegawai->user->role_id ?? null,
                    'created_at' => $pegawai->created_at,
                    'updated_at' => $pegawai->updated_at,
                    'pegawai'    => [
                        'id'         => $pegawai->id,
                        'nama'       => $pegawai->nama,
                        'nip'        => $pegawai->nip,
                        'departemen' => $pegawai->departemen->nama ?? null,
                        'jabatan'    => $pegawai->jabatan->nama ?? null,
                    ],
                ];
            });

            return response()->json([
                'data' => $transformedData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data pegawai',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pegawai data by filter.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getPegawaiByFilter(Request $request)
    {
        try {
            $authUser = $request->user();
            if (!$authUser) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $authUser->loadMissing(['pegawai:id,jabatan_id,departemen_id', 'role:id,nama']);

            $callerRole        = $authUser->role->nama ?? null;
            $isSuperAdmin      = $callerRole === 'superadmin';
            $callerJabatanId   = $authUser->pegawai->jabatan_id ?? null;
            $callerPegawaiId   = $authUser->pegawai_id;

            // Hitung daftar jabatan yang boleh dilihat oleh caller.
            // null = tidak ada batas (superadmin), array kosong = tidak boleh melihat siapa-siapa.
            $allowedJabatanIds = null;
            if (!$isSuperAdmin) {
                if ($callerJabatanId === null) {
                    // User tanpa jabatan (mis. akun sistem) → blokir total demi aman.
                    return response()->json([
                        'data' => [],
                        'meta' => [
                            'allowed_jabatan_ids' => [],
                            'caller_jabatan_id'   => null,
                        ],
                    ], 200);
                }

                $allowedJabatanIds = Jabatan::where('id', '>', $callerJabatanId)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }

            $query = Pegawai::with([
                'user:id,pegawai_id,email,role_id',
                'user.role:id,nama',
                'departemen:id,nama',
                'jabatan:id,nama',
            ])
                // Hanya pegawai yang sudah punya akun users yang relevan
                // sebagai calon petugas — sesuai kontrak endpoint ini yang
                // mengembalikan data dalam "User format" (lihat PHPDoc index()).
                // Tanpa filter ini, row factory yang tak punya user bisa masuk
                // daftar lalu membuat POST /workorder 422 karena rule
                // `exists:users,id` pada petugas_id.
                ->whereHas('user');

            // ---- Filter: departemen_id (bebas, tidak dibatasi hirarki) ----
            if ($request->filled('departemen_id')) {
                $query->where('departemen_id', (int) $request->input('departemen_id'));
            }

            // ---- Filter: jabatan_id[] dengan clamp ----
            $requestedJabatan = $request->input('jabatan_id');
            if ($requestedJabatan !== null && $requestedJabatan !== '') {
                $requestedJabatan = is_array($requestedJabatan) ? $requestedJabatan : [$requestedJabatan];
                $requestedJabatan = array_values(array_unique(array_map('intval', $requestedJabatan)));

                if ($allowedJabatanIds !== null) {
                    $effectiveJabatan = array_values(array_intersect($requestedJabatan, $allowedJabatanIds));
                } else {
                    $effectiveJabatan = $requestedJabatan;
                }

                // Semua jabatan yang diminta di luar izin → hasil pasti kosong.
                if (empty($effectiveJabatan)) {
                    return response()->json([
                        'data' => [],
                        'meta' => [
                            'allowed_jabatan_ids' => $allowedJabatanIds ?? [],
                            'caller_jabatan_id'   => $callerJabatanId,
                        ],
                    ], 200);
                }

                $query->whereIn('jabatan_id', $effectiveJabatan);
            } elseif ($allowedJabatanIds !== null) {
                // Client tidak kirim filter → default ke daftar yang diizinkan.
                if (empty($allowedJabatanIds)) {
                    return response()->json([
                        'data' => [],
                        'meta' => [
                            'allowed_jabatan_ids' => [],
                            'caller_jabatan_id'   => $callerJabatanId,
                        ],
                    ], 200);
                }
                $query->whereIn('jabatan_id', $allowedJabatanIds);
            }

            // ---- Hardening tambahan untuk non-superadmin ----
            if (!$isSuperAdmin) {
                // Jangan sertakan pegawai yang akun user-nya ber-role superadmin.
                $query->whereDoesntHave('user.role', function ($q) {
                    $q->where('nama', 'superadmin');
                });

                // Jangan sertakan diri sendiri.
                if ($callerPegawaiId) {
                    $query->where('id', '!=', $callerPegawaiId);
                }
            }

            $pegawaiList = $query->get();

            $transformedData = $pegawaiList->map(function ($pegawai) {
                return [
                    'id'         => $pegawai->user->id,
                    'pegawai_id' => $pegawai->id,
                    'name'       => $pegawai->nama,
                    'email'      => $pegawai->user->email ?? null,
                    'role_id'    => $pegawai->user->role_id ?? null,
                    'created_at' => $pegawai->created_at,
                    'updated_at' => $pegawai->updated_at,
                    'pegawai'    => [
                        'id'            => $pegawai->id,
                        'nama'          => $pegawai->nama,
                        'nip'           => $pegawai->nip,
                        'departemen'    => $pegawai->departemen->nama ?? null,
                        'departemen_id' => $pegawai->departemen_id,
                        'jabatan'       => $pegawai->jabatan->nama ?? null,
                        'jabatan_id'    => $pegawai->jabatan_id,
                    ],
                ];
            });

            return response()->json([
                'data' => $transformedData,
                'meta' => [
                    'allowed_jabatan_ids' => $allowedJabatanIds, // null untuk superadmin
                    'caller_jabatan_id'   => $callerJabatanId,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Terjadi kesalahan saat mengambil data pegawai',
                'message' => $e->getMessage(),
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
     * Lengkapi data pegawai (nip, alamat, departemen, jabatan) setelah
     * karyawan melakukan self-register via mobile. Hanya boleh dipanggil
     * oleh Super Admin (proteksi via middleware role:superadmin).
     */
    public function assign(Request $request, $id)
    {
        try {
            $pegawai = Pegawai::findOrFail($id);

            $validated = $request->validate([
                'nip'           => 'sometimes|nullable|string|max:50|unique:m_pegawai,nip,' . $pegawai->id,
                'alamat'        => 'sometimes|nullable|string|max:255',
                'departemen_id' => 'sometimes|nullable|exists:m_departemen,id',
                'jabatan_id'    => 'sometimes|nullable|exists:m_jabatan,id',
            ]);

            $pegawai->fill($validated)->save();
            $pegawai->load('departemen:id,nama', 'jabatan:id,nama', 'user:id,pegawai_id,email,role_id');

            return response()->json([
                'success' => true,
                'message' => 'Data pegawai berhasil dilengkapi',
                'data'    => [
                    'id'            => $pegawai->id,
                    'nama'          => $pegawai->nama,
                    'nip'           => $pegawai->nip,
                    'alamat'        => $pegawai->alamat,
                    'telepon'       => $pegawai->telepon,
                    'jenis_kelamin' => $pegawai->jenis_kelamin,
                    'tanggal_lahir' => $pegawai->tanggal_lahir,
                    'departemen'    => $pegawai->departemen->nama ?? null,
                    'jabatan'       => $pegawai->jabatan->nama ?? null,
                    'user'          => $pegawai->user,
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data pegawai',
                'error'   => $e->getMessage(),
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
