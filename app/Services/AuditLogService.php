<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(
        Request $request,
        string $action,
        ?string $tenantId = null,
        ?string $actorUserId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = []
    ): void {
        AuditLog::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata ?: null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
