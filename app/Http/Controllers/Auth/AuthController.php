<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the vocational LMS login portal.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $throttleKey = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $credentials = $request->only('email', 'password');
        $loggedIn = Auth::attempt($credentials, $request->boolean('remember'));

        // Local development convenience: allow common dev password variants for seed users
        if (! $loggedIn && app()->environment('local')) {
            $candidateUser = User::where('email', $request->input('email'))->first();
            if ($candidateUser) {
                $devPasswords = ['password123', 'Password123!', 'password'];
                if (in_array($request->input('password'), $devPasswords, true)) {
                    foreach ($devPasswords as $devPass) {
                        if (Hash::check($devPass, $candidateUser->password)) {
                            $candidateUser->password = Hash::make($request->input('password'));
                            $candidateUser->save();
                            Auth::login($candidateUser, $request->boolean('remember'));
                            $loggedIn = true;
                            break;
                        }
                    }
                }
            }
        }

        if (! $loggedIn) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Kredensial yang diberikan tidak cocok dengan data pengguna kami.',
            ]);
        }

        $user = Auth::user();
        if ($user instanceof User && isset($user->is_active) && ! $user->is_active) {
            Auth::logout();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Akun Anda telah dinonaktifkan oleh administrator sekolah. Silakan hubungi pihak sekolah.',
            ]);
        }

        $isSuperuser = $user instanceof User && $user->isSuperuser();
        $schoolIsActive = $user instanceof User
            && $user->school_id !== null
            && $user->school()->where('status', 'active')->exists();

        if (! $isSuperuser && ! $schoolIsActive) {
            Auth::logout();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Kredensial yang diberikan tidak cocok dengan data pengguna kami.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::user();

        if ($user && $user->must_change_password) {
            return redirect()->route('password.first_change')
                ->with('warning', 'Demi keamanan akun vokasi Anda, silakan ubah kata sandi awal sebelum melanjutkan.');
        }

        // Redirect sesuai peran pengguna vokasi
        if ($user && ($user->isSuperuser() || $user->isAdmin())) {
            return redirect()->intended(route('dashboard'));
        }

        if ($user && $user->role === 'guru') {
            return redirect()->intended(route('schedules.index'));
        }

        if ($user && $user->role === 'siswa') {
            return redirect()->intended(route('modules.index'));
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Sesi Anda telah berakhir. Berhasil keluar.');
    }
}
