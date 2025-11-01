<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureWebClient
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()->currentAccessToken();
        
        if (!$token || !$token->can('web:access')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only accessible to web clients.'
            ], 403);
        }
        
        return $next($request);
    }
}

