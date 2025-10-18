<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
        // Login untuk SPA Sanctum
        public function spaLogin(Request $request)
        {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);
            
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email atau password salah'
                ], 401);
            }

            $request->session()->regenerate();

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan ketika login'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => Auth::user()
            ], 200);
        }
        
        // Logout untuk SPA Sanctum
        public function spaLogout(Request $request)
        {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil'
            ], 200);
        }

        // Login untuk API Token
        public function apiLogin(Request $request)
        {
            $data = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
            
            $user = User::where('email', $data['email'])->first();
            
            if (!$user || !Hash::check($data['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email atau password salah'
                ], 401);
            }

            // Hapus token lama
            $user->tokens()->delete();
            
            // Buat token baru
            $token = $user->createToken('api-token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                ],
            ], 200);
        }

        // Logout untuk API Token
        public function apiLogout(Request $request)
        {
            $user = $request->user();
            if ($user) {
                // Hapus token yang digunakan untuk request ini
                $user->currentAccessToken()->delete();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Logout berhasil'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ], 401);
            }
        }


    public function register(Request $request)
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
        return response()->json($request->user(), 200);

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
            
            return response()->json([
                'success' => true,
                'message' => 'Data user berhasil diambil',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
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
