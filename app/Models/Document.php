<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Document extends Model
{
    use CentralConnection;
    use HasUlids;

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_NEED_SIGNATURE = 'need_signature';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version_number', 'max');
    }

    public function latestSignedVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version_number', 'max');
    }


    public function signers(): HasMany
    {
        return $this->hasMany(DocumentSigner::class);
    }
}
