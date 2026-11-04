<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            if ($request->routeIs('password.first_change*') || $request->routeIs('logout')) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'must_change_password' => true,
                    'message' => 'Anda harus mengubah kata sandi awal sebelum dapat mengakses layanan ini.',
                ], 403);
            }

            return redirect()->route('password.first_change')
                ->with('warning', 'Demi keamanan akun vokasi Anda, silakan perbarui kata sandi awal sebelum melanjutkan.');
        }

        return $next($request);
    }
}
