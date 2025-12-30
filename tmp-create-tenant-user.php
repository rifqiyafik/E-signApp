<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Str;

$tenantId = '01KCTVRDZ7F51PJHM5C70PK00W';
$email = 'test@example.com';
$password = 'secret123';

$user = \App\Models\User::where('email', $email)->first();
if (! $user) {
    $user = \App\Models\User::create([
        'global_id' => (string) Str::ulid(),
        'name' => 'Test User',
        'email' => $email,
        'password' => bcrypt($password),
    ]);
}

$tenant = \App\Models\Tenant::find($tenantId);
if (! $tenant) {
    fwrite(STDERR, "Tenant not found: {$tenantId}\n");
    exit(1);
}

$tenant->run(function () use ($user, $tenant) {
    \App\Models\Tenant\User::updateOrCreate(
        ['global_id' => $user->global_id],
        [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
            'is_owner' => true,
            'tenant_join_date' => now(),
        ]
    );
});

echo "Tenant user ready for {$email}\n";
