<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

final class ReportController extends Controller
{
    public function store(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'targetType' => ['required', Rule::in(['user', 'negotiation'])],
            'targetId' => ['required', 'uuid'],
            'reasonCode' => ['required', Rule::in(Report::REASONS)],
        ]);
        $this->assertTargetExists($data['targetType'], $data['targetId'], $request->user()->id);
        $report = Report::query()->firstOrCreate([
            'reporter_id' => $request->user()->id,
            'target_type' => $data['targetType'],
            'target_public_id' => $data['targetId'],
            'reason_code' => $data['reasonCode'],
        ], ['status' => 'open']);
        if ($report->wasRecentlyCreated) {
            $audit->record($request->user(), 'report.created', 'report', $report->public_id, [], [
                'target_type' => $report->target_type,
                'target_public_id' => $report->target_public_id,
                'status' => $report->status,
            ], $report->reason_code, request: $request);
        }

        return back()->with('success', 'Report submitted for structured review.');
    }

    public function update(Request $request, string $report, AuditRecorder $audit): RedirectResponse
    {
        $case = Report::query()->where('public_id', $report)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(Report::STATUSES)]]);
        if (in_array($case->status, ['resolved', 'dismissed'], true) && $data['status'] === 'reviewing') {
            abort_unless($case->resolved_at?->gte(now()->subDays((int) config('operations.moderation_appeal_days'))), 422, 'The seven-day appeal window has closed.');
        }
        $before = ['status' => $case->status];
        $case->forceFill([
            'status' => $data['status'],
            'assigned_operator_id' => $request->user()->id,
            'resolved_by' => in_array($data['status'], ['resolved', 'dismissed'], true) ? $request->user()->id : null,
            'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null,
        ])->save();
        $audit->record($request->user(), 'report.status_changed', 'report', $case->public_id, $before, ['status' => $case->status], request: $request);

        return back()->with('success', 'Report status updated.');
    }

    private function assertTargetExists(string $type, string $publicId, int $reporterId): void
    {
        if ($type === 'user') {
            abort_unless(User::query()->where('public_id', $publicId)->whereKeyNot($reporterId)->exists(), 422, 'Report target is invalid.');
            return;
        }

        abort_unless(Schema::hasTable('negotiations') && DB::table('negotiations')->where('public_id', $publicId)
            ->where(fn ($query) => $query->where('initiator_id', $reporterId)->orWhere('owner_id', $reporterId))->exists(), 422, 'Report target is invalid.');
    }
}
