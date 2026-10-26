<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Models\AuditEvent;
use App\Models\Report;
use App\Models\Suspension;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class OperatorAuditController extends Controller
{
    public function __invoke(Request $request, AuditRecorder $audit): Response
    {
        $audit->record($request->user(), 'audit.accessed', 'audit_event', metadata: ['scope' => 'latest_100'], request: $request);
        $events = AuditEvent::query()->latest('id')->limit(100)->get([
            'public_id', 'correlation_id', 'event_type', 'subject_type', 'subject_public_id', 'reason_code', 'before_state', 'after_state', 'metadata', 'created_at',
        ]);

        $reports = Report::query()
            ->orderByRaw("case when reason_code in ('suspected_scam', 'identity_misuse') then 0 else 1 end")
            ->orderByRaw("case when status in ('open', 'reviewing') and created_at <= ? then 0 else 1 end", [now()->subHours((int) config('operations.moderation_review_hours'))])
            ->oldest('created_at')->limit(100)->get()->map(function (Report $report): array {
            $targets = collect();
            if ($report->target_type === 'user') {
                $targets = User::query()->where('public_id', $report->target_public_id)->get();
            } elseif ($report->target_type === 'negotiation') {
                $negotiation = DB::table('negotiations')->where('public_id', $report->target_public_id)->first();
                if ($negotiation) {
                    $targets = User::query()->whereIn('id', [$negotiation->initiator_id, $negotiation->owner_id])->get();
                }
            }

            return [
                'id' => $report->public_id,
                'targetType' => $report->target_type,
                'targetId' => $report->target_public_id,
                'reasonCode' => $report->reason_code,
                'status' => $report->status,
                'createdAt' => $report->created_at->toIso8601String(),
                'slaBreached' => in_array($report->status, ['open', 'reviewing'], true)
                    && $report->created_at->lte(now()->subHours((int) config('operations.moderation_review_hours'))),
                'targets' => $targets->map(fn (User $user): array => ['id' => $user->public_id, 'name' => $user->name])->values(),
            ];
        });
        $suspensions = Suspension::query()->active()->latest('id')->get()->map(function (Suspension $suspension): array {
            $user = User::query()->find($suspension->user_id);
            return [
                'id' => $suspension->public_id,
                'userId' => $user?->public_id,
                'userName' => $user?->name ?? 'Unknown account',
                'reasonCode' => $suspension->reason_code,
                'endsAt' => $suspension->ends_at?->toIso8601String(),
            ];
        });

        return Inertia::render('operator-audit', ['events' => $events, 'reports' => $reports, 'suspensions' => $suspensions]);
    }
}
