<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Report extends Model
{
    public const REASONS = ['suspected_scam', 'no_show', 'identity_misuse', 'abusive_behavior', 'other'];
    public const STATUSES = ['open', 'reviewing', 'resolved', 'dismissed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $report) => $report->public_id ??= (string) Str::uuid());
    }
}
