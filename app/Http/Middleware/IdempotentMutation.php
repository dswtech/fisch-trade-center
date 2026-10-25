<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdempotentMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->hasHeader('X-Idempotency-Key')) {
            return $next($request);
        }

        $key = (string) $request->header('X-Idempotency-Key');
        abort_unless((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key), 422, 'Invalid idempotency key.');

        $fingerprint = hash('sha256', $request->method().'|'.$request->path());
        $records = $request->session()->get('idempotency_results', []);
        if (isset($records[$key])) {
            abort_unless(hash_equals($records[$key]['fingerprint'], $fingerprint), 409, 'Idempotency key was already used for another operation.');
            foreach ($records[$key]['flash'] as $name => $value) {
                $request->session()->flash($name, $value);
            }

            return redirect()->to($records[$key]['location']);
        }

        $response = $next($request);
        if ($response->isRedirection() && ! $request->session()->has('errors')) {
            $records[$key] = [
                'fingerprint' => $fingerprint,
                'location' => (string) $response->headers->get('Location', url()->previous()),
                'flash' => collect(['success', 'error'])->mapWithKeys(fn (string $name): array => $request->session()->has($name) ? [$name => $request->session()->get($name)] : [])->all(),
            ];
            $request->session()->put('idempotency_results', array_slice($records, -50, null, true));
        }

        return $response;
    }
}
