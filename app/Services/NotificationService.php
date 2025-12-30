<?php

namespace App\Services;

use App\Models\UserNotification;

class NotificationService
{
    public function notify(string $tenantId, string $userId, string $type, array $payload = []): UserNotification
    {
        return UserNotification::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'payload' => $payload ?: null,
        ]);
    }
}
