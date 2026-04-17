<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    /**
     * Pakai sebagai middleware route: ->middleware('role:superadmin')
     * atau ->middleware('role:superadmin,manager') untuk multi-role.
     *
     * $user->role adalah relasi belongsTo(Role::class); pembandingan harus
     * terhadap kolom `nama` pada m_role, bukan terhadap object relasinya.
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->loadMissing('role');
        $userRole = $user->role->nama ?? null;

        if (!$userRole || !in_array($userRole, $roles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
