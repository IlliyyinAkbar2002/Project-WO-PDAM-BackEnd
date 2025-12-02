<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureValidClient
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()->currentAccessToken();
        
        if (!$token || !$token->can('access')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Invalid client permissions.'
            ], 403);
        }
        
        return $next($request);
    }
}
