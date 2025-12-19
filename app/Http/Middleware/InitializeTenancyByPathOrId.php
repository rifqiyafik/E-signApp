<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

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
            } else {
                throw $th;
            }
        }

        return $response;
    }
}
