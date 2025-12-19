<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Contracts\Syncable;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;

/**
 * Tenant User Model
 * 
 * This user model exists in each tenant database and syncs from central.
 * Implements Syncable for resource syncing from central database.
 */
class User extends Authenticatable implements Syncable, Auditable
{
    use HasFactory, Notifiable, HasApiTokens;
    use ResourceSyncing;
    use AuditableTrait;
    use HasRoles;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The guard name for Spatie Permission
     *
     * @var string
     */
    protected $guard_name = 'api';

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
            'is_owner' => 'boolean',
            'tenant_join_date' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get synced attribute names for resource syncing
     * 
     * CUSTOMIZE: Add more attributes to sync from central as needed
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
     * Get the global identifier key name
     */
    public function getGlobalIdentifierKeyName(): string
    {
        return 'global_id';
    }

    /**
     * Get the global identifier key value
     */
    public function getGlobalIdentifierKey()
    {
        return $this->getAttribute($this->getGlobalIdentifierKeyName());
    }

    /**
     * Get the central model name
     */
    public function getCentralModelName(): string
    {
        return \App\Models\User::class;
    }

    /**
     * Get attributes to exclude from audit
     */
    public function getAuditExclude(): array
    {
        return ['password', 'remember_token'];
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is owner of current tenant
     */
    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }
}
