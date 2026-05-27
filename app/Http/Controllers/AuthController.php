<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
        // Login untuk Mobile Client dengan Token Abilities
    public function AuthLogin(Request $request)
    {
        if (!$request->filled('email')) {
            return response()->json([
                'success' => false,
                'message' => 'Email wajib diisi'
            ], 422);
        }
            
        if (!$request->filled('password')) {
            return response()->json([
                'success' => false,
                'message' => 'Password wajib diisi'
            ], 422);
        }

        $data = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
            
        $user = User::where('email', $data['email'])->first();
            
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email salah'
            ], 401);
        }
            
        if (!Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah'
            ], 401);
        }

        $tokenName = 'access_token';
        $ability = 'access';

        // Hapus token lama
        $user->tokens()->where('name', $tokenName)->delete();
        
        // Buat token baru dengan abilities yang sesuai
        $token = $user->createToken($tokenName, [
            $ability,
        ])->plainTextToken;
        
        // Load pegawai + jabatan + departemen.
        // jabatan WAJIB diikutkan: role_id=3 dipakai bersama oleh SPV dan Staff,
        // FE membedakan keduanya lewat pegawai.jabatan.kode (SPV vs STAFF/SENIOR_STAFF).
        $user->load('pegawai.jabatan', 'pegawai.departemen');

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'pegawai' => $user->pegawai ? [
                    'id'        => $user->pegawai->id,
                    'nama'      => $user->pegawai->nama,
                    'nip'       => $user->pegawai->nip,
                    'jabatan'   => $user->pegawai->jabatan ? [
                        'id'   => $user->pegawai->jabatan->id,
                        'kode' => $user->pegawai->jabatan->kode,
                        'nama' => $user->pegawai->jabatan->nama,
                    ] : null,
                    'departemen' => $user->pegawai->departemen ? [
                        'id'   => $user->pegawai->departemen->id,
                        'nama' => $user->pegawai->departemen->nama,
                    ] : null,
                ] : null,
            ],
        ], 200);
    }

    public function AuthLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ], 200);
    }


    public function AuthRegister(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                'email'         => 'required|email|unique:users,email',
                'password'      => 'required|string|min:8',
                'telepon'       => 'required|string|max:20',
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'tanggal_lahir' => 'required|date',
            ]);

            $roleEmployee = Role::where('nama', 'employee')->first();
            if (!$roleEmployee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role default "employee" belum tersedia. Hubungi admin.',
                ], 500);
            }

            $user = DB::transaction(function () use ($validated, $roleEmployee) {
                $pegawai = Pegawai::create([
                    'nama'          => $validated['name'],
                    'telepon'       => $validated['telepon'],
                    'jenis_kelamin' => $validated['jenis_kelamin'],
                    'tanggal_lahir' => $validated['tanggal_lahir'],
                    // nip, alamat, departemen_id, jabatan_id sengaja dikosongkan;
                    // diisi oleh Super Admin lewat endpoint assign.
                ]);

                return User::create([
                    'pegawai_id' => $pegawai->id,
                    'role_id'    => $roleEmployee->id,
                    'email'      => $validated['email'],
                    'password'   => Hash::make($validated['password']),
                ]);
            });

            $user->load('pegawai', 'role');

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil. Menunggu penugasan departemen & jabatan oleh Super Admin.',
                'user'    => [
                    'id'      => $user->id,
                    'email'   => $user->email,
                    'role_id' => $user->role_id,
                    'role'    => $user->role->nama ?? null,
                    'pegawai' => [
                        'id'            => $user->pegawai->id,
                        'nama'          => $user->pegawai->nama,
                        'telepon'       => $user->pegawai->telepon,
                        'jenis_kelamin' => $user->pegawai->jenis_kelamin,
                        'tanggal_lahir' => $user->pegawai->tanggal_lahir,
                        'nip'           => $user->pegawai->nip,
                        'alamat'        => $user->pegawai->alamat,
                        'departemen_id' => $user->pegawai->departemen_id,
                        'jabatan_id'    => $user->pegawai->jabatan_id,
                    ],
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request) {
        $user = $request->user();
        $user->load('pegawai.jabatan', 'pegawai.departemen');

        return response()->json([
            'id' => $user->id,
            'name' => $user->pegawai?->nama,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'pegawai' => $user->pegawai ? [
                'id'        => $user->pegawai->id,
                'nama'      => $user->pegawai->nama,
                'nip'       => $user->pegawai->nip,
                'jabatan'   => $user->pegawai->jabatan ? [
                    'id'   => $user->pegawai->jabatan->id,
                    'kode' => $user->pegawai->jabatan->kode,
                    'nama' => $user->pegawai->jabatan->nama,
                ] : null,
                'departemen' => $user->pegawai->departemen ? [
                    'id'   => $user->pegawai->departemen->id,
                    'nama' => $user->pegawai->departemen->nama,
                ] : null,
            ] : null,
        ], 200);
    }

    public function getUser(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ], 401);
            }
            
            // Load relasi pegawai + jabatan + departemen agar FE bisa membedakan SPV vs Staff
            $user->load('pegawai.jabatan', 'pegawai.departemen');

            return response()->json([
                'success' => true,
                'message' => 'Data user berhasil diambil',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->pegawai?->nama,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'pegawai' => $user->pegawai ? [
                        'id'        => $user->pegawai->id,
                        'nama'      => $user->pegawai->nama,
                        'nip'       => $user->pegawai->nip,
                        'jabatan'   => $user->pegawai->jabatan ? [
                            'id'   => $user->pegawai->jabatan->id,
                            'kode' => $user->pegawai->jabatan->kode,
                            'nama' => $user->pegawai->jabatan->nama,
                        ] : null,
                        'departemen' => $user->pegawai->departemen ? [
                            'id'   => $user->pegawai->departemen->id,
                            'nama' => $user->pegawai->departemen->nama,
                        ] : null,
                    ] : null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
