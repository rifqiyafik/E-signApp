<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['super_admin', 'admin'], true)) {
            return response()->json([
                'message' => 'Forbidden.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
