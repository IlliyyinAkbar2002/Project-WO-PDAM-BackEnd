<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // public function login(Request $request)
    // {
    //     $credentials = $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required|string',
    //     ]);

    // // Coba login pakai Auth::attempt()
    // if (!Auth::attempt($credentials)) {
    //     return response()->json([
    //         'message' => 'Email atau password salah'
    //     ], 401);
    // }

    // // Ambil user yang sedang login
    // $user = Auth::user();

    // dd(get_class(Auth::user()));


    // $token = $user->createToken('auth_token')->plainTextToken;

    // return response()->json([
    //     'message' => 'Login berhasil',
    //     'access_token' => $token,
    //     'token_type' => 'Bearer',
    //     'user' => [
    //         'id' => $user->id,
    //         'name' => $user->name,
    //         'email' => $user->email,
    //         'role_id' => $user->role_id,
    //     ],
    // ], 201);
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id],
        ], 200)->cookie(
            'token', $token, 60 * 24);
    }

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
        ]);
        $validatedData['password'] = bcrypt($validatedData['password']);
        $user = User::create($validatedData);
        return response()->json([
            'message' => 'Registrasi berhasil',
            'user' => $user
        ], 201);
    }

    public function me(Request $request) {
    return response()->json($request->user());
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }
}
