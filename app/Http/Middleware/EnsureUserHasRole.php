<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'))->with('error', 'Silakan login terlebih dahulu.');
        }

        // Normalisasi role: jika mencari 'guru', 'superuser' juga memiliki akses
        $allowedRoles = $roles;
        if (in_array('guru', $allowedRoles) && ! in_array('superuser', $allowedRoles)) {
            $allowedRoles[] = 'superuser';
        }

        if (! in_array($user->role, $allowedRoles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak. Peran Anda (' . $user->role . ') tidak memiliki izin untuk tindakan ini.',
                ], 403);
            }

            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses halaman atau aksi ini.');
        }

        return $next($request);
    }
}
