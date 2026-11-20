<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function create(): View
    {
        return view('auth.admin-login');
    }

    /**
     * Handle an admin login attempt.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Against password guessing: 5 tries per minute for one email from
        // one address, and 20 per minute from one address for any email.
        $perEmail = 'admin-login:'.Str::lower($credentials['email']).'|'.$request->ip();
        $perIp = 'admin-login-ip:'.$request->ip();

        foreach ([$perEmail => 5, $perIp => 20] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);
                throw ValidationException::withMessages([
                    'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                ]);
            }
        }

        // Only admin accounts may sign in here; other users get the same
        // generic error so the form doesn't reveal which emails exist.
        $credentials['is_admin'] = true;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($perEmail, 60);
            RateLimiter::hit($perIp, 60);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        RateLimiter::clear($perEmail);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.periods'));
    }

    /**
     * Log the admin out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
