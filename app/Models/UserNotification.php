<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class UserNotification extends Model
{
    use CentralConnection;
    use HasUlids;

    protected $table = 'notifications';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];
}
