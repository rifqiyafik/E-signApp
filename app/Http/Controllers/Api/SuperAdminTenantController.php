<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SuperAdminTenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        $tenants = $query->orderByDesc('created_at')
            ->get()
            ->map(fn (Tenant $tenant) => $this->payload($tenant))
            ->values()
            ->all();

        return response()->json([
            'tenants' => $tenants,
        ]);
    }

    public function update(Request $request, string $tenant, AuditLogService $auditLogService): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:60', 'alpha_dash'],
            'plan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,suspended,deleted'],
        ], [
            'name.string' => 'Nama tenant harus berupa teks.',
            'name.max' => 'Nama tenant maksimal 150 karakter.',
            'slug.string' => 'Slug tenant harus berupa teks.',
            'slug.max' => 'Slug tenant maksimal 60 karakter.',
            'slug.alpha_dash' => 'Slug tenant hanya boleh huruf, angka, strip, dan underscore.',
            'plan.string' => 'Plan harus berupa teks.',
            'plan.max' => 'Plan maksimal 50 karakter.',
            'status.in' => 'Status harus salah satu dari active, suspended, atau deleted.',
        ], [
            'name' => 'nama tenant',
            'slug' => 'slug tenant',
            'plan' => 'plan',
            'status' => 'status',
        ]);

        if (array_key_exists('name', $data) && $data['name']) {
            $name = $data['name'];
            if (Tenant::where('name', $name)->where('id', '!=', $tenantModel->id)->exists()) {
                return response()->json([
                    'message' => 'Nama tenant sudah digunakan.',
                    'code' => 'tenant_name_exists',
                    'errors' => [
                        'name' => ['Nama tenant sudah digunakan.'],
                    ],
                ], 409);
            }
            $tenantModel->name = $name;
        }

        if (array_key_exists('slug', $data)) {
            $slug = $data['slug'];
            $slug = $slug !== null ? Str::slug($slug) : null;

            if ($slug && Tenant::where('slug', $slug)->where('id', '!=', $tenantModel->id)->exists()) {
                return response()->json([
                    'message' => 'Slug tenant sudah digunakan.',
                    'code' => 'tenant_slug_exists',
                    'errors' => [
                        'slug' => ['Slug tenant sudah digunakan.'],
                    ],
                ], 409);
            }

            $tenantModel->slug = $slug;
        }

        if (array_key_exists('plan', $data)) {
            $tenantModel->plan = $data['plan'] ?? $tenantModel->plan;
        }

        if (array_key_exists('status', $data)) {
            $status = $data['status'];
            $tenantModel->status = $status ?? $tenantModel->status;

            if ($status === 'suspended' || $status === 'deleted') {
                $tenantModel->suspended_at = $tenantModel->suspended_at ?? now();
                $tenantModel->suspended_by_user_id = $request->user('central_api')?->global_id;
            } elseif ($status === 'active') {
                $tenantModel->suspended_at = null;
                $tenantModel->suspended_by_user_id = null;
            }
        }

        $tenantModel->save();

        $auditLogService->log(
            $request,
            'tenant_updated',
            $tenantModel->id,
            $request->user('central_api')?->global_id,
            Tenant::class,
            $tenantModel->id
        );

        return response()->json([
            'tenant' => $this->payload($tenantModel),
        ]);
    }

    public function destroy(Request $request, string $tenant, AuditLogService $auditLogService): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);
        $tenantModel->status = 'deleted';
        $tenantModel->suspended_at = $tenantModel->suspended_at ?? now();
        $tenantModel->suspended_by_user_id = $request->user('central_api')?->global_id;
        $tenantModel->save();

        $auditLogService->log(
            $request,
            'tenant_deleted',
            $tenantModel->id,
            $request->user('central_api')?->global_id,
            Tenant::class,
            $tenantModel->id
        );

        return response()->json([
            'tenant' => $this->payload($tenantModel),
        ]);
    }

    private function resolveTenant(string $tenant): Tenant
    {
        return Tenant::where('id', $tenant)
            ->orWhere('slug', $tenant)
            ->firstOrFail();
    }

    private function payload(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'code' => $tenant->code,
            'plan' => $tenant->plan,
            'status' => $tenant->status,
            'suspendedAt' => optional($tenant->suspended_at)->toIso8601String(),
            'suspendedByUserId' => $tenant->suspended_by_user_id,
            'createdAt' => optional($tenant->created_at)->toIso8601String(),
        ];
    }
}
