<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckKyc
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try to get user from default guard first, then merchant guard
        $user = Auth::user() ?? Auth::guard('merchant')->user();

        // Check if the user is authenticated
        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // Check if KYC is completed
        if (is_null($user->nin) || is_null($user->bvn) || is_null($user->utility_bill_path)) {
            return response()->json([
                'message' => 'KYC not completed. Please complete your KYC to access this resource.',
                'requires_kyc' => true
            ], 403);
        }

        return $next($request);
    }
}