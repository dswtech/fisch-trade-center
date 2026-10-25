<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PreDealVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fee_quote' => 'float', 'expires_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::uuid());
    }
}
