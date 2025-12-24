<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class AuditLog extends Model
{
    use CentralConnection;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
