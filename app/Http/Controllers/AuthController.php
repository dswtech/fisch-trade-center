<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(Request $request): Response { return Inertia::render('auth', ['mode' => $request->routeIs('register') ? 'register' : 'login']); }
    public function verificationNotice(): Response { return Inertia::render('verify-email'); }
    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse { $request->fulfill(); return to_route('dashboard')->with('success', 'Email verified.'); }
    public function resendVerification(Request $request): RedirectResponse { $request->user()->sendEmailVerificationNotification(); return back()->with('success', 'Verification link sent.'); }
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required','string','max:80'], 'email' => ['required','email','max:255','unique:users,email'], 'password' => ['required','confirmed',Password::min(8)]]);
        $user = User::create($data); event(new Registered($user)); Auth::login($user); $request->session()->regenerate();
        return to_route('verification.notice');
    }
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        $request->session()->regenerate(); return redirect()->intended(route('dashboard'));
    }
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return to_route('dashboard');
    }
}
