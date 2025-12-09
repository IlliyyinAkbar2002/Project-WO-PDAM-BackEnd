<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        
        // Load relasi pegawai untuk mendapatkan nama
        $user->load('pegawai');
        
        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role_id' => $user->role_id,
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
            $validatedData = $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8',
                // 'pegawai_id' => 'required|exists:m_pegawai,id',
                'role_id' => 'required|exists:m_role,id',
            ]);
            
            $validatedData['password'] = bcrypt($validatedData['password']);
            
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => $validatedData['password'],
                // 'pegawai_id' => $validatedData['pegawai_id'],
                'role_id' => $validatedData['role_id'],
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                ]
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request) {
        $user = $request->user();
        $user->load('pegawai');
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->pegawai->nama ?? null,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'pegawai' => $user->pegawai,
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
            
            // Load relasi pegawai untuk mendapatkan nama
            $user->load('pegawai');
            
            return response()->json([
                'success' => true,
                'message' => 'Data user berhasil diambil',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->pegawai->nama ?? null,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
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
