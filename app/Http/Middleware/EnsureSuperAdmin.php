<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('central_api');

        if (!$user || !$user->is_superadmin) {
            return response()->json([
                'message' => 'Forbidden.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
