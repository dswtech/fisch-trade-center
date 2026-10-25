<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
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

        return Inertia::render('operator-audit', ['events' => $events]);
    }
}
