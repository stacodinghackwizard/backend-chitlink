<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class UpdateTokenActivity
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
        // Get the authenticated user (could be User or Merchant)
        $user = Auth::user();
        
        if ($user && $request->bearerToken()) {
            // Get the current token from the request
            $token = $request->user()->currentAccessToken();
            
            if ($token) {
                // Update the token's last_used_at timestamp
                $token->update([
                    'last_used_at' => Carbon::now(),
                ]);
                
                // Extend the token expiration by 1 hour from now
                $token->update([
                    'expires_at' => Carbon::now()->addHour(),
                ]);
            }
        }

        return $next($request);
    }
} 