<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

/**
 * UID Generator
 * 
 * Generates unique identifiers for tenancy resources using ULID.
 * 
 * ULID benefits:
 * - More collision-resistant than uniqid()
 * - Sortable (contains time component)
 * - URL-safe string format
 * 
 * For truly random IDs, consider using Str::uuid() instead.
 */
class UIDGenerator implements UniqueIdentifierGenerator
{
    public static function generate($resource): string
    {
        return (string) Str::ulid();
    }
}
