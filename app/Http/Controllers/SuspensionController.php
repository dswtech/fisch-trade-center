<?php

namespace App\Http\Controllers;

use App\Domain\Moderation\SuspensionService;
use App\Models\Suspension;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SuspensionController extends Controller
{
    public function store(Request $request, SuspensionService $service): RedirectResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'uuid'],
            'reasonCode' => ['required', Rule::in(['suspected_scam', 'identity_misuse', 'abusive_behavior', 'operator_action'])],
            'duration' => ['required', Rule::in(['1', '7', '30', 'permanent'])],
        ]);
        $target = User::query()->where('public_id', $data['userId'])->firstOrFail();
        abort_if($target->is($request->user()) || $target->role === 'admin', 422, 'This account cannot be suspended by this action.');
        $service->suspend($target, $request->user(), $data['reasonCode'], $data['duration'], $request);

        return back()->with('success', 'Account suspension is active.');
    }

    public function destroy(Request $request, string $suspension, SuspensionService $service): RedirectResponse
    {
        $record = Suspension::query()->where('public_id', $suspension)->firstOrFail();
        $service->lift($record, $request->user(), $request);

        return back()->with('success', 'Account suspension lifted.');
    }
}
