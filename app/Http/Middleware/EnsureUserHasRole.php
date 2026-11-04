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

        // Both privileged admin roles receive explicit access to superuser-only routes.
        $allowedRoles = $roles;
        if (in_array('superuser', $allowedRoles, true)) {
            $allowedRoles[] = 'admin';
        }

        // Superusers and admins inherit teacher routes, but not vice versa.
        if (in_array('guru', $allowedRoles, true)) {
            $allowedRoles[] = 'superuser';
            $allowedRoles[] = 'admin';
        }

        if (! in_array($user->role, array_unique($allowedRoles), true)) {
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
