<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = (string) $request->header('X-Correlation-ID', '');
        $correlationId = Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
        $request->attributes->set('correlation_id', $correlationId);
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
