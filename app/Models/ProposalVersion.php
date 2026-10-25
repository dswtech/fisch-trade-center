<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProposalVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['initiator_value' => 'float', 'owner_value' => 'float', 'expires_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::uuid());
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class);
    }
}
