<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * TenantUser Model - Pivot between User and Tenant
 * 
 * This model manages the relationship between users and tenants,
 * including role assignments and tenant-specific user data.
 */
class TenantUser extends Model
{
    use CentralConnection;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_owner' => 'boolean',
        'is_nusawork_integrated' => 'boolean',
        'tenant_join_date' => 'datetime',
        'last_login_at' => 'datetime',
        'nusawork_integrated_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the tenant user.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * Get the user that owns the tenant user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'global_user_id', 'global_id');
    }

    /**
     * Check if user has super admin role in this tenant
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    // =====================================================
    // Nusawork Integration Methods
    // =====================================================

    /**
     * Get domain URL from nusawork_id
     * Format: "https://domain.app.nusawork.com|user_id"
     */
    public function getDomainUrl(): ?string
    {
        if (!$this->nusawork_id) {
            return null;
        }

        $nusaworkData = explode('|', $this->nusawork_id);
        return $nusaworkData[0] ?? null;
    }

    /**
     * Get user ID dari nusawork_id
     */
    public function getUserIdNusawork(): ?string
    {
        if (!$this->nusawork_id) {
            return null;
        }

        $nusaworkData = explode('|', $this->nusawork_id);
        return $nusaworkData[1] ?? null;
    }

    /**
     * Check if user is integrated with Nusawork
     */
    public function isNusaworkIntegrated(): bool
    {
        return $this->is_nusawork_integrated && !empty($this->nusawork_id);
    }

    /**
     * Get API token from Nusawork
     * 
     * CUSTOMIZE: Implementasikan sesuai kebutuhan.
     * Ini adalah contoh sederhana yang fetch public key dan generate token.
     */
    public function getTokenApi(): ?string
    {
        $domainUrl = $this->getDomainUrl();

        if (!$domainUrl) {
            return null;
        }

        try {
            // Get public key dari Nusawork
            $publicKey = $this->getPublicKeyNusawork($domainUrl);

            if (!$publicKey) {
                return null;
            }

            // Generate SSO token (implementasi sederhana)
            // CUSTOMIZE: Sesuaikan dengan kebutuhan autentikasi Nusawork
            return $this->generateSsoToken($publicKey, $domainUrl);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Nusawork public key
     */
    public function getPublicKeyNusawork(?string $domainUrl = null): ?string
    {
        $domainUrl = $domainUrl ?? $this->getDomainUrl();

        if (!$domainUrl) {
            return null;
        }

        // Cache key berdasarkan domain
        $cacheKey = 'nusawork_public_key_' . md5($domainUrl);

        return cache()->remember($cacheKey, 3600, function () use ($domainUrl) {
            try {
                $response = Http::get($domainUrl . '/emp/api/nusahire/integration/get_public_key');

                if ($response->successful()) {
                    return $response->json('public_key');
                }
            } catch (\Exception $e) {
                // Log error jika perlu
            }

            return null;
        });
    }

    /**
     * Generate SSO token untuk Nusawork API
     * 
     * CUSTOMIZE: Implementasikan sesuai spesifikasi Nusawork.
     */
    protected function generateSsoToken(string $publicKey, string $domainUrl): ?string
    {
        // Implementasi dasar - sesuaikan dengan kebutuhan
        // Biasanya menggunakan JWT atau token exchange

        // Contoh sederhana menggunakan service jika ada
        // return SsoTokenService::generate($this, $publicKey, $domainUrl);

        return null; // Placeholder - implementasikan sesuai kebutuhan
    }
}
