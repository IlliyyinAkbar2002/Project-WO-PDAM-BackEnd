<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
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

    public function resetPassword($id)
    {
        try {
            $user = User::findOrFail($id);
            $defaultPassword = 'password';
            $user->update([
                'password' => Hash::make($defaultPassword)
            ]);
            return response()->json([
                'message' => 'Password berhasil direset',
                'default_password' => $defaultPassword
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat reset password',
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

    public function toggleAccountStatus($id)
    {
        try {
            $user = User::findOrFail($id);
            if ($user->id === auth()->id()) {
                return response()->json([
                    'error' => 'Anda tidak dapat menonaktifkan akun sendiri.'
                ], 422);
            }
            $user->is_active = !$user->is_active;
            $user->save();
            return response()->json([
                'message' => $user->is_active
                    ? 'Akun berhasil diaktifkan'
                    : 'Akun berhasil dinonaktifkan',
                'is_active' => $user->is_active,
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengubah status akun'
            ], 500);
        }
    }
}
