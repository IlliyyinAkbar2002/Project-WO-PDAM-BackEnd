<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMobileClient
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()->currentAccessToken();
        
        if (!$token || !$token->can('mobile:access')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only accessible to mobile clients.'
            ], 403);
        }
        
        return $next($request);
    }
}

