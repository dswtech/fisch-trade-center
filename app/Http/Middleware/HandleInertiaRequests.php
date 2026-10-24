<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        return [...parent::share($request),
            'auth' => ['user' => $user ? ['id' => $user->public_id, 'name' => $user->name, 'emailVerified' => $user->hasVerifiedEmail(), 'role' => $user->role] : null],
            'preferences' => ['locale' => $user?->locale, 'theme' => $user?->theme],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
        ];
    }
}
