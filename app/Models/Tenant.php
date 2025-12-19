<?php

namespace App\Models;

use App\Services\UIDGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Models\TenantPivot;

/**
 * Tenant Model
 * 
 * This is the main tenant model for multi-tenancy.
 * Extend this class and add custom columns as needed.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasFactory, HasDatabase, HasDomains;

    /**
     * Get the custom columns for the model.
     * 
     * CUSTOMIZE: Add your custom columns here
     *
     * @return array
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'code',
            'slug',
            'plan',
            'owner_id',
            // Add your custom columns here
        ];
    }

    /**
     * Set the id attribute using ULID generator.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setIdAttribute($value)
    {
        $this->attributes['id'] = $value ?? UIDGenerator::generate($this);
    }

    /**
     * Set the plan attribute with default value.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setPlanAttribute($value)
    {
        $this->attributes['plan'] = $value ?? 'free';
    }

    /**
     * Get the plan attribute.
     *
     * @return string
     */
    public function getPlanAttribute()
    {
        return $this->attributes['plan'] ?? 'free';
    }

    /**
     * Get the users for the tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_users', 'tenant_id', 'global_user_id', 'id', 'global_id')
            ->using(TenantPivot::class);
    }

    /**
     * Get tenant users relationship
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'tenant_id', 'id');
    }

    /**
     * Get owner user
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * Check if user is owner of this tenant
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->global_id;
    }

    /**
     * Get super admin users for this tenant
     */
    public function getSuperAdmins()
    {
        return $this->tenantUsers()->where('role', 'super_admin')->with('user')->get();
    }

    /**
     * Generate random code for tenant
     */
    public static function generateCode(): string
    {
        return Str::random(10);
    }

    /**
     * Generate unique slug from name
     *
     * @param string $name
     * @return string
     */
    public static function generateSlug(string $name): string
    {
        $baseSlug = static::prepareSlugFromName($name);
        return static::ensureUniqueSlug($baseSlug);
    }

    /**
     * Prepare slug from company name by removing common prefixes
     *
     * CUSTOMIZE: Add more prefixes as needed for your locale
     *
     * @param string $name Company name
     * @return string Prepared slug
     */
    public static function prepareSlugFromName(string $name): string
    {
        // List of company prefixes to remove (case insensitive)
        $prefixes = [
            'PT. ',
            'PT.',
            'PT ',
            'CV. ',
            'CV.',
            'CV ',
            'UD. ',
            'UD.',
            'UD ',
            'LLC ',
            'Inc. ',
            'Inc ',
            'Ltd. ',
            'Ltd ',
        ];

        $cleanName = trim($name);
        $lowerName = strtolower($cleanName);

        // Remove matching prefix (case insensitive)
        foreach ($prefixes as $prefix) {
            if (str_starts_with($lowerName, strtolower($prefix))) {
                $cleanName = trim(substr($cleanName, strlen($prefix)));
                break;
            }
        }

        if (empty($cleanName)) {
            $cleanName = $name;
        }

        return Str::slug($cleanName);
    }

    /**
     * Ensure slug is unique by appending random string if needed
     *
     * @param string $baseSlug Base slug to make unique
     * @return string Unique slug
     */
    protected static function ensureUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;

        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . Str::random(5);
        }

        return $slug;
    }
}
