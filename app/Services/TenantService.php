<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Service
 * 
 * Service layer for tenant CRUD operations.
 * Handles tenant creation, update, and deletion with proper error handling.
 */
class TenantService
{
    /**
     * Create a new tenant
     *
     * @param array $input ['name', 'code', 'slug'?]
     * @return array
     */
    public static function store(array $input): array
    {
        $user = Auth::user();

        // Check if tenant with same name or slug exists
        $tenantExists = Tenant::where('name', $input['name'])
            ->when(!empty($input['slug']), function ($query) use ($input) {
                return $query->orWhere('slug', $input['slug']);
            })
            ->first();

        if ($tenantExists) {
            return [
                'status' => 'warning',
                'message' => __('Tenant already exists. Use a different name'),
            ];
        }

        try {
            DB::beginTransaction();

            // Create tenant
            $tenant = new Tenant();
            $tenant->name = $input['name'];
            $tenant->code = $input['code'];
            $tenant->slug = $input['slug'] ?? Tenant::generateSlug($input['name']);
            $tenant->owner_id = $user?->id;
            $tenant->save();

            // Create domain for tenant
            $tenant->domains()->create([
                'domain' => $input['code'],
            ]);

            // Associate user with tenant if authenticated
            if ($user) {
                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'global_user_id' => $user->global_id,
                    'role' => 'super_admin',
                    'is_owner' => true,
                    'tenant_join_date' => now(),
                ]);
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => __('Tenant created successfully'),
                'tenant' => $tenant,
            ];
        } catch (\Throwable $th) {
            DB::rollBack();

            Log::error('Failed to create tenant', [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => __('Failed to create tenant'),
                'error' => $th->getMessage(),
            ];
        }
    }

    /**
     * Update a tenant
     *
     * @param array $validatedData
     * @param string $id
     * @return array
     */
    public static function update(array $validatedData, string $id): array
    {
        $tenant = Tenant::findOrFail($id);

        try {
            DB::beginTransaction();

            $tenant->update([
                'name' => $validatedData['name'] ?? $tenant->name,
                'code' => $validatedData['code'] ?? $tenant->code,
                // Add more fields as needed
            ]);

            $tenant->refresh();

            DB::commit();

            return [
                'status' => 'success',
                'message' => __('Tenant updated successfully'),
                'tenant' => $tenant,
            ];
        } catch (\Throwable $th) {
            DB::rollBack();

            Log::error('Failed to update tenant', [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => __('Failed to update tenant'),
                'error' => $th->getMessage(),
            ];
        }
    }

    /**
     * Delete a tenant
     *
     * @param string $id
     * @return array
     */
    public static function destroy(string $id): array
    {
        $tenant = Tenant::findOrFail($id);

        try {
            // Delete tenant (will trigger TenantDeleted event which deletes database)
            $tenant->delete();

            return [
                'status' => 'success',
                'message' => __('Tenant deleted successfully'),
            ];
        } catch (\Throwable $th) {
            Log::error('Failed to delete tenant', [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => __('Failed to delete tenant'),
                'error' => $th->getMessage(),
            ];
        }
    }

    /**
     * Get tenant by ID or slug
     *
     * @param string $identifier
     * @return Tenant|null
     */
    public static function find(string $identifier): ?Tenant
    {
        return Tenant::where('slug', $identifier)->first()
            ?? Tenant::find($identifier);
    }
}
