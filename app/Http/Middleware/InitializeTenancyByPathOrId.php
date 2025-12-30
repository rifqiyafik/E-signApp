<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

/**
 * Initialize Tenancy By Path Or ID
 * 
 * This middleware identifies tenants by slug (primary) or ID (fallback)
 * in the URL path parameter.
 * 
 * Example routes:
 * - /{tenant}/api/users -> Identifies tenant by slug or ID
 */
class InitializeTenancyByPathOrId extends InitializeTenancyByPath
{
    public function handle(Request $request, Closure $next)
    {
        $response = null;

        try {
            $route = $request->route();
            $tenantIdentifier = $route->parameter('tenant');

            // Try to find tenant by slug first
            $tenant = Tenant::where('slug', $tenantIdentifier)->first();

            // If not found by slug, try by ID
            if (!$tenant) {
                $tenant = Tenant::find($tenantIdentifier);
            }

            if (!$tenant && (in_array('api', $route->gatherMiddleware(), true) || $request->expectsJson())) {
                Log::warning('Tenant not found for API request', [
                    'tenant' => $tenantIdentifier,
                    'path' => $request->path(),
                    'host' => $request->getHost(),
                ]);

                return response()->json([
                    'message' => 'Tenant not found.',
                    'tenant' => $tenantIdentifier,
                ], 404);
            }

            // Abort 404 for web routes if tenant not found
            if (!$tenant && in_array('web', $route->gatherMiddleware())) {
                abort(404);
            }

            // Set tenant ID parameter for tenancy initialization
            if ($tenant) {
                $route->setParameter('tenant', $tenant->id);
            }

            $response = parent::handle($request, $next);
        } catch (\Throwable $th) {
            // Handle TenantNotFound
            if ($th instanceof TenantCouldNotBeIdentifiedException) {
                // Handle PreventAccessFromCentralDomains
                if (in_array($request->getHost(), config('tenancy.central_domains'))) {
                    $abortRequest = static::$abortRequest ?? function () {
                        abort(404);
                    };

                    $response = $abortRequest($request, $next);
                } else {
                    $response = $next($request);
                }
            } elseif ($th instanceof TenantDatabaseDoesNotExistException) {
                $tenantIdentifier = $request->route()?->parameter('tenant');
                Log::error('Tenant database missing', [
                    'tenant' => $tenantIdentifier,
                    'path' => $request->path(),
                    'host' => $request->getHost(),
                ]);

                if ($request->expectsJson() || in_array('api', $request->route()?->gatherMiddleware() ?? [], true)) {
                    return response()->json([
                        'message' => 'Tenant database not initialized.',
                        'tenant' => $tenantIdentifier,
                    ], 500);
                }

                abort(500, 'Tenant database not initialized.');
            } else {
                throw $th;
            }
        }

        return $response;
    }
}
