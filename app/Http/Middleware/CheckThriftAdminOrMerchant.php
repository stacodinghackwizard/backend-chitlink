<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ThriftPackage;

class CheckThriftAdminOrMerchant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $packageId = $request->route('id') ?? $request->route('thrift_package_id');

        $isMerchant = $user && $user instanceof \App\Models\Merchant;
        $isAdmin = $user && $packageId && $user->adminThriftPackages()->where('thrift_package_id', $packageId)->exists();

        if ($isMerchant || $isAdmin) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden: Only merchant or thrift admin can perform this action.'], 403);
    }
}