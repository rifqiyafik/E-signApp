<?php

namespace App\Models;

use App\Models\Tenant\User as TenantUser;
use App\Models\TenantUser as CentralTenantUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Stancl\Tenancy\Contracts\SyncMaster;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;
use Stancl\Tenancy\Database\Models\TenantPivot;

/**
 * Central User Model
 * 
 * This is the central/global user model that syncs to tenant databases.
 * Implements SyncMaster for resource syncing across tenant databases.
 */
class User extends Authenticatable implements SyncMaster
{
    use HasFactory, Notifiable, HasApiTokens;
    use ResourceSyncing, CentralConnection;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get tenants that this user belongs to
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users', 'global_user_id', 'tenant_id', 'global_id')
            ->using(TenantPivot::class);
    }

    /**
     * Get the tenant model name for syncing
     */
    public function getTenantModelName(): string
    {
        return TenantUser::class;
    }

    /**
     * Get the global identifier key value
     */
    public function getGlobalIdentifierKey()
    {
        return $this->getAttribute($this->getGlobalIdentifierKeyName());
    }

    /**
     * Get the global identifier key name
     */
    public function getGlobalIdentifierKeyName(): string
    {
        return 'global_id';
    }

    /**
     * Get the central model name
     */
    public function getCentralModelName(): string
    {
        return static::class;
    }

    /**
     * Get the attribute names to sync to tenant
     * 
     * CUSTOMIZE: Add more attributes to sync as needed
     */
    public function getSyncedAttributeNames(): array
    {
        return [
            'global_id',
            'name',
            'password',
            'email',
        ];
    }

    /**
     * Get tenant users relationship (central)
     */
    public function tenantUsers()
    {
        return $this->hasMany(CentralTenantUser::class, 'global_user_id', 'global_id');
    }

    /**
     * Accessor for role attribute
     * Gets role from tenant user context
     *
     * @return string|null
     */
    public function getRoleAttribute(): ?string
    {
        return $this->getRole();
    }

    /**
     * Get user role for specific tenant or current tenant context
     *
     * @param string|null $tenantId
     * @return string|null
     */
    public function getRole(?string $tenantId = null): ?string
    {
        // If in tenant context, use tenant ID from context
        if (!$tenantId && function_exists('tenant')) {
            $tenant = tenant();
            $tenantId = $tenant ? $tenant->id : null;
        }

        $tenantUser = $this->getTenantUser($tenantId);
        return $tenantUser ? $tenantUser->role : null;
    }

    /**
     * Check if user is super admin in specific tenant or current tenant context
     *
     * @param string|null $tenantId
     * @return bool
     */
    public function isSuperAdmin(?string $tenantId = null): bool
    {
        return $this->getRole($tenantId) === 'super_admin';
    }

    /**
     * Get tenant user data for specific tenant
     *
     * @param string|null $tenantId
     * @return CentralTenantUser|null
     */
    public function getTenantUser(?string $tenantId = null): ?CentralTenantUser
    {
        if (!$tenantId) {
            return $this->tenantUsers()->first();
        }

        return $this->tenantUsers()->where('tenant_id', $tenantId)->first();
    }
}
