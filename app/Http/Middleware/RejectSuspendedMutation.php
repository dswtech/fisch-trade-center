<?php

namespace App\Http\Middleware;

use App\Models\Suspension;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RejectSuspendedMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && $request->user() && ! in_array($request->route()?->getName(), ['logout', 'reports.store'], true)) {
            abort_if(Suspension::query()->where('user_id', $request->user()->id)->active()->exists(), 423, 'Account is suspended from new trade actions.');
        }

        return $next($request);
    }
}
