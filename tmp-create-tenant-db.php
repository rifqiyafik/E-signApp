<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenantId = '01KCTVRDZ7F51PJHM5C70PK00W';
$tenant = \App\Models\Tenant::find($tenantId);
if (! $tenant) {
    fwrite(STDERR, "Tenant not found: {$tenantId}\n");
    exit(1);
}

$tenant->database()->makeCredentials();
$tenant->database()->manager()->createDatabase($tenant);
\Illuminate\Support\Facades\Artisan::call('tenants:migrate', [
    '--tenants' => [$tenant->id],
]);

echo \Illuminate\Support\Facades\Artisan::output();
