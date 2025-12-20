<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenants = \App\Models\Tenant::query()->get(['id', 'slug', 'name']);
foreach ($tenants as $tenant) {
    echo $tenant->id . "\t" . ($tenant->slug ?? '') . "\t" . ($tenant->name ?? '') . "\n";
}
