<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Negotiation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_activity_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::uuid());
    }

    public function isParticipant(int $userId): bool
    {
        return $this->initiator_id === $userId || $this->owner_id === $userId;
    }
}
