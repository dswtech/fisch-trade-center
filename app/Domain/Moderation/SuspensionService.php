<?php

namespace App\Domain\Moderation;

use App\Domain\Audit\AuditRecorder;
use App\Models\Suspension;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SuspensionService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function suspend(User $target, User $actor, string $reasonCode, int|string $duration, ?Request $request = null): Suspension
    {
        return DB::transaction(function () use ($target, $actor, $reasonCode, $duration, $request): Suspension {
            Suspension::query()->where('user_id', $target->id)->active()->lockForUpdate()->get();
            $endsAt = $duration === 'permanent' ? null : now()->addDays((int) $duration);
            $suspension = Suspension::create([
                'user_id' => $target->id,
                'reason_code' => $reasonCode,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'created_by' => $actor->id,
            ]);
            $this->audit->record($actor, 'user.suspended', 'user', $target->public_id, [], [
                'duration_days' => $duration,
                'ends_at' => $endsAt?->toIso8601String(),
            ], $reasonCode, request: $request);

            return $suspension;
        });
    }

    public function lift(Suspension $suspension, User $actor, ?Request $request = null): void
    {
        DB::transaction(function () use ($suspension, $actor, $request): void {
            $locked = Suspension::query()->lockForUpdate()->findOrFail($suspension->id);
            if ($locked->lifted_at !== null) {
                return;
            }
            $locked->forceFill(['lifted_at' => now(), 'lifted_by' => $actor->id])->save();
            $this->audit->record($actor, 'user.suspension_lifted', 'suspension', $locked->public_id, request: $request);
        });
    }
}
