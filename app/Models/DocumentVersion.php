<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class DocumentVersion extends Model
{
    use CentralConnection;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'signed_at' => 'datetime',
        'tsa_signed_at' => 'datetime',
        'ltv_snapshot' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signers(): HasMany
    {
        return $this->hasMany(DocumentSigner::class, 'version_id');
    }
}
