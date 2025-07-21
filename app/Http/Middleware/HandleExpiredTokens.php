<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class HandleExpiredTokens
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if ($user && $request->bearerToken()) {
            $token = $request->user()->currentAccessToken();
            
            if ($token) {
                // Check if token has expired
                if ($token->expires_at && Carbon::now()->isAfter($token->expires_at)) {
                    // Delete the expired token
                    $token->delete();
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Session expired. Please login again.',
                        'code' => 'TOKEN_EXPIRED'
                    ], 401);
                }
                
                // Check for inactivity (more than 1 hour since last use)
                if ($token->last_used_at && Carbon::now()->diffInHours($token->last_used_at) >= 1) {
                    // Delete the inactive token
                    $token->delete();
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Session expired due to inactivity. Please login again.',
                        'code' => 'TOKEN_INACTIVE'
                    ], 401);
                }
            }
        }

        return $next($request);
    }
} 