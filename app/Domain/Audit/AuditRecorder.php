<?php

namespace App\Domain\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditRecorder
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'token', 'secret', 'authorization', 'cookie', 'email', 'usn', 'ign', 'payload'];

    public function record(
        ?User $actor,
        string $eventType,
        string $subjectType,
        ?string $subjectPublicId = null,
        array $before = [],
        array $after = [],
        ?string $reasonCode = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditEvent {
        return AuditEvent::create([
            'actor_id' => $actor?->id,
            'correlation_id' => $this->correlationId($request),
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_public_id' => $subjectPublicId,
            'reason_code' => $reasonCode,
            'before_state' => $this->redact($before),
            'after_state' => $this->redact($after),
            'metadata' => $this->redact($metadata),
            'created_at' => now(),
        ]);
    }

    public function redact(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true)) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $safe;
    }

    private function correlationId(?Request $request): string
    {
        $candidate = $request?->attributes->get('correlation_id');

        return is_string($candidate) && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
    }
}
